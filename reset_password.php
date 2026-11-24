<?php
require_once __DIR__ . '/includes/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$tokenIsValid = false;
$formError = '';
$resetComplete = false;
if ($pdo instanceof PDO && preg_match('/^[a-f0-9]{64}$/i', $token)) {
    try {
        $check = $pdo->prepare(
            'SELECT rt.id_reset_token FROM password_reset_tokens rt
             INNER JOIN perdorues u ON u.id_perdorues = rt.id_perdorues
             WHERE rt.token_hash = :token_hash AND rt.used_at IS NULL
               AND rt.expires_at > CURRENT_TIMESTAMP AND u.is_active = 1 LIMIT 1'
        );
        $check->execute(['token_hash' => hash('sha256', $token)]);
        $tokenIsValid = (bool) $check->fetchColumn();
    } catch (Throwable $exception) {
        error_log('Verifikimi i tokenit të rivendosjes dështoi: ' . $exception->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $formError = 'Kërkesa nuk u verifikua. Rifreskojeni lidhjen dhe provoni përsëri.';
    } elseif (($policyError = password_policy_error($password)) !== null) {
        $formError = $policyError;
    } elseif (!hash_equals($password, $confirmation)) {
        $formError = 'Fjalëkalimet nuk përputhen.';
    } elseif (!$tokenIsValid) {
        $formError = 'Lidhja e rivendosjes është e pavlefshme ose ka skaduar. Kërkoni një lidhje të re.';
    } else {
        try {
            $pdo->beginTransaction();
            $lock = $pdo->prepare(
                'SELECT rt.id_reset_token, rt.id_perdorues FROM password_reset_tokens rt
                 INNER JOIN perdorues u ON u.id_perdorues = rt.id_perdorues
                 WHERE rt.token_hash = :token_hash AND rt.used_at IS NULL
                   AND rt.expires_at > CURRENT_TIMESTAMP AND u.is_active = 1 FOR UPDATE'
            );
            $lock->execute(['token_hash' => hash('sha256', $token)]);
            $reset = $lock->fetch();
            if (!$reset) {
                $pdo->rollBack();
                $formError = 'Lidhja e rivendosjes është e pavlefshme ose ka skaduar. Kërkoni një lidhje të re.';
            } else {
                $update = $pdo->prepare(
                    'UPDATE perdorues SET password_hash = :password_hash, must_change_password = 0
                     WHERE id_perdorues = :user_id AND is_active = 1'
                );
                $update->execute([
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'user_id' => (int) $reset['id_perdorues'],
                ]);
                $consume = $pdo->prepare(
                    'UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE id_reset_token = :token_id'
                );
                $consume->execute(['token_id' => (int) $reset['id_reset_token']]);
                $deleteOther = $pdo->prepare(
                    'DELETE FROM password_reset_tokens WHERE id_perdorues = :user_id AND id_reset_token <> :token_id'
                );
                $deleteOther->execute([
                    'user_id' => (int) $reset['id_perdorues'],
                    'token_id' => (int) $reset['id_reset_token'],
                ]);
                $pdo->commit();
                $resetComplete = true;
                $tokenIsValid = false;
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Rivendosja e fjalëkalimit dështoi: ' . $exception->getMessage());
            $formError = 'Fjalëkalimi nuk u ndryshua. Provoni përsëri.';
        }
    }
}
?>
<!doctype html>
<html lang="sq">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vendos fjalëkalim të ri | Klinika</title>
    <link rel="stylesheet" href="<?= h(APP_BASE_URL) ?>/assets/css/tokens.css">
    <link rel="stylesheet" href="<?= h(APP_BASE_URL) ?>/assets/css/layout.css">
    <link rel="stylesheet" href="<?= h(APP_BASE_URL) ?>/assets/css/components.css">
    <link rel="stylesheet" href="<?= h(APP_BASE_URL) ?>/assets/css/pages.css">
</head>
<body class="auth-page">
<main class="auth-layout auth-layout-setup">
    <section class="auth-visual" aria-label="Siguria e llogarisë">
        <a class="auth-brand" href="<?= h(APP_BASE_URL) ?>/login.php"><span class="auth-brand-mark" aria-hidden="true">+</span><span><strong>Klinika</strong><small>Menaxhimi i vizitave</small></span></a>
        <div class="auth-visual-copy"><span class="auth-kicker">FJALËKALIM I RI</span><h1>Vendosni një fjalëkalim të fortë.</h1><p>Lidhja përdoret vetëm një herë dhe skadon pas 30 minutash.</p></div>
        <div class="auth-visual-footer"><span class="auth-shield" aria-hidden="true">✓</span> Fjalëkalimet ruhen me hash të sigurt</div>
    </section>
    <section class="auth-panel"><div class="auth-panel-inner">
        <div class="auth-mobile-brand"><span class="auth-brand-mark" aria-hidden="true">+</span><span><strong>Klinika</strong><small>Menaxhimi i vizitave</small></span></div>
        <div class="auth-heading"><p class="eyebrow">RIVENDOSJA</p><h2>Krijoni fjalëkalim të ri</h2><p>Pas ndryshimit, kyçuni përsëri me fjalëkalimin e ri.</p></div>
        <?php if ($formError !== ''): ?><div class="auth-notice auth-notice-error" role="alert"><?= h($formError) ?></div><?php endif; ?>
        <?php if ($resetComplete): ?>
            <div class="auth-notice auth-notice-success" role="status">Fjalëkalimi u ndryshua. Kyçuni me të dhënat e reja.</div>
            <a class="button button-primary auth-submit" href="<?= h(APP_BASE_URL) ?>/login.php">Kthehu te kyçja</a>
        <?php elseif (!$tokenIsValid): ?>
            <div class="auth-notice auth-notice-error" role="alert">Lidhja është e pavlefshme ose ka skaduar.</div>
            <a class="button button-secondary" href="<?= h(APP_BASE_URL) ?>/forgot_password.php">Kërko lidhje të re</a>
        <?php else: ?>
        <form class="auth-form" action="<?= h(APP_BASE_URL) ?>/reset_password.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="token" value="<?= h($token) ?>">
            <div class="auth-field"><label for="password">Fjalëkalimi i ri <small>(12+ karaktere, 3 lloje karakteresh)</small></label><div class="auth-input-wrap password-wrap"><span class="auth-input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="10" width="18" height="11" rx="2"/><path d="M7 10V7a5 5 0 0 1 10 0v3M12 14v3"/></svg></span><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required><button class="password-toggle" type="button" data-password-toggle="password" aria-label="Shfaq fjalëkalimin" aria-pressed="false">Shfaq</button></div></div>
            <div class="auth-field"><label for="password_confirmation">Përsëritni fjalëkalimin</label><div class="auth-input-wrap password-wrap"><span class="auth-input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="10" width="18" height="11" rx="2"/><path d="M7 10V7a5 5 0 0 1 10 0v3M12 14v3"/></svg></span><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="72" required><button class="password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Shfaq fjalëkalimin" aria-pressed="false">Shfaq</button></div></div>
            <button class="button button-primary auth-submit" type="submit">Ruaj fjalëkalimin <span aria-hidden="true">→</span></button>
        </form>
        <?php endif; ?>
        <footer class="auth-footer"><a href="<?= h(APP_BASE_URL) ?>/login.php">Kthehu te kyçja</a></footer>
    </div></section>
</main>
<script src="<?= h(APP_BASE_URL) ?>/assets/js/app.js" defer></script>
</body></html>
