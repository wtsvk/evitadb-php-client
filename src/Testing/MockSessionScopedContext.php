<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Testing;

use Wtsvk\EvitaDbClient\Protocol\GrpcEntityUpsertMutation;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcSealedEntity;
use Wtsvk\EvitaDbClient\Transaction\WriteTransactionContext;

/**
 * Transaction context returned by EvitaDbMockClient::transaction() and ::readTransaction().
 *
 * Reads delegate back to the mock client's entity store. Writes are also forwarded
 * to the mock client unless $dryRun is true, in which case write methods are no-ops
 * — mutations are NOT recorded in the mock client's spy lists. This mirrors what
 * a consumer would observe from EvitaDB's dryRun session flag (server discards
 * mutations at close).
 *
 * Note: real EvitaDB sessions show pending mutations to subsequent reads inside the
 * same session even when dryRun is on. The mock skips this nuance — reads after a
 * dryRun write see pre-transaction state. Acceptable for unit testing application
 * logic; if you need that fidelity, use an integration test against a real instance.
 */
final readonly class MockSessionScopedContext implements WriteTransactionContext
{
    public function __construct(
        private EvitaDbMockClient $client,
        private string $catalog,
        private bool $dryRun = false,
    ) {
    }

    public function query(GrpcQueryRequest $queryRequest): GrpcQueryResponse
    {
        return $this->client->query(catalog: $this->catalog, queryRequest: $queryRequest);
    }

    public function getEntity(string $entityType, int $primaryKey): GrpcSealedEntity
    {
        return $this->client->getEntity(
            catalog: $this->catalog,
            entityType: $entityType,
            primaryKey: $primaryKey,
        );
    }

    public function findEntity(string $entityType, int $primaryKey): ?GrpcSealedEntity
    {
        return $this->client->findEntity(
            catalog: $this->catalog,
            entityType: $entityType,
            primaryKey: $primaryKey,
        );
    }

    public function defineEntitySchema(string $entityType): true
    {
        if ($this->dryRun) {
            return true;
        }

        return $this->client->defineEntitySchema(catalog: $this->catalog, entityType: $entityType);
    }

    public function upsertEntity(GrpcEntityUpsertMutation $upsertMutation): int
    {
        if ($this->dryRun) {
            // Real EvitaDB still assigns a primary key even when dryRun is set
            // (the server discards the mutation only at session close). Mirror
            // that here so consumer code that relies on the returned PK still
            // works under dryRun, but skip the upsertCalls recording so the
            // consumer can assert "no mutations persisted".
            return $this->client->nextPrimaryKey++;
        }

        return $this->client->upsertEntity(catalog: $this->catalog, upsertMutation: $upsertMutation);
    }

    public function deleteEntity(string $entityType, int $primaryKey): true
    {
        if ($this->dryRun) {
            return true;
        }

        return $this->client->deleteEntity(
            catalog: $this->catalog,
            entityType: $entityType,
            primaryKey: $primaryKey,
        );
    }
}
