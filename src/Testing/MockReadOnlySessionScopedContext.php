<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Testing;

use Wtsvk\EvitaDbClient\Transaction\ReadTransactionContext;

/**
 * Read-only session-scoped context returned by EvitaDbMockClient::readTransaction().
 *
 * Reads delegate back to the mock client's stub registries.
 */
final readonly class MockReadOnlySessionScopedContext implements ReadTransactionContext
{
    use MockSessionScopedReads;

    public function __construct(
        private EvitaDbMockClient $client,
    ) {
    }

    private function client(): EvitaDbMockClient
    {
        return $this->client;
    }
}
