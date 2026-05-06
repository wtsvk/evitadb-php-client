<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Transaction;

use Wtsvk\EvitaDbClient\Protocol\EvitaSessionServiceClient;

/**
 * Read-only session-scoped transaction context.
 *
 * Bound to a single read-only EvitaDB session opened by EvitaDbClient::readTransaction().
 * Implements ReadTransactionContext only — write methods are intentionally not exposed
 * so the type system enforces the read-only guarantee at the interface level.
 */
final readonly class ReadOnlySessionScopedContext implements ReadTransactionContext
{
    use SessionScopedReads;

    public function __construct(
        private EvitaSessionServiceClient $sessionService,
        private string $sessionId,
        private string $catalog,
    ) {
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
