<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_roles('recepsionist');
$pageTitle = 'Paneli i recepsionit';
$activePage = 'home';
$counts = ['pacienti' => 0, 'mjeku' => 0, 'termini_sot' => 0, 'fatura_papaguara' => 0];
$upcoming = [];
$unpaid = [];
if ($pdo instanceof PDO) {
    try {
        $counts['pacienti'] = (int) $pdo->query('SELECT COUNT(*) FROM pacienti')->fetchColumn();
        $counts['mjeku'] = (int) $pdo->query('SELECT COUNT(*) FROM mjeku')->fetchColumn();
        $counts['termini_sot'] = (int) $pdo->query("SELECT COUNT(*) FROM termini WHERE DATE(data_ora) = CURDATE() AND statusi <> 'anuluar'")->fetchColumn();
        $counts['fatura_papaguara'] = (int) $pdo->query("SELECT COUNT(*) FROM fatura WHERE statusi IN ('papaguar', 'pjeserisht')")->fetchColumn();

        $upcoming = $pdo->query(
            "SELECT t.id_termini, t.id_pacienti, t.data_ora, t.statusi, t.lloji_vizites,
                    p.emri AS pacient_emri, p.mbiemri AS pacient_mbiemri, p.telefoni AS pacient_telefoni,
                    m.emri AS mjek_emri, m.mbiemri AS mjek_mbiemri
             FROM termini t
             INNER JOIN pacienti p ON p.id_pacienti = t.id_pacienti
             INNER JOIN mjeku m ON m.id_mjeku = t.id_mjeku
             WHERE t.data_ora >= CURDATE() AND t.statusi IN ('planifikuar', 'konfirmuar', 'ne_pritje')
             ORDER BY t.data_ora ASC LIMIT 10"
        )->fetchAll();

        $unpaid = $pdo->query(
            "SELECT f.id_fatura, f.nr_fatures, f.shuma_totale, f.shuma_paguar, f.statusi, p.emri, p.mbiemri
             FROM fatura f INNER JOIN pacienti p ON p.id_pacienti = f.id_pacienti
             WHERE f.statusi IN ('papaguar', 'pjeserisht')
             ORDER BY f.id_fatura DESC LIMIT 5"
        )->fetchAll();
    } catch (Throwable $exception) {
        error_log('Paneli i recepsionit nuk u lexua: ' . $exception->getMessage());
    }
}
include __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
    <div><p class="eyebrow">PANELI I RECEPSIONIT</p><h1>Organizimi dhe pritja</h1><p>Regjistroni pacientët, menaxhoni check-in e termineve dhe lëshoni faturat.</p></div>
    <div class="page-head-actions">
        <a class="button button-secondary" href="<?= h(APP_BASE_URL) ?>/pacientet/shto.php">Regjistro pacient</a>
        <a class="button button-secondary" href="<?= h(APP_BASE_URL) ?>/faturimi/shto.php">Krijo faturë</a>
        <a class="button button-primary" href="<?= h(APP_BASE_URL) ?>/terminet/shto.php"><span aria-hidden="true">+</span> Cakto termin</a>
    </div>
</section>

<section class="kpi-grid">
    <article class="kpi-card"><span class="kpi-label">Termine sot</span><strong class="kpi-value tabular-nums"><?= h($counts['termini_sot']) ?></strong><span class="kpi-meta">Vizitat e ditës së sotme</span></article>
    <article class="kpi-card"><span class="kpi-label">Pacientë</span><strong class="kpi-value tabular-nums"><?= h($counts['pacienti']) ?></strong><span class="kpi-meta">Profile të regjistruara</span></article>
    <article class="kpi-card"><span class="kpi-label">Mjekë</span><strong class="kpi-value tabular-nums"><?= h($counts['mjeku']) ?></strong><span class="kpi-meta">Ekipi mjekësor</span></article>
    <article class="kpi-card"><span class="kpi-label">Fatura në pritje</span><strong class="kpi-value tabular-nums"><?= h($counts['fatura_papaguara']) ?></strong><span class="kpi-meta">Për t'u arkëtuar</span></article>
