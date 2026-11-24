<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_roles('pacient');
$pageTitle = 'Portali im';
$activePage = 'home';
$user = current_auth_user();
$patientId = (int) ($user['patient_id'] ?? 0);
$patientProfile = null;
$appointments = [];
$recentRecords = [];
$invoices = [];
$upcomingCount = 0;
$ehrCount = 0;
$unpaidBalance = 0.0;

if ($pdo instanceof PDO && $patientId > 0) {
    try {
        $pStmt = $pdo->prepare('SELECT * FROM pacienti WHERE id_pacienti = :pid LIMIT 1');
        $pStmt->execute(['pid' => $patientId]);
        $patientProfile = $pStmt->fetch();

        $countQuery = $pdo->prepare("SELECT COUNT(*) FROM termini WHERE id_pacienti = :patient_id AND data_ora >= NOW() AND statusi IN ('planifikuar', 'konfirmuar')");
        $countQuery->execute(['patient_id' => $patientId]);
        $upcomingCount = (int) $countQuery->fetchColumn();

        $ehrQ = $pdo->prepare('SELECT COUNT(*) FROM kartela_mjekesore WHERE id_pacienti = :patient_id');
        $ehrQ->execute(['patient_id' => $patientId]);
        $ehrCount = (int) $ehrQ->fetchColumn();

        $balQ = $pdo->prepare("SELECT COALESCE(SUM(shuma_totale - shuma_paguar), 0) FROM fatura WHERE id_pacienti = :patient_id AND statusi IN ('papaguar', 'pjeserisht')");
        $balQ->execute(['patient_id' => $patientId]);
        $unpaidBalance = (float) $balQ->fetchColumn();

        $listQuery = $pdo->prepare(
            "SELECT t.id_termini, t.data_ora, t.statusi, t.lloji_vizites, m.emri AS mjek_emri, m.mbiemri AS mjek_mbiemri, m.specializimi
             FROM termini t INNER JOIN mjeku m ON m.id_mjeku = t.id_mjeku
             WHERE t.id_pacienti = :patient_id AND t.data_ora >= CURDATE() AND t.statusi <> 'anuluar'
             ORDER BY t.data_ora ASC LIMIT 5"
        );
        $listQuery->execute(['patient_id' => $patientId]);
        $appointments = $listQuery->fetchAll();

        $recQuery = $pdo->prepare(
            "SELECT k.id_kartela, k.data_vizites, k.diagnoza, k.terapia_receta, m.emri AS mjek_emri, m.mbiemri AS mjek_mbiemri
             FROM kartela_mjekesore k LEFT JOIN mjeku m ON m.id_mjeku = k.id_mjeku
             WHERE k.id_pacienti = :patient_id
             ORDER BY k.data_vizites DESC LIMIT 4"
        );
        $recQuery->execute(['patient_id' => $patientId]);
        $recentRecords = $recQuery->fetchAll();

        $invQuery = $pdo->prepare(
            "SELECT id_fatura, nr_fatures, data_fatures, shuma_totale, shuma_paguar, statusi
             FROM fatura WHERE id_pacienti = :patient_id ORDER BY data_fatures DESC LIMIT 4"
        );
        $invQuery->execute(['patient_id' => $patientId]);
        $invoices = $invQuery->fetchAll();
    } catch (Throwable $exception) {
        error_log('Paneli i pacientit nuk u lexua: ' . $exception->getMessage());
    }
}

include __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
    <div>
        <p class="eyebrow">PORTALI I PACIENTIT</p>
        <h1>Përshëndetje, <?= h($patientProfile ? ($patientProfile['emri'] . ' ' . $patientProfile['mbiemri']) : ($user['username'] ?? 'Pacient')) ?></h1>
        <p>Ndiqni terminet tuaja, historinë mjekësore dhe faturat nga një vend i sigurt.</p>
    </div>
    <?php if ($patientId > 0): ?>
        <div class="page-head-actions">
            <a class="button button-secondary" href="<?= h(APP_BASE_URL) ?>/mjeket/lista.php">Shiko mjekët</a>
            <a class="button button-primary" href="<?= h(APP_BASE_URL) ?>/terminet/shto.php"><span aria-hidden="true">+</span> Rezervo termin</a>
        </div>
    <?php endif; ?>
</section>

<?php if ($patientId < 1): ?>
    <div class="notice notice-warning" role="status"><strong>Llogaria juaj nuk është lidhur ende me një profil pacienti.</strong><p>Kërkoni nga administratori i klinikës ta lidhë llogarinë me profilin tuaj për të parë terminet, kartelën dhe faturat.</p></div>
<?php elseif (!$pdo instanceof PDO): ?>
    <?php include __DIR__ . '/../includes/database_notice.php'; ?>
