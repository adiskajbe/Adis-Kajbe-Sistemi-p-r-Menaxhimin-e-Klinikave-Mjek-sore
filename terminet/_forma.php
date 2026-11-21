<?php
include __DIR__ . '/../includes/header.php';
$isPatientForm = auth_user_has_role('pacient');
?>
<section class="page-heading compact-heading">
    <div><p class="eyebrow"><?= $isPatientForm ? 'REZERVIMI IM' : 'ORGANIZIMI' ?></p><h1><?= h($heading) ?></h1><p><?= $isPatientForm ? 'Zgjidhni mjekun, datën dhe orën që ju përshtatet.' : 'Plotësoni pacientin, mjekun, datën, orën dhe statusin.' ?></p></div>
</section>
<?php if (!$pdo instanceof PDO): ?><?php include __DIR__ . '/../includes/database_notice.php'; ?><?php endif; ?>
<?php if ($errors): ?><div class="notice notice-warning" role="alert"><strong>Kontrolloni të dhënat e terminit.</strong><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($pdo instanceof PDO && $isPatientForm && !$patients): ?>
    <div class="notice notice-warning" role="status">Llogaria juaj nuk është lidhur ende me profilin e pacientit. Kërkoni nga administratori ta lidhë profilin para se të rezervoni termin.</div>
<?php elseif ($pdo instanceof PDO && (!$patients || !$doctors)): ?>
    <div class="notice notice-warning" role="status">Për të caktuar termin duhet të ekzistojë së paku një pacient dhe një mjek. Regjistroni fillimisht të dhënat përkatëse.</div>
<?php endif; ?>
<?php if ($pdo instanceof PDO && $patients && $doctors && $showForm): ?>
<section class="content-card form-card">
    <form method="post" action="<?= h($action) ?>" class="patient-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= h($id ?? '') ?>">
        <div class="form-grid">
            <?php if ($isPatientForm): ?>
            <div class="form-field"><span class="form-label">Profili juaj</span><div class="patient-profile-inline"><span class="stat-icon icon-patient" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21v-2a7 7 0 0 1 14 0v2M19 8v6m-3-3h6"/></svg></span><strong><?= h($patients[0]['emri'] . ' ' . $patients[0]['mbiemri']) ?></strong><small>Termini lidhet me këtë profil.</small></div></div>
            <?php else: ?><div class="form-field"><label for="id_pacienti">Pacienti <span>*</span></label>
                <select id="id_pacienti" name="id_pacienti" required><option value="">Zgjidhni pacientin</option>
                    <?php foreach ($patients as $patient): ?><option value="<?= h($patient['id_pacienti']) ?>" <?= (int) $values['id_pacienti'] === (int) $patient['id_pacienti'] ? 'selected' : '' ?>><?= h($patient['emri'] . ' ' . $patient['mbiemri']) ?></option><?php endforeach; ?>
                </select>
            </div><?php endif; ?>
            <div class="form-field"><label for="id_mjeku">Mjeku <span>*</span></label>
                <select id="id_mjeku" name="id_mjeku" required><option value="">Zgjidhni mjekun</option>
                    <?php foreach ($doctors as $doctor): ?><option value="<?= h($doctor['id_mjeku']) ?>" <?= (int) $values['id_mjeku'] === (int) $doctor['id_mjeku'] ? 'selected' : '' ?>><?= h($doctor['emri'] . ' ' . $doctor['mbiemri'] . ' - ' . $doctor['specializimi']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-field"><label for="data_ora">Data dhe ora <span>*</span></label><input id="data_ora" name="data_ora" type="datetime-local" min="<?= h((new DateTimeImmutable('now'))->format('Y-m-d\TH:i')) ?>" value="<?= h($values['data_ora']) ?>" required><?php if ($isPatientForm): ?><small class="auth-field-hint">Nuk mund të rezervohet një orë që është caktuar tashmë për këtë mjek.</small><?php endif; ?></div>
            <?php if (!$isPatientForm): ?>
            <div class="form-field"><label for="statusi">Statusi <span>*</span></label>
                <select id="statusi" name="statusi" required>
                    <?php foreach (['planifikuar' => 'Planifikuar', 'perfunduar' => 'Përfunduar', 'anuluar' => 'Anuluar'] as $key => $label): ?>
                        <option value="<?= h($key) ?>" <?= $values['statusi'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <?php if (auth_user_has_role('admin')): ?><div class="form-field form-field-wide"><label for="shenime">Shënime <small>(opsionale)</small></label><textarea id="shenime" name="shenime" rows="4" maxlength="2000"><?= h($values['shenime']) ?></textarea></div><?php endif; ?>
        </div>
        <div class="form-actions"><a class="button button-quiet" href="<?= h(APP_BASE_URL) ?>/terminet/lista.php">Anulo</a><button class="button button-primary" type="submit"><?= h($submitLabel) ?></button></div>
    </form>
</section>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
