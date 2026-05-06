<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Testing;

use Wtsvk\EvitaDbClient\EntityFetch;
use Wtsvk\EvitaDbClient\Exception\EvitaDbEntityNotFoundException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbStatusException;
use Wtsvk\EvitaDbClient\Protocol\GrpcCatalogSchema;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntitySchema;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcSealedEntity;

use function sprintf;

/**
 * Shared read-side implementations for mock session-scoped contexts.
 *
 * Schema introspection methods (getCatalogSchema, getEntitySchema,
 * getAllEntityTypes, getEntityCollectionSize) are intentionally not supported
 * by the mock and throw — schema-aware code is too server-specific to fake
 * meaningfully. Override or wrap the mock if your tests need these.
 */
trait MockSessionScopedReads
{
    abstract private function client(): EvitaDbMockClient;

    public function query(GrpcQueryRequest $queryRequest): GrpcQueryResponse
    {
        $response = $this->client()->findStubbedQueryResponse($queryRequest);

        if ($response === null) {
            throw new EvitaDbStatusException(
                message: sprintf(
                    'EvitaDbMockClient: no query stub matched for catalog %s. Query: %s',
                    $this->client()->catalog,
                    $queryRequest->getQuery(),
                ),
            );
        }

        return $response;
    }

    public function getEntity(string $entityType, int $primaryKey, ?EntityFetch $require = null): GrpcSealedEntity
    {
        unset($require);

        $entity = $this->client()->findStubbedEntity($entityType, $primaryKey);

        if ($entity === null) {
            throw new EvitaDbEntityNotFoundException(
                message: sprintf(
                    'Entity %s pk=%d not found in catalog %s',
                    $entityType,
                    $primaryKey,
                    $this->client()->catalog,
                ),
            );
        }

        return $entity;
    }

    public function findEntity(string $entityType, int $primaryKey, ?EntityFetch $require = null): ?GrpcSealedEntity
    {
        unset($require);

        return $this->client()->findStubbedEntity($entityType, $primaryKey);
    }

    public function getCatalogSchema(): GrpcCatalogSchema
    {
        throw new EvitaDbStatusException('EvitaDbMockClient: getCatalogSchema is not supported in mock context.');
    }

    public function getEntitySchema(string $entityType): GrpcEntitySchema
    {
        throw new EvitaDbStatusException(
            message: sprintf(
                'EvitaDbMockClient: getEntitySchema is not supported in mock context (requested %s).',
                $entityType,
            ),
        );
    }

    /**
     * @return list<string>
     */
    public function getAllEntityTypes(): array
    {
        throw new EvitaDbStatusException('EvitaDbMockClient: getAllEntityTypes is not supported in mock context.');
    }

    public function getEntityCollectionSize(string $entityType): int
    {
        throw new EvitaDbStatusException(
            message: sprintf(
                'EvitaDbMockClient: getEntityCollectionSize is not supported in mock context (requested %s).',
                $entityType,
            ),
        );
    }
}
