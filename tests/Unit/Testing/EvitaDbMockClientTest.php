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
use Wtsvk\EvitaDbClient\Testing\MockReadOnlySessionScopedContext;
use Wtsvk\EvitaDbClient\Testing\MockReadWriteSessionScopedContext;
use Wtsvk\EvitaDbClient\Transaction\ReadTransactionContext;
use Wtsvk\EvitaDbClient\Transaction\WriteTransactionContext;

#[RequiresPhpExtension('grpc')]
#[RequiresPhpExtension('protobuf')]
final class EvitaDbMockClientTest extends TestCase
{
    public function testWriteTransactionPassesReadWriteContext(): void
    {
        $captured = null;
        (new EvitaDbMockClient('cat'))->writeTransaction(
            fn: static function (WriteTransactionContext $tx) use (&$captured): void {
                $captured = $tx;
            },
        );

        $this->assertInstanceOf(MockReadWriteSessionScopedContext::class, $captured);
    }

    public function testReadTransactionPassesReadOnlyContext(): void
    {
        $captured = null;
        (new EvitaDbMockClient('cat'))->readTransaction(
            fn: static function (ReadTransactionContext $tx) use (&$captured): void {
                $captured = $tx;
            },
        );

        $this->assertInstanceOf(MockReadOnlySessionScopedContext::class, $captured);
    }

    public function testGetEntityReturnsStubbedEntityInsideTransaction(): void
    {
        $entity = new GrpcSealedEntity();
        $client = (new EvitaDbMockClient('cat'))
            ->withEntity(entityType: 'Product', primaryKey: 42, entity: $entity);

        $result = $client->readTransaction(
            fn: static fn (ReadTransactionContext $tx): GrpcSealedEntity =>
                $tx->getEntity(entityType: 'Product', primaryKey: 42),
        );

        $this->assertSame($entity, $result);
    }

    public function testGetEntityThrowsNotFoundWhenNoStub(): void
    {
        $client = new EvitaDbMockClient('cat');

        $this->expectException(EvitaDbEntityNotFoundException::class);
        $this->expectExceptionMessageMatches('/not found in catalog cat/');

        $client->readTransaction(
            fn: static fn (ReadTransactionContext $tx): GrpcSealedEntity =>
                $tx->getEntity(entityType: 'Product', primaryKey: 999),
        );
    }

    public function testFindEntityReturnsNullWhenNoStub(): void
    {
        $result = (new EvitaDbMockClient('cat'))->readTransaction(
            fn: static fn (ReadTransactionContext $tx): ?GrpcSealedEntity =>
                $tx->findEntity(entityType: 'Product', primaryKey: 999),
        );

        $this->assertNull($result);
    }

    public function testFindEntityReturnsStubbedEntity(): void
    {
        $entity = new GrpcSealedEntity();
        $client = (new EvitaDbMockClient('cat'))
            ->withEntity(entityType: 'Product', primaryKey: 42, entity: $entity);

        $result = $client->readTransaction(
            fn: static fn (ReadTransactionContext $tx): ?GrpcSealedEntity =>
                $tx->findEntity(entityType: 'Product', primaryKey: 42),
        );

        $this->assertSame($entity, $result);
    }

    public function testEntitiesAreScopedByTypeAndPrimaryKey(): void
    {
        $entityA = new GrpcSealedEntity();
        $entityB = new GrpcSealedEntity();
        $client = (new EvitaDbMockClient('cat'))
            ->withEntity(entityType: 'Product', primaryKey: 1, entity: $entityA)
            ->withEntity(entityType: 'Product', primaryKey: 2, entity: $entityB);

        $resultA = $client->readTransaction(
            fn: static fn (ReadTransactionContext $tx): GrpcSealedEntity =>
                $tx->getEntity(entityType: 'Product', primaryKey: 1),
        );
        $resultB = $client->readTransaction(
            fn: static fn (ReadTransactionContext $tx): GrpcSealedEntity =>
                $tx->getEntity(entityType: 'Product', primaryKey: 2),
        );

        $this->assertSame($entityA, $resultA);
        $this->assertSame($entityB, $resultB);
    }

    public function testQueryReturnsStubResponseWhenMatcherMatches(): void
    {
        $response = new GrpcQueryResponse();
        $client = (new EvitaDbMockClient('cat'))
            ->onQuery(
                matcher: static fn (GrpcQueryRequest $r): bool => $r->getQuery() === 'foo',
                response: $response,
            );

        $request = new GrpcQueryRequest();
        $request->setQuery('foo');

        $result = $client->readTransaction(
            fn: static fn (ReadTransactionContext $tx): GrpcQueryResponse =>
                $tx->query($request),
        );

        $this->assertSame($response, $result);
    }

    public function testQueryThrowsWhenNoMatcherMatches(): void
    {
        $client = (new EvitaDbMockClient('cat'))
            ->onQuery(
                matcher: static fn (): bool => false,
                response: new GrpcQueryResponse(),
            );

        $this->expectException(EvitaDbStatusException::class);
        $this->expectExceptionMessageMatches('/no query stub matched/');

        $client->readTransaction(
            fn: static fn (ReadTransactionContext $tx): GrpcQueryResponse =>
                $tx->query(new GrpcQueryRequest()),
        );
    }

    public function testFirstMatchingQueryStubWins(): void
    {
        $first = new GrpcQueryResponse();
        $second = new GrpcQueryResponse();
        $client = (new EvitaDbMockClient('cat'))
            ->onQuery(matcher: static fn (): bool => true, response: $first)
            ->onQuery(matcher: static fn (): bool => true, response: $second);

        $result = $client->readTransaction(
            fn: static fn (ReadTransactionContext $tx): GrpcQueryResponse =>
                $tx->query(new GrpcQueryRequest()),
        );

        $this->assertSame($first, $result);
    }

