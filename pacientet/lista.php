<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Pacientët';
$activePage = 'patients';
$patients = [];
$search = trim((string) ($_GET['kerko'] ?? ''));
$flash = take_flash();

if ($pdo instanceof PDO) {
    try {
        if ($search !== '') {
            $statement = $pdo->prepare(
                'SELECT id_pacienti, emri, mbiemri, telefoni, email FROM pacienti
                 WHERE emri LIKE :name_search OR mbiemri LIKE :surname_search
                 ORDER BY id_pacienti DESC'
            );
            $pattern = '%' . $search . '%';
            $statement->execute(['name_search' => $pattern, 'surname_search' => $pattern]);
        } else {
            $statement = $pdo->query(
                'SELECT id_pacienti, emri, mbiemri, telefoni, email FROM pacienti
                 ORDER BY id_pacienti DESC'
            );
        }
        $patients = $statement->fetchAll();
    } catch (Throwable $exception) {
        error_log('Lista e pacientëve nuk u lexua: ' . $exception->getMessage());
        $databaseError = 'Nuk u arrit leximi i pacientëve. Kontrolloni nëse skema është importuar.';
        $pdo = null;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<section class="page-heading">
    <div><p class="eyebrow">REGJISTRI</p><h1>Pacientët</h1><p>Shikoni, kërkoni dhe përditësoni të dhënat bazë të pacientëve.</p></div>
    <a class="button button-primary" href="<?= h(APP_BASE_URL) ?>/pacientet/shto.php"><span aria-hidden="true">+</span> Regjistro pacient</a>
</section>

<?php if ($flash): ?>
    <div class="notice <?= $flash['type'] === 'success' ? 'notice-success' : 'notice-warning' ?>" role="status"><?= h($flash['message']) ?></div>
<?php endif; ?>
<?php if (!$pdo instanceof PDO): ?>
    <?php include __DIR__ . '/../includes/database_notice.php'; ?>
<?php else: ?>
    <section class="content-card">
        <form class="search-form" action="<?= h(APP_BASE_URL) ?>/pacientet/lista.php" method="get">
            <label class="sr-only" for="kerko">Kërko sipas emrit ose mbiemrit</label>
            <input id="kerko" name="kerko" type="search" value="<?= h($search) ?>" placeholder="Kërko sipas emrit ose mbiemrit">
            <button class="button button-secondary" type="submit">Kërko</button>
            <?php if ($search !== ''): ?><a class="text-link" href="<?= h(APP_BASE_URL) ?>/pacientet/lista.php">Pastro</a><?php endif; ?>
        </form>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Pacienti</th><th>Telefoni</th><th>Email</th><th class="actions-heading">Veprimet</th></tr></thead>
                <tbody>
                <?php if ($patients): foreach ($patients as $patient): ?>
                    <tr>
                        <td><strong><?= h($patient['emri'] . ' ' . $patient['mbiemri']) ?></strong><small class="muted">ID <?= h($patient['id_pacienti']) ?></small></td>
                        <td><?= h($patient['telefoni']) ?></td>
                        <td><?= h($patient['email'] ?: '—') ?></td>
                        <td class="actions-cell">
                            <a class="text-link" href="<?= h(APP_BASE_URL) ?>/pacientet/ndrysho.php?id=<?= h($patient['id_pacienti']) ?>">Ndrysho</a>
                            <form class="inline-form" action="<?= h(APP_BASE_URL) ?>/pacientet/fshi.php" method="post" data-confirm="A dëshironi ta fshini këtë pacient?">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= h($patient['id_pacienti']) ?>">
                                <button class="link-button danger-link" type="submit">Fshi</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td class="empty-state" colspan="4"><?= $search !== '' ? 'Nuk u gjet pacient me këtë kërkim.' : 'Lista është ende bosh. Regjistroni pacientin e parë.' ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="table-caption">Gjithsej <?= h(count($patients)) ?> <?= count($patients) === 1 ? 'pacient' : 'pacientë' ?><?= $search !== '' ? ' për kërkimin “' . h($search) . '”' : '' ?>.</p>
    </section>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>