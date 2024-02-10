<?php
declare(strict_types=1);

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Jowy\DbalPdoSwoole\Driver\PDO\ConnectionPoolFactory;
use Jowy\DbalPdoSwoole\Driver\PDO\DriverMiddleware;
use Jowy\DbalPdoSwoole\Driver\PDO\SQLite\Driver;
use Swoole\Runtime;

require '../vendor/autoload.php';

Runtime::enableCoroutine();

$connPoolConfig = [
    'size' => 4,
    'connectionTtl' => 60,
    'maxQueryUsage' => 100
];

$connectionParams = [
    'path' => 'app.db',
    'user' => 'root',
    'password' => 'root',
    'memory' => true,
    'driver' => 'pdo_sqlite',
    'driverClass' => Driver::class,
    'timeout' => 1,
    'retryDelay' => 1,
    'maxAttempts' => 3,
];

$connPool = (new ConnectionPoolFactory())($connPoolConfig);
$configuration = new Configuration();
$configuration->setMiddlewares(
    [new DriverMiddleware($connPool)]
);

$connFactory = static fn(): Connection => DriverManager::getConnection($connectionParams, $configuration);
$conn = $connFactory();

\Co\run(function() use ($conn) {
    for ($i = 1; $i <= 50; $i++) {
        go(function () use ($conn) {
            $v = $conn->fetchOne('SELECT sqlite_version()');
            echo $v . PHP_EOL;
            sleep(1);
            defer(static fn() => $conn->close());
        });
    }
});