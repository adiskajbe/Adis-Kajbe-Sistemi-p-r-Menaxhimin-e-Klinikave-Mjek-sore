<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Terminet';
$activePage = 'appointments';
$appointments = [];
$search = trim((string) ($_GET['kerko'] ?? ''));
$flash = take_flash();

if ($pdo instanceof PDO) {
    try {
        $baseSql = 'SELECT t.id_termini, t.data_ora, t.statusi, t.shenime,
                           p.emri AS pacient_emri, p.mbiemri AS pacient_mbiemri,
                           m.emri AS mjek_emri, m.mbiemri AS mjek_mbiemri, m.specializimi
                    FROM termini t
                    INNER JOIN pacienti p ON p.id_pacienti = t.id_pacienti
                    INNER JOIN mjeku m ON m.id_mjeku = t.id_mjeku';
        if ($search !== '') {
            $statement = $pdo->prepare($baseSql . '
                WHERE p.emri LIKE :patient_name OR p.mbiemri LIKE :patient_surname
                   OR m.emri LIKE :doctor_name OR m.mbiemri LIKE :doctor_surname OR t.statusi LIKE :status
                ORDER BY t.data_ora DESC, t.id_termini DESC');
            $pattern = '%' . $search . '%';
            $statement->execute([
                'patient_name' => $pattern, 'patient_surname' => $pattern,
                'doctor_name' => $pattern, 'doctor_surname' => $pattern, 'status' => $pattern,
            ]);
        } else {
            $statement = $pdo->query($baseSql . ' ORDER BY t.data_ora DESC, t.id_termini DESC');
        }
        $appointments = $statement->fetchAll();
    } catch (Throwable $exception) {
        error_log('Lista e termineve nuk u lexua: ' . $exception->getMessage());
        $databaseError = 'Nuk u arrit leximi i termineve. Kontrolloni skemën e databazës.';
        $pdo = null;
    }
}
$statusLabels = ['planifikuar' => 'Planifikuar', 'perfunduar' => 'Përfunduar', 'anuluar' => 'Anuluar'];
include __DIR__ . '/../includes/header.php';
?>
<section class="page-heading">
    <div><p class="eyebrow">ORGANIZIMI</p><h1>Terminet</h1><p>Shikoni, caktoni, ndryshoni ose anuloni terminet.</p></div>
    <a class="button button-primary" href="<?= h(APP_BASE_URL) ?>/terminet/shto.php"><span aria-hidden="true">+</span> Cakto termin</a>
</section>
<?php if ($flash): ?><div class="notice <?= $flash['type'] === 'success' ? 'notice-success' : 'notice-warning' ?>" role="status"><?= h($flash['message']) ?></div><?php endif; ?>
<?php if (!$pdo instanceof PDO): ?>
    <?php include __DIR__ . '/../includes/database_notice.php'; ?>
<?php else: ?>
<section class="content-card">
    <form class="search-form" action="<?= h(APP_BASE_URL) ?>/terminet/lista.php" method="get">
        <label class="sr-only" for="kerko">Kërko termin</label>
        <input id="kerko" name="kerko" type="search" value="<?= h($search) ?>" placeholder="Pacienti, mjeku ose statusi">
        <button class="button button-secondary" type="submit">Kërko</button>
        <?php if ($search !== ''): ?><a class="text-link" href="<?= h(APP_BASE_URL) ?>/terminet/lista.php">Pastro</a><?php endif; ?>
    </form>
    <div class="table-wrap"><table>
        <thead><tr><th>Data dhe ora</th><th>Pacienti</th><th>Mjeku</th><th>Statusi</th><th>Shënime</th><th class="actions-heading">Veprimet</th></tr></thead>
        <tbody>
        <?php if ($appointments): foreach ($appointments as $appointment): ?>
            <tr>
                <td><strong><?= h((new DateTimeImmutable($appointment['data_ora']))->format('d.m.Y H:i')) ?></strong><small class="muted">ID <?= h($appointment['id_termini']) ?></small></td>
                <td><?= h($appointment['pacient_emri'] . ' ' . $appointment['pacient_mbiemri']) ?></td>
                <td><?= h($appointment['mjek_emri'] . ' ' . $appointment['mjek_mbiemri']) ?><small class="muted"><?= h($appointment['specializimi']) ?></small></td>
                <td><span class="status-pill status-<?= h($appointment['statusi']) ?>"><?= h($statusLabels[$appointment['statusi']] ?? $appointment['statusi']) ?></span></td>
                <td><?= h($appointment['shenime'] ?: '—') ?></td>
                <td class="actions-cell">
                    <a class="text-link" href="<?= h(APP_BASE_URL) ?>/terminet/ndrysho.php?id=<?= h($appointment['id_termini']) ?>">Ndrysho</a>
                    <?php if ($appointment['statusi'] === 'planifikuar'): ?>
                    <form class="inline-form" action="<?= h(APP_BASE_URL) ?>/terminet/anulo.php" method="post" data-confirm="A dëshironi ta anuloni termin?">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= h($appointment['id_termini']) ?>">
                        <button class="link-button" type="submit">Anulo</button>
                    </form>
                    <?php endif; ?>
                    <form class="inline-form" action="<?= h(APP_BASE_URL) ?>/terminet/fshi.php" method="post" data-confirm="Fshirja është e përhershme. A dëshironi të vazhdoni?">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= h($appointment['id_termini']) ?>">
                        <button class="link-button danger-link" type="submit">Fshi</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; else: ?><tr><td class="empty-state" colspan="6"><?= $search !== '' ? 'Nuk u gjet termin me këtë kërkim.' : 'Nuk ka termine. Caktoni termin e parë.' ?></td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <p class="table-caption">Gjithsej <?= h(count($appointments)) ?> <?= count($appointments) === 1 ? 'termin' : 'termine' ?>. Anulimi e ruan rreshtin me statusin “anuluar”.</p>
</section>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
