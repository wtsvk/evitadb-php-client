<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Wtsvk\EvitaDbClient\Protocol;

/**
 * This service contains RPCs that could be called by gRPC clients on evitaDB. Main purpose of this service is to provide
 * a way to create sessions and catalogs, and to update the catalog.
 */
class EvitaServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * Procedure used to check readiness of the API
     * @param \Google\Protobuf\GPBEmpty $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function IsReady(\Google\Protobuf\GPBEmpty $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/IsReady',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcReadyResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to create read only sessions.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function CreateReadOnlySession(\Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/CreateReadOnlySession',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to create read write sessions.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function CreateReadWriteSession(\Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/CreateReadWriteSession',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to create read-only session which will return data in binary format. Part of the Private API.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function CreateBinaryReadOnlySession(\Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/CreateBinaryReadOnlySession',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to create read-write session which will return data in binary format. Part of the Private API.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function CreateBinaryReadWriteSession(\Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/CreateBinaryReadWriteSession',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to terminate existing session.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionTerminationRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function TerminateSession(\Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionTerminationRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/TerminateSession',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcEvitaSessionTerminationResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to get names of all existing catalogs.
     * @param \Google\Protobuf\GPBEmpty $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetCatalogNames(\Google\Protobuf\GPBEmpty $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/GetCatalogNames',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcCatalogNamesResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to get state of the catalog by its name.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcGetCatalogStateRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetCatalogState(\Wtsvk\EvitaDbClient\Protocol\GrpcGetCatalogStateRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/GetCatalogState',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcGetCatalogStateResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to define a new catalog.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcDefineCatalogRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DefineCatalog(\Wtsvk\EvitaDbClient\Protocol\GrpcDefineCatalogRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/DefineCatalog',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcDefineCatalogResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to delete an existing catalog.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcDeleteCatalogIfExistsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DeleteCatalogIfExists(\Wtsvk\EvitaDbClient\Protocol\GrpcDeleteCatalogIfExistsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/DeleteCatalogIfExists',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcDeleteCatalogIfExistsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to update the catalog with a set of mutations.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcApplyMutationRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ApplyMutation(\Wtsvk\EvitaDbClient\Protocol\GrpcApplyMutationRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/ApplyMutation',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcApplyMutationResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to update the catalog with a set of mutations which tracks the progress of the operation.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcApplyMutationRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function ApplyMutationWithProgress(\Wtsvk\EvitaDbClient\Protocol\GrpcApplyMutationRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/ApplyMutationWithProgress',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcApplyMutationWithProgressResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to rename an existing catalog.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcRenameCatalogRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RenameCatalog(\Wtsvk\EvitaDbClient\Protocol\GrpcRenameCatalogRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/RenameCatalog',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcRenameCatalogResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to rename an existing catalog with progress tracking.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcRenameCatalogRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function RenameCatalogWithProgress(\Wtsvk\EvitaDbClient\Protocol\GrpcRenameCatalogRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/RenameCatalogWithProgress',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcApplyMutationWithProgressResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to replace an existing catalog.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcReplaceCatalogRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ReplaceCatalog(\Wtsvk\EvitaDbClient\Protocol\GrpcReplaceCatalogRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/ReplaceCatalog',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcReplaceCatalogResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to replace an existing catalog with progress tracking.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcReplaceCatalogRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function ReplaceCatalogWithProgress(\Wtsvk\EvitaDbClient\Protocol\GrpcReplaceCatalogRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/ReplaceCatalogWithProgress',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcApplyMutationWithProgressResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to make a catalog mutable.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcMakeCatalogMutableRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function MakeCatalogMutable(\Wtsvk\EvitaDbClient\Protocol\GrpcMakeCatalogMutableRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/MakeCatalogMutable',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcMakeCatalogMutableResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to make a catalog mutable with progress tracking.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcMakeCatalogMutableRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function MakeCatalogMutableWithProgress(\Wtsvk\EvitaDbClient\Protocol\GrpcMakeCatalogMutableRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/MakeCatalogMutableWithProgress',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcApplyMutationWithProgressResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to make a catalog immutable.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcMakeCatalogImmutableRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function MakeCatalogImmutable(\Wtsvk\EvitaDbClient\Protocol\GrpcMakeCatalogImmutableRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/MakeCatalogImmutable',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcMakeCatalogImmutableResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to make a catalog immutable with progress tracking.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcMakeCatalogImmutableRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function MakeCatalogImmutableWithProgress(\Wtsvk\EvitaDbClient\Protocol\GrpcMakeCatalogImmutableRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/MakeCatalogImmutableWithProgress',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcApplyMutationWithProgressResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to make a catalog alive.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcMakeCatalogAliveRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function MakeCatalogAlive(\Wtsvk\EvitaDbClient\Protocol\GrpcMakeCatalogAliveRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/MakeCatalogAlive',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcMakeCatalogAliveResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to make a catalog alive with progress tracking.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcMakeCatalogAliveRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function MakeCatalogAliveWithProgress(\Wtsvk\EvitaDbClient\Protocol\GrpcMakeCatalogAliveRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/MakeCatalogAliveWithProgress',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcApplyMutationWithProgressResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to duplicate a catalog.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcDuplicateCatalogRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DuplicateCatalog(\Wtsvk\EvitaDbClient\Protocol\GrpcDuplicateCatalogRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/DuplicateCatalog',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcDuplicateCatalogResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to duplicate a catalog with progress tracking.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcDuplicateCatalogRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function DuplicateCatalogWithProgress(\Wtsvk\EvitaDbClient\Protocol\GrpcDuplicateCatalogRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/DuplicateCatalogWithProgress',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcApplyMutationWithProgressResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to activate a catalog.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcActivateCatalogRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ActivateCatalog(\Wtsvk\EvitaDbClient\Protocol\GrpcActivateCatalogRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/ActivateCatalog',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcActivateCatalogResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to activate a catalog with progress tracking.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcActivateCatalogRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function ActivateCatalogWithProgress(\Wtsvk\EvitaDbClient\Protocol\GrpcActivateCatalogRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/ActivateCatalogWithProgress',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcApplyMutationWithProgressResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to deactivate a catalog.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcDeactivateCatalogRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DeactivateCatalog(\Wtsvk\EvitaDbClient\Protocol\GrpcDeactivateCatalogRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/DeactivateCatalog',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcDeactivateCatalogResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to deactivate a catalog with progress tracking.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcDeactivateCatalogRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function DeactivateCatalogWithProgress(\Wtsvk\EvitaDbClient\Protocol\GrpcDeactivateCatalogRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/DeactivateCatalogWithProgress',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcApplyMutationWithProgressResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to register a system change capture.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcRegisterSystemChangeCaptureRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function RegisterSystemChangeCapture(\Wtsvk\EvitaDbClient\Protocol\GrpcRegisterSystemChangeCaptureRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/RegisterSystemChangeCapture',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcRegisterSystemChangeCaptureResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to initiate progress consumption for top-level engine mutations.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcGetProgressRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function GetProgress(\Wtsvk\EvitaDbClient\Protocol\GrpcGetProgressRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/io.evitadb.externalApi.grpc.generated.EvitaService/GetProgress',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcGetProgressResponse', 'decode'],
        $metadata, $options);
    }

}
