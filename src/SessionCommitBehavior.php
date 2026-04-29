<?php

declare(strict_types=1);

namespace Wtsvk\EvitaDbClient;

enum SessionCommitBehavior
{
    case Commit;
    case Discard;
}
