<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Tests\Unit\Testing;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbEntityNotFoundException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbStatusException;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityUpsertMutation;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcSealedEntity;
use Wtsvk\EvitaDbClient\Testing\EvitaDbMockClient;
use Wtsvk\EvitaDbClient\Transaction\ReadTransactionContext;
use Wtsvk\EvitaDbClient\Transaction\WriteTransactionContext;

#[RequiresPhpExtension('grpc')]
#[RequiresPhpExtension('protobuf')]
final class EvitaDbMockClientTest extends TestCase
{
    public function testIsHealthyDefaultsToTrue(): void
    {
        $this->assertTrue((new EvitaDbMockClient())->isHealthy());
    }

    public function testIsHealthyCanBeFlipped(): void
    {
        $client = new EvitaDbMockClient();
        $client->healthy = false;

        $this->assertFalse($client->isHealthy());
    }

    public function testGetEntityReturnsStubbedEntity(): void
    {
        $entity = new GrpcSealedEntity();
        $client = (new EvitaDbMockClient())
            ->withEntity(catalog: 'cat', entityType: 'Product', primaryKey: 42, entity: $entity);

        $this->assertSame(
            $entity,
            $client->getEntity(catalog: 'cat', entityType: 'Product', primaryKey: 42),
        );
    }

    public function testGetEntityThrowsNotFoundWhenNoStub(): void
    {
        $client = new EvitaDbMockClient();

        $this->expectException(EvitaDbEntityNotFoundException::class);
        $this->expectExceptionMessageMatches('/not found in catalog cat/');

        $client->getEntity(catalog: 'cat', entityType: 'Product', primaryKey: 999);
    }

    public function testFindEntityReturnsNullWhenNoStub(): void
    {
        $this->assertNull(
            (new EvitaDbMockClient())->findEntity(
                catalog: 'cat',
                entityType: 'Product',
                primaryKey: 999,
            ),
        );
    }

    public function testFindEntityReturnsStubbedEntity(): void
    {
        $entity = new GrpcSealedEntity();
        $client = (new EvitaDbMockClient())
            ->withEntity(catalog: 'cat', entityType: 'Product', primaryKey: 42, entity: $entity);

        $this->assertSame(
            $entity,
            $client->findEntity(catalog: 'cat', entityType: 'Product', primaryKey: 42),
        );
    }

    public function testEntitiesAreScopedByCatalogTypeAndPrimaryKey(): void
    {
        $entityA = new GrpcSealedEntity();
        $entityB = new GrpcSealedEntity();
        $client = (new EvitaDbMockClient())
            ->withEntity(catalog: 'cat', entityType: 'Product', primaryKey: 1, entity: $entityA)
            ->withEntity(catalog: 'cat', entityType: 'Product', primaryKey: 2, entity: $entityB);

        $this->assertSame(
            $entityA,
            $client->getEntity(catalog: 'cat', entityType: 'Product', primaryKey: 1),
        );
        $this->assertSame(
            $entityB,
            $client->getEntity(catalog: 'cat', entityType: 'Product', primaryKey: 2),
        );
    }

    public function testQueryReturnsStubResponseWhenMatcherMatches(): void
    {
        $response = new GrpcQueryResponse();
        $client = (new EvitaDbMockClient())
            ->onQuery(
                catalog: 'cat',
                matcher: static fn (GrpcQueryRequest $r): bool => $r->getQuery() === 'foo',
                response: $response,
            );

        $request = new GrpcQueryRequest();
        $request->setQuery('foo');

        $this->assertSame($response, $client->query(catalog: 'cat', queryRequest: $request));
    }

    public function testQueryThrowsWhenNoMatcherMatches(): void
    {
        $client = (new EvitaDbMockClient())
            ->onQuery(
                catalog: 'cat',
                matcher: static fn (): bool => false,
                response: new GrpcQueryResponse(),
            );

        $this->expectException(EvitaDbStatusException::class);
        $this->expectExceptionMessageMatches('/no query stub matched/');

        $client->query(catalog: 'cat', queryRequest: new GrpcQueryRequest());
    }

    public function testQueryThrowsWhenNoStubsRegistered(): void
    {
        $this->expectException(EvitaDbStatusException::class);
        $this->expectExceptionMessageMatches('/no query stub matched/');

        (new EvitaDbMockClient())->query(catalog: 'cat', queryRequest: new GrpcQueryRequest());
    }

