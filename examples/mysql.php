<?php
declare(strict_types=1);

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Jowy\DbalPdoSwoole\Driver\PDO\ConnectionPoolFactory;
use Jowy\DbalPdoSwoole\Driver\PDO\DriverMiddleware;
use Jowy\DbalPdoSwoole\Driver\PDO\MySQL\Driver;
use Swoole\Runtime;

require '../vendor/autoload.php';

Runtime::enableCoroutine();

$connPoolConfig = [
    'size' => 4,
    'connectionTtl' => 60,
    'maxQueryUsage' => 10000000,
    'retryDelay' => 1,
    'maxAttempts' => 3,
];

$connectionParams = [
    'dbname' => 'doctor_dev',
    'user' => 'root',
    'password' => 'root',
    'host' => '127.0.0.1',
    'driver' => 'pdo_mysql',
    'driverClass' => Driver::class,
    'timeout' => 1,
    'maxAttempts' => 3,
];

$connPool = (new ConnectionPoolFactory())($connPoolConfig);
$configuration = new Configuration();
$configuration->setMiddlewares(
    [new DriverMiddleware($connPool)]
);

$connFactory = static fn(): Connection => DriverManager::getConnection($connectionParams, $configuration);

$http = new Swoole\Http\Server('127.0.0.1', 9501);
$http->set([
    'hook_flags' => SWOOLE_HOOK_ALL,
    'worker_num' => 2,
]);

$conn = $connFactory();

$http->on('request', function ($request, $response) use ($conn) {
    $conn->beginTransaction();
    $res = $conn->fetchAssociative('SELECT * FROM chat_room LIMIT 1');
    $conn->commit();

    $response->end(json_encode($res));
    $conn->close();
});

$http->on('workerstart', function() use ($connPool) {
    $connPool->fill();
});


$http->start();

//\Co\run(function() use ($connFactory, $connPool) {
//    $time = time();
//    echo $time . PHP_EOL;
//    echo memory_get_usage(true) / 1000000 . PHP_EOL;
//    $wg = new \Co\WaitGroup();
//
//    for ($i = 1; $i <= 1000; $i++) {
//        go(function () use ($connFactory, $wg, $connPool) {
//            $wg->add();
//            $conn = $connFactory();
//            $conn->fetchOne('SELECT version()');
//            $conn->fetchOne('SELECT sleep(1)');
//            defer(static fn() => function() use($conn) {
//                $conn->close();
//            });
//            $wg->done();
//        });
//    }
//
//    $wg->wait();
//
//    echo (time() - $time) . PHP_EOL;
//    echo memory_get_usage(true) / 1000000 . PHP_EOL;
//});