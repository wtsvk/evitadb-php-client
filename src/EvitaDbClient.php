<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient;

use Google\Protobuf\GPBEmpty;
use Grpc\ChannelCredentials;
use stdClass;
use Throwable;
use Webmozart\Assert\Assert;
use Wtsvk\EvitaDbClient\Exception\EvitaDbConnectionException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbStatusException;
use Wtsvk\EvitaDbClient\Protocol\EvitaServiceClient;
use Wtsvk\EvitaDbClient\Protocol\EvitaSessionServiceClient;
use Wtsvk\EvitaDbClient\Protocol\GrpcCloseRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcCommitBehavior;
use Wtsvk\EvitaDbClient\Protocol\GrpcDefineCatalogRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcDefineCatalogResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityUpsertMutation;
use Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcReadyResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcSealedEntity;
use Wtsvk\EvitaDbClient\Transaction\ReadTransactionContext;
use Wtsvk\EvitaDbClient\Transaction\SessionScopedContext;
use Wtsvk\EvitaDbClient\Transaction\WriteTransactionContext;

use function array_merge;
use function is_int;
use function is_string;
use function property_exists;
use function sprintf;

use const Grpc\STATUS_OK;

final class EvitaDbClient implements EvitaDbClientInterface
{
    public function __construct(
        private readonly EvitaServiceClient $evitaService,
        private readonly EvitaSessionServiceClient $sessionService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $grpcOpts  Additional gRPC channel options merged on top of sane defaults.
     */
    public static function create(
        string $host,
        int $port,
        array $grpcOpts = [],
    ): self {
        $target = $host . ':' . $port;

        $opts = array_merge(
            [
                'credentials' => ChannelCredentials::createInsecure(),
                'grpc.keepalive_time_ms' => 30000,
                'grpc.keepalive_timeout_ms' => 5000,
                'grpc.keepalive_permit_without_calls' => 1,
                'grpc.http2.max_pings_without_data' => 0,
            ],
            $grpcOpts,
        );

        return new self(
            new EvitaServiceClient($target, $opts),
            new EvitaSessionServiceClient($target, $opts),
        );
    }

    public function isHealthy(): bool
    {
        try {
            [$response, $status] = $this->evitaService->IsReady(new GPBEmpty())->wait();

            if ($status->code !== STATUS_OK || $response === null) {
                return false;
            }

            Assert::isInstanceOf($response, GrpcReadyResponse::class);

            return $response->getReady();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @throws EvitaDbConnectionException When the gRPC channel fails.
     * @throws EvitaDbStatusException When EvitaDB rejects the request.
     */
    public function defineCatalog(string $catalog): bool
    {
        try {
            $request = new GrpcDefineCatalogRequest();
            $request->setCatalogName($catalog);

            [$response, $status] = $this->evitaService->DefineCatalog($request)->wait();
        } catch (Throwable $e) {
            throw new EvitaDbConnectionException(
                message: sprintf(
                    'Error defining catalog %s: %s',
                    $catalog,
                    $e->getMessage(),
                ),
                previous: $e,
            );
        }

        if ($status->code !== STATUS_OK || $response === null) {
            throw new EvitaDbStatusException(
                message: sprintf(
                    'Failed to define catalog %s: %s (status %d)',
                    $catalog,
                    $this->statusDetails($status),
                    $this->statusCode($status),
                ),
            );
        }

        Assert::isInstanceOf($response, GrpcDefineCatalogResponse::class);

        return $response->getSuccess();
    }

    public function defineEntitySchema(string $catalog, string $entityType): true
    {
        return $this->transaction(
            catalog: $catalog,
            fn: static fn (WriteTransactionContext $tx): true => $tx->defineEntitySchema($entityType),
        );
    }

    public function upsertEntity(string $catalog, GrpcEntityUpsertMutation $upsertMutation): ?int
    {
        return $this->transaction(
            catalog: $catalog,
            fn: static fn (WriteTransactionContext $tx): ?int => $tx->upsertEntity($upsertMutation),
        );
    }

    public function deleteEntity(string $catalog, string $entityType, int $primaryKey): true
    {
        return $this->transaction(
            catalog: $catalog,
            fn: static fn (WriteTransactionContext $tx): true => $tx->deleteEntity($entityType, $primaryKey),
        );
    }

    public function getEntity(string $catalog, string $entityType, int $primaryKey): GrpcSealedEntity
    {
        return $this->readTransaction(
            catalog: $catalog,
            fn: static fn (ReadTransactionContext $tx): GrpcSealedEntity => $tx->getEntity($entityType, $primaryKey),
        );
    }

    public function findEntity(string $catalog, string $entityType, int $primaryKey): ?GrpcSealedEntity
    {
        return $this->readTransaction(
            catalog: $catalog,
            fn: static fn (ReadTransactionContext $tx): ?GrpcSealedEntity => $tx->findEntity($entityType, $primaryKey),
        );
    }

    public function query(string $catalog, GrpcQueryRequest $queryRequest): GrpcQueryResponse
    {
        return $this->readTransaction(
            catalog: $catalog,
            fn: static fn (ReadTransactionContext $tx): GrpcQueryResponse => $tx->query($queryRequest),
        );
    }

    /**
     * Open a read-write session, run the callable, commit on success.
     *
     * EvitaDB does not support runtime rollback at session close. On exception
     * inside the callable the session is closed with WAIT_FOR_CONFLICT_RESOLUTION
     * (fastest commit) — pending mutations WILL still be applied server-side.
     * For guaranteed discard (e.g. dry-running migrations or integration tests),
     * pass $dryRun=true; the session is then opened with the dryRun flag and
     * EvitaDB rolls back ALL mutations regardless of outcome.
     *
     * @template T
     *
     * @param  callable(WriteTransactionContext): T  $fn
     * @return T
     *
     * @throws EvitaDbConnectionException
     */
    public function transaction(string $catalog, callable $fn, bool $dryRun = false): mixed
    {
        $sessionId = $this->createSession(
            catalog: $catalog,
            type: SessionType::ReadWrite,
            dryRun: $dryRun,
        );
        $context = new SessionScopedContext(
            sessionService: $this->sessionService,
            sessionId: $sessionId,
            catalog: $catalog,
        );

        try {
            $result = $fn($context);
            $this->closeSession(
                catalog: $catalog,
                sessionId: $sessionId,
                commitBehavior: SessionCommitBehavior::Commit,
            );

            return $result;
        } catch (Throwable $e) {
            $this->closeSession(
                catalog: $catalog,
                sessionId: $sessionId,
                commitBehavior: SessionCommitBehavior::Discard,
            );

            throw $e;
        }
    }

    /**
     * @template T
     *
     * @param  callable(ReadTransactionContext): T  $fn
     * @return T
     *
     * @throws EvitaDbConnectionException
     */
    public function readTransaction(string $catalog, callable $fn): mixed
    {
        $sessionId = $this->createSession(
            catalog: $catalog,
            type: SessionType::ReadOnly,
        );
        $context = new SessionScopedContext(
            sessionService: $this->sessionService,
            sessionId: $sessionId,
            catalog: $catalog,
        );

        try {
            return $fn($context);
        } finally {
            $this->closeSession(
                catalog: $catalog,
                sessionId: $sessionId,
                commitBehavior: SessionCommitBehavior::Discard,
            );
        }
    }

    /**
     * @throws EvitaDbConnectionException
     */
    private function createSession(string $catalog, SessionType $type, bool $dryRun = false): string
    {
        $call = match ($type) {
            SessionType::ReadOnly => $this->evitaService->CreateReadOnlySession(...),
            SessionType::ReadWrite => $this->evitaService->CreateReadWriteSession(...),
        };

        try {
            $request = new GrpcEvitaSessionRequest();
            $request->setCatalogName($catalog);
            $request->setDryRun($dryRun);

            [$response, $status] = $call($request)->wait();
        } catch (Throwable $e) {
            throw new EvitaDbConnectionException(
                message: sprintf(
                    'Error creating %s session for %s: %s',
                    $type->label(),
                    $catalog,
                    $e->getMessage(),
                ),
                previous: $e,
            );
        }

        if ($status->code !== STATUS_OK || $response === null) {
            throw new EvitaDbConnectionException(
                message: sprintf(
                    'Failed to create %s session for %s: %s (status %d)',
                    $type->label(),
                    $catalog,
                    $this->statusDetails($status),
                    $this->statusCode($status),
                ),
            );
        }

        Assert::isInstanceOf($response, GrpcEvitaSessionResponse::class);

        return $response->getSessionId();
    }

    /**
     * Best-effort cleanup; failures are silently swallowed because we're already in a finally block
     * and the consumer cannot meaningfully recover from a failed session close.
     */
    private function closeSession(string $catalog, string $sessionId, SessionCommitBehavior $commitBehavior): void
    {
        try {
            $request = new GrpcCloseRequest();
            $request->setCatalogName($catalog);
            $request->setCommitBehaviour(
                match ($commitBehavior) {
                    SessionCommitBehavior::Commit => GrpcCommitBehavior::WAIT_FOR_CHANGES_VISIBLE,
                    SessionCommitBehavior::Discard => GrpcCommitBehavior::WAIT_FOR_CONFLICT_RESOLUTION,
                },
            );

            $this->sessionService->CloseSession($request, $this->sessionMeta($sessionId))->wait();
        } catch (Throwable) {
            // intentionally swallowed
        }
    }

    private function statusDetails(stdClass $status): string
    {
        return property_exists($status, 'details') && is_string($status->details) ? $status->details : 'no details';
    }

    private function statusCode(stdClass $status): int
    {
        return property_exists($status, 'code') && is_int($status->code) ? $status->code : -1;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function sessionMeta(string $sessionId): array
    {
        return ['sessionid' => [$sessionId]];
    }
}
