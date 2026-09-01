<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Wtsvk\EvitaDbClient\Protocol;

/**
 * This service contains RPCs that could be called by gRPC clients on evitaDB's catalog by usage of a before created
 * session. Main purpose of this service is to provide a way to query and manage recorded traffic (queries, mutations,
 * session lifecycle events) captured for diagnostics.
 */
class GrpcEvitaTrafficRecordingServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * Procedure that returns requested list of past traffic records with limited size that match the request criteria.
     * Order of the returned records is from the oldest sessions to the newest,
     * traffic records within the session are ordered from the oldest to the newest.
     * @param \Wtsvk\EvitaDbClient\Protocol\GetTrafficHistoryListRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetTrafficRecordingHistoryList(\Wtsvk\EvitaDbClient\Protocol\GetTrafficHistoryListRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.GrpcEvitaTrafficRecordingService/GetTrafficRecordingHistoryList',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GetTrafficHistoryListResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that returns requested list of past traffic records with limited size that match the request criteria.
     * Order of the returned records is from the newest sessions to the oldest,
     * traffic records within the session are ordered from the newest to the oldest.
     * @param \Wtsvk\EvitaDbClient\Protocol\GetTrafficHistoryListRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetTrafficRecordingHistoryListReversed(\Wtsvk\EvitaDbClient\Protocol\GetTrafficHistoryListRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.GrpcEvitaTrafficRecordingService/GetTrafficRecordingHistoryListReversed',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GetTrafficHistoryListResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that returns stream of all past traffic records that match the request criteria.
     * Order of the returned records is from the newest sessions to the oldest,
     * traffic records within the session are ordered from the newest to the oldest.
     * @param \Wtsvk\EvitaDbClient\Protocol\GetTrafficHistoryRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function GetTrafficRecordingHistory(\Wtsvk\EvitaDbClient\Protocol\GetTrafficHistoryRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/io.evitadb.externalApi.grpc.generated.GrpcEvitaTrafficRecordingService/GetTrafficRecordingHistory',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GetTrafficHistoryResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure returns a list of top unique labels names ordered by cardinality of their values present in the traffic recording.
     * @param \Wtsvk\EvitaDbClient\Protocol\GetTrafficRecordingLabelNamesRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetTrafficRecordingLabelsNamesOrderedByCardinality(\Wtsvk\EvitaDbClient\Protocol\GetTrafficRecordingLabelNamesRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.GrpcEvitaTrafficRecordingService/GetTrafficRecordingLabelsNamesOrderedByCardinality',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GetTrafficRecordingLabelNamesResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure returns a list of top unique label values ordered by cardinality of their values present in the traffic recording.
     * @param \Wtsvk\EvitaDbClient\Protocol\GetTrafficRecordingValuesNamesRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetTrafficRecordingLabelValuesOrderedByCardinality(\Wtsvk\EvitaDbClient\Protocol\GetTrafficRecordingValuesNamesRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.GrpcEvitaTrafficRecordingService/GetTrafficRecordingLabelValuesOrderedByCardinality',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GetTrafficRecordingValuesNamesResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that starts the traffic recording for the given criteria and settings. Fails if a recording is
     * already in progress - only one recording may run at a time.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcStartTrafficRecordingRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function StartTrafficRecording(\Wtsvk\EvitaDbClient\Protocol\GrpcStartTrafficRecordingRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.GrpcEvitaTrafficRecordingService/StartTrafficRecording',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GetTrafficRecordingStatusResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that stops the traffic recording
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcStopTrafficRecordingRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function StopTrafficRecording(\Wtsvk\EvitaDbClient\Protocol\GrpcStopTrafficRecordingRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.GrpcEvitaTrafficRecordingService/StopTrafficRecording',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GetTrafficRecordingStatusResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Procedure that exports a consistent, on-demand snapshot of the currently buffered traffic recording window
     * to a downloadable zip archive - not gated by any running recording task.
     * @param \Wtsvk\EvitaDbClient\Protocol\GrpcExportTrafficRecordingRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ExportTrafficRecording(\Wtsvk\EvitaDbClient\Protocol\GrpcExportTrafficRecordingRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/io.evitadb.externalApi.grpc.generated.GrpcEvitaTrafficRecordingService/ExportTrafficRecording',
        $argument,
        ['\Wtsvk\EvitaDbClient\Protocol\GetTrafficRecordingStatusResponse', 'decode'],
        $metadata, $options);
    }

}
