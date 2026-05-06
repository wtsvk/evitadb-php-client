<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Transaction;

use Webmozart\Assert\Assert;
use Wtsvk\EvitaDbClient\EntityFetch;
use Wtsvk\EvitaDbClient\Exception\EvitaDbEntityNotFoundException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbStatusException;
use Wtsvk\EvitaDbClient\GrpcStatus;
use Wtsvk\EvitaDbClient\Protocol\EvitaSessionServiceClient;
use Wtsvk\EvitaDbClient\Protocol\GrpcDefineEntitySchemaRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcDeleteEntitiesRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcDeleteEntitiesResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcDeleteEntityRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcDeleteEntityResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityMutation;
use Wtsvk\EvitaDbClient\Protocol\GrpcEntityUpsertMutation;
use Wtsvk\EvitaDbClient\Protocol\GrpcModifyEntitySchemaMutation;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcUpdateEntitySchemaRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcUpdateEntitySchemaResponse;
use Wtsvk\EvitaDbClient\Protocol\GrpcUpsertEntityRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcUpsertEntityResponse;

use function sprintf;

use const Grpc\STATUS_OK;

/**
 * Read-write session-scoped transaction context.
 *
 * Bound to a single read-write EvitaDB session opened by EvitaDbClient::writeTransaction().
 * Implements WriteTransactionContext (which extends ReadTransactionContext) so callers
 * can both read and mutate within one server-side transaction.
 */
final readonly class ReadWriteSessionScopedContext implements WriteTransactionContext
{
    use SessionScopedReads;

    public function __construct(
        private EvitaSessionServiceClient $sessionService,
        private string $sessionId,
        private string $catalog,
    ) {
    }

    public function defineEntitySchema(string $entityType): true
    {
        $request = new GrpcDefineEntitySchemaRequest();
        $request->setEntityType($entityType);

        [, $rawStatus] = $this->sessionService
            ->DefineEntitySchema($request, $this->sessionMeta())
            ->wait();
        $status = GrpcStatus::fromRaw($rawStatus);

        if ($status->code !== STATUS_OK) {
            throw new EvitaDbStatusException(
                message: sprintf('Failed to define entity schema %s: %s', $entityType, $status),
            );
        }

        return true;
    }

    public function upsertEntity(GrpcEntityUpsertMutation $upsertMutation): int
    {
        $response = $this->upsert($upsertMutation);

        if ($response->hasEntityReference()) {
            $ref = $response->getEntityReference();
            Assert::notNull($ref);

            return $ref->getPrimaryKey();
        }

        if ($response->hasEntity()) {
            $entity = $response->getEntity();
            Assert::notNull($entity);

            return $entity->getPrimaryKey();
        }

        if ($response->hasEntityReferenceWithAssignedPrimaryKeys()) {
            $refKeys = $response->getEntityReferenceWithAssignedPrimaryKeys();
            Assert::notNull($refKeys);

            return $refKeys->getPrimaryKey();
        }

        throw new EvitaDbStatusException('EvitaDB returned successful status but no entity identification in response.');
    }

    public function upsert(GrpcEntityUpsertMutation $upsertMutation, ?EntityFetch $require = null): GrpcUpsertEntityResponse
    {
        $entityMutation = new GrpcEntityMutation();
        $entityMutation->setEntityUpsertMutation($upsertMutation);

        $request = new GrpcUpsertEntityRequest();
        $request->setEntityMutation($entityMutation);

        if ($require !== null) {
            $request->setRequire($require->toEvitaQL());
        }

        [$response, $rawStatus] = $this->sessionService
            ->UpsertEntity($request, $this->sessionMeta())
            ->wait();
        $status = GrpcStatus::fromRaw($rawStatus);

        if ($status->code !== STATUS_OK || $response === null) {
            throw new EvitaDbStatusException(
                message: sprintf('Failed to upsert entity: %s', $status),
            );
        }

        Assert::isInstanceOf($response, GrpcUpsertEntityResponse::class);

        return $response;
    }

    public function deleteEntity(string $entityType, int $primaryKey): true
    {
        $request = new GrpcDeleteEntityRequest();
        $request->setEntityType($entityType);
        $request->setPrimaryKeyUnwrapped($primaryKey);

        [$response, $rawStatus] = $this->sessionService
            ->DeleteEntity($request, $this->sessionMeta())
            ->wait();
        $status = GrpcStatus::fromRaw($rawStatus);

        if ($status->code !== STATUS_OK) {
            throw new EvitaDbStatusException(
                message: sprintf(
                    'Failed to delete entity %s pk=%d: %s',
                    $entityType,
                    $primaryKey,
                    $status,
                ),
            );
        }

        Assert::isInstanceOf($response, GrpcDeleteEntityResponse::class);

        // EvitaDB's DeleteEntity response is a oneof of (entityReference | entity).
        // We never set `require` on the request, so a successful delete fills
        // entityReference; a missing target leaves both fields unset. If a
        // future refactor starts setting `require`, the `entity` branch would
        // fire on success and this check still holds.
        if (! $response->hasEntityReference() && ! $response->hasEntity()) {
            throw new EvitaDbEntityNotFoundException(
                message: sprintf(
                    'Entity %s pk=%d not found in catalog %s',
                    $entityType,
                    $primaryKey,
                    $this->catalog,
                ),
            );
        }

        return true;
    }

    public function deleteEntities(GrpcQueryRequest $query): int
    {
        $request = new GrpcDeleteEntitiesRequest();
        $request->setQuery($query->getQuery());

        if ($query->getPositionalQueryParams()->count() > 0) {
            $request->setPositionalQueryParams($query->getPositionalQueryParams());
        }

        [$response, $rawStatus] = $this->sessionService
            ->DeleteEntities($request, $this->sessionMeta())
            ->wait();
        $status = GrpcStatus::fromRaw($rawStatus);

        if ($status->code !== STATUS_OK || $response === null) {
            throw new EvitaDbStatusException(
                message: sprintf('Failed to delete entities: %s', $status),
            );
        }

        Assert::isInstanceOf($response, GrpcDeleteEntitiesResponse::class);

        return $response->getDeletedEntities();
    }

    public function updateEntitySchema(GrpcModifyEntitySchemaMutation $mutation): int
    {
        $request = new GrpcUpdateEntitySchemaRequest();
        $request->setSchemaMutation($mutation);

        [$response, $rawStatus] = $this->sessionService
            ->UpdateEntitySchema($request, $this->sessionMeta())
            ->wait();
        $status = GrpcStatus::fromRaw($rawStatus);

        if ($status->code !== STATUS_OK || $response === null) {
            throw new EvitaDbStatusException(
                message: sprintf('Failed to update entity schema: %s', $status),
            );
        }

        Assert::isInstanceOf($response, GrpcUpdateEntitySchemaResponse::class);

        return $response->getVersion();
    }

    private function sessionService(): EvitaSessionServiceClient
    {
        return $this->sessionService;
    }

    private function sessionId(): string
    {
        return $this->sessionId;
    }

    private function catalog(): string
    {
        return $this->catalog;
    }
}
