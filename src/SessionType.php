<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient;

enum SessionType
{
    case ReadOnly;
    case ReadWrite;

    public function label(): string
    {
        return match ($this) {
            self::ReadOnly => 'read-only',
            self::ReadWrite => 'read-write',
        };
    }
}
