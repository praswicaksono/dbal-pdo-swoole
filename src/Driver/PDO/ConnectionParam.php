<?php

declare(strict_types=1);

namespace Jowy\DbalPdoSwoole\Driver\PDO;

final readonly class ConnectionParam
{
    public function __construct(
        public int $connectionPoolTimeout = 3,
        public int $maxAttempts = 3,
    ) {
    }
}