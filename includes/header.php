<?php
require_once __DIR__ . '/../config/app.php';
$pageTitle = $pageTitle ?? 'Ballina';
$activePage = $activePage ?? '';
$authUser = current_auth_user();
$authRole = $authUser['role'] ?? '';
?>
<!doctype html>
<html lang="sq">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#087f79">
    <title><?= h($pageTitle) ?> | Menaxhimi i Klinikave</title>
    <link rel="stylesheet" href="<?= h(APP_BASE_URL) ?>/assets/css/style.css">
</head>
<body class="page-<?= h($activePage ?: 'home') ?>" data-role="<?= h($authRole) ?>">
    <header class="site-header">
        <div class="header-inner">
            <a class="brand" href="<?= h(APP_BASE_URL) ?>/index.php">
                <span class="brand-mark" aria-hidden="true">+</span>
                <span><strong>Klinika</strong><small>Menaxhimi i vizitave</small></span>
            </a>
            <nav class="main-nav" aria-label="Navigimi kryesor">
                <?php
                $homeHref = [
                    'mjek' => '/panels/doctor.php',
                    'recepsionist' => '/panels/receptionist.php',
                    'pacient' => '/panels/patient.php',
                ][$authRole] ?? '/index.php';
                $homeLabel = in_array($authRole, ['mjek', 'pacient'], true) ? 'Paneli im' : 'Paneli';
                $currentPath = rawurldecode(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')));
                $basePrefix = rawurldecode(APP_BASE_URL) . '/';
                if ($basePrefix !== '/' && strpos($currentPath, $basePrefix) === 0) {
                    $currentPath = substr($currentPath, strlen($basePrefix));
                } else {
                    $currentPath = ltrim($currentPath, '/');
                }
                $currentRoute = '/' . ltrim($currentPath, '/');
                $menuItems = [];
                if ($authRole === 'admin') {
                    $menuItems = [
                        ['label' => 'Pacientët', 'description' => 'Kërko dhe menaxho profilet', 'url' => '/pacientet/lista.php', 'icon' => 'patients', 'tone' => 'teal'],
                        ['label' => 'Regjistro pacient', 'description' => 'Shto profil të ri', 'url' => '/pacientet/shto.php', 'icon' => 'add', 'tone' => 'blue'],
                        ['label' => 'Mjekët', 'description' => 'Profilet dhe specializimet', 'url' => '/mjeket/lista.php', 'icon' => 'doctors', 'tone' => 'blue'],
                        ['label' => 'Shto mjek', 'description' => 'Regjistro mjek të ri', 'url' => '/mjeket/shto.php', 'icon' => 'add', 'tone' => 'teal'],
                        ['label' => 'Terminet', 'description' => 'Orari i plotë i klinikës', 'url' => '/terminet/lista.php', 'icon' => 'calendar', 'tone' => 'amber'],
                        ['label' => 'Cakto termin', 'description' => 'Rezervo vizitë për pacient', 'url' => '/terminet/shto.php', 'icon' => 'clock', 'tone' => 'amber'],
                        ['label' => 'Llogaritë dhe rolet', 'description' => 'Përdoruesit dhe qasjet', 'url' => '/admin/users.php', 'icon' => 'users', 'tone' => 'violet'],
                        ['label' => 'Regjistri i hyrjeve', 'description' => 'Kontrollo aktivitetin', 'url' => '/admin/login_logs.php', 'icon' => 'shield', 'tone' => 'rose'],
                    ];
                } elseif ($authRole === 'mjek') {
                    $menuItems = [
                        ['label' => 'Pacientët e mi', 'description' => 'Pacientët e termineve tuaja', 'url' => '/pacientet/lista.php', 'icon' => 'patients', 'tone' => 'teal'],
                        ['label' => 'Orari im', 'description' => 'Vetëm terminet tuaja', 'url' => '/terminet/lista.php', 'icon' => 'calendar', 'tone' => 'amber'],
                        ['label' => 'Mjekët dhe specializimet', 'description' => 'Shiko ekipin e klinikës', 'url' => '/mjeket/lista.php', 'icon' => 'doctors', 'tone' => 'blue'],
                    ];
                } elseif ($authRole === 'recepsionist') {
                    $menuItems = [
                        ['label' => 'Pacientët', 'description' => 'Kërko profilet e pacientëve', 'url' => '/pacientet/lista.php', 'icon' => 'patients', 'tone' => 'teal'],
                        ['label' => 'Regjistro pacient', 'description' => 'Shto pacient të ri', 'url' => '/pacientet/shto.php', 'icon' => 'add', 'tone' => 'blue'],
                        ['label' => 'Mjekët', 'description' => 'Shiko specializimet', 'url' => '/mjeket/lista.php', 'icon' => 'doctors', 'tone' => 'blue'],
                        ['label' => 'Terminet', 'description' => 'Shiko orarin e klinikës', 'url' => '/terminet/lista.php', 'icon' => 'calendar', 'tone' => 'amber'],
                        ['label' => 'Cakto termin', 'description' => 'Rezervo për një pacient', 'url' => '/terminet/shto.php', 'icon' => 'clock', 'tone' => 'amber'],
                    ];
                } elseif ($authRole === 'pacient') {
                    $menuItems = [
                        ['label' => 'Mjekët', 'description' => 'Zgjidh sipas specializimit', 'url' => '/mjeket/lista.php', 'icon' => 'doctors', 'tone' => 'blue'],
                        ['label' => 'Terminet e mia', 'description' => 'Data, ora dhe mjeku', 'url' => '/terminet/lista.php', 'icon' => 'calendar', 'tone' => 'amber'],
                        ['label' => 'Rezervo termin', 'description' => 'Zgjidh mjekun dhe orën', 'url' => '/terminet/shto.php', 'icon' => 'clock', 'tone' => 'teal'],
                    ];
                }
                $icons = [
                    'patients' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="4"/><path d="M2 21v-2a7 7 0 0 1 14 0v2M19 8v6m-3-3h6"/></svg>',
                    'doctors' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3v5a6 6 0 0 0 12 0V3M4 3h4m8 0h4M12 14v2a5 5 0 0 0 5 5h2"/><circle cx="20" cy="21" r="1"/></svg>',
                    'calendar' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h3"/></svg>',
                    'clock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
                    'add' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>',
                    'users' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2 20v-1a7 7 0 0 1 14 0v1M16 5.5a3.5 3.5 0 0 1 0 6.8M18 14a5 5 0 0 1 4 5v1"/></svg>',
                    'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/></svg>',
                ];
                $menuIsActive = false;
                foreach ($menuItems as $item) {
                    if ($item['url'] === $currentRoute) {
                        $menuIsActive = true;
                        break;
                    }
                }
                $promoUrl = $authRole === 'mjek' ? '/terminet/lista.php' : '/terminet/shto.php';
                $promoTitle = $authRole === 'mjek' ? 'Orari juaj, i organizuar' : ($authRole === 'pacient' ? 'Rezervoni vizitën tuaj' : 'Kujdes i organizuar, çdo ditë');
                $promoText = $authRole === 'mjek' ? 'Kontrolloni terminet e lidhura me profilin tuaj.' : ($authRole === 'pacient' ? 'Zgjidhni mjekun që ju përshtatet.' : 'Hapni shpejt kalendarin dhe shërbimet e klinikës.');
                ?>
                <a class="nav-link<?= $activePage === 'home' ? ' active' : '' ?>" href="<?= h(APP_BASE_URL . $homeHref) ?>"<?= $activePage === 'home' ? ' aria-current="page"' : '' ?>><svg class="nav-home-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-6v-7h-4v7H4a1 1 0 0 1-1-1Z"/></svg><?= h($homeLabel) ?></a>
                <?php if ($menuItems): ?>
                    <div class="nav-dropdown<?= $menuIsActive ? ' has-active' : '' ?>">
                        <button class="nav-dropdown-toggle<?= $menuIsActive ? ' active' : '' ?>" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="clinic-services-menu">
                            <svg class="nav-module-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="8" height="7" rx="1.5"/><rect x="13" y="4" width="8" height="7" rx="1.5"/><rect x="3" y="13" width="8" height="7" rx="1.5"/><rect x="13" y="13" width="8" height="7" rx="1.5"/></svg>
                            <span>Shërbimet</span><svg class="chev" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="mega-menu" id="clinic-services-menu">
                            <div class="mega-list">
                                <?php foreach ($menuItems as $item): ?>
                                    <?php $isCurrentItem = $item['url'] === $currentRoute; ?>
                                    <a class="mega-item tone-<?= h($item['tone']) ?><?= $isCurrentItem ? ' is-current' : '' ?>" href="<?= h(APP_BASE_URL . $item['url']) ?>"<?= $isCurrentItem ? ' aria-current="page"' : '' ?>>
                                        <span class="mega-icon"><?= $icons[$item['icon']] ?></span>
                                        <span><strong><?= h($item['label']) ?></strong><small><?= h($item['description']) ?></small></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <a class="mega-promo" href="<?= h(APP_BASE_URL . $promoUrl) ?>">
                                <img src="<?= h(APP_BASE_URL) ?>/assets/img/<?= $authRole === 'pacient' ? 'doctors.jpg' : 'appointments.jpg' ?>" alt="" loading="lazy">
                                <span><strong><?= h($promoTitle) ?></strong><small><?= h($promoText) ?></small></span>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($authUser): ?>
                    <a class="account-label" href="<?= h(APP_BASE_URL) ?>/change_password.php" title="Ndrysho fjalëkalimin"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg><span><?= h($authUser['username']) ?></span></a>
                    <form class="logout-form" action="<?= h(APP_BASE_URL) ?>/logout.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <button class="logout-button" type="submit"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3m9-9h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/></svg>Dil</button>
                    </form>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="page-shell">
