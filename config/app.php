<?php
declare(strict_types=1);

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Europe/Budapest');

$appRoot = realpath(dirname(__DIR__));
$documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;

if ($appRoot !== false && $documentRoot !== false) {
    $normalizedRoot = strtolower(str_replace('\\', '/', $appRoot));
    $normalizedDocumentRoot = rtrim(strtolower(str_replace('\\', '/', $documentRoot)), '/');

    if (strpos($normalizedRoot, $normalizedDocumentRoot . '/') === 0) {
        $relativePath = substr($appRoot, strlen($documentRoot));
        $baseUrl = '/' . trim(str_replace('\\', '/', $relativePath), '/');
    } else {
        $baseUrl = '/' . basename($appRoot);
    }
} else {
    $baseUrl = '/' . basename(dirname(__DIR__));
}

if (!defined('APP_BASE_URL')) {
    define('APP_BASE_URL', rtrim($baseUrl, '/'));
}
