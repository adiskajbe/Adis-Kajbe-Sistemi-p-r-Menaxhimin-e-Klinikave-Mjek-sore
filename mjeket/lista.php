<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Mjekët';
$activePage = 'doctors';
$doctors = [];
$search = trim((string) ($_GET['kerko'] ?? ''));
$flash = take_flash();

if ($pdo instanceof PDO) {
    try {
        if ($search !== '') {
            $statement = $pdo->prepare(
                'SELECT id_mjeku, emri, mbiemri, specializimi, telefoni, email FROM mjeku
                 WHERE emri LIKE :name OR mbiemri LIKE :surname OR specializimi LIKE :specialty
                 ORDER BY id_mjeku DESC'
            );
            $pattern = '%' . $search . '%';
            $statement->execute(['name' => $pattern, 'surname' => $pattern, 'specialty' => $pattern]);
        } else {
            $statement = $pdo->query(
                'SELECT id_mjeku, emri, mbiemri, specializimi, telefoni, email FROM mjeku
                 ORDER BY id_mjeku DESC'
            );
        }
        $doctors = $statement->fetchAll();
    } catch (Throwable $exception) {
        error_log('Lista e mjekëve nuk u lexua: ' . $exception->getMessage());
        $databaseError = 'Nuk u arrit leximi i mjekëve. Kontrolloni skemën e databazës.';
        $pdo = null;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<section class="page-heading">
    <div><p class="eyebrow">REGJISTRI</p><h1>Mjekët</h1><p>Regjistroni dhe mirëmbani të dhënat e mjekëve.</p></div>
    <a class="button button-primary" href="<?= h(APP_BASE_URL) ?>/mjeket/shto.php"><span aria-hidden="true">+</span> Regjistro mjek</a>
</section>
<?php if ($flash): ?><div class="notice <?= $flash['type'] === 'success' ? 'notice-success' : 'notice-warning' ?>" role="status"><?= h($flash['message']) ?></div><?php endif; ?>
<?php if (!$pdo instanceof PDO): ?>
    <?php include __DIR__ . '/../includes/database_notice.php'; ?>
<?php else: ?>
<section class="content-card">
    <form class="search-form" action="<?= h(APP_BASE_URL) ?>/mjeket/lista.php" method="get">
        <label class="sr-only" for="kerko">Kërko mjek</label>
        <input id="kerko" name="kerko" type="search" value="<?= h($search) ?>" placeholder="Emri, mbiemri ose specializimi">
        <button class="button button-secondary" type="submit">Kërko</button>
        <?php if ($search !== ''): ?><a class="text-link" href="<?= h(APP_BASE_URL) ?>/mjeket/lista.php">Pastro</a><?php endif; ?>
    </form>
    <div class="table-wrap"><table>
        <thead><tr><th>Mjeku</th><th>Specializimi</th><th>Telefoni</th><th>Email</th><th class="actions-heading">Veprimet</th></tr></thead>
        <tbody>
        <?php if ($doctors): foreach ($doctors as $doctor): ?>
            <tr>
                <td><strong><?= h($doctor['emri'] . ' ' . $doctor['mbiemri']) ?></strong><small class="muted">ID <?= h($doctor['id_mjeku']) ?></small></td>
                <td><?= h($doctor['specializimi']) ?></td><td><?= h($doctor['telefoni']) ?></td><td><?= h($doctor['email'] ?: '—') ?></td>
                <td class="actions-cell">
                    <a class="text-link" href="<?= h(APP_BASE_URL) ?>/mjeket/ndrysho.php?id=<?= h($doctor['id_mjeku']) ?>">Ndrysho</a>
                    <form class="inline-form" action="<?= h(APP_BASE_URL) ?>/mjeket/fshi.php" method="post" data-confirm="A dëshironi ta fshini këtë mjek?">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= h($doctor['id_mjeku']) ?>">
                        <button class="link-button danger-link" type="submit">Fshi</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; else: ?><tr><td class="empty-state" colspan="5"><?= $search !== '' ? 'Nuk u gjet mjek me këtë kërkim.' : 'Lista është bosh. Regjistroni mjekun e parë.' ?></td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <p class="table-caption">Gjithsej <?= h(count($doctors)) ?> <?= count($doctors) === 1 ? 'mjek' : 'mjekë' ?>.</p>
</section>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
