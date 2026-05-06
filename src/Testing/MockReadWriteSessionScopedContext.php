<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Testing;

use Webmozart\Assert\Assert;
use Wtsvk\EvitaDbClient\EntityFetch;
use Wtsvk\EvitaDbClient\Exception\EvitaDbEntityNotFoundException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbStatusException;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityReference;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityUpsertMutation;
use Wtsvk\EvitaDbClient\Protocol\GrpcModifyEntitySchemaMutation;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcSealedEntity;
use Wtsvk\EvitaDbClient\Protocol\GrpcUpsertEntityResponse;
use Wtsvk\EvitaDbClient\Transaction\WriteTransactionContext;

use function sprintf;

/**
 * Read-write session-scoped context returned by EvitaDbMockClient::writeTransaction().
 *
 * Reads delegate to the mock client's stub registries. Writes are recorded as
 * spy entries on the mock client unless $dryRun is true, in which case write
 * methods are no-ops — mutations are NOT recorded. This mirrors what a consumer
 * would observe from EvitaDB's dryRun session flag (server discards mutations
 * at close).
 *
 * deleteEntities and updateEntitySchema are intentionally not implemented —
 * they are too server-specific to fake meaningfully.
 */
final readonly class MockReadWriteSessionScopedContext implements WriteTransactionContext
{
    use MockSessionScopedReads;

    public function __construct(
        private EvitaDbMockClient $client,
        private bool $dryRun = false,
    ) {
    }

    public function defineEntitySchema(string $entityType): true
    {
        if (! $this->dryRun) {
            $this->client->definedEntitySchemas[] = new MockedSchemaDefinition(entityType: $entityType);
        }

        return true;
    }

    public function upsertEntity(GrpcEntityUpsertMutation $upsertMutation): int
    {
        $response = $this->upsert($upsertMutation);

        if ($response->hasEntityReference()) {
            $ref = $response->getEntityReference();
            Assert::notNull($ref);

            return $ref->getPrimaryKey();
        }

        if ($response->hasEntity()) {
            $entity = $response->getEntity();
            Assert::notNull($entity);

            return $entity->getPrimaryKey();
        }

        if ($response->hasEntityReferenceWithAssignedPrimaryKeys()) {
            $refKeys = $response->getEntityReferenceWithAssignedPrimaryKeys();
            Assert::notNull($refKeys);

            return $refKeys->getPrimaryKey();
        }

        throw new EvitaDbStatusException('EvitaDbMockClient: upsert returned no entity identification.');
    }

    public function upsert(GrpcEntityUpsertMutation $upsertMutation, ?EntityFetch $require = null): GrpcUpsertEntityResponse
    {
        if (! $this->dryRun) {
            $this->client->upsertCalls[] = new MockedUpsert(mutation: $upsertMutation);
        }

        $response = new GrpcUpsertEntityResponse();
        $assignedPk = $this->client->nextPrimaryKey++;

        if ($require !== null) {
            $entity = new GrpcSealedEntity();
            $entity->setPrimaryKey($assignedPk);
            $response->setEntity($entity);
        } else {
            $ref = new GrpcEntityReference();
            $ref->setPrimaryKey($assignedPk);
            $response->setEntityReference($ref);
        }

        return $response;
    }

    public function deleteEntity(string $entityType, int $primaryKey): true
    {
        if ($this->client->findStubbedEntity($entityType, $primaryKey) === null) {
            throw new EvitaDbEntityNotFoundException(
                message: sprintf(
                    'Entity %s pk=%d not found in catalog %s',
                    $entityType,
                    $primaryKey,
                    $this->client->catalog,
                ),
            );
        }

        if (! $this->dryRun) {
            $this->client->removeStubbedEntity($entityType, $primaryKey);
            $this->client->deleteCalls[] = new MockedDelete(
                entityType: $entityType,
                primaryKey: $primaryKey,
            );
        }

        return true;
    }

    public function deleteEntities(GrpcQueryRequest $query): int
    {
        if ($this->dryRun) {
            return 0;
        }

        throw new EvitaDbStatusException('EvitaDbMockClient: deleteEntities is not supported in mock context.');
    }

    public function updateEntitySchema(GrpcModifyEntitySchemaMutation $mutation): int
    {
        if ($this->dryRun) {
            return 1;
        }

        throw new EvitaDbStatusException('EvitaDbMockClient: updateEntitySchema is not supported in mock context.');
    }

    private function client(): EvitaDbMockClient
    {
        return $this->client;
    }
}
