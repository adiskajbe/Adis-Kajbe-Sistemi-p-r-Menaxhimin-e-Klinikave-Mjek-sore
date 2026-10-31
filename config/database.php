<?php
declare(strict_types=1);

$host = getenv('DB_HOST') ?: '127.0.0.1';
$database = getenv('DB_NAME') ?: 'klinika_db';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $database);

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    error_log('Lidhja me databazën dështoi: ' . $exception->getMessage());
    throw new RuntimeException('Nuk u arrit lidhja me databazën.', 0, $exception);
}