<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Ndrysho pacient';
$activePage = 'patients';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$errors = [];
$patient = null;

if (!$id || $id < 1) {
    http_response_code(400);
    $errors[] = 'ID-ja e pacientit nuk është e vlefshme.';
} elseif ($pdo instanceof PDO) {
    try {
        $statement = $pdo->prepare('SELECT id_pacienti, emri, mbiemri, telefoni, email FROM pacienti WHERE id_pacienti = :id');
        $statement->execute(['id' => $id]);
        $patient = $statement->fetch();
        if (!$patient) {
            http_response_code(404);
            $errors[] = 'Pacienti nuk u gjet.';
        }
    } catch (Throwable $exception) {
        error_log('Leximi i pacientit dështoi: ' . $exception->getMessage());
        $databaseError = 'Nuk u arrit leximi i pacientit. Kontrolloni nëse skema është importuar.';
        $pdo = null;
    }
}

$values = $patient ?: ['emri' => '', 'mbiemri' => '', 'telefoni' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $patient) {
    $submittedId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if ((int) $submittedId !== (int) $id || !valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Forma nuk është e vlefshme. Rifreskojeni faqen dhe provoni përsëri.';
    }
    [$values, $fieldErrors] = valid_patient_fields($_POST);
    $errors = array_merge($errors, $fieldErrors);

    if (!$pdo instanceof PDO) {
        $errors[] = 'Databaza nuk është e disponueshme.';
    }
    if (!$errors && $pdo instanceof PDO) {
        try {
            $statement = $pdo->prepare(
                'UPDATE pacienti SET emri = :emri, mbiemri = :mbiemri, telefoni = :telefoni, email = :email
                 WHERE id_pacienti = :id'
            );
            $statement->execute([
                'emri' => $values['emri'],
                'mbiemri' => $values['mbiemri'],
                'telefoni' => $values['telefoni'],
                'email' => $values['email'] !== '' ? $values['email'] : null,
                'id' => $id,
            ]);
            set_flash('success', 'Të dhënat e pacientit u përditësuan.');
            redirect_to('/pacientet/lista.php');
        } catch (Throwable $exception) {
            error_log('Përditësimi i pacientit dështoi: ' . $exception->getMessage());
            $errors[] = 'Ndryshimet nuk u ruajtën. Provoni përsëri.';
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>
<section class="page-heading compact-heading">
    <div><p class="eyebrow">REGJISTRI</p><h1>Ndrysho të dhënat</h1><p>Përditësoni të dhënat bazë të pacientit.</p></div>
</section>
<?php if (!$pdo instanceof PDO): ?><?php include __DIR__ . '/../includes/database_notice.php'; ?><?php endif; ?>
<?php if ($errors): ?><div class="notice notice-warning" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($patient): ?>
<section class="content-card form-card">
    <form method="post" action="<?= h(APP_BASE_URL) ?>/pacientet/ndrysho.php?id=<?= h($id) ?>" class="patient-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= h($id) ?>">
        <div class="form-grid">
            <div class="form-field"><label for="emri">Emri <span>*</span></label><input id="emri" name="emri" maxlength="100" value="<?= h($values['emri']) ?>" required></div>
            <div class="form-field"><label for="mbiemri">Mbiemri <span>*</span></label><input id="mbiemri" name="mbiemri" maxlength="100" value="<?= h($values['mbiemri']) ?>" required></div>
            <div class="form-field"><label for="telefoni">Numri i telefonit <span>*</span></label><input id="telefoni" name="telefoni" maxlength="30" value="<?= h($values['telefoni']) ?>" required></div>
            <div class="form-field"><label for="email">Email <small>(opsionale)</small></label><input id="email" name="email" type="email" maxlength="190" value="<?= h($values['email']) ?>"></div>
        </div>
        <div class="form-actions"><a class="button button-quiet" href="<?= h(APP_BASE_URL) ?>/pacientet/lista.php">Anulo</a><button class="button button-primary" type="submit">Ruaj ndryshimet</button></div>
    </form>
</section>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>