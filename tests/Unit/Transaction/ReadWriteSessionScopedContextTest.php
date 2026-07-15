<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Tests\Unit\Transaction;

use Google\Protobuf\Internal\Message;
use Grpc\UnaryCall;
use Override;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use stdClass;
use Webmozart\Assert\Assert;
use Wtsvk\EvitaDbClient\EntityFetch;
use Wtsvk\EvitaDbClient\Exception\EvitaDbEntityNotFoundException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbStatusException;
use Wtsvk\EvitaDbClient\Protocol\EvitaSessionServiceClient;
use Wtsvk\EvitaDbClient\Protocol\GrpcDeleteEntityResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityReference;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityUpsertMutation;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryParam;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcSealedEntity;
use Wtsvk\EvitaDbClient\Protocol\GrpcUpsertEntityRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcUpsertEntityResponse;
use Wtsvk\EvitaDbClient\Transaction\ReadWriteSessionScopedContext;

use function count;
use function iterator_to_array;

use const Grpc\STATUS_OK;

#[RequiresPhpExtension('grpc')]
#[RequiresPhpExtension('protobuf')]
final class ReadWriteSessionScopedContextTest extends TestCase
{
    private const string SESSION_ID = 'sess-uuid-abc';

    private const string CATALOG = 'testCatalog';

    private EvitaSessionServiceClient&Stub $sessionService;

    private ReadWriteSessionScopedContext $context;

    #[Override]
    protected function setUp(): void
    {
        $this->sessionService = static::createStub(EvitaSessionServiceClient::class);
        $this->context = new ReadWriteSessionScopedContext(
            sessionService: $this->sessionService,
            sessionId: self::SESSION_ID,
            catalog: self::CATALOG,
        );
    }

    public function testQueryReturnsResponseOnSuccess(): void
    {
        $expected = new GrpcQueryResponse();
        $this->sessionService
            ->method('Query')
            ->willReturn($this->createUnaryCall($expected, STATUS_OK));

        $this->assertSame($expected, $this->context->query(new GrpcQueryRequest()));
    }

    public function testQueryThrowsStatusExceptionOnNonOkStatus(): void
    {
        $this->sessionService
            ->method('Query')
            ->willReturn($this->createUnaryCall(null, 13, 'internal error'));

        $this->expectException(EvitaDbStatusException::class);
        $this->expectExceptionMessageMatches('/Query failed/');

        $this->context->query(new GrpcQueryRequest());
    }

    public function testQueryPassesSessionIdInMetadata(): void
    {
        /** @var EvitaSessionServiceClient&MockObject $mock */
        $mock = static::createMock(EvitaSessionServiceClient::class);
        $mock
            ->expects(static::once())
            ->method('Query')
            ->with(
                static::isInstanceOf(GrpcQueryRequest::class),
                ['sessionid' => [self::SESSION_ID]],
            )
            ->willReturn($this->createUnaryCall(new GrpcQueryResponse(), STATUS_OK));

        $context = new ReadWriteSessionScopedContext(
            sessionService: $mock,
            sessionId: self::SESSION_ID,
            catalog: self::CATALOG,
        );

        $context->query(new GrpcQueryRequest());
    }

    public function testGetEntityReturnsEntityOnSuccess(): void
    {
        $entity = new GrpcSealedEntity();
        $response = new GrpcEntityResponse();
        $response->setEntity($entity);

        $this->sessionService
            ->method('GetEntity')
            ->willReturn($this->createUnaryCall($response, STATUS_OK));

        $this->assertSame(
            $entity,
            $this->context->getEntity(entityType: 'Product', primaryKey: 1),
        );
    }

    public function testGetEntityThrowsNotFoundWhenEntityIsNull(): void
    {
        $this->sessionService
            ->method('GetEntity')
            ->willReturn($this->createUnaryCall(new GrpcEntityResponse(), STATUS_OK));

        $this->expectException(EvitaDbEntityNotFoundException::class);
        $this->expectExceptionMessageMatches('/not found in catalog ' . self::CATALOG . '/');

        $this->context->getEntity(entityType: 'Product', primaryKey: 999);
    }

    public function testGetEntityThrowsStatusExceptionOnFailure(): void
    {
        $this->sessionService
            ->method('GetEntity')
            ->willReturn($this->createUnaryCall(null, 14, 'unavailable'));

        $this->expectException(EvitaDbStatusException::class);
        $this->expectExceptionMessageMatches('/GetEntity failed/');

        $this->context->getEntity(entityType: 'Product', primaryKey: 1);
    }

    public function testFindEntityReturnsNullWhenEntityIsNull(): void
    {
        $this->sessionService
            ->method('GetEntity')
            ->willReturn($this->createUnaryCall(new GrpcEntityResponse(), STATUS_OK));

        $this->assertNull($this->context->findEntity(entityType: 'Product', primaryKey: 999));
    }

