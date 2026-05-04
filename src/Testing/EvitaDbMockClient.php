<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Testing;

use Closure;
use Wtsvk\EvitaDbClient\EvitaDbClientInterface;
use Wtsvk\EvitaDbClient\Exception\EvitaDbEntityNotFoundException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbStatusException;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityUpsertMutation;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcSealedEntity;

use function sprintf;

/**
 * In-memory fake of EvitaDbClientInterface for testing consumer applications.
 *
 * Combines stub (canned responses), spy (records calls) and fake (in-memory entity
 * store) facets. Operates strictly: any call without a matching stub throws so
 * misconfigurations fail loud rather than silently returning empty data.
 *
 * Typical usage:
 *
 *     $client = (new EvitaDbMockClient())
 *         ->withEntity(catalog: 'cat', entityType: 'Product', primaryKey: 42, entity: $sealed)
 *         ->onQuery(catalog: 'cat', matcher: $myMatcher, response: $cannedResponse);
 *
 *     $service = new ProductService($client);
 *     $service->doSomething(42);
 *
 *     $this->assertCount(1, $client->upsertCalls);
 */
final class EvitaDbMockClient implements EvitaDbClientInterface
{
    public bool $healthy = true;

    /**
     * Counter used to mimic EvitaDB's auto-assigned primary keys returned by
     * upsertEntity(). Starts at 1, increments on each call. Adjust if your test
     * expects specific PK values (e.g. set to 1000 to mimic existing data).
     */
    public int $nextPrimaryKey = 1;

    /**
     * @var list<MockedUpsert>
     */
    public array $upsertCalls = [];

    /**
     * @var list<MockedDelete>
     */
    public array $deleteCalls = [];

    /**
     * @var list<string>
     */
    public array $definedCatalogs = [];

    /**
     * @var list<MockedSchemaDefinition>
     */
    public array $definedEntitySchemas = [];

    /**
     * @var array<string, GrpcSealedEntity>
     */
    private array $entities = [];

    /**
     * @var list<MockedQueryStub>
     */
    private array $queryStubs = [];

    // ─────────────────────────────────────────────────────────────────────────
    // Configuration (fluent)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Register an entity that will be returned by getEntity()/findEntity().
     */
    public function withEntity(
        string $catalog,
        string $entityType,
        int $primaryKey,
        GrpcSealedEntity $entity,
    ): self {
        $this->entities[$this->entityKey($catalog, $entityType, $primaryKey)] = $entity;

        return $this;
    }

    /**
     * Register a query response. The matcher receives the GrpcQueryRequest and
     * decides whether this stub applies. First matching stub wins.
     *
     * @param  callable(GrpcQueryRequest): bool  $matcher
     */
    public function onQuery(string $catalog, callable $matcher, GrpcQueryResponse $response): self
    {
        $this->queryStubs[] = new MockedQueryStub(
            catalog: $catalog,
            matcher: Closure::fromCallable($matcher),
            response: $response,
        );

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EvitaDbClientInterface — read-side
    // ─────────────────────────────────────────────────────────────────────────

    public function isHealthy(): bool
    {
        return $this->healthy;
    }

    public function getEntity(string $catalog, string $entityType, int $primaryKey): GrpcSealedEntity
    {
        $entity = $this->findEntity(catalog: $catalog, entityType: $entityType, primaryKey: $primaryKey);
        if ($entity === null) {
            throw new EvitaDbEntityNotFoundException(
                message: sprintf(
                    'Entity %s pk=%d not found in catalog %s',
                    $entityType,
                    $primaryKey,
                    $catalog,
                ),
            );
        }

        return $entity;
    }

    public function findEntity(string $catalog, string $entityType, int $primaryKey): ?GrpcSealedEntity
    {
        return $this->entities[$this->entityKey($catalog, $entityType, $primaryKey)] ?? null;
    }

    public function query(string $catalog, GrpcQueryRequest $queryRequest): GrpcQueryResponse
    {
        foreach ($this->queryStubs as $stub) {
            if ($stub->catalog !== $catalog) {
                continue;
            }
            if (($stub->matcher)($queryRequest)) {
                return $stub->response;
            }
        }

        throw new EvitaDbStatusException(
            message: sprintf(
                'EvitaDbMockClient: no query stub matched for catalog %s. Query: %s',
                $catalog,
                $queryRequest->getQuery(),
            ),
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EvitaDbClientInterface — write-side
    // ─────────────────────────────────────────────────────────────────────────

    public function defineCatalog(string $catalog): bool
    {
        $this->definedCatalogs[] = $catalog;

        return true;
    }

    public function defineEntitySchema(string $catalog, string $entityType): true
    {
        $this->definedEntitySchemas[] = new MockedSchemaDefinition(
            catalog: $catalog,
            entityType: $entityType,
        );

        return true;
    }

    public function upsertEntity(string $catalog, GrpcEntityUpsertMutation $upsertMutation): int
    {
        $this->upsertCalls[] = new MockedUpsert(catalog: $catalog, mutation: $upsertMutation);

        return $this->nextPrimaryKey++;
    }

    public function deleteEntity(string $catalog, string $entityType, int $primaryKey): true
    {
        $this->deleteCalls[] = new MockedDelete(
            catalog: $catalog,
            entityType: $entityType,
            primaryKey: $primaryKey,
        );

        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Transaction wrappers
    // ─────────────────────────────────────────────────────────────────────────

    public function transaction(string $catalog, callable $fn, bool $dryRun = false): mixed
    {
        return $fn(new MockSessionScopedContext(
            client: $this,
            catalog: $catalog,
            dryRun: $dryRun,
        ));
    }

    public function readTransaction(string $catalog, callable $fn): mixed
    {
        return $fn(new MockSessionScopedContext(
            client: $this,
            catalog: $catalog,
        ));
    }

    private function entityKey(string $catalog, string $entityType, int $primaryKey): string
    {
        return $catalog . '|' . $entityType . '|' . $primaryKey;
    }
}
