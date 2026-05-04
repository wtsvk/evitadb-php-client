<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient\Testing;

use Closure;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryRequest;
use Wtsvk\EvitaDbClient\Protocol\GrpcQueryResponse;

/**
 * Internal stub registration for query routing inside EvitaDbMockClient.
 *
 * @internal
 */
final readonly class MockedQueryStub
{
    /**
     * @param  Closure(GrpcQueryRequest): bool  $matcher
     */
    public function __construct(
        public string $catalog,
        public Closure $matcher,
        public GrpcQueryResponse $response,
    ) {
    }
}
