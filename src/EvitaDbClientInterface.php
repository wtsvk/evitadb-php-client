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
    public function query(string $catalog, GrpcQueryRequest $queryRequest): GrpcQueryResponse;

    /**
     * @template T
     *
     * @param  callable(string): T  $fn
     * @return T
     *
     * @throws EvitaDbConnectionException
     */
    public function withReadSession(string $catalog, callable $fn): mixed;

    /**
     * @template T
     *
     * @param  callable(string): T  $fn
     * @return T
     *
     * @throws EvitaDbConnectionException
     */
    public function withWriteSession(string $catalog, callable $fn): mixed;
}
