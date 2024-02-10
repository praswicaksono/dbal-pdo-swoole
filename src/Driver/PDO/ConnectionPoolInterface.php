<?php

declare(strict_types=1);

namespace Jowy\DbalPdoSwoole\Driver\PDO;

use PDO;
use Closure;

interface ConnectionPoolInterface
{
    public function replaceConstructor(Closure $constructor): void;
    public function get(float $timeout = -1) : array;

    public function put(PDO $connection) : void;

    public function capacity() : int;

    public function length() : int;

    public function close() : void;

    public function stats() : array;

    public function fill() : void;
}