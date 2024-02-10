<?php

declare(strict_types=1);

namespace Jowy\DbalPdoSwoole\Driver\PDO;

use Jowy\DbalPdoSwoole\Driver\PDO\Exception\DriverException;

final readonly class ConnectionPoolFactory
{
    public const int DEFAULT_POOL_SIZE = 4;
    public const int DEFAULT_CONNECTION_TTL = -1;
    public const int DEFAULT_MAX_QUERY_USAGE = -1;

    public const int DEFAULT_RETRY_DELAY = 1;

    public const int DEFAULT_MAX_ATTEMPTS = 3;

    public function __invoke(array $params = []): ConnectionPoolInterface
    {
        $poolSize = @$params['size'] ?? self::DEFAULT_POOL_SIZE;
        $connectionTtl = @$params['connectionTtl'] ?? self::DEFAULT_CONNECTION_TTL;
        $maxQueryUsage = @$params['maxQueryUsage'] ?? self::DEFAULT_MAX_QUERY_USAGE;
        $retryDelay = @$params['retryDelay'] ?? self::DEFAULT_RETRY_DELAY;
        $maxAttempts = @$params['maxAttempts'] ?? self::DEFAULT_MAX_ATTEMPTS;

        return new ConnectionPool(
            fn() => throw new DriverException('Need to override inside driver'),
            new ConnectionPoolParam(
                size: $poolSize,
                connectionTtl: $connectionTtl,
                maxQueryUsage: $maxQueryUsage,
                retryDelay: $retryDelay,
                maxAttempts: $maxAttempts,
            )
        );
    }
}