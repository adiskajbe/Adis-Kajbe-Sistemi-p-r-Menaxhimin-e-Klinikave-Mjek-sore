<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Regjistro pacient';
$activePage = 'patients';
$values = ['emri' => '', 'mbiemri' => '', 'telefoni' => '', 'email' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = [
        'emri' => (string) ($_POST['emri'] ?? ''),
        'mbiemri' => (string) ($_POST['mbiemri'] ?? ''),
        'telefoni' => (string) ($_POST['telefoni'] ?? ''),
        'email' => (string) ($_POST['email'] ?? ''),
    ];

    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Forma ka skaduar. Rifreskojeni faqen dhe provoni përsëri.';
    }
    [$values, $fieldErrors] = valid_patient_fields($values);
    $errors = array_merge($errors, $fieldErrors);

    if (!$pdo instanceof PDO) {
        $errors[] = 'Databaza nuk është e disponueshme.';
    }
    if ($pdo instanceof PDO && !$errors) {
        try {
            $statement = $pdo->prepare(
                'INSERT INTO pacienti (emri, mbiemri, telefoni, email)
                 VALUES (:emri, :mbiemri, :telefoni, :email)'
            );
            $statement->execute([
                'emri' => $values['emri'],
                'mbiemri' => $values['mbiemri'],
                'telefoni' => $values['telefoni'],
                'email' => $values['email'] !== '' ? $values['email'] : null,
            ]);
            set_flash('success', 'Pacienti u regjistrua me sukses.');
            redirect_to('/pacientet/lista.php');
        } catch (Throwable $exception) {
            error_log('Regjistrimi i pacientit dështoi: ' . $exception->getMessage());
            $errors[] = 'Regjistrimi nuk u krye. Kontrolloni lidhjen me databazën dhe provoni përsëri.';
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>
<section class="page-heading compact-heading">
    <div><p class="eyebrow">REGJISTRI</p><h1>Regjistro pacient</h1><p>Plotësoni të dhënat bazë. Fushat me yll janë të detyrueshme.</p></div>
</section>
<?php if (!$pdo instanceof PDO): ?><?php include __DIR__ . '/../includes/database_notice.php'; ?><?php endif; ?>
<?php if ($errors): ?>
    <div class="notice notice-warning" role="alert"><strong>Kontrolloni të dhënat e formës.</strong><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<section class="content-card form-card">
    <form method="post" action="<?= h(APP_BASE_URL) ?>/pacientet/shto.php" class="patient-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <div class="form-grid">
            <div class="form-field"><label for="emri">Emri <span>*</span></label><input id="emri" name="emri" maxlength="100" autocomplete="given-name" value="<?= h($values['emri']) ?>" required></div>
            <div class="form-field"><label for="mbiemri">Mbiemri <span>*</span></label><input id="mbiemri" name="mbiemri" maxlength="100" autocomplete="family-name" value="<?= h($values['mbiemri']) ?>" required></div>
            <div class="form-field"><label for="telefoni">Numri i telefonit <span>*</span></label><input id="telefoni" name="telefoni" maxlength="30" autocomplete="tel" value="<?= h($values['telefoni']) ?>" required></div>
            <div class="form-field"><label for="email">Email <small>(opsionale)</small></label><input id="email" name="email" type="email" maxlength="190" autocomplete="email" value="<?= h($values['email']) ?>"></div>
        </div>
        <div class="form-actions"><a class="button button-quiet" href="<?= h(APP_BASE_URL) ?>/pacientet/lista.php">Anulo</a><button class="button button-primary" type="submit">Ruaj pacientin</button></div>
    </form>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>