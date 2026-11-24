<?php
declare(strict_types=1);

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Europe/Budapest');

$appRoot = realpath(dirname(__DIR__));
$documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;

if ($appRoot !== false && $documentRoot !== false) {
    $normalizedRoot = strtolower(str_replace('\\', '/', $appRoot));
    $normalizedDocumentRoot = rtrim(strtolower(str_replace('\\', '/', $documentRoot)), '/');

    if ($normalizedRoot === $normalizedDocumentRoot) {
        $baseUrl = '';
    } elseif (strpos($normalizedRoot, $normalizedDocumentRoot . '/') === 0) {
        $relativePath = substr($appRoot, strlen($documentRoot));
        $urlSegments = array_filter(explode('/', trim(str_replace('\\', '/', $relativePath), '/')), 'strlen');
        $baseUrl = '/' . implode('/', array_map('rawurlencode', $urlSegments));
    } else {
        $baseUrl = '/' . rawurlencode(basename($appRoot));
    }
} else {
    $baseUrl = '/' . rawurlencode(basename(dirname(__DIR__)));
}

if (!defined('APP_BASE_URL')) {
    define('APP_BASE_URL', rtrim($baseUrl, '/'));
}

if (!defined('APP_LOCAL_AUTH_BYPASS')) {
    define('APP_LOCAL_AUTH_BYPASS', false); // Set true only for temporary localhost development.
}