    public function testFindEntityReturnsEntityOnSuccess(): void
    {
        $entity = new GrpcSealedEntity();
        $response = new GrpcEntityResponse();
        $response->setEntity($entity);

        $this->sessionService
            ->method('GetEntity')
            ->willReturn($this->createUnaryCall($response, STATUS_OK));

        $this->assertSame(
            $entity,
            $this->context->findEntity(entityType: 'Product', primaryKey: 1),
        );
    }

    public function testFindEntityThrowsStatusExceptionOnFailure(): void
    {
        $this->sessionService
            ->method('GetEntity')
            ->willReturn($this->createUnaryCall(null, 14, 'unavailable'));

        $this->expectException(EvitaDbStatusException::class);

        $this->context->findEntity(entityType: 'Product', primaryKey: 1);
    }

    public function testDefineEntitySchemaCompletesWithoutThrowingOnSuccess(): void
    {
        $this->sessionService
            ->method('DefineEntitySchema')
            ->willReturn($this->createUnaryCall(null, STATUS_OK));

        $this->expectNotToPerformAssertions();

        $this->context->defineEntitySchema('Product');
    }

    public function testDefineEntitySchemaThrowsStatusExceptionOnFailure(): void
    {
        $this->sessionService
            ->method('DefineEntitySchema')
            ->willReturn($this->createUnaryCall(null, 5, 'invalid'));

        $this->expectException(EvitaDbStatusException::class);
        $this->expectExceptionMessageMatches('/Failed to define entity schema/');

        $this->context->defineEntitySchema('Product');
    }

    public function testUpsertEntityThrowsExceptionWhenNoResponseSet(): void
    {
        $this->sessionService
            ->method('UpsertEntity')
            ->willReturn($this->createUnaryCall(new GrpcUpsertEntityResponse(), STATUS_OK));

        $this->expectException(EvitaDbStatusException::class);
        $this->expectExceptionMessage('no entity identification');

        $this->context->upsertEntity(new GrpcEntityUpsertMutation());
    }

    public function testUpsertEntityReturnsPkFromSealedEntity(): void
    {
        $entity = new GrpcSealedEntity();
        $entity->setPrimaryKey(123);

        $response = new GrpcUpsertEntityResponse();
        $response->setEntity($entity);

        $this->sessionService
            ->method('UpsertEntity')
            ->willReturn($this->createUnaryCall($response, STATUS_OK));

        $this->assertSame(123, $this->context->upsertEntity(new GrpcEntityUpsertMutation()));
    }

    public function testUpsertEntityThrowsStatusExceptionOnFailure(): void
    {
        $this->sessionService
            ->method('UpsertEntity')
            ->willReturn($this->createUnaryCall(null, 13, 'failure'));

        $this->expectException(EvitaDbStatusException::class);
        $this->expectExceptionMessageMatches('/Failed to upsert entity/');

        $this->context->upsertEntity(new GrpcEntityUpsertMutation());
    }

    public function testDeleteEntityCompletesWithoutThrowingWhenEntityExists(): void
    {
        $response = new GrpcDeleteEntityResponse();
        $ref = new GrpcEntityReference();
        $ref->setPrimaryKey(42);
        $response->setEntityReference($ref);

        $this->sessionService
            ->method('DeleteEntity')
            ->willReturn($this->createUnaryCall($response, STATUS_OK));

        $this->expectNotToPerformAssertions();

        $this->context->deleteEntity(entityType: 'Product', primaryKey: 42);
    }

    public function testDeleteEntityThrowsNotFoundWhenResponseIsEmpty(): void
    {
        $response = new GrpcDeleteEntityResponse();

        $this->sessionService
            ->method('DeleteEntity')
            ->willReturn($this->createUnaryCall($response, STATUS_OK));

        $this->expectException(EvitaDbEntityNotFoundException::class);
        $this->expectExceptionMessageMatches('/not found in catalog ' . self::CATALOG . '/');

        $this->context->deleteEntity(entityType: 'Product', primaryKey: 42);
    }

    public function testDeleteEntityThrowsStatusExceptionOnFailure(): void
    {
        $this->sessionService
            ->method('DeleteEntity')
            ->willReturn($this->createUnaryCall(null, 5, 'not found'));

        $this->expectException(EvitaDbStatusException::class);
        $this->expectExceptionMessageMatches('/Failed to delete entity Product pk=42/');

        $this->context->deleteEntity(entityType: 'Product', primaryKey: 42);
    }

