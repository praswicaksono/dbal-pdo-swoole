<?php

declare(strict_types=1);

namespace Jowy\DbalPdoSwoole\Driver\PDO;

use PDO;
interface ConnectionPoolAwareInterface
{
    public function setConnectionPool(ConnectionPoolInterface $connectionPool): void;

    public static function createPDO(array $params): ?PDO;
}