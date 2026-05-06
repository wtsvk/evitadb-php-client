<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Testing;

use Closure;
use Wtsvk\EvitaDbClient\EvitaDbClientInterface;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcSealedEntity;
use Wtsvk\EvitaDbClient\SessionCommitBehavior;

/**
 * In-memory fake of EvitaDbClientInterface for testing consumer applications.
 *
 * Combines stub (canned responses), spy (records calls) and fake (in-memory entity
 * store) facets. Operates strictly: any call without a matching stub throws so
 * misconfigurations fail loud rather than silently returning empty data.
 *
 * This mock is catalog-scoped (like the real EvitaDbClient). The catalog context
 * is implicit — set at construction time.
 *
 * Typical usage:
 *
 *     $client = (new EvitaDbMockClient('testCatalog'))
 *         ->withEntity(entityType: 'Product', primaryKey: 42, entity: $sealed)
 *         ->onQuery(matcher: $myMatcher, response: $cannedResponse);
 *
 *     $service = new ProductService($client);
 *     $service->doSomething(42);
 *
 *     $this->assertCount(1, $client->upsertCalls);
 */
final class EvitaDbMockClient implements EvitaDbClientInterface
{
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

    public function __construct(
        public readonly string $catalog,
    ) {
    }

    /**
     * Register an entity that will be returned by getEntity()/findEntity()
     * inside transactions.
     */
    public function withEntity(
        string $entityType,
        int $primaryKey,
        GrpcSealedEntity $entity,
    ): self {
        $this->entities[$this->entityKey($entityType, $primaryKey)] = $entity;

        return $this;
    }

    /**
     * Register a query response. The matcher receives the GrpcQueryRequest and
     * decides whether this stub applies. First matching stub wins.
     *
     * @param callable(GrpcQueryRequest): bool $matcher
     */
    public function onQuery(callable $matcher, GrpcQueryResponse $response): self
    {
        $this->queryStubs[] = new MockedQueryStub(
            matcher: Closure::fromCallable($matcher),
            response: $response,
        );

        return $this;
    }

    public function writeTransaction(
        callable $fn,
        bool $dryRun = false,
        ?SessionCommitBehavior $commitBehavior = null,
    ): mixed {
        return $fn(new MockReadWriteSessionScopedContext(
            client: $this,
            dryRun: $dryRun,
        ));
    }

    public function readTransaction(callable $fn): mixed
    {
        return $fn(new MockReadOnlySessionScopedContext(client: $this));
    }

    /**
     * @internal Used by mock context classes to fetch a stubbed entity.
     */
    public function findStubbedEntity(string $entityType, int $primaryKey): ?GrpcSealedEntity
    {
        return $this->entities[$this->entityKey($entityType, $primaryKey)] ?? null;
    }

    /**
     * @internal Used by mock context classes after a successful delete so that
     * subsequent reads in the same test see the entity as gone, matching how
     * the real EvitaDB server behaves.
     */
    public function removeStubbedEntity(string $entityType, int $primaryKey): void
    {
        unset($this->entities[$this->entityKey($entityType, $primaryKey)]);
    }

    /**
     * @internal Used by mock context classes to route queries to registered stubs.
     */
    public function findStubbedQueryResponse(GrpcQueryRequest $queryRequest): ?GrpcQueryResponse
    {
        foreach ($this->queryStubs as $stub) {
            if (($stub->matcher)($queryRequest)) {
                return $stub->response;
            }
        }

        return null;
    }

    private function entityKey(string $entityType, int $primaryKey): string
    {
        return $entityType . '|' . $primaryKey;
    }
}
