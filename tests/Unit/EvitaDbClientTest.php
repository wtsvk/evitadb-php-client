<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Tests\Unit;

use Google\Protobuf\Internal\Message;
use Grpc\UnaryCall;
use Override;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Wtsvk\EvitaDbClient\EvitaDbClient;
use Wtsvk\EvitaDbClient\Exception\EvitaDbConnectionException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbEntityNotFoundException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbStatusException;
use Wtsvk\EvitaDbClient\Protocol\EvitaServiceClient;
use Wtsvk\EvitaDbClient\Protocol\EvitaSessionServiceClient;
use Wtsvk\EvitaDbClient\Protocol\GrpcDefineCatalogResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcReadyResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcSealedEntity;

use const Grpc\STATUS_OK;

#[RequiresPhpExtension('grpc')]
#[RequiresPhpExtension('protobuf')]
final class EvitaDbClientTest extends TestCase
{
    private EvitaDbClient $client;

    private EvitaServiceClient&Stub $evitaService;

    private EvitaSessionServiceClient&Stub $sessionService;

    #[Override]
    protected function setUp(): void
    {
        $this->evitaService = static::createStub(EvitaServiceClient::class);
        $this->sessionService = static::createStub(EvitaSessionServiceClient::class);

        $this->client = new EvitaDbClient(
            evitaService: $this->evitaService,
            sessionService: $this->sessionService,
        );
    }

    public function testIsHealthyReturnsTrueWhenReady(): void
    {
        $response = new GrpcReadyResponse();
        $response->setReady(true);

        $this->mockEvitaCall('IsReady', $response, STATUS_OK);

        $this->assertTrue($this->client->isHealthy());
    }

    public function testIsHealthyReturnsFalseOnNonOkStatus(): void
    {
        $this->mockEvitaCall('IsReady', null, 14);

        $this->assertFalse($this->client->isHealthy());
    }

    public function testIsHealthyReturnsFalseOnException(): void
    {
        $unaryCall = static::createStub(UnaryCall::class);
        $unaryCall->method('wait')->willThrowException(new RuntimeException('connection refused'));

        $this->evitaService->method('IsReady')->willReturn($unaryCall);

        $this->assertFalse($this->client->isHealthy());
    }

    public function testDefineCatalogReturnsTrue(): void
    {
        $response = new GrpcDefineCatalogResponse();
        $response->setSuccess(true);

        $this->mockEvitaCall('DefineCatalog', $response, STATUS_OK);

        $this->assertTrue($this->client->defineCatalog('testCatalog'));
    }

    public function testDefineCatalogThrowsStatusExceptionOnFailure(): void
    {
        $this->mockEvitaCall('DefineCatalog', null, 5, 'catalog error');

        $this->expectException(EvitaDbStatusException::class);
        $this->expectExceptionMessageMatches('/Failed to define catalog/');

        $this->client->defineCatalog('testCatalog');
    }

    public function testDefineCatalogThrowsConnectionExceptionOnTransportError(): void
    {
        $unaryCall = static::createStub(UnaryCall::class);
        $unaryCall->method('wait')->willThrowException(new RuntimeException('network down'));

        $this->evitaService->method('DefineCatalog')->willReturn($unaryCall);

        $this->expectException(EvitaDbConnectionException::class);
        $this->expectExceptionMessageMatches('/network down/');

        $this->client->defineCatalog('testCatalog');
    }

    public function testQueryReturnsResponseOnSuccess(): void
    {
        $this->mockSessionCreation();

        $queryResponse = new GrpcQueryResponse();
        $this->mockSessionCall('Query', $queryResponse, STATUS_OK);
        $this->mockSessionClose();

        $result = $this->client->query(
            catalog: 'testCatalog',
            queryRequest: new GrpcQueryRequest(),
        );

        $this->assertSame($queryResponse, $result);
    }

    public function testQueryThrowsStatusExceptionOnFailure(): void
    {
        $this->mockSessionCreation();
        $this->mockSessionCall('Query', null, 13, 'internal error');
        $this->mockSessionClose();

        $this->expectException(EvitaDbStatusException::class);
        $this->expectExceptionMessageMatches('/Query failed/');

        $this->client->query(
            catalog: 'testCatalog',
            queryRequest: new GrpcQueryRequest(),
        );
    }