    public function testQueryStubsAreScopedByCatalog(): void
    {
        $response = new GrpcQueryResponse();
        $client = (new EvitaDbMockClient())
            ->onQuery(
                catalog: 'other',
                matcher: static fn (): bool => true,
                response: $response,
            );

        $this->expectException(EvitaDbStatusException::class);

        $client->query(catalog: 'cat', queryRequest: new GrpcQueryRequest());
    }

    public function testFirstMatchingQueryStubWins(): void
    {
        $first = new GrpcQueryResponse();
        $second = new GrpcQueryResponse();
        $client = (new EvitaDbMockClient())
            ->onQuery(
                catalog: 'cat',
                matcher: static fn (): bool => true,
                response: $first,
            )
            ->onQuery(
                catalog: 'cat',
                matcher: static fn (): bool => true,
                response: $second,
            );

        $this->assertSame(
            $first,
            $client->query(catalog: 'cat', queryRequest: new GrpcQueryRequest()),
        );
    }

    public function testUpsertEntityRecordsCall(): void
    {
        $client = new EvitaDbMockClient();
        $mutation = new GrpcEntityUpsertMutation();

        $client->upsertEntity(catalog: 'cat', upsertMutation: $mutation);

        $this->assertCount(1, $client->upsertCalls);
        $this->assertSame('cat', $client->upsertCalls[0]->catalog);
        $this->assertSame($mutation, $client->upsertCalls[0]->mutation);
    }

    public function testUpsertEntityReturnsAutoIncrementingPrimaryKey(): void
    {
        $client = new EvitaDbMockClient();

        $this->assertSame(1, $client->upsertEntity(catalog: 'cat', upsertMutation: new GrpcEntityUpsertMutation()));
        $this->assertSame(2, $client->upsertEntity(catalog: 'cat', upsertMutation: new GrpcEntityUpsertMutation()));
        $this->assertSame(3, $client->upsertEntity(catalog: 'cat', upsertMutation: new GrpcEntityUpsertMutation()));
    }

    public function testNextPrimaryKeyCanBeAdjusted(): void
    {
        $client = new EvitaDbMockClient();
        $client->nextPrimaryKey = 1000;

        $this->assertSame(1000, $client->upsertEntity(catalog: 'cat', upsertMutation: new GrpcEntityUpsertMutation()));
        $this->assertSame(1001, $client->upsertEntity(catalog: 'cat', upsertMutation: new GrpcEntityUpsertMutation()));
    }

    public function testDeleteEntityRecordsCall(): void
    {
        $client = new EvitaDbMockClient();

        $client->deleteEntity(catalog: 'cat', entityType: 'Product', primaryKey: 42);

        $this->assertCount(1, $client->deleteCalls);
        $this->assertSame('cat', $client->deleteCalls[0]->catalog);
        $this->assertSame('Product', $client->deleteCalls[0]->entityType);
        $this->assertSame(42, $client->deleteCalls[0]->primaryKey);
    }

    public function testDefineCatalogRecordsCall(): void
    {
        $client = new EvitaDbMockClient();

        $client->defineCatalog('cat');

        $this->assertSame(['cat'], $client->definedCatalogs);
    }

    public function testDefineEntitySchemaRecordsCall(): void
    {
        $client = new EvitaDbMockClient();

        $client->defineEntitySchema(catalog: 'cat', entityType: 'Product');

        $this->assertCount(1, $client->definedEntitySchemas);
        $this->assertSame('cat', $client->definedEntitySchemas[0]->catalog);
        $this->assertSame('Product', $client->definedEntitySchemas[0]->entityType);
    }

    public function testTransactionPassesWriteContextAndDelegatesReads(): void
    {
        $entity = new GrpcSealedEntity();
        $client = (new EvitaDbMockClient())
            ->withEntity(catalog: 'cat', entityType: 'Product', primaryKey: 1, entity: $entity);

        $captured = $client->transaction(
            catalog: 'cat',
            fn: static fn (WriteTransactionContext $tx): GrpcSealedEntity =>
                $tx->getEntity(entityType: 'Product', primaryKey: 1),
        );

        $this->assertSame($entity, $captured);
    }

