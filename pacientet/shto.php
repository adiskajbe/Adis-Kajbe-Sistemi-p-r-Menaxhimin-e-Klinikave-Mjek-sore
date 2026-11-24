<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Regjistro pacient';
$activePage = 'patients';
$values = [
    'emri' => '', 'mbiemri' => '', 'datelindja' => '', 'gjinia' => '',
    'nr_personal' => '', 'grupi_gjakut' => '', 'alergjite' => '',
    'telefoni' => '', 'email' => '', 'adresa' => '', 'kontakti_emergjent' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Forma ka skaduar. Rifreskojeni faqen dhe provoni përsëri.';
    }
    [$values, $fieldErrors] = valid_patient_fields($_POST);
    $errors = array_merge($errors, $fieldErrors);

    if (!$pdo instanceof PDO) {
        $errors[] = 'Databaza nuk është e disponueshme.';
    }
    if ($pdo instanceof PDO && !$errors) {
        try {
            $statement = $pdo->prepare(
                'INSERT INTO pacienti (emri, mbiemri, datelindja, gjinia, nr_personal, grupi_gjakut, alergjite, telefoni, email, adresa, kontakti_emergjent)
                 VALUES (:emri, :mbiemri, :datelindja, :gjinia, :nr_personal, :grupi_gjakut, :alergjite, :telefoni, :email, :adresa, :kontakti_emergjent)'
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
<section class="page-head">
    <div><p class="eyebrow">REGJISTRI</p><h1>Regjistro pacient</h1><p>Plotësoni të dhënat bazë dhe profilin klinik fillestar. Fushat me * janë të detyrueshme.</p></div>
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
            <div class="form-field"><label for="datelindja">Datëlindja</label><input id="datelindja" name="datelindja" type="date" value="<?= h($values['datelindja']) ?>"></div>
            <div class="form-field"><label for="gjinia">Gjinia</label><select id="gjinia" name="gjinia"><option value="">Pa zgjedhur</option><option value="F" <?= $values['gjinia'] === 'F' ? 'selected' : '' ?>>Femër</option><option value="M" <?= $values['gjinia'] === 'M' ? 'selected' : '' ?>>Mashkull</option><option value="Tjeter" <?= $values['gjinia'] === 'Tjeter' ? 'selected' : '' ?>>Tjetër</option></select></div>
            <div class="form-field"><label for="nr_personal">Nr. Personal / ID</label><input id="nr_personal" name="nr_personal" maxlength="30" value="<?= h($values['nr_personal']) ?>"></div>
            <div class="form-field"><label for="grupi_gjakut">Grupi i gjakut</label><select id="grupi_gjakut" name="grupi_gjakut"><option value="">I papërcaktuar</option><?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?><option value="<?= h($bg) ?>" <?= $values['grupi_gjakut'] === $bg ? 'selected' : '' ?>><?= h($bg) ?></option><?php endforeach; ?></select></div>
            <div class="form-field"><label for="telefoni">Numri i telefonit <span>*</span></label><input id="telefoni" name="telefoni" maxlength="30" autocomplete="tel" value="<?= h($values['telefoni']) ?>" required></div>
            <div class="form-field"><label for="email">Email <small>(opsionale)</small></label><input id="email" name="email" type="email" maxlength="190" autocomplete="email" value="<?= h($values['email']) ?>"></div>
            <div class="form-field"><label for="adresa">Adresa</label><input id="adresa" name="adresa" maxlength="255" value="<?= h($values['adresa']) ?>"></div>
            <div class="form-field"><label for="kontakti_emergjent">Kontakti emergjent</label><input id="kontakti_emergjent" name="kontakti_emergjent" maxlength="120" placeholder="Emri · Telefoni" value="<?= h($values['kontakti_emergjent']) ?>"></div>
            <div class="form-field form-field-wide"><label for="alergjite">Alergjitë e njohura</label><textarea id="alergjite" name="alergjite" rows="2" maxlength="1000" placeholder="p.sh. Penicilinë, polen, asnjë e njohur"><?= h($values['alergjite']) ?></textarea></div>
        </div>
        <div class="form-actions"><a class="button button-quiet" href="<?= h(APP_BASE_URL) ?>/pacientet/lista.php">Anulo</a><button class="button button-primary" type="submit">Ruaj pacientin</button></div>
    </form>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
