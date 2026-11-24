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
        $statement = $pdo->prepare('SELECT * FROM mjeku WHERE id_mjeku = :id');
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
$values = $doctor ?: ['emri' => '', 'mbiemri' => '', 'specializimi' => '', 'telefoni' => '', 'email' => '', 'nr_dhome' => '', 'orari_punes' => '', 'tarifa_bazike' => '30.00'];

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
    if (!$errors && $pdo instanceof PDO) {
        try {
            $statement = $pdo->prepare(
                'UPDATE mjeku SET emri = :emri, mbiemri = :mbiemri, specializimi = :specializimi,
                 telefoni = :telefoni, email = :email, nr_dhome = :nr_dhome, orari_punes = :orari_punes, tarifa_bazike = :tarifa_bazike
                 WHERE id_mjeku = :id'
            );
            $statement->execute([
                'emri' => $values['emri'],
                'mbiemri' => $values['mbiemri'],
                'specializimi' => $values['specializimi'],
                'telefoni' => $values['telefoni'],
                'email' => $values['email'] !== '' ? $values['email'] : null,
                'nr_dhome' => $values['nr_dhome'] !== '' ? $values['nr_dhome'] : null,
                'orari_punes' => $values['orari_punes'] !== '' ? $values['orari_punes'] : null,
                'tarifa_bazike' => $values['tarifa_bazike'],
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
<section class="page-head"><div><p class="eyebrow">EKIPI MJEKËSOR</p><h1>Ndrysho të dhënat e mjekut</h1><p>Përditësoni të dhënat, kabinetin dhe tarifën e mjekut.</p></div></section>
<?php if (!$pdo instanceof PDO): ?><?php include __DIR__ . '/../includes/database_notice.php'; ?><?php endif; ?>
<?php if ($errors): ?><div class="notice notice-warning" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($doctor): ?><section class="content-card form-card"><form method="post" action="<?= h(APP_BASE_URL) ?>/mjeket/ndrysho.php?id=<?= h($id) ?>" class="patient-form">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= h($id) ?>">
    <div class="form-grid">
        <div class="form-field"><label for="emri">Emri <span>*</span></label><input id="emri" name="emri" maxlength="100" value="<?= h($values['emri']) ?>" required></div>
        <div class="form-field"><label for="mbiemri">Mbiemri <span>*</span></label><input id="mbiemri" name="mbiemri" maxlength="100" value="<?= h($values['mbiemri']) ?>" required></div>
        <div class="form-field"><label for="specializimi">Specializimi <span>*</span></label><input id="specializimi" name="specializimi" maxlength="150" value="<?= h($values['specializimi']) ?>" required></div>
        <div class="form-field"><label for="telefoni">Numri i telefonit <span>*</span></label><input id="telefoni" name="telefoni" maxlength="30" value="<?= h($values['telefoni']) ?>" required></div>
        <div class="form-field"><label for="email">Email <small>(opsional)</small></label><input id="email" name="email" type="email" maxlength="190" value="<?= h($values['email'] ?? '') ?>"></div>
        <div class="form-field"><label for="nr_dhome">Kabineti / Dhoma</label><input id="nr_dhome" name="nr_dhome" maxlength="30" value="<?= h($values['nr_dhome'] ?? '') ?>"></div>
        <div class="form-field"><label for="orari_punes">Orari i punës</label><input id="orari_punes" name="orari_punes" maxlength="120" value="<?= h($values['orari_punes'] ?? '') ?>"></div>
        <div class="form-field"><label for="tarifa_bazike">Tarifa e konsultës (€)</label><input id="tarifa_bazike" name="tarifa_bazike" type="number" step="0.01" min="0" value="<?= h($values['tarifa_bazike'] ?? '30.00') ?>"></div>
    </div>
    <div class="form-actions"><a class="button button-quiet" href="<?= h(APP_BASE_URL) ?>/mjeket/lista.php">Anulo</a><button class="button button-primary" type="submit">Ruaj ndryshimet</button></div>
</form></section><?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
