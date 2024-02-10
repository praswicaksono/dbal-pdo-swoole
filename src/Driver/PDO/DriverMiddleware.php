<?php

declare(strict_types=1);

namespace Jowy\DbalPdoSwoole\Driver\PDO;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;

final class DriverMiddleware implements MiddlewareInterface
{
    public function __construct(private ConnectionPoolInterface $connectionPool)
    {
    }

    public function wrap(DriverInterface $driver): DriverInterface
    {
        if ($driver instanceof ConnectionPoolAwareInterface) {
            $driver->setConnectionPool($this->connectionPool);
        }

        return $driver;
    }
}