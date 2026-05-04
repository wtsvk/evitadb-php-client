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
use Wtsvk\EvitaDbClient\Exception\EvitaDbEntityNotFoundException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbStatusException;
use Wtsvk\EvitaDbClient\Protocol\EvitaSessionServiceClient;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityUpsertMutation;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcSealedEntity;
use Wtsvk\EvitaDbClient\Protocol\GrpcUpsertEntityResponse;
use Wtsvk\EvitaDbClient\Transaction\SessionScopedContext;

use const Grpc\STATUS_OK;

#[RequiresPhpExtension('grpc')]
#[RequiresPhpExtension('protobuf')]
final class SessionScopedContextTest extends TestCase
{
    private const string SESSION_ID = 'sess-uuid-abc';

    private const string CATALOG = 'testCatalog';

    private EvitaSessionServiceClient&Stub $sessionService;

    private SessionScopedContext $context;

    #[Override]
    protected function setUp(): void
    {
        $this->sessionService = static::createStub(EvitaSessionServiceClient::class);
        $this->context = new SessionScopedContext(
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
        // Use a separate mock to verify the sessionid metadata header is passed.
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

        $context = new SessionScopedContext(
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
        // The method has a `: true` return type, so the meaningful assertion is
        // "no exception thrown when the gRPC status is OK".
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

    public function testUpsertEntityReturnsNullWhenNoEntityReference(): void
    {
        $this->sessionService
            ->method('UpsertEntity')
            ->willReturn($this->createUnaryCall(new GrpcUpsertEntityResponse(), STATUS_OK));

        $this->assertNull($this->context->upsertEntity(new GrpcEntityUpsertMutation()));
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

    public function testDeleteEntityCompletesWithoutThrowingOnSuccess(): void
    {
        $this->sessionService
            ->method('DeleteEntity')
            ->willReturn($this->createUnaryCall(null, STATUS_OK));

        $this->expectNotToPerformAssertions();

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
        // Verify that every operation within the same context uses the same
        // sessionid metadata header — i.e. they're bound to one session.
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

        $mock
            ->expects(static::once())
            ->method('UpsertEntity')
            ->with(static::anything(), $expectedMeta)
            ->willReturn($this->createUnaryCall(new GrpcUpsertEntityResponse(), STATUS_OK));

        $context = new SessionScopedContext(
            sessionService: $mock,
            sessionId: self::SESSION_ID,
            catalog: self::CATALOG,
        );

        $context->query(new GrpcQueryRequest());
        $context->getEntity(entityType: 'Product', primaryKey: 1);
        $context->upsertEntity(new GrpcEntityUpsertMutation());
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
