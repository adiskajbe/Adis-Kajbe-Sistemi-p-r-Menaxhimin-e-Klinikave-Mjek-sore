<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['auth_user']['id'])) {
    redirect_to('/index.php');
}

$authReady = false;
$authTableExists = false;
$accountCount = 0;
$formError = '';
$username = '';
$email = '';
$doctorId = '';
$doctors = [];

if ($pdo instanceof PDO) {
    try {
        $accountCount = (int) $pdo->query('SELECT COUNT(*) FROM perdorues')->fetchColumn();
        $authTableExists = true;
        $pdo->query('SELECT role, is_active, must_change_password, id_mjeku, id_pacienti FROM perdorues LIMIT 0');
        $pdo->query('SELECT bucket_type FROM login_rate_limits LIMIT 0');
        $pdo->query('SELECT status FROM login_logs LIMIT 0');
        $pdo->query('SELECT token_hash FROM password_reset_tokens LIMIT 0');
        $authReady = true;
        if ($accountCount > 0) {
            redirect_to('/login.php');
        }
        $doctors = $pdo->query(
            'SELECT m.id_mjeku, m.emri, m.mbiemri, m.specializimi
             FROM mjeku m LEFT JOIN perdorues u ON u.id_mjeku = m.id_mjeku
             WHERE u.id_perdorues IS NULL ORDER BY m.emri, m.mbiemri'
        )->fetchAll();
    } catch (Throwable $exception) {
        error_log('Konfigurimi i llogarisë nuk u lexua: ' . $exception->getMessage());
        $authReady = false;
    }
}

