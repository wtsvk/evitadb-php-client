<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient;

use Wtsvk\EvitaDbClient\Exception\EvitaDbConnectionException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbStatusException;
use Wtsvk\EvitaDbClient\Transaction\ReadTransactionContext;
use Wtsvk\EvitaDbClient\Transaction\WriteTransactionContext;

interface EvitaDbClientInterface
{
    /**
     * Open a read-write session, run the callable, commit on normal return.
     *
     * On exception inside the callable, the session is intentionally orphaned:
     * the EvitaDB server's gRPC Close() RPC has no rollback semantics — calling
     * it always commits. The only way to roll back pending mutations over gRPC
     * is to let the server's session timeout discard the buffered transaction.
     * To get a deterministic rollback regardless of outcome, pass $dryRun=true.
     *
     * If the callable returns normally but the close call fails (transport
     * error or non-OK status), an EvitaDbConnectionException / EvitaDbStatusException
     * propagates — the consumer learns the commit didn't actually happen.
     *
     * @template T
     *
     * @param callable(WriteTransactionContext): T $fn
     * @param SessionCommitBehavior|null $commitBehavior Overrides the client's default commit behavior on success.
     * @return T
     *
     * @throws EvitaDbConnectionException
     * @throws EvitaDbStatusException
     */
    public function writeTransaction(
        callable $fn,
        bool $dryRun = false,
        ?SessionCommitBehavior $commitBehavior = null,
    ): mixed;

    /**
     * Open a read-only session and run the callable. The session gives a
     * consistent snapshot across all reads inside the callable.
     *
     * Close failures on the read-only session are swallowed (read-only sessions
     * have nothing to commit) so they don't mask the actual return value or
     * an exception already in flight.
     *
     * @template T
     *
     * @param callable(ReadTransactionContext): T $fn
     * @return T
     *
     * @throws EvitaDbConnectionException
     */
    public function readTransaction(callable $fn): mixed;
}
