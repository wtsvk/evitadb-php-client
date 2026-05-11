<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Transaction;

use Google\Protobuf\GPBEmpty;
use Webmozart\Assert\Assert;
use Wtsvk\EvitaDbClient\EntityFetch;
use Wtsvk\EvitaDbClient\Exception\EvitaDbEntityNotFoundException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbStatusException;
use Wtsvk\EvitaDbClient\GrpcStatus;
use Wtsvk\EvitaDbClient\Protocol\EvitaSessionServiceClient;
use Wtsvk\EvitaDbClient\Protocol\GrpcCatalogSchema;
use Wtsvk\EvitaDbClient\Protocol\GrpcCatalogSchemaResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityCollectionSizeRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityCollectionSizeResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntitySchema;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntitySchemaRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntitySchemaResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityTypesResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcGetCatalogSchemaRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcSealedEntity;

use function iterator_to_array;
use function sprintf;

use const Grpc\STATUS_OK;

/**
 * Shared read-side gRPC implementations for session-scoped contexts.
 *
 * Both ReadOnlySessionScopedContext and ReadWriteSessionScopedContext use this
 * trait to avoid duplicating the read-path code while still allowing the type
 * system to enforce read-only vs read-write semantics at the interface level.
 */
trait SessionScopedReads
{
    abstract private function sessionService(): EvitaSessionServiceClient;

    abstract private function sessionId(): string;

    abstract private function catalog(): string;

    public function query(GrpcQueryRequest $queryRequest): GrpcQueryResponse
    {
        [$response, $rawStatus] = $this->sessionService()
            ->Query($queryRequest, $this->sessionMeta())
            ->wait();
        $status = GrpcStatus::fromRaw($rawStatus);

        if ($status->code !== STATUS_OK) {
            throw new EvitaDbStatusException(
                message: sprintf('Query failed: %s', $status),
            );
        }

        Assert::isInstanceOf($response, GrpcQueryResponse::class);

        return $response;
    }

    public function getEntity(string $entityType, int $primaryKey, ?EntityFetch $require = null): GrpcSealedEntity
    {
        $entity = $this->fetchEntity(
            entityType: $entityType,
            primaryKey: $primaryKey,
            require: $require,
        );

        if ($entity === null) {
            throw new EvitaDbEntityNotFoundException(
                message: sprintf(
                    'Entity %s pk=%d not found in catalog %s',
                    $entityType,
                    $primaryKey,
                    $this->catalog(),
                ),
            );
        }

        return $entity;
    }

    public function findEntity(string $entityType, int $primaryKey, ?EntityFetch $require = null): ?GrpcSealedEntity
    {
        return $this->fetchEntity(
            entityType: $entityType,
            primaryKey: $primaryKey,
            require: $require,
        );
    }

    public function getCatalogSchema(): GrpcCatalogSchema
    {
        $request = new GrpcGetCatalogSchemaRequest();

        [$response, $rawStatus] = $this->sessionService()
            ->GetCatalogSchema($request, $this->sessionMeta())
            ->wait();
        $status = GrpcStatus::fromRaw($rawStatus);

        if ($status->code !== STATUS_OK || $response === null) {
            throw new EvitaDbStatusException(
                message: sprintf('Failed to get catalog schema: %s', $status),
            );
        }

        Assert::isInstanceOf($response, GrpcCatalogSchemaResponse::class);

        $schema = $response->getCatalogSchema();
        Assert::notNull($schema);

        return $schema;
    }

    public function getEntitySchema(string $entityType): GrpcEntitySchema
    {
        $request = new GrpcEntitySchemaRequest();
        $request->setEntityType($entityType);

        [$response, $rawStatus] = $this->sessionService()
            ->GetEntitySchema($request, $this->sessionMeta())
            ->wait();
        $status = GrpcStatus::fromRaw($rawStatus);

        if ($status->code !== STATUS_OK || $response === null) {
            throw new EvitaDbStatusException(
                message: sprintf('Failed to get entity schema %s: %s', $entityType, $status),
            );
        }

        Assert::isInstanceOf($response, GrpcEntitySchemaResponse::class);

        $schema = $response->getEntitySchema();
        Assert::notNull($schema);

        return $schema;
    }

    /**
     * @return list<string>
     */
    public function getAllEntityTypes(): array
    {
        [$response, $rawStatus] = $this->sessionService()
            ->GetAllEntityTypes(new GPBEmpty(), $this->sessionMeta())
            ->wait();
        $status = GrpcStatus::fromRaw($rawStatus);

        if ($status->code !== STATUS_OK || $response === null) {
            throw new EvitaDbStatusException(
                message: sprintf('Failed to get entity types: %s', $status),
            );
        }

        Assert::isInstanceOf($response, GrpcEntityTypesResponse::class);

        /** @var list<string> $types */
        $types = iterator_to_array($response->getEntityTypes());

        return $types;
    }

    public function getEntityCollectionSize(string $entityType): int
    {
        $request = new GrpcEntityCollectionSizeRequest();
        $request->setEntityType($entityType);

        [$response, $rawStatus] = $this->sessionService()
            ->GetEntityCollectionSize($request, $this->sessionMeta())
            ->wait();
        $status = GrpcStatus::fromRaw($rawStatus);

        if ($status->code !== STATUS_OK || $response === null) {
            throw new EvitaDbStatusException(
                message: sprintf(
                    'Failed to get entity collection size for %s: %s',
                    $entityType,
                    $status,
                ),
            );
        }

        Assert::isInstanceOf($response, GrpcEntityCollectionSizeResponse::class);

        return $response->getSize();
    }

    private function fetchEntity(string $entityType, int $primaryKey, ?EntityFetch $require = null): ?GrpcSealedEntity
    {
        $request = new GrpcEntityRequest();
        $request->setEntityType($entityType);
        $request->setPrimaryKey($primaryKey);

        if ($require !== null) {
            $request->setRequire($require->toEvitaQL());
            $requireParams = $require->getParams();
            if ($requireParams !== []) {
                $request->setPositionalQueryParams($requireParams);
            }
        }

        [$response, $rawStatus] = $this->sessionService()
            ->GetEntity($request, $this->sessionMeta())
            ->wait();
        $status = GrpcStatus::fromRaw($rawStatus);

        if ($status->code !== STATUS_OK || $response === null) {
            throw new EvitaDbStatusException(
                message: sprintf(
                    'GetEntity failed for %s pk=%d: %s',
                    $entityType,
                    $primaryKey,
                    $status,
                ),
            );
        }

        Assert::isInstanceOf($response, GrpcEntityResponse::class);

        return $response->getEntity();
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function sessionMeta(): array
    {
        return ['sessionid' => [$this->sessionId()]];
    }
}
