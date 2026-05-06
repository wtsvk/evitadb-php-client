<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Tests\Unit\Transaction;

use Google\Protobuf\Internal\Message;
use Grpc\UnaryCall;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use stdClass;
use Wtsvk\EvitaDbClient\Exception\EvitaDbEntityNotFoundException;
use Wtsvk\EvitaDbClient\Protocol\EvitaSessionServiceClient;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcSealedEntity;
use Wtsvk\EvitaDbClient\Transaction\ReadOnlySessionScopedContext;

use const Grpc\STATUS_OK;

/**
 * The read-side gRPC behavior is exhaustively covered by ReadWriteSessionScopedContextTest
 * (both classes share the SessionScopedReads trait). This test verifies the read-only
 * variant correctly wires the trait and exposes only the ReadTransactionContext interface.
 */
#[RequiresPhpExtension('grpc')]
#[RequiresPhpExtension('protobuf')]
final class ReadOnlySessionScopedContextTest extends TestCase
{
    private const string SESSION_ID = 'sess-uuid-ro';

    private const string CATALOG = 'testCatalog';

    public function testQueryDelegatesThroughTraitToSessionService(): void
    {
        $sessionService = static::createStub(EvitaSessionServiceClient::class);
        $expected = new GrpcQueryResponse();
        $sessionService
            ->method('Query')
            ->willReturn($this->createUnaryCall($expected, STATUS_OK));

        $context = new ReadOnlySessionScopedContext(
            sessionService: $sessionService,
            sessionId: self::SESSION_ID,
            catalog: self::CATALOG,
        );

        $this->assertSame($expected, $context->query(new GrpcQueryRequest()));
    }

    public function testGetEntityThrowsNotFoundWhenMissing(): void
    {
        $sessionService = static::createStub(EvitaSessionServiceClient::class);
        $sessionService
            ->method('GetEntity')
            ->willReturn($this->createUnaryCall(new GrpcEntityResponse(), STATUS_OK));

        $context = new ReadOnlySessionScopedContext(
            sessionService: $sessionService,
            sessionId: self::SESSION_ID,
            catalog: self::CATALOG,
        );

        $this->expectException(EvitaDbEntityNotFoundException::class);

        $context->getEntity(entityType: 'Product', primaryKey: 999);
    }

    public function testFindEntityReturnsEntity(): void
    {
        $entity = new GrpcSealedEntity();
        $response = new GrpcEntityResponse();
        $response->setEntity($entity);

        $sessionService = static::createStub(EvitaSessionServiceClient::class);
        $sessionService
            ->method('GetEntity')
            ->willReturn($this->createUnaryCall($response, STATUS_OK));

        $context = new ReadOnlySessionScopedContext(
            sessionService: $sessionService,
            sessionId: self::SESSION_ID,
            catalog: self::CATALOG,
        );

        $this->assertSame($entity, $context->findEntity(entityType: 'Product', primaryKey: 1));
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
