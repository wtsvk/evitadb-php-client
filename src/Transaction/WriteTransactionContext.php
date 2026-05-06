<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Transaction;

use Wtsvk\EvitaDbClient\EntityFetch;
use Wtsvk\EvitaDbClient\Exception\EvitaDbEntityNotFoundException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbStatusException;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityUpsertMutation;
use Wtsvk\EvitaDbClient\Protocol\GrpcModifyEntitySchemaMutation;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcUpsertEntityResponse;

/**
 * Operations available within a read-write transaction scope.
 *
 * All calls share a single underlying EvitaDB session. Mutations are accumulated
 * server-side and committed when the enclosing transaction callable returns
 * normally; on exception the session is closed without waiting for visibility,
 * but pending mutations may still be persisted (EvitaDB does not support runtime
 * rollback at close — see EvitaDbClient::transaction docblock).
 */
interface WriteTransactionContext extends ReadTransactionContext
{
    /**
     * @throws EvitaDbStatusException
     */
    public function defineEntitySchema(string $entityType): true;

    /**
     * @throws EvitaDbStatusException
     */
    public function upsertEntity(GrpcEntityUpsertMutation $upsertMutation): int;

    /**
     * @throws EvitaDbStatusException
     */
    public function upsert(GrpcEntityUpsertMutation $upsertMutation, ?EntityFetch $require = null): GrpcUpsertEntityResponse;

    /**
     * @throws EvitaDbStatusException
     * @throws EvitaDbEntityNotFoundException When the entity does not exist.
     */
    public function deleteEntity(string $entityType, int $primaryKey): true;

    /**
     * Bulk delete entities matching the query. Returns the count of deleted entities.
     *
     * @throws EvitaDbStatusException
     */
    public function deleteEntities(GrpcQueryRequest $query): int;

    /**
     * Returns the new schema version after applying the mutation.
     *
     * @throws EvitaDbStatusException
     */
    public function updateEntitySchema(GrpcModifyEntitySchemaMutation $mutation): int;
}
