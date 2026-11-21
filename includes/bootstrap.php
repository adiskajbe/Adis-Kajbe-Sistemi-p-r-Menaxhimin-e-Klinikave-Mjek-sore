<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => APP_BASE_URL !== '' ? APP_BASE_URL . '/' : '/',
        'secure' => !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

$pdo = null;
$databaseError = null;
try {
    require __DIR__ . '/../config/database.php';
} catch (Throwable $exception) {
    $databaseError = 'Kontrolloni që MySQL është aktiv dhe databaza klinika_db është e disponueshme.';
}

$publicPages = ['login.php', 'setup.php', 'forgot_password.php', 'reset_password.php', 'logout.php'];
$scriptPath = rawurldecode(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')));
$basePrefix = rawurldecode(APP_BASE_URL) . '/';
if ($basePrefix !== '/' && strpos($scriptPath, $basePrefix) === 0) {
    $scriptPath = substr($scriptPath, strlen($basePrefix));
} else {
    $scriptPath = ltrim($scriptPath, '/');
}
$scriptPath = ltrim($scriptPath, '/');
$isPublicPage = in_array($scriptPath, $publicPages, true);

if (!$isPublicPage && !empty($_SESSION['auth_user']['id'])) {
    if (!$pdo instanceof PDO) {
        $_SESSION = [];
        session_regenerate_id(true);
    } else {
        try {
            $statement = $pdo->prepare(
                'SELECT id_perdorues, username, email, role, is_active, must_change_password, id_mjeku, id_pacienti
                 FROM perdorues WHERE id_perdorues = :id LIMIT 1'
            );
            $statement->execute(['id' => (int) $_SESSION['auth_user']['id']]);
            $user = $statement->fetch();
            if (!$user || (int) $user['is_active'] !== 1) {
                $_SESSION = [];
                session_regenerate_id(true);
                set_flash('error', 'Kjo llogari nuk është aktive. Kontaktoni administratorin.');
            } else {
                $_SESSION['auth_user'] = [
                    'id' => (int) $user['id_perdorues'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'is_active' => (int) $user['is_active'],
                    'must_change_password' => (int) $user['must_change_password'],
                    'doctor_id' => $user['id_mjeku'] !== null ? (int) $user['id_mjeku'] : null,
                    'patient_id' => $user['id_pacienti'] !== null ? (int) $user['id_pacienti'] : null,
                ];

                $now = time();
                $lastActivity = (int) ($_SESSION['last_activity'] ?? $now);
                if ($now - $lastActivity >= 1200) {
                    $_SESSION = [];
                    session_regenerate_id(true);
                    set_flash('warning', 'Seanca përfundoi pas 20 minutash pa aktivitet. Kyçuni përsëri.');
                } else {
                    $_SESSION['last_activity'] = $now;
                }
            }
        } catch (Throwable $exception) {
            error_log('Verifikimi i llogarisë dështoi: ' . $exception->getMessage());
            $_SESSION = [];
            session_regenerate_id(true);
        }
    }
}

if ($isPublicPage) {
    return;
}

if (empty($_SESSION['auth_user']['id'])) {
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/index.php');
    $requestPath = parse_url($requestUri, PHP_URL_PATH) ?: '/index.php';
    if (APP_BASE_URL !== '' && strpos($requestPath, APP_BASE_URL . '/') === 0) {
        $requestPath = substr($requestPath, strlen(APP_BASE_URL));
    }
    if ($requestPath === '' || $requestPath[0] !== '/' || strpos($requestPath, '//') === 0) {
        $requestPath = '/index.php';
    }
    $query = parse_url($requestUri, PHP_URL_QUERY);
    if (!in_array($requestPath, ['/login.php', '/setup.php', '/forgot_password.php', '/reset_password.php', '/logout.php'], true)) {
        $_SESSION['intended_url'] = $requestPath . ($query ? '?' . $query : '');
    }
    if (strpos($scriptPath, 'api/') === 0) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Duhet të kyçeni për të vazhduar.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . APP_BASE_URL . '/login.php', true, 303);
    exit;
}

$role = (string) ($_SESSION['auth_user']['role'] ?? '');
if (!empty($_SESSION['auth_user']['must_change_password']) && !in_array($scriptPath, ['change_password.php', 'logout.php'], true)) {
    redirect_to('/change_password.php');
}

$allowedRoutes = [
    'admin' => [],
    'mjek' => [
        'index.php', 'panels/doctor.php', 'pacientet/lista.php', 'mjeket/lista.php',
        'terminet/lista.php', 'api/terminet.php', 'change_password.php', 'logout.php',
    ],
    'recepsionist' => [
        'index.php', 'panels/receptionist.php', 'pacientet/lista.php', 'pacientet/shto.php',
        'pacientet/ndrysho.php', 'pacientet/fshi.php', 'mjeket/lista.php', 'terminet/lista.php',
        'terminet/shto.php', 'terminet/anulo.php', 'change_password.php', 'logout.php',
    ],
    'pacient' => [
        'index.php', 'panels/patient.php', 'mjeket/lista.php', 'terminet/lista.php',
        'terminet/shto.php', 'change_password.php', 'logout.php',
    ],
];

if (!array_key_exists($role, $allowedRoutes) || ($role !== 'admin' && !in_array($scriptPath, $allowedRoutes[$role], true))) {
    http_response_code(403);
    if (strpos($scriptPath, 'api/') === 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Nuk keni leje për këtë modul.'], JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="sq"><meta charset="utf-8"><title>Qasje e ndaluar</title>'
            . '<body><main><h1>Qasje e ndaluar</h1><p>Nuk keni leje për këtë modul.</p>'
            . '<a href="' . h(APP_BASE_URL) . '/index.php">Kthehu te paneli</a></main></body></html>';
    }
    exit;
}