    public function testUpsertEntityRecordsCallInsideWriteTransaction(): void
    {
        $client = new EvitaDbMockClient('cat');
        $mutation = new GrpcEntityUpsertMutation();

        $client->writeTransaction(
            fn: static fn (WriteTransactionContext $tx): int =>
                $tx->upsertEntity($mutation),
        );

        $this->assertCount(1, $client->upsertCalls);
        $this->assertSame($mutation, $client->upsertCalls[0]->mutation);
    }

    public function testUpsertEntityReturnsAutoIncrementingPrimaryKey(): void
    {
        $client = new EvitaDbMockClient('cat');

        $first = $client->writeTransaction(
            fn: static fn (WriteTransactionContext $tx): int => $tx->upsertEntity(new GrpcEntityUpsertMutation()),
        );
        $second = $client->writeTransaction(
            fn: static fn (WriteTransactionContext $tx): int => $tx->upsertEntity(new GrpcEntityUpsertMutation()),
        );
        $third = $client->writeTransaction(
            fn: static fn (WriteTransactionContext $tx): int => $tx->upsertEntity(new GrpcEntityUpsertMutation()),
        );

        $this->assertSame(1, $first);
        $this->assertSame(2, $second);
        $this->assertSame(3, $third);
    }

    public function testNextPrimaryKeyCanBeAdjusted(): void
    {
        $client = new EvitaDbMockClient('cat');
        $client->nextPrimaryKey = 1000;

        $assigned = $client->writeTransaction(
            fn: static fn (WriteTransactionContext $tx): int => $tx->upsertEntity(new GrpcEntityUpsertMutation()),
        );

        $this->assertSame(1000, $assigned);
    }

    public function testDeleteEntityRecordsCallInsideWriteTransaction(): void
    {
        $client = (new EvitaDbMockClient('cat'))
            ->withEntity(entityType: 'Product', primaryKey: 42, entity: new GrpcSealedEntity());

        $client->writeTransaction(
            fn: static fn (WriteTransactionContext $tx): true =>
                $tx->deleteEntity(entityType: 'Product', primaryKey: 42),
        );

        $this->assertCount(1, $client->deleteCalls);
        $this->assertSame('Product', $client->deleteCalls[0]->entityType);
        $this->assertSame(42, $client->deleteCalls[0]->primaryKey);
    }

    public function testDeleteEntityThrowsNotFoundWhenEntityWasNotStubbed(): void
    {
        $client = new EvitaDbMockClient('cat');

        $this->expectException(EvitaDbEntityNotFoundException::class);
        $this->expectExceptionMessageMatches('/not found in catalog cat/');

        $client->writeTransaction(
            fn: static fn (WriteTransactionContext $tx): true =>
                $tx->deleteEntity(entityType: 'Product', primaryKey: 42),
        );
    }

    public function testDeleteEntityRemovesEntityFromStubStoreSoSubsequentReadsThrow(): void
    {
        $client = (new EvitaDbMockClient('cat'))
            ->withEntity(entityType: 'Product', primaryKey: 42, entity: new GrpcSealedEntity());

        $client->writeTransaction(
            fn: static fn (WriteTransactionContext $tx): true =>
                $tx->deleteEntity(entityType: 'Product', primaryKey: 42),
        );

        $this->expectException(EvitaDbEntityNotFoundException::class);

        $client->readTransaction(
            fn: static fn (ReadTransactionContext $tx): GrpcSealedEntity =>
                $tx->getEntity(entityType: 'Product', primaryKey: 42),
        );
    }

    public function testDefineEntitySchemaRecordsCallInsideWriteTransaction(): void
    {
        $client = new EvitaDbMockClient('cat');

        $client->writeTransaction(
            fn: static fn (WriteTransactionContext $tx): true =>
                $tx->defineEntitySchema(entityType: 'Product'),
        );

        $this->assertCount(1, $client->definedEntitySchemas);
        $this->assertSame('Product', $client->definedEntitySchemas[0]->entityType);
    }

    public function testWriteTransactionWithDryRunSkipsRecordingMutations(): void
    {
        $client = (new EvitaDbMockClient('cat'))
            ->withEntity(entityType: 'Product', primaryKey: 7, entity: new GrpcSealedEntity());

        $client->writeTransaction(
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

    public function testWriteTransactionWithDryRunStillReturnsPrimaryKeyForUpsert(): void
    {
        $client = new EvitaDbMockClient('cat');

        $assignedPk = $client->writeTransaction(
            fn: static fn (WriteTransactionContext $tx): int =>
                $tx->upsertEntity(new GrpcEntityUpsertMutation()),
            dryRun: true,
        );

        $this->assertSame(1, $assignedPk);
        $this->assertSame([], $client->upsertCalls);
    }

    public function testWriteTransactionWithDryRunStillAllowsReads(): void
    {
        $entity = new GrpcSealedEntity();
        $client = (new EvitaDbMockClient('cat'))
            ->withEntity(entityType: 'Product', primaryKey: 1, entity: $entity);

        $result = $client->writeTransaction(
            fn: static fn (WriteTransactionContext $tx): GrpcSealedEntity =>
                $tx->getEntity(entityType: 'Product', primaryKey: 1),
            dryRun: true,
        );

        $this->assertSame($entity, $result);
    }

    public function testWriteTransactionExceptionPropagatesAndKeepsRecordedMutations(): void
    {
        $client = new EvitaDbMockClient('cat');

        try {
            $client->writeTransaction(
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
}
