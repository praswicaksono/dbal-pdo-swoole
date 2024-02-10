<?php

declare(strict_types=1);

namespace Jowy\DbalPdoSwoole\Driver\PDO;

use Jowy\DbalPdoSwoole\Driver\PDO\Exception\DriverException;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Throwable;
use WeakMap;
use Closure;
use PDO;

final class ConnectionPool implements ConnectionPoolInterface
{
    private Channel $chan;
    /** @psalm-var WeakMap<PDO, ConnectionStats> $map */
    private WeakMap $map;

    public function __construct(
        private Closure $constructor,
        private readonly ConnectionPoolParam $connPoolParam,
    ) {
        if ($this->connPoolParam->size < 0) {
            throw new DriverException('Expected, connection pull size > 0');
        }
        $this->chan = new Channel($this->connPoolParam->size);
        /** @psalm-suppress PropertyTypeCoercion */
        $this->map = new WeakMap();
    }

    public function replaceConstructor(Closure $constructor): void
    {
        $this->constructor = $constructor;
    }


    /** @psalm-return array{PDO|null, ConnectionStats|null } */
    public function get(float $timeout = -1) : array
    {
        if ($this->chan->isEmpty()) {
            /** try to fill pull with new connect */
            $this->make();
        }

        /** @var PDO|null $connection */
        $connection = $this->chan->pop($timeout);
        if (! $connection instanceof PDO) {
            return [null, null];
        }

        // remove connection if no longer referenced
        if ($this->map->offsetExists($connection) === false) {
            var_dump($this->map->count(), $connection, $this->chan->length());
        }

        return [
            $connection,
            $this->map[$connection] ?? throw new DriverException('Connection stats could not be empty'),
        ];
    }

    public function put(PDO $connection) : void
    {
        $stats = $this->map[$connection] ?? null;
        if (! $stats || $stats->isOverdue()) {
            $this->remove($connection);

            return;
        }
        if ($this->connPoolParam->size <= $this->chan->length()) {
            $this->remove($connection);

            return;
        }

        /** to prevent hypothetical freeze if channel is full, will never happen but for sure */
        if (! $this->chan->push($connection, 1)) {
            $this->remove($connection);
        }
    }

    public function close() : void
    {
        $this->chan->close();
        gc_collect_cycles();
    }

    public function capacity() : int
    {
        return $this->map->count();
    }

    public function length() : int
    {
        return $this->chan->length();
    }

    public function stats() : array
    {
        return $this->chan->stats();
    }

    public function fill() : void
    {
        while($this->chan->length() < $this->connPoolParam->size) {
            $this->make();
        }
        var_dump($this->map, $this->chan->length());
    }

    public function getConnectionFromWeakMap(): array
    {
        $connection = null;
        $stats = null;

        foreach ($this->map as $key => $value) {
            $connection = $key;
            $stats = $value;
            break;
        }

        return [$connection, $stats];
    }

    /**
     * Exclude object data from doctrine cache serialization
     *
     * @see vendor/doctrine/dbal/src/Cache/QueryCacheProfile.php:127
     */
    public function __serialize() : array
    {
        return [];
    }

    /**
     * @param string $data
     */
    public function __unserialize($data) : void
    {
        // Do nothing
    }

    private function remove(PDO $connection) : void
    {
        $this->map->offsetUnset($connection);
        unset($connection);
    }

    private function make() : void
    {
        if ($this->connPoolParam->size <= $this->capacity()) {
            return;
        }

        /** @var ?PDO $connection */
        $connection = null;
        $attempts = 0;
        while($connection === null) {
            try {
                // connection return null if error are retryable
                // retry until it connected
                $connection = ($this->constructor)();
            } catch (Throwable $e) {
                $attempts++;
                if($attempts >= $this->connPoolParam->maxAttempts) {
                    throw $e;
                }
                Coroutine::sleep($this->connPoolParam->retryDelay + $attempts);
            }
        }

        /** Allocate to map only after successful push(exclude chanel overflow cause of concurrency)
         *
         * @psalm-suppress PossiblyNullReference
         */
        if ($this->chan->push($connection, 1)) {
            $this->map[$connection] = new ConnectionStats(time(), 1, $this->connPoolParam->connectionTtl, $this->connPoolParam->maxQueryUsage);
        } else {
            var_dump($connection, $this->map->count(), $this->chan->length());
        }
    }
}