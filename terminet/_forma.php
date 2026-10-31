<?php
include __DIR__ . '/../includes/header.php';
?>
<section class="page-heading compact-heading">
    <div><p class="eyebrow">ORGANIZIMI</p><h1><?= h($heading) ?></h1><p>Plotësoni pacientin, mjekun, datën, orën dhe statusin.</p></div>
</section>
<?php if (!$pdo instanceof PDO): ?><?php include __DIR__ . '/../includes/database_notice.php'; ?><?php endif; ?>
<?php if ($errors): ?><div class="notice notice-warning" role="alert"><strong>Kontrolloni të dhënat e terminit.</strong><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($pdo instanceof PDO && (!$patients || !$doctors)): ?>
    <div class="notice notice-warning" role="status">Për të caktuar termin duhet të ekzistojë së paku një pacient dhe një mjek. Regjistroni fillimisht të dhënat përkatëse.</div>
<?php endif; ?>
<?php if ($pdo instanceof PDO && $patients && $doctors && $showForm): ?>
<section class="content-card form-card">
    <form method="post" action="<?= h($action) ?>" class="patient-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= h($id ?? '') ?>">
        <div class="form-grid">
            <div class="form-field"><label for="id_pacienti">Pacienti <span>*</span></label>
                <select id="id_pacienti" name="id_pacienti" required><option value="">Zgjidhni pacientin</option>
                    <?php foreach ($patients as $patient): ?><option value="<?= h($patient['id_pacienti']) ?>" <?= (int) $values['id_pacienti'] === (int) $patient['id_pacienti'] ? 'selected' : '' ?>><?= h($patient['emri'] . ' ' . $patient['mbiemri']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-field"><label for="id_mjeku">Mjeku <span>*</span></label>
                <select id="id_mjeku" name="id_mjeku" required><option value="">Zgjidhni mjekun</option>
                    <?php foreach ($doctors as $doctor): ?><option value="<?= h($doctor['id_mjeku']) ?>" <?= (int) $values['id_mjeku'] === (int) $doctor['id_mjeku'] ? 'selected' : '' ?>><?= h($doctor['emri'] . ' ' . $doctor['mbiemri'] . ' - ' . $doctor['specializimi']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-field"><label for="data_ora">Data dhe ora <span>*</span></label><input id="data_ora" name="data_ora" type="datetime-local" value="<?= h($values['data_ora']) ?>" required></div>
            <div class="form-field"><label for="statusi">Statusi <span>*</span></label>
                <select id="statusi" name="statusi" required>
                    <?php foreach (['planifikuar' => 'Planifikuar', 'perfunduar' => 'Përfunduar', 'anuluar' => 'Anuluar'] as $key => $label): ?>
                        <option value="<?= h($key) ?>" <?= $values['statusi'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field form-field-wide"><label for="shenime">Shënime <small>(opsionale)</small></label><textarea id="shenime" name="shenime" rows="4" maxlength="2000"><?= h($values['shenime']) ?></textarea></div>
        </div>
        <div class="form-actions"><a class="button button-quiet" href="<?= h(APP_BASE_URL) ?>/terminet/lista.php">Anulo</a><button class="button button-primary" type="submit"><?= h($submitLabel) ?></button></div>
    </form>
</section>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
