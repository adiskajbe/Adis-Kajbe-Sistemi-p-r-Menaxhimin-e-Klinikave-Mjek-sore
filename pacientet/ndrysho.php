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
        $statement = $pdo->prepare('SELECT * FROM pacienti WHERE id_pacienti = :id');
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

$values = $patient ?: [
    'emri' => '', 'mbiemri' => '', 'datelindja' => '', 'gjinia' => '',
    'nr_personal' => '', 'grupi_gjakut' => '', 'alergjite' => '',
    'telefoni' => '', 'email' => '', 'adresa' => '', 'kontakti_emergjent' => '',
];

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
                'UPDATE pacienti SET emri = :emri, mbiemri = :mbiemri, datelindja = :datelindja, gjinia = :gjinia,
                 nr_personal = :nr_personal, grupi_gjakut = :grupi_gjakut, alergjite = :alergjite,
                 telefoni = :telefoni, email = :email, adresa = :adresa, kontakti_emergjent = :kontakti_emergjent
                 WHERE id_pacienti = :id'
            );
            $statement->execute([
                'emri' => $values['emri'],
                'mbiemri' => $values['mbiemri'],
                'datelindja' => $values['datelindja'] !== '' ? $values['datelindja'] : null,
                'gjinia' => $values['gjinia'] !== '' ? $values['gjinia'] : null,
                'nr_personal' => $values['nr_personal'] !== '' ? $values['nr_personal'] : null,
                'grupi_gjakut' => $values['grupi_gjakut'] !== '' ? $values['grupi_gjakut'] : null,
                'alergjite' => $values['alergjite'] !== '' ? $values['alergjite'] : null,
                'telefoni' => $values['telefoni'],
                'email' => $values['email'] !== '' ? $values['email'] : null,
                'adresa' => $values['adresa'] !== '' ? $values['adresa'] : null,
                'kontakti_emergjent' => $values['kontakti_emergjent'] !== '' ? $values['kontakti_emergjent'] : null,
                'id' => $id,
            ]);
            set_flash('success', 'Të dhënat e pacientit u përditësuan.');
            redirect_to('/pacientet/profili.php?id=' . (int) $id);
        } catch (Throwable $exception) {
            error_log('Përditësimi i pacientit dështoi: ' . $exception->getMessage());
            $errors[] = 'Ndryshimet nuk u ruajtën. Provoni përsëri.';
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
    <div><p class="eyebrow">REGJISTRI</p><h1>Ndrysho të dhënat e pacientit</h1><p>Përditësoni të dhënat demografike dhe klinike të pacientit.</p></div>
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
            <div class="form-field"><label for="datelindja">Datëlindja</label><input id="datelindja" name="datelindja" type="date" value="<?= h($values['datelindja'] ?? '') ?>"></div>
            <div class="form-field"><label for="gjinia">Gjinia</label><select id="gjinia" name="gjinia"><option value="">Pa zgjedhur</option><option value="F" <?= ($values['gjinia'] ?? '') === 'F' ? 'selected' : '' ?>>Femër</option><option value="M" <?= ($values['gjinia'] ?? '') === 'M' ? 'selected' : '' ?>>Mashkull</option><option value="Tjeter" <?= ($values['gjinia'] ?? '') === 'Tjeter' ? 'selected' : '' ?>>Tjetër</option></select></div>
            <div class="form-field"><label for="nr_personal">Nr. Personal / ID</label><input id="nr_personal" name="nr_personal" maxlength="30" value="<?= h($values['nr_personal'] ?? '') ?>"></div>
            <div class="form-field"><label for="grupi_gjakut">Grupi i gjakut</label><select id="grupi_gjakut" name="grupi_gjakut"><option value="">I papërcaktuar</option><?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?><option value="<?= h($bg) ?>" <?= ($values['grupi_gjakut'] ?? '') === $bg ? 'selected' : '' ?>><?= h($bg) ?></option><?php endforeach; ?></select></div>
            <div class="form-field"><label for="telefoni">Numri i telefonit <span>*</span></label><input id="telefoni" name="telefoni" maxlength="30" value="<?= h($values['telefoni']) ?>" required></div>
            <div class="form-field"><label for="email">Email <small>(opsionale)</small></label><input id="email" name="email" type="email" maxlength="190" value="<?= h($values['email'] ?? '') ?>"></div>
            <div class="form-field"><label for="adresa">Adresa</label><input id="adresa" name="adresa" maxlength="255" value="<?= h($values['adresa'] ?? '') ?>"></div>
            <div class="form-field"><label for="kontakti_emergjent">Kontakti emergjent</label><input id="kontakti_emergjent" name="kontakti_emergjent" maxlength="120" value="<?= h($values['kontakti_emergjent'] ?? '') ?>"></div>
            <div class="form-field form-field-wide"><label for="alergjite">Alergjitë e njohura</label><textarea id="alergjite" name="alergjite" rows="2" maxlength="1000"><?= h($values['alergjite'] ?? '') ?></textarea></div>
        </div>
        <div class="form-actions"><a class="button button-quiet" href="<?= h(APP_BASE_URL) ?>/pacientet/profili.php?id=<?= h($id) ?>">Anulo</a><button class="button button-primary" type="submit">Ruaj ndryshimet</button></div>
    </form>
</section>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
