<?php

use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = config('database.connections.mysql');
$database = 'zaylo_milestone4_check';
$dsn = sprintf(
    'mysql:host=%s;port=%s;charset=%s',
    $connection['host'],
    $connection['port'],
    $connection['charset'] ?? 'utf8mb4',
);
$pdo = new PDO($dsn, $connection['username'], $connection['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$pdo->exec("DROP DATABASE IF EXISTS `{$database}`");

if (($argv[1] ?? 'prepare') === 'prepare') {
    $collation = $connection['collation'] ?? 'utf8mb4_unicode_ci';
    $pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE {$collation}");
    echo "Prepared {$database}".PHP_EOL;
} else {
    echo "Removed {$database}".PHP_EOL;
}
