<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient;

use Throwable;
use Webmozart\Assert\Assert;
use Wtsvk\EvitaDbClient\Exception\EvitaDbConnectionException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbStatusException;
use Wtsvk\EvitaDbClient\Protocol\EvitaServiceClient;
use Wtsvk\EvitaDbClient\Protocol\EvitaSessionServiceClient;
use Wtsvk\EvitaDbClient\Protocol\GrpcCloseRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionResponse;
use Wtsvk\EvitaDbClient\Transaction\ReadOnlySessionScopedContext;
use Wtsvk\EvitaDbClient\Transaction\ReadWriteSessionScopedContext;

use function sprintf;

use const Grpc\STATUS_OK;

final class EvitaDbClient implements EvitaDbClientInterface
{
    public function __construct(
        private readonly EvitaServiceClient $evitaService,
        private readonly EvitaSessionServiceClient $sessionService,
        private readonly string $catalog,
        private readonly SessionCommitBehavior $defaultCommitBehavior = SessionCommitBehavior::WaitForChangesVisible,
    ) {
    }

    /**
     * Convenience factory — opens a connection and returns a catalog-scoped client
     * in one step. Internally delegates to `EvitaDbConnection::create()->catalog()`,
     * so keepalive defaults stay in one place.
     *
     * @param array<string, mixed> $grpcOpts
     */
    public static function create(
        string $host,
        int $port,
        string $catalog,
        SessionCommitBehavior $defaultCommitBehavior = SessionCommitBehavior::WaitForChangesVisible,
        array $grpcOpts = [],
    ): self {
        return EvitaDbConnection::create(
            host: $host,
            port: $port,
            grpcOpts: $grpcOpts,
        )->catalog(
            catalog: $catalog,
            defaultCommitBehavior: $defaultCommitBehavior,
        );
    }

    public function writeTransaction(
        callable $fn,
        bool $dryRun = false,
        ?SessionCommitBehavior $commitBehavior = null,
    ): mixed {
        $sessionId = $this->createSession(
            type: SessionType::ReadWrite,
            dryRun: $dryRun,
        );
        $context = new ReadWriteSessionScopedContext(
            sessionService: $this->sessionService,
            sessionId: $sessionId,
            catalog: $this->catalog,
        );

        $result = $fn($context);

        $this->closeSession(
            sessionId: $sessionId,
            commitBehavior: $commitBehavior ?? $this->defaultCommitBehavior,
        );

        return $result;
    }

    public function readTransaction(callable $fn): mixed
    {
        $sessionId = $this->createSession(type: SessionType::ReadOnly);
        $context = new ReadOnlySessionScopedContext(
            sessionService: $this->sessionService,
            sessionId: $sessionId,
            catalog: $this->catalog,
        );

        try {
            return $fn($context);
        } finally {
            try {
                $this->closeSession(
                    sessionId: $sessionId,
                    commitBehavior: SessionCommitBehavior::WaitForConflictResolution,
                );
            } catch (Throwable) {
                // Read-only session has nothing to commit; close failures here
                // are informational and would mask the more useful exception
                // already in flight (or the actual return value).
            }
        }
    }

    /**
     * @throws EvitaDbConnectionException
     */
    private function createSession(SessionType $type, bool $dryRun = false): string
    {
        $call = match ($type) {
            SessionType::ReadOnly => $this->evitaService->CreateReadOnlySession(...),
            SessionType::ReadWrite => $this->evitaService->CreateReadWriteSession(...),
        };

        try {
            $request = new GrpcEvitaSessionRequest();
            $request->setCatalogName($this->catalog);
            $request->setDryRun($dryRun);

            [$response, $rawStatus] = $call($request)->wait();
        } catch (Throwable $e) {
            throw new EvitaDbConnectionException(
                message: sprintf(
                    'Error creating %s session for %s: %s',
                    $type->label(),
                    $this->catalog,
                    $e->getMessage(),
                ),
                previous: $e,
            );
        }

        $status = GrpcStatus::fromRaw($rawStatus);

        if ($status->code !== STATUS_OK || $response === null) {
            throw new EvitaDbConnectionException(
                message: sprintf(
                    'Failed to create %s session for %s: %s',
                    $type->label(),
                    $this->catalog,
                    $status,
                ),
            );
        }

        Assert::isInstanceOf($response, GrpcEvitaSessionResponse::class);

        return $response->getSessionId();
    }

    /**
     * Closes the session via gRPC `Close()` (which always commits server-side).
     *
     * Failures propagate so the caller of `writeTransaction()` learns when a
     * commit didn't actually happen. Caller in `readTransaction()` wraps this
     * in its own try/catch to swallow read-only close failures.
     *
     * @throws EvitaDbConnectionException Transport-level failure of the close call.
     * @throws EvitaDbStatusException Server returned a non-OK status on close.
     */
    private function closeSession(string $sessionId, SessionCommitBehavior $commitBehavior): void
    {
        try {
            $request = new GrpcCloseRequest();
            $request->setCatalogName($this->catalog);
            $request->setCommitBehaviour($commitBehavior->value);

            [, $rawStatus] = $this->sessionService
                ->CloseSession($request, $this->sessionMeta($sessionId))
                ->wait();
        } catch (Throwable $e) {
            throw new EvitaDbConnectionException(
                message: sprintf('Error closing session for %s: %s', $this->catalog, $e->getMessage()),
                previous: $e,
            );
        }

        $status = GrpcStatus::fromRaw($rawStatus);

        if ($status->code !== STATUS_OK) {
            throw new EvitaDbStatusException(
                message: sprintf('Failed to close session for %s: %s', $this->catalog, $status),
            );
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function sessionMeta(string $sessionId): array
    {
        return ['sessionid' => [$sessionId]];
    }
}
