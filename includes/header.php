<?php
require_once __DIR__ . '/../config/app.php';
$pageTitle = $pageTitle ?? 'Ballina';
$activePage = $activePage ?? '';
?>
<!doctype html>
<html lang="sq">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> | Menaxhimi i Klinikave</title>
    <link rel="stylesheet" href="<?= h(APP_BASE_URL) ?>/assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a class="brand" href="<?= h(APP_BASE_URL) ?>/index.php">
                <span class="brand-mark" aria-hidden="true">+</span>
                <span><strong>Klinika</strong><small>Menaxhimi i vizitave</small></span>
            </a>
            <nav class="main-nav" aria-label="Navigimi kryesor">
                <a class="<?= $activePage === 'home' ? 'active' : '' ?>" href="<?= h(APP_BASE_URL) ?>/index.php">Ballina</a>
                <a class="<?= $activePage === 'patients' ? 'active' : '' ?>" href="<?= h(APP_BASE_URL) ?>/pacientet/lista.php">Pacientët</a>
                <a class="<?= $activePage === 'doctors' ? 'active' : '' ?>" href="<?= h(APP_BASE_URL) ?>/mjeket/lista.php">Mjekët</a>
                <a class="<?= $activePage === 'appointments' ? 'active' : '' ?>" href="<?= h(APP_BASE_URL) ?>/terminet/lista.php">Terminet</a>
            </nav>
        </div>
    </header>
    <main class="page-shell">
