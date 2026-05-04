<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Testing;

final readonly class MockedDelete
{
    public function __construct(
        public string $catalog,
        public string $entityType,
        public int $primaryKey,
    ) {
    }
}
