<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient;

use Wtsvk\EvitaDbClient\Exception\EvitaDbConnectionException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbEntityNotFoundException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbStatusException;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityUpsertMutation;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcSealedEntity;
use Wtsvk\EvitaDbClient\Transaction\ReadTransactionContext;
use Wtsvk\EvitaDbClient\Transaction\WriteTransactionContext;

interface EvitaDbClientInterface
{
    public function isHealthy(): bool;

    /**
     * @throws EvitaDbConnectionException
     * @throws EvitaDbStatusException
     */
    public function defineCatalog(string $catalog): bool;

    /**
     * @throws EvitaDbConnectionException
     * @throws EvitaDbStatusException
     */
    public function defineEntitySchema(string $catalog, string $entityType): true;

    /**
     * @throws EvitaDbConnectionException
     * @throws EvitaDbStatusException
     */
    public function upsertEntity(string $catalog, GrpcEntityUpsertMutation $upsertMutation): ?int;

    /**
     * @throws EvitaDbConnectionException
     * @throws EvitaDbStatusException
     */
    public function deleteEntity(string $catalog, string $entityType, int $primaryKey): true;

    /**
     * @throws EvitaDbConnectionException
     * @throws EvitaDbStatusException
     * @throws EvitaDbEntityNotFoundException
     */
    public function getEntity(string $catalog, string $entityType, int $primaryKey): GrpcSealedEntity;

    /**
     * @throws EvitaDbConnectionException
     * @throws EvitaDbStatusException
     */
    public function findEntity(string $catalog, string $entityType, int $primaryKey): ?GrpcSealedEntity;

    /**
     * @throws EvitaDbConnectionException
     * @throws EvitaDbStatusException
     */
    public function query(string $catalog, GrpcQueryRequest $queryRequest): GrpcQueryResponse;

    /**
     * Open a read-write session, run the callable, commit on success.
     *
     * On exception inside the callable the session is closed without waiting
     * for change visibility but pending mutations may still persist server-side
     * (EvitaDB does not support runtime rollback at close — set $dryRun=true
     * for guaranteed discard).
     *
     * @template T
     *
     * @param  callable(WriteTransactionContext): T  $fn
     * @return T
     *
     * @throws EvitaDbConnectionException
     */
    public function transaction(string $catalog, callable $fn, bool $dryRun = false): mixed;

    /**
     * Open a read-only session and run the callable. The session gives a
     * consistent snapshot across all reads inside the callable.
     *
     * @template T
     *
     * @param  callable(ReadTransactionContext): T  $fn
     * @return T
     *
     * @throws EvitaDbConnectionException
     */
    public function readTransaction(string $catalog, callable $fn): mixed;
}
