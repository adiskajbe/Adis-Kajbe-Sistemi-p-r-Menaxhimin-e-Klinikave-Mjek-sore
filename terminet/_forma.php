<?php
include __DIR__ . '/../includes/header.php';
$isPatientForm = auth_user_has_role('pacient');
?>
<section class="page-head">
    <div><p class="eyebrow"><?= $isPatientForm ? 'REZERVIMI IM' : 'ORGANIZIMI' ?></p><h1><?= h($heading) ?></h1><p><?= $isPatientForm ? 'Zgjidhni mjekun, llojin e vizitës, datën dhe orën.' : 'Plotësoni pacientin, mjekun, datën, orën dhe detajet e vizitës.' ?></p></div>
</section>
<?php if (!$pdo instanceof PDO): ?><?php include __DIR__ . '/../includes/database_notice.php'; ?><?php endif; ?>
<?php if ($errors): ?><div class="notice notice-warning" role="alert"><strong>Kontrolloni të dhënat e terminit.</strong><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($pdo instanceof PDO && $isPatientForm && !$patients): ?>
    <div class="notice notice-warning" role="status">Llogaria juaj nuk është lidhur ende me profilin e pacientit.</div>
<?php elseif ($pdo instanceof PDO && (!$patients || !$doctors)): ?>
    <div class="notice notice-warning" role="status">Për të caktuar termin duhet të ekzistojë së paku një pacient dhe një mjek.</div>
<?php endif; ?>
<?php if ($pdo instanceof PDO && $patients && $doctors && $showForm): ?>
<section class="content-card form-card">
    <form method="post" action="<?= h($action) ?>" class="patient-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= h($id ?? '') ?>">
        <div class="form-grid">
            <?php if ($isPatientForm): ?>
            <div class="form-field"><span class="form-label">Profili juaj</span><div class="patient-profile-inline"><strong><?= h($patients[0]['emri'] . ' ' . $patients[0]['mbiemri']) ?></strong><small>Termini lidhet me këtë profil.</small></div></div>
            <?php else: ?><div class="form-field"><label for="id_pacienti">Pacienti <span>*</span></label>
                <select id="id_pacienti" name="id_pacienti" required><option value="">Zgjidhni pacientin</option>
                    <?php foreach ($patients as $patient): ?><option value="<?= h($patient['id_pacienti']) ?>" <?= (int) $values['id_pacienti'] === (int) $patient['id_pacienti'] ? 'selected' : '' ?>><?= h($patient['emri'] . ' ' . $patient['mbiemri']) ?></option><?php endforeach; ?>
                </select>
            </div><?php endif; ?>
            <div class="form-field"><label for="id_mjeku">Mjeku <span>*</span></label>
                <select id="id_mjeku" name="id_mjeku" required><option value="">Zgjidhni mjekun</option>
                    <?php foreach ($doctors as $doctor): ?><option value="<?= h($doctor['id_mjeku']) ?>" <?= (int) $values['id_mjeku'] === (int) $doctor['id_mjeku'] ? 'selected' : '' ?>><?= h($doctor['emri'] . ' ' . $doctor['mbiemri'] . ' · ' . $doctor['specializimi']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-field"><label for="data_ora">Data dhe ora <span>*</span></label><input id="data_ora" name="data_ora" type="datetime-local" min="<?= h((new DateTimeImmutable('now'))->format('Y-m-d\TH:i')) ?>" value="<?= h($values['data_ora']) ?>" required></div>
            <div class="form-field"><label for="kohezgjatja_min">Kohëzgjatja (minuta)</label><select id="kohezgjatja_min" name="kohezgjatja_min"><?php foreach ([15, 20, 30, 45, 60] as $mins): ?><option value="<?= h($mins) ?>" <?= (int) ($values['kohezgjatja_min'] ?? 30) === $mins ? 'selected' : '' ?>><?= h($mins) ?> minuta</option><?php endforeach; ?></select></div>
            <div class="form-field"><label for="lloji_vizites">Lloji i vizitës</label>
                <input id="lloji_vizites" name="lloji_vizites" list="clinic-visit-types" maxlength="100" value="<?= h($values['lloji_vizites'] ?? 'Kontrollë e përgjithshme') ?>">
                <datalist id="clinic-visit-types">
                    <option value="Kontrollë e përgjithshme">
                    <?php foreach ($services as $srv): ?><option value="<?= h($srv['emri']) ?>"><?php endforeach; ?>
                </datalist>
            </div>
            <?php if (!$isPatientForm): ?>
            <div class="form-field"><label for="statusi">Statusi <span>*</span></label>
                <select id="statusi" name="statusi" required>
                    <?php foreach (['planifikuar', 'konfirmuar', 'ne_pritje', 'perfunduar', 'anuluar'] as $key): ?>
                        <option value="<?= h($key) ?>" <?= $values['statusi'] === $key ? 'selected' : '' ?>><?= h(appointment_status_label($key)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-field form-field-wide"><label for="arsyeja">Arsyeja e vizitës <small>(opsionale)</small></label><input id="arsyeja" name="arsyeja" maxlength="255" placeholder="p.sh. Kontrollë periodike ose simptoma të caktuara" value="<?= h($values['arsyeja'] ?? '') ?>"></div>
            <?php if (auth_user_has_role('admin')): ?><div class="form-field form-field-wide"><label for="shenime">Shënime të brendshme <small>(opsionale)</small></label><textarea id="shenime" name="shenime" rows="3" maxlength="2000"><?= h($values['shenime'] ?? '') ?></textarea></div><?php endif; ?>
        </div>
        <div class="form-actions"><a class="button button-quiet" href="<?= h(APP_BASE_URL) ?>/terminet/lista.php">Anulo</a><button class="button button-primary" type="submit"><?= h($submitLabel) ?></button></div>
    </form>
</section>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
