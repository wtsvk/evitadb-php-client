<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient;

use Wtsvk\EvitaDbClient\Exception\EvitaDbConnectionException;
use Wtsvk\EvitaDbClient\Exception\EvitaDbStatusException;

interface EvitaDbConnectionInterface
{
    public function isHealthy(): bool;

    /**
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
