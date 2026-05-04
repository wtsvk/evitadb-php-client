<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Transaction;

use Wtsvk\EvitaDbClient\Exception\EvitaDbStatusException;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityUpsertMutation;

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
    public function upsertEntity(GrpcEntityUpsertMutation $upsertMutation): ?int;

    /**
     * @throws EvitaDbStatusException
     */
    public function deleteEntity(string $entityType, int $primaryKey): true;
}
