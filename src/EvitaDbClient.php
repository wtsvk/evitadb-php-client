<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient;

use Google\Protobuf\GPBEmpty;
use Grpc\ChannelCredentials;
use stdClass;
use Throwable;
use Webmozart\Assert\Assert;
use Wtsvk\EvitaDbClient\Exception\EvitaDbConnectionException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbEntityNotFoundException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbStatusException;
use Wtsvk\EvitaDbClient\Protocol\EvitaServiceClient;
use Wtsvk\EvitaDbClient\Protocol\EvitaSessionServiceClient;
use Wtsvk\EvitaDbClient\Protocol\GrpcCloseRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcCommitBehavior;
use Wtsvk\EvitaDbClient\Protocol\GrpcDefineCatalogRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcDefineCatalogResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcDefineEntitySchemaRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcDeleteEntityRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityMutation;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityUpsertMutation;
use Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcReadyResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcSealedEntity;
use Wtsvk\EvitaDbClient\Protocol\GrpcUpsertEntityRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcUpsertEntityResponse;

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

    /**
     * @throws EvitaDbConnectionException
     * @throws EvitaDbStatusException
     */
    public function defineEntitySchema(string $catalog, string $entityType): true
    {
        return $this->withWriteSession(
            catalog: $catalog,
            fn: function (string $sessionId) use ($entityType): true {
                $request = new GrpcDefineEntitySchemaRequest();
                $request->setEntityType($entityType);

                [, $status] = $this->sessionService
                    ->DefineEntitySchema($request, $this->sessionMeta($sessionId))
                    ->wait();

                if ($status->code !== STATUS_OK) {
                    throw new EvitaDbStatusException(
                        message: sprintf(
                            'Failed to define entity schema %s: %s (status %d)',
                            $entityType,
                            $this->statusDetails($status),
                            $this->statusCode($status),
                        ),
                    );
                }

                return true;
            },
        );
    }

    /**
     * @throws EvitaDbConnectionException
     * @throws EvitaDbStatusException
     */
    public function upsertEntity(string $catalog, GrpcEntityUpsertMutation $upsertMutation): ?int
    {
        return $this->withWriteSession(
            catalog: $catalog,
            fn: function (string $sessionId) use ($upsertMutation): ?int {
                $entityMutation = new GrpcEntityMutation();
                $entityMutation->setEntityUpsertMutation($upsertMutation);

                $request = new GrpcUpsertEntityRequest();
                $request->setEntityMutation($entityMutation);

                [$response, $status] = $this->sessionService
                    ->UpsertEntity($request, $this->sessionMeta($sessionId))
                    ->wait();

                if ($status->code !== STATUS_OK || $response === null) {
                    throw new EvitaDbStatusException(
                        message: sprintf(
                            'Failed to upsert entity: %s (status %d)',
                            $this->statusDetails($status),
                            $this->statusCode($status),
                        ),
                    );
                }

                Assert::isInstanceOf($response, GrpcUpsertEntityResponse::class);

                return $response->getEntityReference()?->getPrimaryKey();
            },
        );
    }

    /**
     * @throws EvitaDbConnectionException
     * @throws EvitaDbStatusException
     */
    public function deleteEntity(string $catalog, string $entityType, int $primaryKey): true
    {
        return $this->withWriteSession(
            catalog: $catalog,
            fn: function (string $sessionId) use ($entityType, $primaryKey): true {
                $request = new GrpcDeleteEntityRequest();
                $request->setEntityType($entityType);
                $request->setPrimaryKeyUnwrapped($primaryKey);

                [, $status] = $this->sessionService
                    ->DeleteEntity($request, $this->sessionMeta($sessionId))
                    ->wait();

                if ($status->code !== STATUS_OK) {
                    throw new EvitaDbStatusException(
                        message: sprintf(
                            'Failed to delete entity %s pk=%d: %s (status %d)',
                            $entityType,
                            $primaryKey,
                            $this->statusDetails($status),
                            $this->statusCode($status),
                        ),
                    );
                }

                return true;
            },
        );
    }

    /**
     * @throws EvitaDbConnectionException
     * @throws EvitaDbStatusException When the gRPC call fails.
     * @throws EvitaDbEntityNotFoundException When the entity does not exist.
     */
    public function getEntity(string $catalog, string $entityType, int $primaryKey): GrpcSealedEntity
    {
        return $this->withReadSession(
            catalog: $catalog,
            fn: function (string $sessionId) use ($catalog, $entityType, $primaryKey): GrpcSealedEntity {
                $request = new GrpcEntityRequest();
                $request->setEntityType($entityType);
                $request->setPrimaryKey($primaryKey);
                $request->setRequire('entityFetch(attributeContentAll(), associatedDataContentAll(), priceContentAll(), referenceContentAll())');

                [$response, $status] = $this->sessionService
                    ->GetEntity($request, $this->sessionMeta($sessionId))
                    ->wait();

                if ($status->code !== STATUS_OK || $response === null) {
                    throw new EvitaDbStatusException(
                        message: sprintf(
                            'GetEntity failed for %s pk=%d: %s (status %d)',
                            $entityType,
                            $primaryKey,
                            $this->statusDetails($status),
                            $this->statusCode($status),
                        ),
                    );
                }

                Assert::isInstanceOf($response, GrpcEntityResponse::class);

                $entity = $response->getEntity();
                if ($entity === null) {
                    throw new EvitaDbEntityNotFoundException(
                        message: sprintf(
                            'Entity %s pk=%d not found in catalog %s',
                            $entityType,
                            $primaryKey,
                            $catalog,
                        ),
                    );
                }

                return $entity;
            },
        );
    }

    /**
     * @throws EvitaDbConnectionException
     * @throws EvitaDbStatusException
     */
    public function query(string $catalog, GrpcQueryRequest $queryRequest): GrpcQueryResponse
    {
        return $this->withReadSession(
            catalog: $catalog,
            fn: function (string $sessionId) use ($queryRequest): GrpcQueryResponse {
                [$response, $status] = $this->sessionService
                    ->Query($queryRequest, $this->sessionMeta($sessionId))
                    ->wait();

                if ($status->code !== STATUS_OK) {
                    throw new EvitaDbStatusException(
                        message: sprintf(
                            'Query failed: %s (status %d)',
                            $this->statusDetails($status),
                            $this->statusCode($status),
                        ),
                    );
                }

                Assert::isInstanceOf($response, GrpcQueryResponse::class);

                return $response;
            },
        );
    }

    /**
     * @template T
     *
     * @param  callable(string): T  $fn
     * @return T
     *
     * @throws EvitaDbConnectionException
     */
    public function withReadSession(string $catalog, callable $fn): mixed
    {
        $sessionId = $this->createSession(catalog: $catalog, type: SessionType::ReadOnly);

        try {
            return $fn($sessionId);
        } finally {
            $this->closeSession(
                catalog: $catalog,
                sessionId: $sessionId,
                commitBehavior: SessionCommitBehavior::Discard,
            );
        }
    }

    /**
     * @template T
     *
     * @param  callable(string): T  $fn
     * @return T
     *
     * @throws EvitaDbConnectionException
     */
    public function withWriteSession(string $catalog, callable $fn): mixed
    {
        $sessionId = $this->createSession(catalog: $catalog, type: SessionType::ReadWrite);

        try {
            return $fn($sessionId);
        } finally {
            $this->closeSession(
                catalog: $catalog,
                sessionId: $sessionId,
                commitBehavior: SessionCommitBehavior::Commit,
            );
        }
    }

    /**
     * @throws EvitaDbConnectionException
     */
    private function createSession(string $catalog, SessionType $type): string
    {
        $call = match ($type) {
            SessionType::ReadOnly => $this->evitaService->CreateReadOnlySession(...),
            SessionType::ReadWrite => $this->evitaService->CreateReadWriteSession(...),
        };

        try {
            $request = new GrpcEvitaSessionRequest();
            $request->setCatalogName($catalog);

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
