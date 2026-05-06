<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient;

use stdClass;
use Stringable;

use function is_int;
use function is_string;
use function property_exists;
use function sprintf;

/**
 * Typed value object wrapping the dynamic stdClass returned by `UnaryCall::wait()`
 * as the second tuple element.
 *
 * The gRPC PHP extension returns status as an unstructured stdClass with optional
 * `code` (int) and `details` (string) properties. This VO parses that once at the
 * boundary so the rest of the code can use a typed value with __toString()-aware
 * formatting in error messages.
 */
final readonly class GrpcStatus implements Stringable
{
    public function __construct(
        public int $code,
        public string $details,
    ) {
    }

    public static function fromRaw(mixed $raw): self
    {
        if (! $raw instanceof stdClass) {
            return new self(code: -1, details: 'invalid status');
        }

        return new self(
            code: property_exists($raw, 'code') && is_int($raw->code) ? $raw->code : -1,
            details: property_exists($raw, 'details') && is_string($raw->details) ? $raw->details : 'no details',
        );
    }

    public function __toString(): string
    {
        return sprintf('%s (status %d)', $this->details, $this->code);
    }
}
