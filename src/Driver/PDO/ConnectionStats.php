<?php

declare(strict_types=1);

namespace Jowy\DbalPdoSwoole\Driver\PDO;

final class ConnectionStats
{
    public function __construct(
        public int $lastInteraction,
        public int $counter,
        private readonly int $ttl = 0,
        private readonly int $counterLimit = 0,
    ) {
    }

    public function isOverdue() : bool
    {
        if (! $this->counterLimit && ! $this->ttl) {
            return false;
        }
        $counterOverflow = ($this->counterLimit !== 0 && $this->counter > $this->counterLimit);
        $ttlOverdue      = $this->ttl !== 0 && time() - $this->lastInteraction > $this->ttl;

        return $counterOverflow || $ttlOverdue;
    }
}