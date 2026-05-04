<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Testing;

final readonly class MockedSchemaDefinition
{
    public function __construct(
        public string $catalog,
        public string $entityType,
    ) {
    }
}