if ($authReady && $pdo instanceof PDO && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim((string) ($_POST['username'] ?? '')));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $doctorId = trim((string) ($_POST['doctor_id'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $formError = 'Seanca e formularit skadoi. Rifreskojeni faqen dhe provoni përsëri.';
    } elseif (!preg_match('/^[a-z0-9][a-z0-9._-]{2,49}$/', $username)) {
        $formError = 'Emri i përdoruesit duhet të ketë 3–50 karaktere: shkronja, numra, pikë, vijë ose nënvizim.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $formError = 'Shkruani një adresë email të vlefshme, deri në 190 karaktere.';
    } elseif (password_policy_error($password) !== null) {
        $formError = password_policy_error($password);
    } elseif (!hash_equals($password, $confirmation)) {
        $formError = 'Fjalëkalimet nuk përputhen.';
    } else {
        try {
            $collision = $pdo->prepare(
                'SELECT COUNT(*) FROM perdorues
                 WHERE username IN (:username_a, :email_a) OR email IN (:username_b, :email_b)'
            );
            $collision->execute([
                'username_a' => $username, 'email_a' => $email,
                'username_b' => $username, 'email_b' => $email,
            ]);
            if ((int) $collision->fetchColumn() > 0) {
                $formError = 'Ky emër përdoruesi ose email është përdorur tashmë.';
            } else {
                $linkedDoctorId = null;
                if ($doctorId !== '') {
                    $validatedDoctorId = filter_var($doctorId, FILTER_VALIDATE_INT);
                    if ($validatedDoctorId === false || $validatedDoctorId < 1) {
                        $formError = 'Zgjidhni një mjek të vlefshëm ose lini fushën bosh.';
                    } else {
                        $doctorCheck = $pdo->prepare(
                            'SELECT m.id_mjeku FROM mjeku m
                             LEFT JOIN perdorues u ON u.id_mjeku = m.id_mjeku
                             WHERE m.id_mjeku = :doctor_id AND u.id_perdorues IS NULL'
                        );
                        $doctorCheck->execute(['doctor_id' => $validatedDoctorId]);
                        $linkedDoctorId = $doctorCheck->fetchColumn();
                        if ($linkedDoctorId === false) {
                            $formError = 'Mjeku i zgjedhur nuk është i disponueshëm për lidhje.';
                        }
                    }
                }

                if ($formError === '') {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $insert = $pdo->prepare(
                        'INSERT INTO perdorues
                            (username, email, password_hash, role, is_active, must_change_password, id_mjeku)
                         VALUES (:username, :email, :password_hash, :role, 1, 0, :doctor_id)'
                    );
                    $insert->execute([
                        'username' => $username,
                        'email' => $email,
                        'password_hash' => $passwordHash,
                        'role' => 'admin',
                        'doctor_id' => $linkedDoctorId === null ? null : (int) $linkedDoctorId,
                    ]);
                    set_flash('success', 'Llogaria u krijua. Tani mund të kyçeni me email-in ose emrin e përdoruesit.');
                    redirect_to('/login.php');
                }
            }
        } catch (Throwable $exception) {
            error_log('Krijimi i llogarisë fillestare dështoi: ' . $exception->getMessage());
            $formError = 'Nuk u krijua llogaria. Rifreskoni faqen dhe provoni përsëri.';
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
    <title>Konfigurimi fillestar | Klinika</title>
    <link rel="stylesheet" href="<?= h(APP_BASE_URL) ?>/assets/css/tokens.css">
    <link rel="stylesheet" href="<?= h(APP_BASE_URL) ?>/assets/css/layout.css">
    <link rel="stylesheet" href="<?= h(APP_BASE_URL) ?>/assets/css/components.css">
    <link rel="stylesheet" href="<?= h(APP_BASE_URL) ?>/assets/css/pages.css">
</head>
<body class="auth-page">
    <main class="auth-layout auth-layout-setup">
        <section class="auth-visual" aria-label="Menaxhimi i klinikës">
            <a class="auth-brand" href="<?= h(APP_BASE_URL) ?>/login.php">
                <span class="auth-brand-mark" aria-hidden="true">+</span>
                <span><strong>Klinika</strong><small>Menaxhimi i vizitave</small></span>
            </a>
            <div class="auth-visual-copy">
                <span class="auth-kicker">HAPI I PARË</span>
                <h1>Vendosni qasjen e sigurt të klinikës.</h1>
                <p>Krijoni llogarinë e administratorit për të menaxhuar pacientët, mjekët, kartelat dhe financat.</p>
            </div>
            <div class="auth-visual-footer"><span class="auth-shield" aria-hidden="true">✓</span> Konfigurimi lejohet vetëm një herë</div>
        </section>
        <section class="auth-panel">
            <div class="auth-panel-inner auth-panel-inner-setup">
                <div class="auth-mobile-brand">
                    <span class="auth-brand-mark" aria-hidden="true">+</span>
                    <span><strong>Klinika</strong><small>Menaxhimi i vizitave</small></span>
                </div>
                <div class="auth-heading">
                    <p class="eyebrow">KONFIGURIMI I PARË</p>
                    <h2>Krijoni llogarinë e administratorit</h2>
                    <p>Kjo faqe çaktivizohet automatikisht sapo të krijohet llogaria e parë.</p>
                </div>

                <?php if ($formError !== ''): ?>
                    <div class="auth-notice auth-notice-error" role="alert"><?= h($formError) ?></div>
                <?php endif; ?>

                <?php if (!$pdo instanceof PDO): ?>
                    <div class="auth-notice auth-notice-error" role="alert"><?= h($databaseError ?: 'Databaza nuk është e disponueshme.') ?></div>
                    <p class="auth-help">Aktivizoni MySQL në XAMPP dhe kontrolloni <code>config/database.php</code>.</p>
                <?php elseif (!$authReady): ?>
                    <div class="auth-notice auth-notice-error" role="alert"><?= $authTableExists ? 'Struktura e llogarive ose lidhja me pacientët nuk është përditësuar.' : 'Tabela e llogarive nuk është krijuar ende.' ?></div>
                    <p class="auth-help">Në phpMyAdmin, zgjidhni <strong>klinika_db</strong> dhe ekzekutoni skriptet në <code>database/</code>.</p>
                <?php else: ?>
                    <form class="auth-form setup-form" action="<?= h(APP_BASE_URL) ?>/setup.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <div class="auth-field">
                            <label for="username">Emri i përdoruesit</label>
                            <div class="auth-input-wrap">
                                <span class="auth-input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/></svg></span>
                                <input id="username" name="username" type="text" value="<?= h($username) ?>" placeholder="p.sh. administratori" autocomplete="username" minlength="3" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9._-]{2,49}" required autofocus>
                            </div>
                            <small class="auth-field-hint">3–50 karaktere; shkronja, numra, pikë, vijë ose nënvizim.</small>
                        </div>
                        <div class="auth-field">
                            <label for="email">Email-i i llogarisë</label>
                            <div class="auth-input-wrap">
                                <span class="auth-input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg></span>
                                <input id="email" name="email" type="email" value="<?= h($email) ?>" placeholder="emri@klinika.al" autocomplete="email" maxlength="190" required>
                            </div>
                        </div>
                        <div class="auth-field">
                            <label for="doctor_id">Lidhja me mjekun <span class="auth-optional">(opsionale)</span></label>
                            <select id="doctor_id" name="doctor_id" class="auth-select">
                                <option value="">Llogari e përgjithshme e klinikës</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?= h($doctor['id_mjeku']) ?>" <?= $doctorId === (string) $doctor['id_mjeku'] ? 'selected' : '' ?>><?= h($doctor['emri'] . ' ' . $doctor['mbiemri'] . ' · ' . $doctor['specializimi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="auth-field">
                            <label for="password">Fjalëkalimi <span class="auth-optional">(12+ karaktere, 3 lloje)</span></label>
                            <div class="auth-input-wrap password-wrap">
                                <span class="auth-input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="10" width="18" height="11" rx="2"/><path d="M7 10V7a5 5 0 0 1 10 0v3M12 14v3"/></svg></span>
                                <input id="password" name="password" type="password" placeholder="Krijoni fjalëkalimin" autocomplete="new-password" minlength="12" maxlength="72" required>
                                <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Shfaq fjalëkalimin" aria-pressed="false">Shfaq</button>
                            </div>
                        </div>
                        <div class="auth-field">
                            <label for="password_confirmation">Përsëritni fjalëkalimin</label>
                            <div class="auth-input-wrap password-wrap">
                                <span class="auth-input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="10" width="18" height="11" rx="2"/><path d="M7 10V7a5 5 0 0 1 10 0v3M12 14v3"/></svg></span>
                                <input id="password_confirmation" name="password_confirmation" type="password" placeholder="Shkruajeni sërish fjalëkalimin" autocomplete="new-password" minlength="12" maxlength="72" required>
                                <button class="password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Shfaq fjalëkalimin" aria-pressed="false">Shfaq</button>
                            </div>
                        </div>
                        <button class="button button-primary auth-submit" type="submit">Krijo llogarinë <span aria-hidden="true">→</span></button>
                    </form>
                <?php endif; ?>
                <footer class="auth-footer"><a href="<?= h(APP_BASE_URL) ?>/login.php">Kthehu te faqja e kyçjes</a></footer>
            </div>
        </section>
    </main>
    <script src="<?= h(APP_BASE_URL) ?>/assets/js/app.js" defer></script>
</body>
</html>
