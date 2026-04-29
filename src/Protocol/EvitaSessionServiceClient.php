<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Wtsvk\EvitaDbClient\Protocol;

/**
 * This service contains RPCs that could be called by gRPC clients on evitaDB's catalog by usage of a before created session.
 * By specifying its UUID and the name of a catalog to which it corresponds to it's possible to execute methods that in
 * evitaDB's implementation a called on an instance of EvitaSessionContract.
 *
 * Main purpose of this service is to provide a way to manipulate with stored entity collections and their schemas. That
 * includes their creating, updating and deleting. Same operations could be done with entities, which in addition could
 * be fetched by specifying a complex queries.
 */
class EvitaSessionServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * Procedure that returns the current (the one on which the used session operates) catalog schema.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcGetCatalogSchemaRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetCatalogSchema(\Wtsvk\EvitaDbClient\Protocol\GrpcGetCatalogSchemaRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/GetCatalogSchema',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcCatalogSchemaResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that returns the current state of the catalog.
     * @param \Google\Protobuf\GPBEmpty $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetCatalogState(\Google\Protobuf\GPBEmpty $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/GetCatalogState',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcCatalogStateResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that returns the schema of a specific entity type.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcEntitySchemaRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetEntitySchema(\Wtsvk\EvitaDbClient\Protocol\GrpcEntitySchemaRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/GetEntitySchema',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcEntitySchemaResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that returns the list of all entity types.
     * @param \Google\Protobuf\GPBEmpty $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetAllEntityTypes(\Google\Protobuf\GPBEmpty $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/GetAllEntityTypes',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcEntityTypesResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that changes the state of the catalog to ALIVE and closes the session.
     * @param \Google\Protobuf\GPBEmpty $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GoLiveAndClose(\Google\Protobuf\GPBEmpty $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/GoLiveAndClose',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcGoLiveAndCloseResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that changes the state of the catalog to ALIVE and closes the session opening a stream that listens
     * to updates of go live procedure.
     * @param \Google\Protobuf\GPBEmpty $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function GoLiveAndCloseWithProgress(\Google\Protobuf\GPBEmpty $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/GoLiveAndCloseWithProgress',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcGoLiveAndCloseWithProgressResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to backup an existing catalog.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcBackupCatalogRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function BackupCatalog(\Wtsvk\EvitaDbClient\Protocol\GrpcBackupCatalogRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/BackupCatalog',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcBackupCatalogResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to backup an existing catalog.
     * @param \Google\Protobuf\GPBEmpty $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function FullBackupCatalog(\Google\Protobuf\GPBEmpty $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/FullBackupCatalog',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcFullBackupCatalogResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that closes the session.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcCloseRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function CloseSession(\Wtsvk\EvitaDbClient\Protocol\GrpcCloseRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/Close',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcCloseResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that closes the session opening a stream that listens to transaction processing phases.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcCloseWithProgressRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function CloseWithProgress(\Wtsvk\EvitaDbClient\Protocol\GrpcCloseWithProgressRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/CloseWithProgress',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcCloseWithProgressResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that executes passed parametrised query and returns zero or one entity.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function QueryOne(\Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/QueryOne',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcQueryOneResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that executes passed parametrised query and returns a list of entities.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function QueryList(\Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/QueryList',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcQueryListResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that executes passed parametrised query and returns a data chunk with computed extra results.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function Query(\Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/Query',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcQueryResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that executes passed query with embedded variables and returns zero or one entity.
     * Do not use in your applications! This method is unsafe and should be used only for internal purposes.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcQueryUnsafeRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function QueryOneUnsafe(\Wtsvk\EvitaDbClient\Protocol\GrpcQueryUnsafeRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/QueryOneUnsafe',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcQueryOneResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that executes passed query with embedded variables and returns a list of entities.
     * Do not use in your applications! This method is unsafe and should be used only for internal purposes.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcQueryUnsafeRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function QueryListUnsafe(\Wtsvk\EvitaDbClient\Protocol\GrpcQueryUnsafeRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/QueryListUnsafe',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcQueryListResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that executes passed query with embedded variables and returns a data chunk with computed extra results.
     * Do not use in your applications! This method is unsafe and should be used only for internal purposes.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcQueryUnsafeRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function QueryUnsafe(\Wtsvk\EvitaDbClient\Protocol\GrpcQueryUnsafeRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/QueryUnsafe',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcQueryResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that find entity by passed entity type and primary key and return it by specified richness by passed parametrised require query part.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcEntityRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetEntity(\Wtsvk\EvitaDbClient\Protocol\GrpcEntityRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/GetEntity',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcEntityResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that updates the catalog schema and return its updated version.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcUpdateCatalogSchemaRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function UpdateCatalogSchema(\Wtsvk\EvitaDbClient\Protocol\GrpcUpdateCatalogSchemaRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/UpdateCatalogSchema',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcUpdateCatalogSchemaResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that updates the catalog schema and returns it.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcUpdateCatalogSchemaRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function UpdateAndFetchCatalogSchema(\Wtsvk\EvitaDbClient\Protocol\GrpcUpdateCatalogSchemaRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/UpdateAndFetchCatalogSchema',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcUpdateAndFetchCatalogSchemaResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that defines the schema of a new entity type and return it.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcDefineEntitySchemaRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DefineEntitySchema(\Wtsvk\EvitaDbClient\Protocol\GrpcDefineEntitySchemaRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/DefineEntitySchema',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcDefineEntitySchemaResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that updates the schema of an existing entity type and return its updated version.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcUpdateEntitySchemaRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function UpdateEntitySchema(\Wtsvk\EvitaDbClient\Protocol\GrpcUpdateEntitySchemaRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/UpdateEntitySchema',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcUpdateEntitySchemaResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that updates the schema of an existing entity type and returns it.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcUpdateEntitySchemaRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function UpdateAndFetchEntitySchema(\Wtsvk\EvitaDbClient\Protocol\GrpcUpdateEntitySchemaRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/UpdateAndFetchEntitySchema',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcUpdateAndFetchEntitySchemaResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that deletes an entity collection.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcDeleteCollectionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DeleteCollection(\Wtsvk\EvitaDbClient\Protocol\GrpcDeleteCollectionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/DeleteCollection',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcDeleteCollectionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that renames an entity collection.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcRenameCollectionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RenameCollection(\Wtsvk\EvitaDbClient\Protocol\GrpcRenameCollectionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/RenameCollection',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcRenameCollectionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that replaces an entity collection.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcReplaceCollectionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ReplaceCollection(\Wtsvk\EvitaDbClient\Protocol\GrpcReplaceCollectionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/ReplaceCollection',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcReplaceCollectionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that returns the size of an entity collection.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcEntityCollectionSizeRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetEntityCollectionSize(\Wtsvk\EvitaDbClient\Protocol\GrpcEntityCollectionSizeRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/GetEntityCollectionSize',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcEntityCollectionSizeResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that upserts (inserts/updates) an entity and returns it with required richness.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcUpsertEntityRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function UpsertEntity(\Wtsvk\EvitaDbClient\Protocol\GrpcUpsertEntityRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/UpsertEntity',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcUpsertEntityResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that deletes an entity and returns it with required richness.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcDeleteEntityRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DeleteEntity(\Wtsvk\EvitaDbClient\Protocol\GrpcDeleteEntityRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/DeleteEntity',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcDeleteEntityResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that deletes an entity and its hierarchy and returns the root entity with required richness.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcDeleteEntityRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DeleteEntityAndItsHierarchy(\Wtsvk\EvitaDbClient\Protocol\GrpcDeleteEntityRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/DeleteEntityAndItsHierarchy',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcDeleteEntityAndItsHierarchyResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that deletes all entities that match the sent query and returns their bodies.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcDeleteEntitiesRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DeleteEntities(\Wtsvk\EvitaDbClient\Protocol\GrpcDeleteEntitiesRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/DeleteEntities',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcDeleteEntitiesResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that archives an entity and returns it with required richness.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcArchiveEntityRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ArchiveEntity(\Wtsvk\EvitaDbClient\Protocol\GrpcArchiveEntityRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/ArchiveEntity',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcArchiveEntityResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that restores an entity and returns it with required richness.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcRestoreEntityRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RestoreEntity(\Wtsvk\EvitaDbClient\Protocol\GrpcRestoreEntityRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/RestoreEntity',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcRestoreEntityResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Applies single mutation to the entity.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcEntityMutation $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ApplyMutation(\Wtsvk\EvitaDbClient\Protocol\GrpcEntityMutation $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/ApplyMutation',
        $argument,
        ['\Google\Protobuf\GPBEmpty', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that opens a transaction.
     * @param \Google\Protobuf\GPBEmpty $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetTransactionId(\Google\Protobuf\GPBEmpty $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/GetTransactionId',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcTransactionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that returns the version of the catalog at a specific moment in time.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcCatalogVersionAtRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetCatalogVersionAt(\Wtsvk\EvitaDbClient\Protocol\GrpcCatalogVersionAtRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/GetCatalogVersionAt',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcCatalogVersionAtResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that returns requested page of past mutations in reversed order that match the request criteria.
     * @param \Wtsvk\EvitaDbClient\Protocol\GetMutationsHistoryPageRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetMutationsHistoryPage(\Wtsvk\EvitaDbClient\Protocol\GetMutationsHistoryPageRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/GetMutationsHistoryPage',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GetMutationsHistoryPageResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that returns stream of all past mutations in reversed order that match the request criteria.
     * @param \Wtsvk\EvitaDbClient\Protocol\GetMutationsHistoryRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function GetMutationsHistory(\Wtsvk\EvitaDbClient\Protocol\GetMutationsHistoryRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/GetMutationsHistory',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GetMutationsHistoryResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that returns details of a specific transactions that move catalog to specified versions.
     * @param \Wtsvk\EvitaDbClient\Protocol\GetTransactionOverviewRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetTransactionOverview(\Wtsvk\EvitaDbClient\Protocol\GetTransactionOverviewRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/GetTransactionOverview',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GetTransactionOverviewResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that registers a change capture.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcRegisterChangeCatalogCaptureRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function RegisterChangeCatalogCapture(\Wtsvk\EvitaDbClient\Protocol\GrpcRegisterChangeCatalogCaptureRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/io.evitadb.externalApi.grpc.generated.EvitaSessionService/RegisterChangeCatalogCapture',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcRegisterChangeCatalogCaptureResponse', 'decode'],
        $metadata, $options);
    }

}
