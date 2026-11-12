<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && valid_csrf_token($_POST['csrf_token'] ?? null)) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
    }
    session_destroy();
    header('Location: ' . APP_BASE_URL . '/login.php', true, 303);
    exit;
}

if (!empty($_SESSION['auth_user']['id'])) {
    set_flash('error', 'Kërkesa për dalje nuk u verifikua. Provoni përsëri.');
    redirect_to('/index.php');
}
redirect_to('/login.php');
