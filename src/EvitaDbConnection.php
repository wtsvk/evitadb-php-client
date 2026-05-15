<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient;

use Error;
use Google\Protobuf\GPBEmpty;
use Grpc\ChannelCredentials;
use Throwable;
use Webmozart\Assert\Assert;
use Wtsvk\EvitaDbClient\Exception\EvitaDbConnectionException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbStatusException;
use Wtsvk\EvitaDbClient\Protocol\EvitaServiceClient;
use Wtsvk\EvitaDbClient\Protocol\EvitaSessionServiceClient;
use Wtsvk\EvitaDbClient\Protocol\GrpcCatalogNamesResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcDefineCatalogRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcDefineCatalogResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcDeleteCatalogIfExistsRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcDeleteCatalogIfExistsResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcGoLiveAndCloseResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcReadyResponse;

use function array_merge;
use function iterator_to_array;
use function sprintf;

use const Grpc\STATUS_OK;

final class EvitaDbConnection implements EvitaDbConnectionInterface
{
    public function __construct(
        private readonly EvitaServiceClient $evitaService,
        private readonly EvitaSessionServiceClient $sessionService,
    ) {
    }

    /**
     * @param array<string, mixed> $grpcOpts
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
            [$response, $rawStatus] = $this->evitaService->IsReady(new GPBEmpty())->wait();
            $status = GrpcStatus::fromRaw($rawStatus);

            if ($status->code !== STATUS_OK || $response === null) {
                return false;
            }

            Assert::isInstanceOf($response, GrpcReadyResponse::class);

            return $response->getReady();
        } catch (Throwable $e) {
            // Programmer bugs (TypeError, AssertionError, ...) propagate; only
            // transport / server-misbehavior is reported as "not healthy".
            if ($e instanceof Error) {
                throw $e;
            }

            return false;
        }
    }

    /**
     * @throws EvitaDbConnectionException
     * @throws EvitaDbStatusException
     */
    public function defineCatalog(string $catalog): bool
    {
        try {
            $request = new GrpcDefineCatalogRequest();
            $request->setCatalogName($catalog);

            [$response, $rawStatus] = $this->evitaService->DefineCatalog($request)->wait();
        } catch (Throwable $e) {
            throw new EvitaDbConnectionException(
                message: sprintf('Error defining catalog %s: %s', $catalog, $e->getMessage()),
                previous: $e,
            );
        }

        $status = GrpcStatus::fromRaw($rawStatus);

        if ($status->code !== STATUS_OK || $response === null) {
            throw new EvitaDbStatusException(
                message: sprintf('Failed to define catalog %s: %s', $catalog, $status),
            );
        }

        Assert::isInstanceOf($response, GrpcDefineCatalogResponse::class);

        if ($response->getSuccess()) {
            $this->goLiveAndClose($catalog);
        }

        return $response->getSuccess();
    }

    /**
     * Transitions a newly created catalog from "warming up" to "alive" state.
     *
     * Opens a temporary write session, calls GoLiveAndClose (which commits,
     * transitions the catalog, and closes the session atomically), then returns.
     *
     * @throws EvitaDbConnectionException
     * @throws EvitaDbStatusException
     */
    private function goLiveAndClose(string $catalog): void
    {
        $sessionRequest = new GrpcEvitaSessionRequest();
        $sessionRequest->setCatalogName($catalog);

        try {
            [$sessionResponse, $rawStatus] = $this->evitaService
                ->CreateReadWriteSession($sessionRequest)
                ->wait();
        } catch (Throwable $e) {
            throw new EvitaDbConnectionException(
                message: sprintf('Error opening session for goLiveAndClose on %s: %s', $catalog, $e->getMessage()),
                previous: $e,
            );
        }

        $status = GrpcStatus::fromRaw($rawStatus);

        if ($status->code !== STATUS_OK || $sessionResponse === null) {
            throw new EvitaDbStatusException(
                message: sprintf('Failed to open session for goLiveAndClose on %s: %s', $catalog, $status),
            );
        }

        Assert::isInstanceOf($sessionResponse, GrpcEvitaSessionResponse::class);
        $sessionId = $sessionResponse->getSessionId();

        try {
            [$goLiveResponse, $rawStatus] = $this->sessionService
                ->GoLiveAndClose(new GPBEmpty(), ['sessionid' => [$sessionId]])
                ->wait();
        } catch (Throwable $e) {
            throw new EvitaDbConnectionException(
                message: sprintf('Error switching catalog %s to alive state: %s', $catalog, $e->getMessage()),
                previous: $e,
            );
        }

        $status = GrpcStatus::fromRaw($rawStatus);

        if ($status->code !== STATUS_OK || $goLiveResponse === null) {
            throw new EvitaDbStatusException(
                message: sprintf('Failed to switch catalog %s to alive state: %s', $catalog, $status),
            );
        }

        Assert::isInstanceOf($goLiveResponse, GrpcGoLiveAndCloseResponse::class);
    }

    /**
     * @return list<string>
     *
     * @throws EvitaDbConnectionException
     * @throws EvitaDbStatusException
     */
    public function getCatalogNames(): array
    {
        try {
            [$response, $rawStatus] = $this->evitaService->GetCatalogNames(new GPBEmpty())->wait();
        } catch (Throwable $e) {
            throw new EvitaDbConnectionException(
                message: sprintf('Error fetching catalog names: %s', $e->getMessage()),
                previous: $e,
            );
        }

        $status = GrpcStatus::fromRaw($rawStatus);

        if ($status->code !== STATUS_OK || $response === null) {
            throw new EvitaDbStatusException(
                message: sprintf('Failed to fetch catalog names: %s', $status),
            );
        }

        Assert::isInstanceOf($response, GrpcCatalogNamesResponse::class);

        /** @var list<string> $names */
        $names = iterator_to_array($response->getCatalogNames());

        return $names;
    }

    /**
     * @throws EvitaDbConnectionException
     * @throws EvitaDbStatusException
     */
    public function deleteCatalog(string $catalog): bool
    {
        try {
            $request = new GrpcDeleteCatalogIfExistsRequest();
            $request->setCatalogName($catalog);

            [$response, $rawStatus] = $this->evitaService->DeleteCatalogIfExists($request)->wait();
        } catch (Throwable $e) {
            throw new EvitaDbConnectionException(
                message: sprintf('Error deleting catalog %s: %s', $catalog, $e->getMessage()),
                previous: $e,
            );
        }

        $status = GrpcStatus::fromRaw($rawStatus);

        if ($status->code !== STATUS_OK || $response === null) {
            throw new EvitaDbStatusException(
                message: sprintf('Failed to delete catalog %s: %s', $catalog, $status),
            );
        }

        Assert::isInstanceOf($response, GrpcDeleteCatalogIfExistsResponse::class);

        return $response->getSuccess();
    }

    public function catalog(
        string $catalog,
        SessionCommitBehavior $defaultCommitBehavior = SessionCommitBehavior::WaitForChangesVisible,
    ): EvitaDbClient {
        return new EvitaDbClient(
            evitaService: $this->evitaService,
            sessionService: $this->sessionService,
            catalog: $catalog,
            defaultCommitBehavior: $defaultCommitBehavior,
        );
    }
}
