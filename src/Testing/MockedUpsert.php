<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Testing;

use Wtsvk\EvitaDbClient\Protocol\GrpcEntityUpsertMutation;

final readonly class MockedUpsert
{
    public function __construct(
        public string $catalog,
        public GrpcEntityUpsertMutation $mutation,
    ) {
    }
}
