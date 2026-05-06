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
use Wtsvk\EvitaDbClient\Exception\EvitaDbStatusException;
use Wtsvk\EvitaDbClient\Protocol\EvitaServiceClient;
use Wtsvk\EvitaDbClient\Protocol\EvitaSessionServiceClient;
use Wtsvk\EvitaDbClient\Protocol\GrpcCloseRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcCommitBehavior;
use Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionResponse;
use Wtsvk\EvitaDbClient\SessionCommitBehavior;
use Wtsvk\EvitaDbClient\Transaction\ReadOnlySessionScopedContext;
use Wtsvk\EvitaDbClient\Transaction\ReadTransactionContext;
use Wtsvk\EvitaDbClient\Transaction\ReadWriteSessionScopedContext;
use Wtsvk\EvitaDbClient\Transaction\WriteTransactionContext;

use const Grpc\STATUS_OK;

#[RequiresPhpExtension('grpc')]
#[RequiresPhpExtension('protobuf')]
final class EvitaDbClientTest extends TestCase
{
    private const string CATALOG = 'testCatalog';

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
            catalog: self::CATALOG,
        );
    }

    public function testWriteTransactionPassesReadWriteContextToCallable(): void
    {
        $this->mockSessionCreation();
        $this->mockSessionClose();

        $captured = null;
        $this->client->writeTransaction(
            fn: static function (WriteTransactionContext $tx) use (&$captured): string {
                $captured = $tx;

                return 'callable-result';
            },
        );

        $this->assertInstanceOf(ReadWriteSessionScopedContext::class, $captured);
    }

    public function testReadTransactionPassesReadOnlyContextToCallable(): void
    {
        $this->mockSessionCreation();
        $this->mockSessionClose();

        $captured = null;
        $this->client->readTransaction(
            fn: static function (ReadTransactionContext $tx) use (&$captured): string {
                $captured = $tx;

                return 'read-result';
            },
        );

        $this->assertInstanceOf(ReadOnlySessionScopedContext::class, $captured);
    }

    public function testWriteTransactionReturnsCallableResult(): void
    {
        $this->mockSessionCreation();
        $this->mockSessionClose();

        $expected = new stdClass();

        $result = $this->client->writeTransaction(fn: static fn (): stdClass => $expected);

        $this->assertSame($expected, $result);
    }

    public function testReadTransactionReturnsCallableResult(): void
    {
        $this->mockSessionCreation();
        $this->mockSessionClose();

        $expected = new stdClass();

        $result = $this->client->readTransaction(fn: static fn (): stdClass => $expected);

        $this->assertSame($expected, $result);
    }

    public function testWriteTransactionClosesSessionWithDefaultCommitBehaviorOnSuccess(): void
    {
        $this->mockSessionCreation();

        /** @var EvitaSessionServiceClient&MockObject $sessionServiceMock */
        $sessionServiceMock = static::createMock(EvitaSessionServiceClient::class);
        $sessionServiceMock
            ->expects(static::once())
            ->method('CloseSession')
            ->with(
                static::callback(static fn (GrpcCloseRequest $request): bool =>
                    $request->getCommitBehaviour() === GrpcCommitBehavior::WAIT_FOR_CHANGES_VISIBLE),
                static::anything(),
            )
            ->willReturn($this->createUnaryCall(null, STATUS_OK));

        $client = new EvitaDbClient(
            evitaService: $this->evitaService,
            sessionService: $sessionServiceMock,
            catalog: self::CATALOG,
        );

        $client->writeTransaction(fn: static fn (): bool => true);
    }

    public function testWriteTransactionClosesSessionWithOverriddenCommitBehavior(): void
    {
        $this->mockSessionCreation();

        /** @var EvitaSessionServiceClient&MockObject $sessionServiceMock */
        $sessionServiceMock = static::createMock(EvitaSessionServiceClient::class);
        $sessionServiceMock
            ->expects(static::once())
            ->method('CloseSession')
            ->with(
                static::callback(static fn (GrpcCloseRequest $request): bool =>
                    $request->getCommitBehaviour() === GrpcCommitBehavior::WAIT_FOR_LOG_PERSISTENCE),
                static::anything(),
            )
            ->willReturn($this->createUnaryCall(null, STATUS_OK));

        $client = new EvitaDbClient(
            evitaService: $this->evitaService,
            sessionService: $sessionServiceMock,
            catalog: self::CATALOG,
        );

        $client->writeTransaction(
            fn: static fn (): bool => true,
            commitBehavior: SessionCommitBehavior::WaitForLogPersistence,
        );
    }

    public function testWriteTransactionOrphansSessionOnExceptionWithoutClosing(): void
    {
        $this->mockSessionCreation();

        /** @var EvitaSessionServiceClient&MockObject $sessionServiceMock */
        $sessionServiceMock = static::createMock(EvitaSessionServiceClient::class);
        $sessionServiceMock
            ->expects(static::never())
            ->method('CloseSession');

        $client = new EvitaDbClient(
            evitaService: $this->evitaService,
            sessionService: $sessionServiceMock,
            catalog: self::CATALOG,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('callable failed');

        $client->writeTransaction(
            fn: static function (): never {
                throw new RuntimeException('callable failed');
            },
        );
    }

    public function testReadTransactionClosesSessionEvenWhenCallableThrows(): void
    {
        $this->mockSessionCreation();

        /** @var EvitaSessionServiceClient&MockObject $sessionServiceMock */
        $sessionServiceMock = static::createMock(EvitaSessionServiceClient::class);
        $sessionServiceMock
            ->expects(static::once())
            ->method('CloseSession')
            ->willReturn($this->createUnaryCall(null, STATUS_OK));

        $client = new EvitaDbClient(
            evitaService: $this->evitaService,
            sessionService: $sessionServiceMock,
            catalog: self::CATALOG,
        );

        $this->expectException(RuntimeException::class);

        $client->readTransaction(
            fn: static function (): never {
                throw new RuntimeException('read failed');
            },
        );
    }

    public function testReadTransactionSwallowsCloseFailureSoConsumerStillSeesResult(): void
    {
        $this->mockSessionCreation();

        $sessionServiceStub = static::createStub(EvitaSessionServiceClient::class);
        $sessionServiceStub
            ->method('CloseSession')
            ->willReturn($this->createUnaryCall(null, 14, 'unavailable'));

        $client = new EvitaDbClient(
            evitaService: $this->evitaService,
            sessionService: $sessionServiceStub,
            catalog: self::CATALOG,
        );

        $marker = new stdClass();

        $result = $client->readTransaction(fn: static fn (): stdClass => $marker);

        $this->assertSame($marker, $result);
    }

    public function testWriteTransactionPropagatesCloseFailureSoConsumerLearnsCommitDidNotHappen(): void
    {
        $this->mockSessionCreation();

        $sessionServiceStub = static::createStub(EvitaSessionServiceClient::class);
        $sessionServiceStub
            ->method('CloseSession')
            ->willReturn($this->createUnaryCall(null, 14, 'unavailable'));

        $client = new EvitaDbClient(
            evitaService: $this->evitaService,
            sessionService: $sessionServiceStub,
            catalog: self::CATALOG,
        );

        $this->expectException(EvitaDbStatusException::class);
        $this->expectExceptionMessageMatches('/Failed to close session/');

        $client->writeTransaction(fn: static fn (): bool => true);
    }

    public function testWriteTransactionWithDryRunSetsFlagOnSessionRequest(): void
    {
        $sessionResponse = new GrpcEvitaSessionResponse();
        $sessionResponse->setSessionId('test-session-id');

        /** @var EvitaServiceClient&MockObject $evitaServiceMock */
        $evitaServiceMock = static::createMock(EvitaServiceClient::class);
        $evitaServiceMock
            ->expects(static::once())
            ->method('CreateReadWriteSession')
            ->with(static::callback(static fn (GrpcEvitaSessionRequest $request): bool =>
                $request->getDryRun() === true))
            ->willReturn($this->createUnaryCall($sessionResponse, STATUS_OK));

        $this->mockSessionClose();

        $client = new EvitaDbClient(
            evitaService: $evitaServiceMock,
            sessionService: $this->sessionService,
            catalog: self::CATALOG,
        );

        $client->writeTransaction(fn: static fn (): bool => true, dryRun: true);
    }

    public function testWriteTransactionDefaultsDryRunToFalse(): void
    {
        $sessionResponse = new GrpcEvitaSessionResponse();
        $sessionResponse->setSessionId('test-session-id');

        /** @var EvitaServiceClient&MockObject $evitaServiceMock */
        $evitaServiceMock = static::createMock(EvitaServiceClient::class);
        $evitaServiceMock
            ->expects(static::once())
            ->method('CreateReadWriteSession')
            ->with(static::callback(static fn (GrpcEvitaSessionRequest $request): bool =>
                $request->getDryRun() === false))
            ->willReturn($this->createUnaryCall($sessionResponse, STATUS_OK));

        $this->mockSessionClose();

        $client = new EvitaDbClient(
            evitaService: $evitaServiceMock,
            sessionService: $this->sessionService,
            catalog: self::CATALOG,
        );

        $client->writeTransaction(fn: static fn (): bool => true);
    }

    public function testReadTransactionUsesReadOnlySessionType(): void
    {
        $sessionResponse = new GrpcEvitaSessionResponse();
        $sessionResponse->setSessionId('test-session-id');

        /** @var EvitaServiceClient&MockObject $evitaServiceMock */
        $evitaServiceMock = static::createMock(EvitaServiceClient::class);
        $evitaServiceMock
            ->expects(static::once())
            ->method('CreateReadOnlySession')
            ->willReturn($this->createUnaryCall($sessionResponse, STATUS_OK));
        $evitaServiceMock
            ->expects(static::never())
            ->method('CreateReadWriteSession');

        $this->mockSessionClose();

        $client = new EvitaDbClient(
            evitaService: $evitaServiceMock,
            sessionService: $this->sessionService,
            catalog: self::CATALOG,
        );

        $client->readTransaction(fn: static fn (): bool => true);
    }

    public function testWriteTransactionUsesReadWriteSessionType(): void
    {
        $sessionResponse = new GrpcEvitaSessionResponse();
        $sessionResponse->setSessionId('test-session-id');

        /** @var EvitaServiceClient&MockObject $evitaServiceMock */
        $evitaServiceMock = static::createMock(EvitaServiceClient::class);
        $evitaServiceMock
            ->expects(static::once())
            ->method('CreateReadWriteSession')
            ->willReturn($this->createUnaryCall($sessionResponse, STATUS_OK));
        $evitaServiceMock
            ->expects(static::never())
            ->method('CreateReadOnlySession');

        $this->mockSessionClose();

        $client = new EvitaDbClient(
            evitaService: $evitaServiceMock,
            sessionService: $this->sessionService,
            catalog: self::CATALOG,
        );

        $client->writeTransaction(fn: static fn (): bool => true);
    }

    public function testClientWithFastCommitBehaviorUsesItOnSuccess(): void
    {
        $this->mockSessionCreation();

        /** @var EvitaSessionServiceClient&MockObject $sessionServiceMock */
        $sessionServiceMock = static::createMock(EvitaSessionServiceClient::class);
        $sessionServiceMock
            ->expects(static::once())
            ->method('CloseSession')
            ->with(
                static::callback(static fn (GrpcCloseRequest $request): bool =>
                    $request->getCommitBehaviour() === GrpcCommitBehavior::WAIT_FOR_CONFLICT_RESOLUTION),
                static::anything(),
            )
            ->willReturn($this->createUnaryCall(null, STATUS_OK));

        $client = new EvitaDbClient(
            evitaService: $this->evitaService,
            sessionService: $sessionServiceMock,
            catalog: self::CATALOG,
            defaultCommitBehavior: SessionCommitBehavior::WaitForConflictResolution,
        );

        $client->writeTransaction(fn: static fn (): bool => true);
    }

    public function testCreateSessionThrowsConnectionExceptionOnTransportError(): void
    {
        $unaryCall = static::createStub(UnaryCall::class);
        $unaryCall->method('wait')->willThrowException(new RuntimeException('refused'));

        $this->evitaService->method('CreateReadOnlySession')->willReturn($unaryCall);

        $this->expectException(EvitaDbConnectionException::class);
        $this->expectExceptionMessageMatches('/refused/');

        $this->client->readTransaction(fn: static fn (): bool => true);
    }

    public function testCreateSessionThrowsConnectionExceptionOnNonOkStatus(): void
    {
        $this->mockEvitaCall('CreateReadOnlySession', null, 14, 'unavailable');

        $this->expectException(EvitaDbConnectionException::class);
        $this->expectExceptionMessageMatches('/Failed to create read-only session/');

        $this->client->readTransaction(fn: static fn (): bool => true);
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
