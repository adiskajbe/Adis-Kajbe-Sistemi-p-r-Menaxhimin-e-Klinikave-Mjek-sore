<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Regjistro mjek';
$activePage = 'doctors';
$values = ['emri' => '', 'mbiemri' => '', 'specializimi' => '', 'telefoni' => '', 'email' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Forma ka skaduar. Rifreskojeni faqen dhe provoni përsëri.';
    }
    [$values, $fieldErrors] = valid_doctor_fields($_POST);
    $errors = array_merge($errors, $fieldErrors);
    if (!$pdo instanceof PDO) {
        $errors[] = 'Databaza nuk është e disponueshme.';
    }
    if (!$errors) {
        try {
            $statement = $pdo->prepare(
                'INSERT INTO mjeku (emri, mbiemri, specializimi, telefoni, email)
                 VALUES (:emri, :mbiemri, :specializimi, :telefoni, :email)'
            );
            $statement->execute([
                'emri' => $values['emri'],
                'mbiemri' => $values['mbiemri'],
                'specializimi' => $values['specializimi'],
                'telefoni' => $values['telefoni'],
                'email' => $values['email'] !== '' ? $values['email'] : null,
            ]);
            set_flash('success', 'Mjeku u regjistrua me sukses.');
            redirect_to('/mjeket/lista.php');
        } catch (Throwable $exception) {
            error_log('Regjistrimi i mjekut dështoi: ' . $exception->getMessage());
            $errors[] = 'Regjistrimi nuk u krye. Kontrolloni databazën dhe provoni përsëri.';
        }
    }
}
include __DIR__ . '/../includes/header.php';
?>
<section class="page-heading compact-heading"><div><p class="eyebrow">REGJISTRI</p><h1>Regjistro mjek</h1><p>Plotësoni të dhënat bazë të mjekut.</p></div></section>
<?php if (!$pdo instanceof PDO): ?><?php include __DIR__ . '/../includes/database_notice.php'; ?><?php endif; ?>
<?php if ($errors): ?><div class="notice notice-warning" role="alert"><strong>Kontrolloni të dhënat.</strong><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<section class="content-card form-card"><form method="post" action="<?= h(APP_BASE_URL) ?>/mjeket/shto.php" class="patient-form">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <div class="form-grid">
        <div class="form-field"><label for="emri">Emri <span>*</span></label><input id="emri" name="emri" maxlength="100" value="<?= h($values['emri']) ?>" required></div>
        <div class="form-field"><label for="mbiemri">Mbiemri <span>*</span></label><input id="mbiemri" name="mbiemri" maxlength="100" value="<?= h($values['mbiemri']) ?>" required></div>
        <div class="form-field"><label for="specializimi">Specializimi <span>*</span></label><input id="specializimi" name="specializimi" maxlength="150" value="<?= h($values['specializimi']) ?>" required></div>
        <div class="form-field"><label for="telefoni">Numri i telefonit <span>*</span></label><input id="telefoni" name="telefoni" maxlength="30" value="<?= h($values['telefoni']) ?>" required></div>
        <div class="form-field"><label for="email">Email <small>(opsional)</small></label><input id="email" name="email" type="email" maxlength="190" value="<?= h($values['email']) ?>"></div>
    </div>
    <div class="form-actions"><a class="button button-quiet" href="<?= h(APP_BASE_URL) ?>/mjeket/lista.php">Anulo</a><button class="button button-primary" type="submit">Ruaj mjekun</button></div>
</form></section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