    public function testTransactionDelegatesWritesToClient(): void
    {
        $client = new EvitaDbMockClient();

        $client->transaction(
            catalog: 'cat',
            fn: static function (WriteTransactionContext $tx): void {
                $tx->upsertEntity(new GrpcEntityUpsertMutation());
                $tx->deleteEntity(entityType: 'Product', primaryKey: 7);
            },
        );

        $this->assertCount(1, $client->upsertCalls);
        $this->assertCount(1, $client->deleteCalls);
        $this->assertSame('cat', $client->upsertCalls[0]->catalog);
        $this->assertSame(7, $client->deleteCalls[0]->primaryKey);
    }

    public function testTransactionWithDryRunSkipsRecordingMutations(): void
    {
        $client = new EvitaDbMockClient();

        $client->transaction(
            catalog: 'cat',
            fn: static function (WriteTransactionContext $tx): void {
                $tx->upsertEntity(new GrpcEntityUpsertMutation());
                $tx->deleteEntity(entityType: 'Product', primaryKey: 7);
                $tx->defineEntitySchema('Product');
            },
            dryRun: true,
        );

        $this->assertSame([], $client->upsertCalls);
        $this->assertSame([], $client->deleteCalls);
        $this->assertSame([], $client->definedEntitySchemas);
    }

    public function testTransactionWithDryRunStillReturnsPrimaryKeyForUpsert(): void
    {
        // Real EvitaDB assigns a PK even when dryRun is on (changes are discarded
        // at session close, but mutations executed within the session do see PKs).
        // Mirror that so consumer code that reads the returned PK keeps working
        // under dryRun.
        $client = new EvitaDbMockClient();

        $assignedPk = $client->transaction(
            catalog: 'cat',
            fn: static fn (WriteTransactionContext $tx): ?int =>
                $tx->upsertEntity(new GrpcEntityUpsertMutation()),
            dryRun: true,
        );

        $this->assertSame(1, $assignedPk);
        $this->assertSame([], $client->upsertCalls); // not recorded, but PK was returned
    }

    public function testTransactionWithDryRunStillAllowsReads(): void
    {
        $entity = new GrpcSealedEntity();
        $client = (new EvitaDbMockClient())
            ->withEntity(catalog: 'cat', entityType: 'Product', primaryKey: 1, entity: $entity);

        $result = $client->transaction(
            catalog: 'cat',
            fn: static fn (WriteTransactionContext $tx): GrpcSealedEntity =>
                $tx->getEntity(entityType: 'Product', primaryKey: 1),
            dryRun: true,
        );

        $this->assertSame($entity, $result);
    }

    public function testTransactionExceptionPropagatesAndKeepsRecordedMutations(): void
    {
        // Mirrors real EvitaDB: mutations applied before the exception are NOT
        // rolled back at session close (the server commits regardless).
        $client = new EvitaDbMockClient();

        try {
            $client->transaction(
                catalog: 'cat',
                fn: static function (WriteTransactionContext $tx): void {
                    $tx->upsertEntity(new GrpcEntityUpsertMutation());

                    throw new RuntimeException('boom');
                },
            );
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertCount(1, $client->upsertCalls);
    }

    public function testReadTransactionPassesReadContext(): void
    {
        $entity = new GrpcSealedEntity();
        $client = (new EvitaDbMockClient())
            ->withEntity(catalog: 'cat', entityType: 'Product', primaryKey: 1, entity: $entity);

        $result = $client->readTransaction(
            catalog: 'cat',
            fn: static fn (ReadTransactionContext $tx): ?GrpcSealedEntity =>
                $tx->findEntity(entityType: 'Product', primaryKey: 1),
        );

        $this->assertSame($entity, $result);
    }

    public function testTransactionContextRoutesQueryThroughMockClient(): void
    {
        $response = new GrpcQueryResponse();
        $request = new GrpcQueryRequest();
        $request->setQuery('q');

        $client = (new EvitaDbMockClient())
            ->onQuery(
                catalog: 'cat',
                matcher: static fn (GrpcQueryRequest $r): bool => $r->getQuery() === 'q',
                response: $response,
            );

        $result = $client->transaction(
            catalog: 'cat',
            fn: static fn (WriteTransactionContext $tx): GrpcQueryResponse => $tx->query($request),
        );

        $this->assertSame($response, $result);
    }
}
