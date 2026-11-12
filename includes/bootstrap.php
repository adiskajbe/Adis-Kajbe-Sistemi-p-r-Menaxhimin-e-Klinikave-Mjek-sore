<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = null;
$databaseError = null;

try {
    require __DIR__ . '/../config/database.php';
} catch (Throwable $exception) {
    $databaseError = 'Kontrolloni që MySQL është aktiv dhe skema klinika_db është importuar.';
}