    public function testGetEntityThrowsNotFoundWhenEntityIsNull(): void
    {
        $this->mockSessionCreation();

        $response = new GrpcEntityResponse();
        $this->mockSessionCall('GetEntity', $response, STATUS_OK);
        $this->mockSessionClose();

        $this->expectException(EvitaDbEntityNotFoundException::class);
        $this->expectExceptionMessageMatches('/not found/');

        $this->client->getEntity(
            catalog: 'testCatalog',
            entityType: 'Product',
            primaryKey: 999,
        );
    }

    public function testGetEntityReturnsEntityOnSuccess(): void
    {
        $this->mockSessionCreation();

        $entity = new GrpcSealedEntity();
        $response = new GrpcEntityResponse();
        $response->setEntity($entity);
        $this->mockSessionCall('GetEntity', $response, STATUS_OK);
        $this->mockSessionClose();

        $result = $this->client->getEntity(
            catalog: 'testCatalog',
            entityType: 'Product',
            primaryKey: 1,
        );

        $this->assertSame($entity, $result);
    }

    public function testSessionIsClosedEvenWhenCallbackThrows(): void
    {
        $this->mockSessionCreation();

        /** @var EvitaSessionServiceClient&MockObject $sessionServiceMock */
        $sessionServiceMock = static::createMock(EvitaSessionServiceClient::class);
        $sessionServiceMock->method('Query')->willReturn($this->createUnaryCall(null, 2, 'unknown'));
        $sessionServiceMock
            ->expects($this->once())
            ->method('CloseSession')
            ->willReturn($this->createUnaryCall(null, STATUS_OK));

        $client = new EvitaDbClient(
            evitaService: $this->evitaService,
            sessionService: $sessionServiceMock,
        );

        $this->expectException(EvitaDbStatusException::class);

        $client->query(
            catalog: 'testCatalog',
            queryRequest: new GrpcQueryRequest(),
        );
    }

    public function testCreateSessionThrowsConnectionExceptionOnTransportError(): void
    {
        $unaryCall = static::createStub(UnaryCall::class);
        $unaryCall->method('wait')->willThrowException(new RuntimeException('refused'));

        $this->evitaService->method('CreateReadOnlySession')->willReturn($unaryCall);

        $this->expectException(EvitaDbConnectionException::class);
        $this->expectExceptionMessageMatches('/refused/');

        $this->client->query(
            catalog: 'testCatalog',
            queryRequest: new GrpcQueryRequest(),
        );
    }

    public function testCreateSessionThrowsConnectionExceptionOnNonOkStatus(): void
    {
        $this->mockEvitaCall('CreateReadOnlySession', null, 14, 'unavailable');

        $this->expectException(EvitaDbConnectionException::class);
        $this->expectExceptionMessageMatches('/Failed to create read-only session/');

        $this->client->query(
            catalog: 'testCatalog',
            queryRequest: new GrpcQueryRequest(),
        );
    }

    private function mockEvitaCall(string $method, mixed $response, int $code, string $details = ''): void
    {
        $this->evitaService
            ->method($method)
            ->willReturn($this->createUnaryCall($response, $code, $details));
    }

    private function mockSessionCreation(): void
    {
        $sessionResponse = new GrpcEvitaSessionResponse();
        $sessionResponse->setSessionId('test-session-id');

        $this->evitaService
            ->method('CreateReadOnlySession')
            ->willReturn($this->createUnaryCall($sessionResponse, STATUS_OK));

        $this->evitaService
            ->method('CreateReadWriteSession')
            ->willReturn($this->createUnaryCall($sessionResponse, STATUS_OK));
    }

    private function mockSessionCall(string $method, mixed $response, int $code, string $details = ''): void
    {
        $this->sessionService
            ->method($method)
            ->willReturn($this->createUnaryCall($response, $code, $details));
    }

    private function mockSessionClose(): void
    {
        $this->sessionService
            ->method('CloseSession')
            ->willReturn($this->createUnaryCall(null, STATUS_OK));
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
