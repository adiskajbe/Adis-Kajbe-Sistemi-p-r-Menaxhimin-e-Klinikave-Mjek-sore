<?php
require_once __DIR__ . '/../config/app.php';
$pageTitle = $pageTitle ?? 'Paneli';
$activePage = $activePage ?? 'home';
$authUser = current_auth_user();
$authRole = $authUser['role'] ?? '';

$roleNames = [
    'admin' => 'Administrator',
    'mjek' => 'Mjek',
    'recepsionist' => 'Recepsionist',
    'pacient' => 'Pacient',
];

$homeHref = [
    'mjek' => '/panels/doctor.php',
    'recepsionist' => '/panels/receptionist.php',
    'pacient' => '/panels/patient.php',
][$authRole] ?? '/index.php';

$currentPath = rawurldecode(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')));
$basePrefix = rawurldecode(APP_BASE_URL) . '/';
if ($basePrefix !== '/' && strpos($currentPath, $basePrefix) === 0) {
    $currentPath = substr($currentPath, strlen($basePrefix));
} else {
    $currentPath = ltrim($currentPath, '/');
}
$currentRoute = '/' . ltrim($currentPath, '/');

$navGroups = [];
if ($authRole === 'admin') {
    $navGroups = [
        'Klinika' => [
            ['id' => 'home', 'label' => 'Analitika & Paneli', 'url' => '/index.php', 'icon' => 'dashboard'],
            ['id' => 'appointments', 'label' => 'Terminet & Kalendari', 'url' => '/terminet/lista.php', 'icon' => 'calendar'],
            ['id' => 'patients', 'label' => 'Pacientët', 'url' => '/pacientet/lista.php', 'icon' => 'patients'],
            ['id' => 'doctors', 'label' => 'Mjekët', 'url' => '/mjeket/lista.php', 'icon' => 'doctors'],
        ],
        'Kujdesi & Financat' => [
            ['id' => 'ehr', 'label' => 'Kartela Mjekësore', 'url' => '/kartela/lista.php', 'icon' => 'ehr'],
            ['id' => 'billing', 'label' => 'Faturimi & Financat', 'url' => '/faturimi/lista.php', 'icon' => 'billing'],
            ['id' => 'services', 'label' => 'Katalogu i Shërbimeve', 'url' => '/faturimi/sherbimet.php', 'icon' => 'services'],
        ],
        'Administrimi' => [
            ['id' => 'admin-users', 'label' => 'Llogaritë & Rolet', 'url' => '/admin/users.php', 'icon' => 'users'],
            ['id' => 'admin-logs', 'label' => 'Regjistri i Kyçjeve', 'url' => '/admin/login_logs.php', 'icon' => 'shield'],
        ],
    ];
} elseif ($authRole === 'mjek') {
    $navGroups = [
        'Puna e Mjekut' => [
            ['id' => 'home', 'label' => 'Paneli i Mjekut', 'url' => '/panels/doctor.php', 'icon' => 'dashboard'],
            ['id' => 'appointments', 'label' => 'Orari im', 'url' => '/terminet/lista.php', 'icon' => 'calendar'],
            ['id' => 'patients', 'label' => 'Pacientët e mi', 'url' => '/pacientet/lista.php', 'icon' => 'patients'],
            ['id' => 'ehr', 'label' => 'Kartelat Mjekësore', 'url' => '/kartela/lista.php', 'icon' => 'ehr'],
            ['id' => 'doctors', 'label' => 'Profili im', 'url' => '/mjeket/lista.php', 'icon' => 'doctors'],
        ],
    ];
} elseif ($authRole === 'recepsionist') {
    $navGroups = [
        'Recepsioni' => [
            ['id' => 'home', 'label' => 'Paneli i Recepsionit', 'url' => '/panels/receptionist.php', 'icon' => 'dashboard'],
            ['id' => 'appointments', 'label' => 'Terminet & Pritja', 'url' => '/terminet/lista.php', 'icon' => 'calendar'],
            ['id' => 'patients', 'label' => 'Pacientët', 'url' => '/pacientet/lista.php', 'icon' => 'patients'],
            ['id' => 'doctors', 'label' => 'Mjekët', 'url' => '/mjeket/lista.php', 'icon' => 'doctors'],
            ['id' => 'billing', 'label' => 'Faturimi & Arkëtimi', 'url' => '/faturimi/lista.php', 'icon' => 'billing'],
        ],
    ];
} elseif ($authRole === 'pacient') {
    $navGroups = [
        'Portali im' => [
            ['id' => 'home', 'label' => 'Përmbledhja ime', 'url' => '/panels/patient.php', 'icon' => 'dashboard'],
            ['id' => 'appointments', 'label' => 'Terminet e mia', 'url' => '/terminet/lista.php', 'icon' => 'calendar'],
            ['id' => 'ehr', 'label' => 'Historia Mjekësore', 'url' => '/kartela/lista.php', 'icon' => 'ehr'],
            ['id' => 'billing', 'label' => 'Faturat e mia', 'url' => '/faturimi/lista.php', 'icon' => 'billing'],
            ['id' => 'doctors', 'label' => 'Mjekët e Klinikës', 'url' => '/mjeket/lista.php', 'icon' => 'doctors'],
        ],
    ];
}

$icons = [
    'dashboard' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="5" rx="1.5"/><rect x="13" y="10" width="8" height="11" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/></svg>',
    'patients' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="4"/><path d="M2 21v-2a7 7 0 0 1 14 0v2M19 8v6m-3-3h6"/></svg>',
    'doctors' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3v5a6 6 0 0 0 12 0V3M4 3h4m8 0h4M12 14v2a5 5 0 0 0 5 5h2"/><circle cx="20" cy="21" r="1"/></svg>',
    'calendar' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h3"/></svg>',
    'ehr' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M9 14h6M12 11v6"/></svg>',
    'billing' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/></svg>',
    'services' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10"/></svg>',
    'users' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2 20v-1a7 7 0 0 1 14 0v1M16 5.5a3.5 3.5 0 0 1 0 6.8M18 14a5 5 0 0 1 4 5v1"/></svg>',
    'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/></svg>',
];

$unreadNotifications = 0;
if (isset($pdo) && $pdo instanceof PDO && $authUser) {
    try {
        $nStmt = $pdo->prepare('SELECT COUNT(*) FROM njoftime WHERE id_perdorues = :uid AND lexuar = 0');
        $nStmt->execute(['uid' => (int) $authUser['id']]);
        $unreadNotifications = (int) $nStmt->fetchColumn();
    } catch (Throwable $exception) {
        $unreadNotifications = 0;
    }
}
?>
<!doctype html>
<html lang="sq">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f766e">
    <title><?= h($pageTitle) ?> | Klinika · Menaxhimi i Klinikave</title>
    <link rel="stylesheet" href="<?= h(APP_BASE_URL) ?>/assets/css/tokens.css">
    <link rel="stylesheet" href="<?= h(APP_BASE_URL) ?>/assets/css/layout.css">
    <link rel="stylesheet" href="<?= h(APP_BASE_URL) ?>/assets/css/components.css">
    <link rel="stylesheet" href="<?= h(APP_BASE_URL) ?>/assets/css/pages.css">
</head>
<body class="app-body page-<?= h($activePage) ?>" data-role="<?= h($authRole) ?>">
    <div class="app-shell">
        <aside class="app-sidebar" id="app-sidebar" aria-label="Menyja kryesore">
            <div class="sidebar-head">
                <a class="brand" href="<?= h(APP_BASE_URL . $homeHref) ?>">
                    <span class="brand-mark" aria-hidden="true">+</span>
                    <span class="brand-text">Klinika</span>
                </a>
                <button class="sidebar-close" type="button" data-sidebar-close aria-label="Mbyll menynë">×</button>
            </div>
            <nav class="sidebar-nav">
                <?php foreach ($navGroups as $groupTitle => $items): ?>
                    <div class="sidebar-group">
                        <p class="sidebar-group-title"><?= h($groupTitle) ?></p>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $isCurrent = $currentRoute === $item['url']
                                || ($item['id'] === $activePage && $activePage !== 'admin')
                                || ($activePage === 'admin' && $currentRoute === $item['url']);
                            ?>
                            <a class="sidebar-link<?= $isCurrent ? ' is-active' : '' ?>" href="<?= h(APP_BASE_URL . $item['url']) ?>"<?= $isCurrent ? ' aria-current="page"' : '' ?>>
                                <span class="sidebar-icon"><?= $icons[$item['icon']] ?></span>
                                <span><?= h($item['label']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-footer">
                <a class="sidebar-user" href="<?= h(APP_BASE_URL) ?>/change_password.php" title="Ndrysho fjalëkalimin">
                    <span class="sidebar-avatar" aria-hidden="true"><?= h(strtoupper(substr((string) ($authUser['username'] ?? 'K'), 0, 1))) ?></span>
                    <span class="sidebar-user-meta">
                        <strong><?= h($authUser['username'] ?? 'Përdorues') ?></strong>
                        <small><?= h($roleNames[$authRole] ?? $authRole) ?></small>
                    </span>
                </a>
            </div>
        </aside>
        <div class="sidebar-backdrop" data-sidebar-backdrop hidden></div>

        <div class="app-main">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="topbar-menu-btn" type="button" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="false" aria-label="Hap menynë">
                        <span></span><span></span><span></span>
                    </button>
                    <div class="topbar-breadcrumb">
                        <a href="<?= h(APP_BASE_URL . $homeHref) ?>">Klinika</a>
                        <span aria-hidden="true">/</span>
                        <strong><?= h($pageTitle) ?></strong>
                    </div>
                </div>
                <div class="topbar-actions">
                    <?php if (in_array($authRole, ['admin', 'recepsionist', 'pacient'], true)): ?>
                        <a class="button button-primary button-sm" href="<?= h(APP_BASE_URL) ?>/terminet/shto.php">
                            <span aria-hidden="true">+</span> <?= $authRole === 'pacient' ? 'Rezervo termin' : 'Termin i ri' ?>
                        </a>
                    <?php elseif ($authRole === 'mjek'): ?>
                        <a class="button button-primary button-sm" href="<?= h(APP_BASE_URL) ?>/kartela/shto.php">
                            <span aria-hidden="true">+</span> Vizitë EHR
                        </a>
                    <?php endif; ?>
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="topbar-notif" title="Njoftime të palexuara"><?= h($unreadNotifications) ?> njoftime</span>
                    <?php endif; ?>
                    <?php if (!$localAuthBypass): ?>
                    <form class="logout-form" action="<?= h(APP_BASE_URL) ?>/logout.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <button class="button button-quiet button-sm" type="submit">Dil</button>
                    </form>
                    <?php endif; ?>
                </div>
            </header>
            <main class="page-shell">
                <?php if ($localAuthBypass): ?>
                <div class="notice notice-warning" role="status">Modaliteti lokal: kyçja është anashkaluar vetëm në localhost.</div>
                <?php endif; ?>
