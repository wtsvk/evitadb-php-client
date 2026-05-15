<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient;

use Wtsvk\EvitaDbClient\Exception\EvitaDbConnectionException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbStatusException;

interface EvitaDbConnectionInterface
{
    public function isHealthy(): bool;

    /**
     * Creates the catalog and immediately transitions it from WARMING_UP to ALIVE
     * (via a temporary write session + GoLiveAndClose RPC) so it is ready to serve
     * transactional reads/writes. Returns false if the catalog already existed —
     * in that case go-live is skipped (assumed to have been done previously).
     *
     * @throws EvitaDbConnectionException
     * @throws EvitaDbStatusException
     */
    public function defineCatalog(string $catalog): bool;

    /**
     * @return list<string>
     *
     * @throws EvitaDbConnectionException
     * @throws EvitaDbStatusException
     */
    public function getCatalogNames(): array;

    /**
     * @throws EvitaDbConnectionException
     * @throws EvitaDbStatusException
     */
    public function deleteCatalog(string $catalog): bool;

    public function catalog(
        string $catalog,
        SessionCommitBehavior $defaultCommitBehavior = SessionCommitBehavior::WaitForChangesVisible,
    ): EvitaDbClientInterface;
}
