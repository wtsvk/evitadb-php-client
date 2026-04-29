<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Wtsvk\EvitaDbClient\Protocol;

/**
 * This service contains RPCs that could be called by gRPC clients on evitaDB. Main purpose of this service is to provide
 * a way to create sessions and catalogs, and to update the catalog.
 */
class EvitaManagementServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * Procedure used to obtain server status.
     * @param \Google\Protobuf\GPBEmpty $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ServerStatus(\Google\Protobuf\GPBEmpty $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaManagementService/ServerStatus',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcEvitaServerStatusResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to obtain server configuration.
     * @param \Google\Protobuf\GPBEmpty $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetConfiguration(\Google\Protobuf\GPBEmpty $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaManagementService/GetConfiguration',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcEvitaConfigurationResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to obtain catalog statistics.
     * @param \Google\Protobuf\GPBEmpty $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetCatalogStatistics(\Google\Protobuf\GPBEmpty $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaManagementService/GetCatalogStatistics',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcEvitaCatalogStatisticsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to restore a catalog from backup.
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ClientStreamingCall
     */
    public function RestoreCatalog($metadata = [], $options = []) {
        return $this->_clientStreamRequest('/io.evitadb.externalApi.grpc.generated.EvitaManagementService/RestoreCatalog',
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcRestoreCatalogResponse','decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to restore a catalog from backup (unary version for gRPC/web).
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcRestoreCatalogUnaryRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RestoreCatalogUnary(\Wtsvk\EvitaDbClient\Protocol\GrpcRestoreCatalogUnaryRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaManagementService/RestoreCatalogUnary',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcRestoreCatalogUnaryResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to restore a catalog from backup.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcRestoreCatalogFromServerFileRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RestoreCatalogFromServerFile(\Wtsvk\EvitaDbClient\Protocol\GrpcRestoreCatalogFromServerFileRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaManagementService/RestoreCatalogFromServerFile',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcRestoreCatalogResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to get listing of task statuses.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcTaskStatusesRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ListTaskStatuses(\Wtsvk\EvitaDbClient\Protocol\GrpcTaskStatusesRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaManagementService/ListTaskStatuses',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcTaskStatusesResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to get detail of particular task status.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcTaskStatusRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetTaskStatus(\Wtsvk\EvitaDbClient\Protocol\GrpcTaskStatusRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaManagementService/GetTaskStatus',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcTaskStatusResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to get multiple details of particular task statuses.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcSpecifiedTaskStatusesRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetTaskStatuses(\Wtsvk\EvitaDbClient\Protocol\GrpcSpecifiedTaskStatusesRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaManagementService/GetTaskStatuses',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcSpecifiedTaskStatusesResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to cancel queued or running task.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcCancelTaskRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function CancelTask(\Wtsvk\EvitaDbClient\Protocol\GrpcCancelTaskRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaManagementService/CancelTask',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcCancelTaskResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to get listing of files available for fetching.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcFilesToFetchRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ListFilesToFetch(\Wtsvk\EvitaDbClient\Protocol\GrpcFilesToFetchRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaManagementService/ListFilesToFetch',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcFilesToFetchResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to get single file by its id available for fetching.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcFileToFetchRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetFileToFetch(\Wtsvk\EvitaDbClient\Protocol\GrpcFileToFetchRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaManagementService/GetFileToFetch',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcFileToFetchResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to get file contents
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcFetchFileRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function FetchFile(\Wtsvk\EvitaDbClient\Protocol\GrpcFetchFileRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/io.evitadb.externalApi.grpc.generated.EvitaManagementService/FetchFile',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcFetchFileResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure used to delete file contents
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcDeleteFileToFetchRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DeleteFile(\Wtsvk\EvitaDbClient\Protocol\GrpcDeleteFileToFetchRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaManagementService/DeleteFile',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcDeleteFileToFetchResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * List reserved keywords
     * @param \Google\Protobuf\GPBEmpty $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ListReservedKeywords(\Google\Protobuf\GPBEmpty $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.EvitaManagementService/ListReservedKeywords',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GrpcReservedKeywordsResponse', 'decode'],
        $metadata, $options);
    }

}