</section>

<section class="dashboard-split">
    <article class="content-card">
        <div class="card-heading"><div><p class="eyebrow">PRITJA &amp; ORARI</p><h2>Terminet e radhës</h2></div><a class="text-link" href="<?= h(APP_BASE_URL) ?>/terminet/lista.php">Të gjitha →</a></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Data dhe ora</th><th>Pacienti</th><th>Mjeku</th><th>Statusi</th><th class="actions-heading">Check-in</th></tr></thead>
            <tbody>
            <?php foreach ($upcoming as $appointment): ?>
                <tr>
                    <td class="tabular-nums"><strong><?= h(format_date_sq($appointment['data_ora'], 'd.m H:i')) ?></strong></td>
                    <td><strong><?= h($appointment['pacient_emri'] . ' ' . $appointment['pacient_mbiemri']) ?></strong><small class="muted"><?= h($appointment['pacient_telefoni']) ?></small></td>
                    <td><?= h($appointment['mjek_emri'] . ' ' . $appointment['mjek_mbiemri']) ?><small class="muted"><?= h($appointment['lloji_vizites']) ?></small></td>
                    <td><span class="status-pill status-<?= h($appointment['statusi']) ?>"><?= h(appointment_status_label($appointment['statusi'])) ?></span></td>
                    <td class="actions-cell">
                        <?php if ($appointment['statusi'] === 'planifikuar'): ?>
                            <form class="inline-form" action="<?= h(APP_BASE_URL) ?>/terminet/statusi.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= h($appointment['id_termini']) ?>">
                                <input type="hidden" name="statusi" value="konfirmuar">
                                <button class="link-button" type="submit">Konfirmo</button>
                            </form>
                        <?php elseif ($appointment['statusi'] === 'konfirmuar'): ?>
                            <form class="inline-form" action="<?= h(APP_BASE_URL) ?>/terminet/statusi.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= h($appointment['id_termini']) ?>">
                                <input type="hidden" name="statusi" value="ne_pritje">
                                <button class="link-button" type="submit">Në pritje</button>
                            </form>
                        <?php endif; ?>
                        <a class="text-link" href="<?= h(APP_BASE_URL) ?>/faturimi/shto.php?id_pacienti=<?= h($appointment['id_pacienti']) ?>&id_termini=<?= h($appointment['id_termini']) ?>">Faturë</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$upcoming): ?><tr><td class="empty-state" colspan="5">Nuk ka termine të ardhshme.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </article>

    <article class="content-card">
        <div class="card-heading"><div><p class="eyebrow">ARKËTIMI</p><h2>Fatura të papaguara</h2></div><a class="text-link" href="<?= h(APP_BASE_URL) ?>/faturimi/lista.php">Faturimi →</a></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Nr.</th><th>Pacienti</th><th>Mbetja</th><th class="actions-heading">Veprim</th></tr></thead>
            <tbody>
            <?php foreach ($unpaid as $inv): ?>
                <?php $mbetja = max(0, (float) $inv['shuma_totale'] - (float) $inv['shuma_paguar']); ?>
                <tr>
                    <td class="tabular-nums"><?= h($inv['nr_fatures']) ?></td>
                    <td><?= h($inv['emri'] . ' ' . $inv['mbiemri']) ?></td>
                    <td class="tabular-nums"><strong><?= h(format_money($mbetja)) ?></strong></td>
                    <td class="actions-cell"><a class="text-link" href="<?= h(APP_BASE_URL) ?>/faturimi/shiko.php?id=<?= h($inv['id_fatura']) ?>">Arkëto</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$unpaid): ?><tr><td class="empty-state" colspan="4">Të gjitha faturat janë paguar.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </article>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
