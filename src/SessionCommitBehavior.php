<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient;

use Wtsvk\EvitaDbClient\Protocol\GrpcCommitBehavior;

/**
 * Controls how long the gRPC `Close()` call blocks before returning a response.
 *
 * All three values result in a committed transaction — EvitaDB does NOT support
 * runtime rollback at session close. The difference is which stage of the commit
 * pipeline the server waits for before sending the response back to the client.
 */
enum SessionCommitBehavior: int
{
    /**
     * Respond after conflict check passes. Fastest, but no durability guarantee —
     * if the server crashes before the WAL is fsynced, changes are lost.
     */
    case WaitForConflictResolution = GrpcCommitBehavior::WAIT_FOR_CONFLICT_RESOLUTION;

    /**
     * Respond after WAL is fsynced to disk. Durable, but changes may not yet be
     * queryable (indexes not yet updated). This is EvitaDB's default.
     */
    case WaitForLogPersistence = GrpcCommitBehavior::WAIT_FOR_LOG_PERSISTENCE;

    /**
     * Respond after indexes are updated. Slowest, but data is immediately queryable
     * after the close call returns.
     */
    case WaitForChangesVisible = GrpcCommitBehavior::WAIT_FOR_CHANGES_VISIBLE;
}
