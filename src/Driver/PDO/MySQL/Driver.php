<?php

declare(strict_types=1);

namespace Jowy\DbalPdoSwoole\Driver\PDO\MySQL;

use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\PDO\Exception;
use Jowy\DbalPdoSwoole\Driver\PDO\ConnectionPoolAwareInterface;
use Jowy\DbalPdoSwoole\Driver\PDO\ConnectionPoolAwareTrait;
use Jowy\DbalPdoSwoole\Driver\PDO\ConnectionPoolInterface;
use PDO;
use PDOException;
use SensitiveParameter;

final class Driver extends AbstractMySQLDriver implements ConnectionPoolAwareInterface
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

    public static function createPDO(array $params): ?PDO
    {
        $driverOptions = $params['driverOptions'] ?? [];

        if (! empty($params['persistent'])) {
            $driverOptions[PDO::ATTR_PERSISTENT] = true;
        }

        $safeParams = $params;
        unset($safeParams['password']);

        try {
            $pdo = new PDO(
                self::constructMysqlPdoDsn($safeParams),
                $params['user'] ?? '',
                $params['password'] ?? '',
                $driverOptions,
            );
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    private static function constructMysqlPdoDsn(array $params): string
    {
        $dsn = 'mysql:';
        if (isset($params['host']) && $params['host'] !== '') {
            $dsn .= 'host=' . $params['host'] . ';';
        }

        if (isset($params['port'])) {
            $dsn .= 'port=' . $params['port'] . ';';
        }

        if (isset($params['dbname'])) {
            $dsn .= 'dbname=' . $params['dbname'] . ';';
        }

        if (isset($params['unix_socket'])) {
            $dsn .= 'unix_socket=' . $params['unix_socket'] . ';';
        }

        if (isset($params['charset'])) {
            $dsn .= 'charset=' . $params['charset'] . ';';
        }

        return $dsn;
    }
}