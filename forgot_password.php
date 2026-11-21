<?php
require_once __DIR__ . '/includes/bootstrap.php';

$sent = false;
$formError = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $formError = 'Kërkesa nuk u verifikua. Rifreskojeni faqen dhe provoni përsëri.';
    } else {
        $sent = true;
        if ($pdo instanceof PDO && filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 190) {
            try {
                $lookup = $pdo->prepare(
                    'SELECT id_perdorues, email FROM perdorues
                     WHERE email = :email AND is_active = 1 LIMIT 1'
                );
                $lookup->execute(['email' => $email]);
                $user = $lookup->fetch();
                if ($user) {
                    $pdo->prepare('DELETE FROM password_reset_tokens WHERE id_perdorues = :user_id')
                        ->execute(['user_id' => (int) $user['id_perdorues']]);
                    $token = bin2hex(random_bytes(32));
                    $insert = $pdo->prepare(
                        'INSERT INTO password_reset_tokens (id_perdorues, token_hash, expires_at)
                         VALUES (:user_id, :token_hash, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 MINUTE))'
                    );
                    $insert->execute([
                        'user_id' => (int) $user['id_perdorues'],
                        'token_hash' => hash('sha256', $token),
                    ]);

                    $from = trim((string) (getenv('APP_MAIL_FROM') ?: ''));
                    if (filter_var($from, FILTER_VALIDATE_EMAIL)) {
                        $resetUrl = absolute_app_url('reset_password.php?token=' . rawurlencode($token));
                        $message = "Për të vendosur një fjalëkalim të ri, hapni lidhjen brenda 30 minutave:\r\n\r\n"
                            . wordwrap($resetUrl, 70, "\r\n", true)
                            . "\r\n\r\nNëse nuk e kërkuat këtë ndryshim, shpërfilleni këtë email.";
                        $headers = [
                            'From' => $from,
                            'Content-Type' => 'text/plain; charset=UTF-8',
                            'X-Mailer' => 'Klinika PHP/' . PHP_VERSION,
                        ];
                        if (!mail($user['email'], 'Rivendosja e fjalëkalimit', $message, $headers)) {
                            error_log('Dërgimi i email-it për rivendosjen e fjalëkalimit dështoi. Kontrolloni SMTP/mail në php.ini.');
                        }
                    } else {
                        error_log('APP_MAIL_FROM nuk është konfiguruar; email-i i rivendosjes nuk u dërgua.');
                    }
                }
            } catch (Throwable $exception) {
                error_log('Kërkesa për rivendosje fjalëkalimi dështoi: ' . $exception->getMessage());
            }
        }
    }
}
?>
<!doctype html>
<html lang="sq">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rivendos fjalëkalimin | Menaxhimi i Klinikave</title>
    <link rel="stylesheet" href="<?= h(APP_BASE_URL) ?>/assets/css/style.css">
</head>
<body class="auth-page">
<main class="auth-layout auth-layout-setup">
    <section class="auth-visual" aria-label="Siguria e llogarisë">
        <a class="auth-brand" href="<?= h(APP_BASE_URL) ?>/login.php"><span class="auth-brand-mark" aria-hidden="true">+</span><span><strong>Klinika</strong><small>Menaxhimi i vizitave</small></span></a>
        <div class="auth-visual-copy"><span class="auth-kicker"><span class="auth-kicker-dot"></span> SIGURI E LLOGARISË</span><h1>Riktheni qasjen në llogari.</h1><p>Do t'ju dërgojmë udhëzimet në email-in e regjistruar.</p></div>
        <div class="auth-visual-footer"><span class="auth-shield" aria-hidden="true">✓</span> Lidhja e rivendosjes skadon pas 30 minutash</div>
    </section>
    <section class="auth-panel"><div class="auth-panel-inner">
        <div class="auth-mobile-brand"><span class="auth-brand-mark" aria-hidden="true">+</span><span><strong>Klinika</strong><small>Menaxhimi i vizitave</small></span></div>
        <div class="auth-heading"><p class="eyebrow">RIVENDOSJA</p><h2>Keni harruar fjalëkalimin?</h2><p>Shkruani email-in e llogarisë dhe kontrolloni kutinë postare.</p></div>
        <?php if ($formError !== ''): ?><div class="auth-notice auth-notice-error" role="alert"><?= h($formError) ?></div><?php endif; ?>
        <?php if ($sent): ?><div class="auth-notice auth-notice-success" role="status">Nëse email-i lidhet me një llogari aktive, do të dërgohen udhëzimet e rivendosjes.</div><?php endif; ?>
        <?php if (!$pdo instanceof PDO): ?><div class="auth-notice auth-notice-error" role="alert">Databaza nuk është e disponueshme. Aktivizoni MySQL dhe provoni përsëri.</div>
        <?php else: ?>
        <form class="auth-form" action="<?= h(APP_BASE_URL) ?>/forgot_password.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <div class="auth-field"><label for="email">Email-i i llogarisë</label><input id="email" name="email" type="email" value="<?= h($email) ?>" autocomplete="email" maxlength="190" required></div>
            <button class="button button-primary auth-submit" type="submit">Dërgo lidhjen <span aria-hidden="true">→</span></button>
        </form>
        <?php endif; ?>
        <footer class="auth-footer"><a href="<?= h(APP_BASE_URL) ?>/login.php">Kthehu te kyçja</a></footer>
    </div></section>
</main>
<script src="<?= h(APP_BASE_URL) ?>/assets/js/script.js" defer></script>
</body></html>
