<?php

declare(strict_types=1);

namespace Jowy\DbalPdoSwoole\Driver\PDO;

trait ConnectionPoolAwareTrait
{
    public function setConnectionPool(ConnectionPoolInterface $connectionPool): void
    {
        $this->connectionPool = $connectionPool;
    }

    private function createConnection(array $params): Connection
    {
        $connParams = new ConnectionParam(
            connectionPoolTimeout: @$params['timeout'] ?? null,
            maxAttempts: @$params['maxAttempts'] ?? null
        );

        $this->connectionPool->replaceConstructor(static fn() => self::createPDO($params));

        return new Connection(
            $this->connectionPool,
            $connParams
        );
    }
}