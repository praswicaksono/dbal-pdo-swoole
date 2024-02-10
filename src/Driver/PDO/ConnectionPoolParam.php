<?php

declare(strict_types=1);

namespace Jowy\DbalPdoSwoole\Driver\PDO;

final readonly class ConnectionPoolParam
{
    public function __construct(
        public int $size,
        public int $connectionTtl,
        public int $maxQueryUsage,
        public int $retryDelay,
        public int $maxAttempts,
    ) {
    }
}