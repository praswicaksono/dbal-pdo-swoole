<?php

declare(strict_types=1);

namespace Jowy\DbalPdoSwoole\Driver\PDO\SQLite;

use Doctrine\DBAL\Driver\AbstractSQLiteDriver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\PDO\Exception;
use Jowy\DbalPdoSwoole\Driver\PDO\ConnectionPoolAwareInterface;
use Jowy\DbalPdoSwoole\Driver\PDO\ConnectionPoolAwareTrait;
use Jowy\DbalPdoSwoole\Driver\PDO\ConnectionPoolInterface;
use SensitiveParameter;
use PDO;
use PDOException;

final class Driver extends AbstractSQLiteDriver implements ConnectionPoolAwareInterface
{
    use ConnectionPoolAwareTrait;

    public function __construct(
        private ?ConnectionPoolInterface $connectionPool = null
    ) {
    }

    public function connect(#[SensitiveParameter] array $params,): DriverConnection
    {
        return $this->createConnection($params);
    }

    public static function createPDO(array $params): PDO
    {
        try {
            $pdo = new PDO(
                self::constructPdoDsn(array_intersect_key($params, ['path' => true, 'memory' => true])),
                $params['user'] ?? '',
                $params['password'] ?? '',
                $params['driverOptions'] ?? [],
            );
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    private static function constructPdoDsn(array $params): string
    {
        $dsn = 'sqlite:';
        if (isset($params['path'])) {
            $dsn .= $params['path'];
        } elseif (isset($params['memory'])) {
            $dsn .= ':memory:';
        }

        return $dsn;
    }
}