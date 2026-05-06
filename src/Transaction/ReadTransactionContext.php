<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Transaction;

use Wtsvk\EvitaDbClient\EntityFetch;
use Wtsvk\EvitaDbClient\Exception\EvitaDbEntityNotFoundException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbStatusException;
use Wtsvk\EvitaDbClient\Protocol\GrpcCatalogSchema;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntitySchema;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcSealedEntity;

/**
 * Operations available within a read-only transaction scope.
 *
 * All calls share a single underlying EvitaDB session, giving consumers
 * a consistent snapshot across multiple reads.
 */
interface ReadTransactionContext
{
    /**
     * @throws EvitaDbStatusException
     */
    public function query(GrpcQueryRequest $queryRequest): GrpcQueryResponse;

    /**
     * @throws EvitaDbStatusException When the gRPC call fails.
     * @throws EvitaDbEntityNotFoundException When the entity does not exist.
     */
    public function getEntity(string $entityType, int $primaryKey, ?EntityFetch $require = null): GrpcSealedEntity;

    /**
     * @throws EvitaDbStatusException
     */
    public function findEntity(string $entityType, int $primaryKey, ?EntityFetch $require = null): ?GrpcSealedEntity;

    /**
     * @throws EvitaDbStatusException
     */
    public function getCatalogSchema(): GrpcCatalogSchema;

    /**
     * @throws EvitaDbStatusException
     */
    public function getEntitySchema(string $entityType): GrpcEntitySchema;

    /**
     * @return list<string>
     *
     * @throws EvitaDbStatusException
     */
    public function getAllEntityTypes(): array;

    /**
     * @throws EvitaDbStatusException
     */
    public function getEntityCollectionSize(string $entityType): int;
}
