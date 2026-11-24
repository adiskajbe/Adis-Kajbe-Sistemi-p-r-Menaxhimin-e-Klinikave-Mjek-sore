<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = current_auth_user();
$formError = '';

if ($pdo instanceof PDO && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    try {
        if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
            $formError = 'Kërkesa nuk u verifikua. Rifreskojeni faqen dhe provoni përsëri.';
        } elseif (($policyError = password_policy_error($newPassword)) !== null) {
            $formError = $policyError;
        } elseif (!hash_equals($newPassword, $confirmation)) {
            $formError = 'Fjalëkalimet e reja nuk përputhen.';
        } else {
            $lookup = $pdo->prepare('SELECT password_hash FROM perdorues WHERE id_perdorues = :id AND is_active = 1');
            $lookup->execute(['id' => (int) $user['id']]);
            $hash = $lookup->fetchColumn();
            if (!$hash || !password_verify($currentPassword, (string) $hash)) {
                $formError = 'Fjalëkalimi aktual nuk është i saktë.';
            } elseif (password_verify($newPassword, (string) $hash)) {
                $formError = 'Zgjidhni një fjalëkalim të ndryshëm nga ai aktual.';
            } else {
                $pdo->beginTransaction();
                $update = $pdo->prepare(
                    'UPDATE perdorues SET password_hash = :password_hash, must_change_password = 0
                     WHERE id_perdorues = :id AND is_active = 1'
                );
                $update->execute([
                    'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                    'id' => (int) $user['id'],
                ]);
                $pdo->prepare('DELETE FROM password_reset_tokens WHERE id_perdorues = :id')
                    ->execute(['id' => (int) $user['id']]);
                $pdo->commit();
                $_SESSION['auth_user']['must_change_password'] = 0;
                $_SESSION['last_activity'] = time();
                session_regenerate_id(true);
                set_flash('success', 'Fjalëkalimi u ndryshua me sukses.');
                $dashboards = [
                    'admin' => '/index.php',
                    'mjek' => '/panels/doctor.php',
                    'recepsionist' => '/panels/receptionist.php',
                    'pacient' => '/panels/patient.php',
                ];
                redirect_to($dashboards[$user['role']] ?? '/index.php');
            }
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Ndërrimi i fjalëkalimit dështoi: ' . $exception->getMessage());
        $formError = 'Fjalëkalimi nuk u ndryshua. Provoni përsëri.';
    }
}
?>
<!doctype html>
<html lang="sq">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ndrysho fjalëkalimin | Klinika</title>
    <link rel="stylesheet" href="<?= h(APP_BASE_URL) ?>/assets/css/tokens.css">
    <link rel="stylesheet" href="<?= h(APP_BASE_URL) ?>/assets/css/layout.css">
    <link rel="stylesheet" href="<?= h(APP_BASE_URL) ?>/assets/css/components.css">
    <link rel="stylesheet" href="<?= h(APP_BASE_URL) ?>/assets/css/pages.css">
</head>
<body class="auth-page">
<main class="auth-layout auth-layout-setup">
    <section class="auth-visual" aria-label="Siguria e llogarisë">
        <a class="auth-brand" href="<?= h(APP_BASE_URL) ?>/index.php"><span class="auth-brand-mark" aria-hidden="true">+</span><span><strong>Klinika</strong><small>Menaxhimi i vizitave</small></span></a>
        <div class="auth-visual-copy"><span class="auth-kicker">SIGURIA E LLOGARISË</span><h1>Mbani qasjen tuaj të sigurt.</h1><p>Fjalëkalimi i ri do të përdoret në kyçjen e ardhshme.</p></div>
        <div class="auth-visual-footer"><span class="auth-shield" aria-hidden="true">✓</span> Fjalëkalimi ruhet me hash të fortë</div>
    </section>
    <section class="auth-panel"><div class="auth-panel-inner">
        <div class="auth-mobile-brand"><span class="auth-brand-mark" aria-hidden="true">+</span><span><strong>Klinika</strong><small>Menaxhimi i vizitave</small></span></div>
        <div class="auth-heading"><p class="eyebrow">SIGURIA E LLOGARISË</p><h2>Ndryshoni fjalëkalimin</h2><p>Përdorni të paktën 12 karaktere dhe 3 lloje karakteresh.</p></div>
        <?php if ($formError !== ''): ?><div class="auth-notice auth-notice-error" role="alert"><?= h($formError) ?></div><?php endif; ?>
        <?php if (!$pdo instanceof PDO): ?><div class="auth-notice auth-notice-error" role="alert">Databaza nuk është e disponueshme.</div><?php else: ?>
        <form class="auth-form" action="<?= h(APP_BASE_URL) ?>/change_password.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <div class="auth-field"><label for="current_password">Fjalëkalimi aktual</label><div class="auth-input-wrap password-wrap"><span class="auth-input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="10" width="18" height="11" rx="2"/><path d="M7 10V7a5 5 0 0 1 10 0v3M12 14v3"/></svg></span><input id="current_password" name="current_password" type="password" autocomplete="current-password" required><button class="password-toggle" type="button" data-password-toggle="current_password" aria-label="Shfaq fjalëkalimin" aria-pressed="false">Shfaq</button></div></div>
            <div class="auth-field"><label for="new_password">Fjalëkalimi i ri</label><div class="auth-input-wrap password-wrap"><span class="auth-input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="10" width="18" height="11" rx="2"/><path d="M7 10V7a5 5 0 0 1 10 0v3M12 14v3"/></svg></span><input id="new_password" name="new_password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required><button class="password-toggle" type="button" data-password-toggle="new_password" aria-label="Shfaq fjalëkalimin" aria-pressed="false">Shfaq</button></div></div>
            <div class="auth-field"><label for="password_confirmation">Përsëritni fjalëkalimin e ri</label><div class="auth-input-wrap password-wrap"><span class="auth-input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="10" width="18" height="11" rx="2"/><path d="M7 10V7a5 5 0 0 1 10 0v3M12 14v3"/></svg></span><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="72" required><button class="password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Shfaq fjalëkalimin" aria-pressed="false">Shfaq</button></div></div>
            <button class="button button-primary auth-submit" type="submit">Ruaj fjalëkalimin <span aria-hidden="true">→</span></button>
        </form>
        <?php endif; ?>
        <footer class="auth-footer">
            <?php if (empty($user['must_change_password'])): ?><a href="<?= h(APP_BASE_URL) ?>/index.php">Kthehu te paneli</a><span>·</span><?php endif; ?>
            <form class="inline-form" action="<?= h(APP_BASE_URL) ?>/logout.php" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button class="link-button" type="submit">Dil nga llogaria</button></form>
        </footer>
    </div></section>
</main>
<script src="<?= h(APP_BASE_URL) ?>/assets/js/app.js" defer></script>
</body></html>
