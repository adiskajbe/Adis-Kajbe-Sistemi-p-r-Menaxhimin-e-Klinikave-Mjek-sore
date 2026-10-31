<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Ndrysho mjek';
$activePage = 'doctors';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$errors = [];
$doctor = null;

if (!$id || $id < 1) {
    http_response_code(400);
    $errors[] = 'ID-ja e mjekut nuk është e vlefshme.';
} elseif ($pdo instanceof PDO) {
    try {
        $statement = $pdo->prepare('SELECT id_mjeku, emri, mbiemri, specializimi, telefoni, email FROM mjeku WHERE id_mjeku = :id');
        $statement->execute(['id' => $id]);
        $doctor = $statement->fetch();
        if (!$doctor) {
            http_response_code(404);
            $errors[] = 'Mjeku nuk u gjet.';
        }
    } catch (Throwable $exception) {
        error_log('Leximi i mjekut dështoi: ' . $exception->getMessage());
        $databaseError = 'Nuk u arrit leximi i mjekut.';
        $pdo = null;
    }
}
$values = $doctor ?: ['emri' => '', 'mbiemri' => '', 'specializimi' => '', 'telefoni' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $doctor) {
    $submittedId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if ((int) $submittedId !== (int) $id || !valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Forma nuk është e vlefshme. Rifreskojeni faqen dhe provoni përsëri.';
    }
    [$values, $fieldErrors] = valid_doctor_fields($_POST);
    $errors = array_merge($errors, $fieldErrors);
    if (!$pdo instanceof PDO) {
        $errors[] = 'Databaza nuk është e disponueshme.';
    }
    if (!$errors) {
        try {
            $statement = $pdo->prepare(
                'UPDATE mjeku SET emri = :emri, mbiemri = :mbiemri, specializimi = :specializimi,
                 telefoni = :telefoni, email = :email WHERE id_mjeku = :id'
            );
            $statement->execute([
                'emri' => $values['emri'],
                'mbiemri' => $values['mbiemri'],
                'specializimi' => $values['specializimi'],
                'telefoni' => $values['telefoni'],
                'email' => $values['email'] !== '' ? $values['email'] : null,
                'id' => $id,
            ]);
            set_flash('success', 'Të dhënat e mjekut u përditësuan.');
            redirect_to('/mjeket/lista.php');
        } catch (Throwable $exception) {
            error_log('Përditësimi i mjekut dështoi: ' . $exception->getMessage());
            $errors[] = 'Ndryshimet nuk u ruajtën. Provoni përsëri.';
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>
<section class="page-heading compact-heading"><div><p class="eyebrow">REGJISTRI</p><h1>Ndrysho të dhënat e mjekut</h1><p>Përditësoni të dhënat e mjekut të zgjedhur.</p></div></section>
<?php if (!$pdo instanceof PDO): ?><?php include __DIR__ . '/../includes/database_notice.php'; ?><?php endif; ?>
<?php if ($errors): ?><div class="notice notice-warning" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($doctor): ?><section class="content-card form-card"><form method="post" action="<?= h(APP_BASE_URL) ?>/mjeket/ndrysho.php?id=<?= h($id) ?>" class="patient-form">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= h($id) ?>">
    <div class="form-grid">
        <div class="form-field"><label for="emri">Emri <span>*</span></label><input id="emri" name="emri" maxlength="100" value="<?= h($values['emri']) ?>" required></div>
        <div class="form-field"><label for="mbiemri">Mbiemri <span>*</span></label><input id="mbiemri" name="mbiemri" maxlength="100" value="<?= h($values['mbiemri']) ?>" required></div>
        <div class="form-field"><label for="specializimi">Specializimi <span>*</span></label><input id="specializimi" name="specializimi" maxlength="150" value="<?= h($values['specializimi']) ?>" required></div>
        <div class="form-field"><label for="telefoni">Numri i telefonit <span>*</span></label><input id="telefoni" name="telefoni" maxlength="30" value="<?= h($values['telefoni']) ?>" required></div>
        <div class="form-field"><label for="email">Email <small>(opsional)</small></label><input id="email" name="email" type="email" maxlength="190" value="<?= h($values['email']) ?>"></div>
    </div>
    <div class="form-actions"><a class="button button-quiet" href="<?= h(APP_BASE_URL) ?>/mjeket/lista.php">Anulo</a><button class="button button-primary" type="submit">Ruaj ndryshimet</button></div>
</form></section><?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
