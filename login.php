<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['auth_user']['id'])) {
    redirect_to('/index.php');
}

$authReady = false;
$authTableExists = false;
$accountCount = 0;
$formError = '';
$identity = '';
$flash = take_flash();

if ($pdo instanceof PDO) {
    try {
        $accountCount = (int) $pdo->query('SELECT COUNT(*) FROM perdorues')->fetchColumn();
        $authTableExists = true;
        $pdo->query('SELECT role, is_active, must_change_password, id_mjeku, id_pacienti FROM perdorues LIMIT 0');
        $pdo->query('SELECT bucket_type FROM login_rate_limits LIMIT 0');
        $pdo->query('SELECT status FROM login_logs LIMIT 0');
        $pdo->query('SELECT token_hash FROM password_reset_tokens LIMIT 0');
        $authReady = true;
    } catch (Throwable $exception) {
        error_log('Tabela e autentifikimit nuk u lexua: ' . $exception->getMessage());
    }
}

if ($authReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = strtolower(trim((string) ($_POST['identity'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $ipAddress = auth_client_ip();

    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        if ($pdo instanceof PDO) {
            try {
                log_login_attempt($pdo, null, $identity, $ipAddress, 'failure');
            } catch (Throwable $exception) {
                error_log('Tentativa me token CSRF të pavlefshëm nuk u auditua: ' . $exception->getMessage());
            }
        }
        $formError = 'Email-i ose fjalëkalimi është i pasaktë.';
    } else {
        try {
            if (!$pdo instanceof PDO) {
                throw new RuntimeException('Databaza nuk është e disponueshme.');
            }
            $pdo->exec("DELETE FROM login_rate_limits WHERE updated_at < CURRENT_TIMESTAMP - INTERVAL 1 DAY AND (locked_until IS NULL OR locked_until < CURRENT_TIMESTAMP)");
            $isLimited = login_is_rate_limited($pdo, $identity, $ipAddress);
            $user = null;
            if (!$isLimited && $identity !== '' && $password !== '') {
                $statement = $pdo->prepare(
                    'SELECT id_perdorues, username, email, password_hash, id_mjeku, id_pacienti, role, is_active, must_change_password
                     FROM perdorues WHERE username = :username_identity OR email = :email_identity LIMIT 1'
                );
                $statement->execute([
                    'username_identity' => $identity,
                    'email_identity' => $identity,
                ]);
                $user = $statement->fetch();
            }
            $passwordIsValid = $user
                ? password_verify($password, $user['password_hash'])
                : password_verify($password, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');

            if (!$isLimited && $identity !== '' && $password !== '' && $user
                && (int) $user['is_active'] === 1 && $passwordIsValid) {
                log_login_attempt($pdo, (int) $user['id_perdorues'], $user['email'], $ipAddress, 'success');
                clear_login_rate_limits($pdo, $identity, $ipAddress);
                session_regenerate_id(true);
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
                $_SESSION['last_activity'] = time();
                unset($_SESSION['csrf_token']);
                unset($_SESSION['intended_url']);
                if (!empty($user['must_change_password'])) {
                    redirect_to('/change_password.php');
                }
                $dashboards = [
                    'admin' => '/index.php',
                    'mjek' => '/panels/doctor.php',
                    'recepsionist' => '/panels/receptionist.php',
                    'pacient' => '/panels/patient.php',
                ];
                redirect_to($dashboards[$user['role']] ?? '/index.php');
            }
            log_login_attempt($pdo, $user ? (int) $user['id_perdorues'] : null, $user['email'] ?? $identity, $ipAddress, 'failure');
            if (!$isLimited) {
                record_failed_login($pdo, $identity, $ipAddress);
            }
            $formError = 'Email-i ose fjalëkalimi është i pasaktë.';
        } catch (Throwable $exception) {
            error_log('Kyçja dështoi gjatë kërkesës: ' . $exception->getMessage());
            $formError = 'Kyçja nuk u krye. Provoni përsëri më vonë.';
        }
    }
}
?>
<!doctype html>
<html lang="sq">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f766e">
    <title>Kyçu | Klinika · Menaxhimi i Klinikave</title>
    <link rel="stylesheet" href="<?= h(APP_BASE_URL) ?>/assets/css/tokens.css">
    <link rel="stylesheet" href="<?= h(APP_BASE_URL) ?>/assets/css/layout.css">
    <link rel="stylesheet" href="<?= h(APP_BASE_URL) ?>/assets/css/components.css">
    <link rel="stylesheet" href="<?= h(APP_BASE_URL) ?>/assets/css/pages.css">
</head>
<body class="auth-page">
    <main class="auth-layout">
        <section class="auth-visual" aria-label="Menaxhimi i klinikës">
            <a class="auth-brand" href="<?= h(APP_BASE_URL) ?>/login.php">
                <span class="auth-brand-mark" aria-hidden="true">+</span>
                <span><strong>Klinika</strong><small>Menaxhimi i vizitave dhe kartelave</small></span>
            </a>
            <div class="auth-visual-copy">
                <span class="auth-kicker">KLINIKË E DIGJITALIZUAR</span>
                <h1>Gjithçka për kujdesin ndaj pacientit, në një vend.</h1>
                <p>Termine, kartela mjekësore EHR, faturime dhe portali i pacientëve të unifikuara në një hapësirë pune.</p>
            </div>
            <div class="auth-visual-footer"><span class="auth-shield" aria-hidden="true">✓</span> Qasje e mbrojtur me sesione dhe role të ndara</div>
        </section>

        <section class="auth-panel">
            <div class="auth-panel-inner">
                <div class="auth-mobile-brand">
                    <span class="auth-brand-mark" aria-hidden="true">+</span>
                    <span><strong>Klinika</strong><small>Menaxhimi i vizitave</small></span>
                </div>
                <div class="auth-heading">
                    <p class="eyebrow">MIRË SE U KTHYET</p>
                    <h2>Kyçuni në llogarinë tuaj</h2>
                    <p>Shkruani email-in ose emrin e përdoruesit për të hyrë në panelin tuaj.</p>
                </div>

                <?php if ($flash): ?>
                    <div class="auth-notice auth-notice-success" role="status"><?= h($flash['message']) ?></div>
                <?php endif; ?>
                <?php if ($formError !== ''): ?>
                    <div class="auth-notice auth-notice-error" role="alert"><?= h($formError) ?></div>
                <?php endif; ?>

                <?php if (!$pdo instanceof PDO): ?>
                    <div class="auth-notice auth-notice-error" role="alert"><?= h($databaseError ?: 'Databaza nuk është e disponueshme.') ?></div>
                    <p class="auth-help">Aktivizoni MySQL në XAMPP dhe kontrolloni konfigurimin te <code>config/database.php</code>.</p>
                <?php elseif (!$authReady): ?>
                    <div class="auth-notice auth-notice-error" role="alert"><?= $authTableExists ? 'Struktura e llogarive ose lidhja me pacientët nuk është përditësuar.' : 'Tabela e llogarive nuk është krijuar ende.' ?></div>
                    <p class="auth-help">Në phpMyAdmin, hapni databazën <strong>klinika_db</strong> dhe ekzekutoni migrimet <code>002_create_perdorues.sql</code>, <code>003_auth_security.sql</code>, <code>004_add_patient_accounts.sql</code> dhe <code>005_klinika_im_modules.sql</code>.</p>
                <?php elseif ($accountCount === 0): ?>
                    <div class="auth-empty-state">
                        <span class="auth-empty-icon" aria-hidden="true">+</span>
                        <h3>Krijoni llogarinë e parë</h3>
                        <p>Konfigurimi fillestar bëhet një herë. Pas krijimit, kjo faqe do të shërbejë për kyçje.</p>
                        <a class="button button-primary auth-submit" href="<?= h(APP_BASE_URL) ?>/setup.php">Nis konfigurimin <span aria-hidden="true">→</span></a>
                    </div>
                <?php else: ?>
                    <form class="auth-form" action="<?= h(APP_BASE_URL) ?>/login.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <div class="auth-field">
                            <label for="identity">Email ose emri i përdoruesit</label>
                            <div class="auth-input-wrap">
                                <span class="auth-input-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/></svg>
                                </span>
                                <input id="identity" name="identity" type="text" value="<?= h($identity) ?>" placeholder="p.sh. emri@klinika.al" autocomplete="username" maxlength="190" required autofocus>
                            </div>
                        </div>
                        <div class="auth-field">
                            <div class="auth-label-row"><label for="password">Fjalëkalimi</label><a class="text-link" href="<?= h(APP_BASE_URL) ?>/forgot_password.php">Keni harruar fjalëkalimin?</a></div>
                            <div class="auth-input-wrap password-wrap">
                                <span class="auth-input-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><rect x="3" y="10" width="18" height="11" rx="2"/><path d="M7 10V7a5 5 0 0 1 10 0v3M12 14v3"/></svg>
                                </span>
                                <input id="password" name="password" type="password" placeholder="Shkruani fjalëkalimin" autocomplete="current-password" required>
                                <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Shfaq fjalëkalimin" aria-pressed="false">Shfaq</button>
                            </div>
                        </div>
                        <button class="button button-primary auth-submit" type="submit">Kyçu në sistem <span aria-hidden="true">→</span></button>
                        <p class="auth-security-note">Lidhja juaj mbrohet me sesion të sigurt dhe mbrojtje CSRF.</p>
                    </form>
                <?php endif; ?>

                <footer class="auth-footer">© <?= date('Y') ?> Klinika · Menaxhimi i Klinikave Mjekësore</footer>
            </div>
        </section>
    </main>
    <script src="<?= h(APP_BASE_URL) ?>/assets/js/app.js" defer></script>
</body>
</html>
