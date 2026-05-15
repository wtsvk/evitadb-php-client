<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Testing;

use Wtsvk\EvitaDbClient\EvitaDbClientInterface;
use Wtsvk\EvitaDbClient\EvitaDbConnectionInterface;
use Wtsvk\EvitaDbClient\SessionCommitBehavior;

use function array_filter;
use function array_values;
use function in_array;

/**
 * In-memory fake of EvitaDbConnectionInterface for testing.
 *
 * Tracks catalog definitions/deletions and produces EvitaDbMockClient instances.
 *
 * Typical usage:
 *
 *     $conn = new MockEvitaDbConnection();
 *     $conn->defineCatalog('testCatalog');
 *     $client = $conn->catalog('testCatalog');
 */
final class MockEvitaDbConnection implements EvitaDbConnectionInterface
{
    public bool $healthy = true;

    /**
     * @var list<string>
     */
    public array $definedCatalogs = [];

    /**
     * @var list<string>
     */
    public array $deletedCatalogs = [];

    /**
     * @var list<string>
     */
    public array $catalogNames = [];

    public function isHealthy(): bool
    {
        return $this->healthy;
    }

    /**
     * Mirrors EvitaDbConnection: a catalog is created and immediately treated as ALIVE
     * (no separate WARMING_UP phase in the mock). Returns false on the second call with
     * the same name to simulate the "already exists" branch.
     */
    public function defineCatalog(string $catalog): bool
    {
        $this->definedCatalogs[] = $catalog;

        if (in_array($catalog, $this->catalogNames, true)) {
            return false;
        }

        $this->catalogNames[] = $catalog;

        return true;
    }

    /**
     * @return list<string>
     */
    public function getCatalogNames(): array
    {
        return $this->catalogNames;
    }

    public function deleteCatalog(string $catalog): bool
    {
        $this->deletedCatalogs[] = $catalog;
        $this->catalogNames = array_values(array_filter(
            $this->catalogNames,
            static fn (string $name): bool => $name !== $catalog,
        ));

        return true;
    }

    public function catalog(
        string $catalog,
        SessionCommitBehavior $defaultCommitBehavior = SessionCommitBehavior::WaitForChangesVisible,
    ): EvitaDbClientInterface {
        unset($defaultCommitBehavior);

        // The mock has no server-side commit pipeline, so the commit-behavior
        // wait-level is meaningless here. The parameter exists only to satisfy
        // the EvitaDbConnectionInterface contract.
        return new EvitaDbMockClient(catalog: $catalog);
    }
}
