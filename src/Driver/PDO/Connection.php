<?php

namespace Jowy\DbalPdoSwoole\Driver\PDO;

use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Exception\IdentityColumnsNotSupported;
use Doctrine\DBAL\Driver\Exception\NoIdentityValue;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Driver\PDO\Result;
use Doctrine\DBAL\Driver\PDO\Statement as BaseStatement;
use PDOException;
use PDO;
use PDOStatement;
use Swoole\Coroutine;

final readonly class Connection implements ConnectionInterface
{
    public function __construct(
        private ConnectionPoolInterface $pool,
        private ConnectionParam $params,
    ) {

    }

    public function getServerVersion(): string
    {
        return $this->getNativeConnection()->getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    public function exec(string $sql): int
    {
        try {
            $result = $this->getNativeConnection()->exec($sql);

            assert($result !== false);

            $stats = $this->connectionStats();
            if ($stats instanceof ConnectionStats) {
                $stats->counter++;
            }

            return $result;
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function query(string $sql): Result
    {
        try {
            $stmt = $this->getNativeConnection()->query($sql, PDO::FETCH_BOTH);
            assert($stmt instanceof PDOStatement);

            $stats = $this->connectionStats();
            if ($stats instanceof ConnectionStats) {
                $stats->counter++;
            }

            return new Result($stmt);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function prepare(string $sql): Statement
    {
        try {
            $stmt = $this->getNativeConnection()->prepare($sql);
            assert($stmt instanceof PDOStatement);

            return new Statement(new BaseStatement($stmt), $this->connectionStats());
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function quote(string $value): string
    {
        return $this->getNativeConnection()->quote($value);
    }

    public function beginTransaction(): void
    {
        try {
            $this->getNativeConnection()->beginTransaction();
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function lastInsertId(): int|string
    {
        try {
            $value = $this->getNativeConnection()->lastInsertId();
        } catch (PDOException $exception) {
            assert($exception->errorInfo !== null);
            [$sqlState] = $exception->errorInfo;

            // if the PDO driver does not support this capability, PDO::lastInsertId() triggers an IM001 SQLSTATE
            // see https://www.php.net/manual/en/pdo.lastinsertid.php
            if ($sqlState === 'IM001') {
                throw IdentityColumnsNotSupported::new();
            }

            // PDO PGSQL throws a 'lastval is not yet defined in this session' error when no identity value is
            // available, with SQLSTATE 55000 'Object Not In Prerequisite State'
            if ($sqlState === '55000' && $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
                throw NoIdentityValue::new($exception);
            }

            throw Exception::new($exception);
        }

        // pdo_mysql & pdo_sqlite return '0', pdo_sqlsrv returns '' or false depending on the PHP version
        if ($value === '0' || $value === '' || $value === false) {
            throw NoIdentityValue::new();
        }

        return $value;
    }

    public function commit(): void
    {
        try {
            $this->getNativeConnection()->commit();
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function rollBack(): void
    {
        try {
            $this->getNativeConnection()->rollBack();
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function getNativeConnection(): PDO
    {
        $context = $this->getContext();
        [$connection, $stats] = $context[self::class] ?? [null, null];

        if ($connection instanceof PDO) {
            return $connection;
        }

        while (! $this->ping($connection)) {
            /**
             * @var PDO $connection
             * @var ConnectionStats $stats
             */
            [$connection, $stats] = $this->pool->get($this->params->connectionPoolTimeout);


            if (! $stats instanceof ConnectionStats) {
                continue;
            }

            // if connection freshly made dont ping again
            if ($stats->counter === 0) {
                break;
            }
        }

        $context[self::class] = [$connection, $stats];

        defer($this->onDefer(...));

        return $connection;
    }

    private function onDefer() : void
    {
        $context = $this->getContext();
        /** @psalm-suppress MixedArrayAccess, MixedAssignment */
        [$connection, $stats] = $context[self::class] ?? [null, null];
        /** @psalm-suppress RedundantCondition */
        if (! $connection instanceof PDO) {
            return;
        }
        /** @psalm-suppress TypeDoesNotContainType */
        if ($stats instanceof ConnectionStats) {
            $stats->lastInteraction = time();
        }
        $this->pool->put($connection);
        unset($context[self::class]);
    }

    private function getContext() : Coroutine\Context
    {
        $context = Coroutine::getContext(Coroutine::getCid());
        if (! $context instanceof Coroutine\Context) {
            throw new ConnectionException('Connection Co::Context unavailable');
        }

        return $context;
    }

    private function connectionStats() : ?ConnectionStats
    {
        [, $stats] = $this->getContext()[self::class] ?? [null, null];

        return $stats;
    }

    private function ping(?PDO $conn = null): bool
    {
        if (! $conn instanceof PDO) {
            return false;
        }

        try {
            $result = $conn->query('SELECT 1');

            if ($result === false) {
                unset($conn);
                return false;
            }

            return true;
        } catch (PDOException $e) {
            unset($conn);
            return false;
        }
    }
}