<?php else: ?>
    <section class="kpi-grid">
        <article class="kpi-card"><span class="kpi-label">Termine të ardhshme</span><strong class="kpi-value tabular-nums"><?= h($upcomingCount) ?></strong><span class="kpi-meta">Vizita aktive</span></article>
        <article class="kpi-card"><span class="kpi-label">Kartela mjekësore</span><strong class="kpi-value tabular-nums"><?= h($ehrCount) ?></strong><span class="kpi-meta">Raporte dhe receta</span></article>
        <article class="kpi-card"><span class="kpi-label">Detyrime të mbetura</span><strong class="kpi-value tabular-nums"><?= h(format_money($unpaidBalance)) ?></strong><span class="kpi-meta">Balanci i faturave</span></article>
        <article class="kpi-card"><span class="kpi-label">Grupi i gjakut</span><strong class="kpi-value"><?= h($patientProfile['grupi_gjakut'] ?: '—') ?></strong><span class="kpi-meta"><?= h($patientProfile['alergjite'] ? 'Alergji: ' . $patientProfile['alergjite'] : 'Pa alergji të shënuara') ?></span></article>
    </section>

    <section class="dashboard-split">
        <article class="content-card">
            <div class="card-heading"><div><p class="eyebrow">VIZITAT E PLANIFIKUARA</p><h2>Terminet e mia</h2></div><a class="text-link" href="<?= h(APP_BASE_URL) ?>/terminet/lista.php">Të gjitha →</a></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Data dhe ora</th><th>Mjeku</th><th>Statusi</th><th class="actions-heading">Veprim</th></tr></thead>
                <tbody>
                <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td class="tabular-nums"><strong><?= h(format_date_sq($appointment['data_ora'], 'd.m.Y H:i')) ?></strong><small class="muted"><?= h($appointment['lloji_vizites']) ?></small></td>
                        <td><?= h($appointment['mjek_emri'] . ' ' . $appointment['mjek_mbiemri']) ?><small class="muted"><?= h($appointment['specializimi']) ?></small></td>
                        <td><span class="status-pill status-<?= h($appointment['statusi']) ?>"><?= h(appointment_status_label($appointment['statusi'])) ?></span></td>
                        <td class="actions-cell">
                            <?php if ($appointment['statusi'] === 'planifikuar'): ?>
                                <form class="inline-form" action="<?= h(APP_BASE_URL) ?>/terminet/anulo.php" method="post" data-confirm="A dëshironi ta anuloni këtë termin?">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= h($appointment['id_termini']) ?>">
                                    <button class="link-button danger-link" type="submit">Anulo</button>
                                </form>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$appointments): ?><tr><td class="empty-state" colspan="4">Nuk keni termine të ardhshme.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </article>

        <article class="content-card">
            <div class="card-heading"><div><p class="eyebrow">DOKUMENTIMI KLINIK</p><h2>Historia e vizitave</h2></div><a class="text-link" href="<?= h(APP_BASE_URL) ?>/kartela/lista.php">Shiko të gjitha →</a></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Data</th><th>Diagnoza</th><th>Mjeku</th></tr></thead>
                <tbody>
                <?php foreach ($recentRecords as $rec): ?>
                    <tr>
                        <td class="tabular-nums"><?= h(format_date_sq($rec['data_vizites'], 'd.m.Y')) ?></td>
                        <td><a class="text-link" href="<?= h(APP_BASE_URL) ?>/kartela/shiko.php?id=<?= h($rec['id_kartela']) ?>"><?= h($rec['diagnoza']) ?></a></td>
                        <td><?= h($rec['mjek_emri'] ? ($rec['mjek_emri'] . ' ' . $rec['mjek_mbiemri']) : '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recentRecords): ?><tr><td class="empty-state" colspan="3">Ende nuk keni vizita të regjistruara në kartelë.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </article>
    </section>

    <section class="content-card mt-section">
        <div class="card-heading"><div><p class="eyebrow">FINANCAT E MIA</p><h2>Faturat e fundit</h2></div><a class="text-link" href="<?= h(APP_BASE_URL) ?>/faturimi/lista.php">Të gjitha faturat →</a></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Nr. Faturës</th><th>Data</th><th>Totali</th><th>Paguar</th><th>Statusi</th><th class="actions-heading">Veprim</th></tr></thead>
            <tbody>
            <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td class="tabular-nums"><strong><?= h($inv['nr_fatures']) ?></strong></td>
                    <td class="tabular-nums"><?= h(format_date_sq($inv['data_fatures'])) ?></td>
                    <td class="tabular-nums"><?= h(format_money($inv['shuma_totale'])) ?></td>
                    <td class="tabular-nums"><?= h(format_money($inv['shuma_paguar'])) ?></td>
                    <td><span class="status-pill status-<?= h($inv['statusi']) ?>"><?= h(invoice_status_label($inv['statusi'])) ?></span></td>
                    <td class="actions-cell"><a class="text-link" href="<?= h(APP_BASE_URL) ?>/faturimi/shiko.php?id=<?= h($inv['id_fatura']) ?>">Shiko faturën</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$invoices): ?><tr><td class="empty-state" colspan="6">Nuk keni fatura të lëshuara.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
