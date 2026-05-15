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
use Wtsvk\EvitaDbClient\EvitaDbConnection;
use Wtsvk\EvitaDbClient\Exception\EvitaDbConnectionException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbStatusException;
use Wtsvk\EvitaDbClient\Protocol\EvitaServiceClient;
use Wtsvk\EvitaDbClient\Protocol\EvitaSessionServiceClient;
use Wtsvk\EvitaDbClient\Protocol\GrpcCatalogNamesResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcDefineCatalogResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcDeleteCatalogIfExistsResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcGoLiveAndCloseResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcReadyResponse;

use const Grpc\STATUS_OK;

#[RequiresPhpExtension('grpc')]
#[RequiresPhpExtension('protobuf')]
final class EvitaDbConnectionTest extends TestCase
{
    private EvitaDbConnection $connection;

    private EvitaServiceClient&Stub $evitaService;

    private EvitaSessionServiceClient&Stub $sessionService;

    #[Override]
    protected function setUp(): void
    {
        $this->evitaService = static::createStub(EvitaServiceClient::class);
        $this->sessionService = static::createStub(EvitaSessionServiceClient::class);

        $this->connection = new EvitaDbConnection(
            evitaService: $this->evitaService,
            sessionService: $this->sessionService,
        );
    }

    public function testIsHealthyReturnsTrueWhenReady(): void
    {
        $response = new GrpcReadyResponse();
        $response->setReady(true);

        $this->mockEvitaCall('IsReady', $response, STATUS_OK);

        $this->assertTrue($this->connection->isHealthy());
    }

    public function testIsHealthyReturnsFalseOnNonOkStatus(): void
    {
        $this->mockEvitaCall('IsReady', null, 14);

        $this->assertFalse($this->connection->isHealthy());
    }

    public function testIsHealthyReturnsFalseOnException(): void
    {
        $unaryCall = static::createStub(UnaryCall::class);
        $unaryCall->method('wait')->willThrowException(new RuntimeException('connection refused'));

        $this->evitaService->method('IsReady')->willReturn($unaryCall);

        $this->assertFalse($this->connection->isHealthy());
    }

    public function testDefineCatalogCreatesCatalogAndTransitionsToAlive(): void
    {
        $defineResponse = new GrpcDefineCatalogResponse();
        $defineResponse->setSuccess(true);

        $this->mockEvitaCall('DefineCatalog', $defineResponse, STATUS_OK);
        $this->mockGoLiveAndClose();

        $this->assertTrue($this->connection->defineCatalog('testCatalog'));
    }

    public function testDefineCatalogSkipsGoLiveWhenCreationDidNotSucceed(): void
    {
        $defineResponse = new GrpcDefineCatalogResponse();
        $defineResponse->setSuccess(false);

        $this->mockEvitaCall('DefineCatalog', $defineResponse, STATUS_OK);

        /** @var EvitaSessionServiceClient&MockObject $sessionServiceMock */
        $sessionServiceMock = static::createMock(EvitaSessionServiceClient::class);
        $sessionServiceMock
            ->expects(static::never())
            ->method('GoLiveAndClose');

        $connection = new EvitaDbConnection(
            evitaService: $this->evitaService,
            sessionService: $sessionServiceMock,
        );

        $this->assertFalse($connection->defineCatalog('testCatalog'));
    }

    public function testDefineCatalogThrowsStatusExceptionOnFailure(): void
    {
        $this->mockEvitaCall('DefineCatalog', null, 5, 'catalog error');

        $this->expectException(EvitaDbStatusException::class);
        $this->expectExceptionMessageMatches('/Failed to define catalog/');

        $this->connection->defineCatalog('testCatalog');
    }

    public function testDefineCatalogThrowsConnectionExceptionOnTransportError(): void
    {
        $unaryCall = static::createStub(UnaryCall::class);
        $unaryCall->method('wait')->willThrowException(new RuntimeException('network down'));

        $this->evitaService->method('DefineCatalog')->willReturn($unaryCall);

        $this->expectException(EvitaDbConnectionException::class);
        $this->expectExceptionMessageMatches('/network down/');

        $this->connection->defineCatalog('testCatalog');
    }

    public function testDefineCatalogThrowsWhenGoLiveFails(): void
    {
        $defineResponse = new GrpcDefineCatalogResponse();
        $defineResponse->setSuccess(true);

        $this->mockEvitaCall('DefineCatalog', $defineResponse, STATUS_OK);

        $sessionResponse = new GrpcEvitaSessionResponse();
        $sessionResponse->setSessionId('session-id');
        $this->evitaService
            ->method('CreateReadWriteSession')
            ->willReturn($this->createUnaryCall($sessionResponse, STATUS_OK));

        $this->sessionService
            ->method('GoLiveAndClose')
            ->willReturn($this->createUnaryCall(null, 13, 'internal'));

        $this->expectException(EvitaDbStatusException::class);
        $this->expectExceptionMessageMatches('/Failed to switch catalog/');

        $this->connection->defineCatalog('testCatalog');
    }

    public function testGetCatalogNamesReturnsNames(): void
    {
        $response = new GrpcCatalogNamesResponse();
        $response->setCatalogNames(['alpha', 'beta']);

        $this->mockEvitaCall('GetCatalogNames', $response, STATUS_OK);

        $this->assertSame(['alpha', 'beta'], $this->connection->getCatalogNames());
    }

    public function testGetCatalogNamesThrowsStatusExceptionOnFailure(): void
    {
        $this->mockEvitaCall('GetCatalogNames', null, 5, 'error');

        $this->expectException(EvitaDbStatusException::class);
        $this->expectExceptionMessageMatches('/Failed to fetch catalog names/');

        $this->connection->getCatalogNames();
    }

    public function testDeleteCatalogReturnsTrue(): void
    {
        $response = new GrpcDeleteCatalogIfExistsResponse();
        $response->setSuccess(true);

        $this->mockEvitaCall('DeleteCatalogIfExists', $response, STATUS_OK);

        $this->assertTrue($this->connection->deleteCatalog('testCatalog'));
    }

    public function testDeleteCatalogThrowsStatusExceptionOnFailure(): void
    {
        $this->mockEvitaCall('DeleteCatalogIfExists', null, 5, 'error');

        $this->expectException(EvitaDbStatusException::class);
        $this->expectExceptionMessageMatches('/Failed to delete catalog/');

        $this->connection->deleteCatalog('testCatalog');
    }

    public function testCatalogReturnsClient(): void
    {
        $this->expectNotToPerformAssertions();

        $this->connection->catalog('testCatalog');
    }

    private function mockGoLiveAndClose(): void
    {
        $sessionResponse = new GrpcEvitaSessionResponse();
        $sessionResponse->setSessionId('go-live-session-id');

        $this->evitaService
            ->method('CreateReadWriteSession')
            ->willReturn($this->createUnaryCall($sessionResponse, STATUS_OK));

        $goLiveResponse = new GrpcGoLiveAndCloseResponse();
        $goLiveResponse->setSuccess(true);

        $this->sessionService
            ->method('GoLiveAndClose')
            ->willReturn($this->createUnaryCall($goLiveResponse, STATUS_OK));
    }

    private function mockEvitaCall(string $method, mixed $response, int $code, string $details = ''): void
    {
        $this->evitaService
            ->method($method)
            ->willReturn($this->createUnaryCall($response, $code, $details));
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