    public function testMultipleCallsShareTheSameSessionId(): void
    {
        $expectedMeta = ['sessionid' => [self::SESSION_ID]];

        /** @var EvitaSessionServiceClient&MockObject $mock */
        $mock = static::createMock(EvitaSessionServiceClient::class);

        $mock
            ->expects(static::once())
            ->method('Query')
            ->with(static::anything(), $expectedMeta)
            ->willReturn($this->createUnaryCall(new GrpcQueryResponse(), STATUS_OK));

        $entityResponse = new GrpcEntityResponse();
        $entityResponse->setEntity(new GrpcSealedEntity());

        $mock
            ->expects(static::once())
            ->method('GetEntity')
            ->with(static::anything(), $expectedMeta)
            ->willReturn($this->createUnaryCall($entityResponse, STATUS_OK));

        $upsertResponse = new GrpcUpsertEntityResponse();
        $ref = new GrpcEntityReference();
        $ref->setPrimaryKey(42);
        $upsertResponse->setEntityReference($ref);

        $mock
            ->expects(static::once())
            ->method('UpsertEntity')
            ->with(static::anything(), $expectedMeta)
            ->willReturn($this->createUnaryCall($upsertResponse, STATUS_OK));

        $context = new ReadWriteSessionScopedContext(
            sessionService: $mock,
            sessionId: self::SESSION_ID,
            catalog: self::CATALOG,
        );

        $context->query(new GrpcQueryRequest());
        $context->getEntity(entityType: 'Product', primaryKey: 1);
        $context->upsertEntity(new GrpcEntityUpsertMutation());
    }

    public function testGetEntityWithCustomEntityFetch(): void
    {
        $entity = new GrpcSealedEntity();
        $response = new GrpcEntityResponse();
        $response->setEntity($entity);

        /** @var EvitaSessionServiceClient&MockObject $mock */
        $mock = static::createMock(EvitaSessionServiceClient::class);
        $mock
            ->expects(static::once())
            ->method('GetEntity')
            ->with(
                static::callback(static function (mixed $request): bool {
                    Assert::isInstanceOf($request, GrpcEntityRequest::class);

                    // GetEntity accepts bare content requires only — no entityFetch() wrapper.
                    return $request->getRequire() === 'attributeContent(?)';
                }),
                static::anything(),
            )
            ->willReturn($this->createUnaryCall($response, STATUS_OK));

        $context = new ReadWriteSessionScopedContext(
            sessionService: $mock,
            sessionId: self::SESSION_ID,
            catalog: self::CATALOG,
        );

        $context->getEntity(
            entityType: 'Product',
            primaryKey: 1,
            require: (new EntityFetch())->attributeContent('name'),
        );
    }

    public function testUpsertWithEntityFetchSetsRequireString(): void
    {
        $upsertResponse = new GrpcUpsertEntityResponse();
        $ref = new GrpcEntityReference();
        $ref->setPrimaryKey(1);
        $upsertResponse->setEntityReference($ref);

        /** @var EvitaSessionServiceClient&MockObject $mock */
        $mock = static::createMock(EvitaSessionServiceClient::class);
        $mock
            ->expects(static::once())
            ->method('UpsertEntity')
            ->with(
                static::callback(static function (mixed $request): bool {
                    Assert::isInstanceOf($request, GrpcUpsertEntityRequest::class);

                    // UpsertEntity accepts bare content requires only — no entityFetch() wrapper.
                    return $request->getRequire() === 'attributeContentAll()';
                }),
                static::anything(),
            )
            ->willReturn($this->createUnaryCall($upsertResponse, STATUS_OK));

        $context = new ReadWriteSessionScopedContext(
            sessionService: $mock,
            sessionId: self::SESSION_ID,
            catalog: self::CATALOG,
        );

        $context->upsert(
            upsertMutation: new GrpcEntityUpsertMutation(),
            require: (new EntityFetch())->attributeContentAll(),
        );
    }

    public function testUpsertWithParameterizedEntityFetchSetsPositionalParams(): void
    {
        $upsertResponse = new GrpcUpsertEntityResponse();
        $ref = new GrpcEntityReference();
        $ref->setPrimaryKey(1);
        $upsertResponse->setEntityReference($ref);

        /** @var EvitaSessionServiceClient&MockObject $mock */
        $mock = static::createMock(EvitaSessionServiceClient::class);
        $mock
            ->expects(static::once())
            ->method('UpsertEntity')
            ->with(
                static::callback(static function (mixed $request): bool {
                    Assert::isInstanceOf($request, GrpcUpsertEntityRequest::class);

                    /** @var list<GrpcQueryParam> $params */
                    $params = iterator_to_array($request->getPositionalQueryParams());

                    // Every ? placeholder in require must have a matching positional param.
                    return $request->getRequire() === 'attributeContent(?)'
                        && count($params) === 1
                        && $params[0]->getStringValue() === 'name';
                }),
                static::anything(),
            )
            ->willReturn($this->createUnaryCall($upsertResponse, STATUS_OK));

        $context = new ReadWriteSessionScopedContext(
            sessionService: $mock,
            sessionId: self::SESSION_ID,
            catalog: self::CATALOG,
        );

        $context->upsert(
            upsertMutation: new GrpcEntityUpsertMutation(),
            require: (new EntityFetch())->attributeContent('name'),
        );
    }

    /**
     * @return UnaryCall<Message>&Stub
     */
    private function createUnaryCall(mixed $response, int $code, string $details = ''): UnaryCall&Stub
    {
        $status = new stdClass();
        $status->code = $code;
        $status->details = $details;
        $status->metadata = [];

        $unaryCall = static::createStub(UnaryCall::class);
        $unaryCall->method('wait')->willReturn([$response, $status]);

        return $unaryCall;
    }
}
