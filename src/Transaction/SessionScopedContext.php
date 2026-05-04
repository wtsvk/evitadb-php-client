<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Transaction;

use stdClass;
use Webmozart\Assert\Assert;
use Wtsvk\EvitaDbClient\Exception\EvitaDbEntityNotFoundException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbStatusException;
use Wtsvk\EvitaDbClient\Protocol\EvitaSessionServiceClient;
use Wtsvk\EvitaDbClient\Protocol\GrpcDefineEntitySchemaRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcDeleteEntityRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityMutation;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityUpsertMutation;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcSealedEntity;
use Wtsvk\EvitaDbClient\Protocol\GrpcUpsertEntityRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcUpsertEntityResponse;

use function is_int;
use function is_string;
use function property_exists;
use function sprintf;

use const Grpc\STATUS_OK;

/**
 * Concrete transaction context bound to a single EvitaDB session.
 *
 * Implements both Read and Write contexts; the public client API exposes only
 * the appropriate interface to the caller depending on the transaction flavor.
 */
final class SessionScopedContext implements WriteTransactionContext
{
    public function __construct(
        private readonly EvitaSessionServiceClient $sessionService,
        private readonly string $sessionId,
        private readonly string $catalog,
    ) {
    }

    public function query(GrpcQueryRequest $queryRequest): GrpcQueryResponse
    {
        [$response, $status] = $this->sessionService
            ->Query($queryRequest, $this->sessionMeta())
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
    }

    public function getEntity(string $entityType, int $primaryKey): GrpcSealedEntity
    {
        $entity = $this->fetchEntity(entityType: $entityType, primaryKey: $primaryKey);
        if ($entity === null) {
            throw new EvitaDbEntityNotFoundException(
                message: sprintf(
                    'Entity %s pk=%d not found in catalog %s',
                    $entityType,
                    $primaryKey,
                    $this->catalog,
                ),
            );
        }

        return $entity;
    }

    public function findEntity(string $entityType, int $primaryKey): ?GrpcSealedEntity
    {
        return $this->fetchEntity(entityType: $entityType, primaryKey: $primaryKey);
    }

    public function defineEntitySchema(string $entityType): true
    {
        $request = new GrpcDefineEntitySchemaRequest();
        $request->setEntityType($entityType);

        [, $status] = $this->sessionService
            ->DefineEntitySchema($request, $this->sessionMeta())
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
    }

    public function upsertEntity(GrpcEntityUpsertMutation $upsertMutation): ?int
    {
        $entityMutation = new GrpcEntityMutation();
        $entityMutation->setEntityUpsertMutation($upsertMutation);

        $request = new GrpcUpsertEntityRequest();
        $request->setEntityMutation($entityMutation);

        [$response, $status] = $this->sessionService
            ->UpsertEntity($request, $this->sessionMeta())
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
    }

    public function deleteEntity(string $entityType, int $primaryKey): true
    {
        $request = new GrpcDeleteEntityRequest();
        $request->setEntityType($entityType);
        $request->setPrimaryKeyUnwrapped($primaryKey);

        [, $status] = $this->sessionService
            ->DeleteEntity($request, $this->sessionMeta())
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
    }

    private function fetchEntity(string $entityType, int $primaryKey): ?GrpcSealedEntity
    {
        $request = new GrpcEntityRequest();
        $request->setEntityType($entityType);
        $request->setPrimaryKey($primaryKey);
        $request->setRequire(
            'entityFetch(attributeContentAll(), associatedDataContentAll(), priceContentAll(), referenceContentAll())',
        );

        [$response, $status] = $this->sessionService
            ->GetEntity($request, $this->sessionMeta())
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

        return $response->getEntity();
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
    private function sessionMeta(): array
    {
        return ['sessionid' => [$this->sessionId]];
    }
}
