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
     * On exception inside the callable, the session is closed with the gRPC
     * Close() `rollback` flag set, so the server discards all pending mutations
     * immediately; the original exception is then rethrown unchanged. If that
     * rollback close itself fails, the failure is swallowed — the session is
     * never committed and the server's session timeout discards it.
     * To discard changes regardless of outcome, pass $dryRun=true.
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
