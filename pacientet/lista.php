<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Pacientët';
$activePage = 'patients';
$patients = [];
$search = trim((string) ($_GET['kerko'] ?? ''));
$flash = take_flash();
$isDoctor = auth_user_has_role('mjek');
$doctorId = (int) (current_auth_user()['doctor_id'] ?? 0);

if ($pdo instanceof PDO) {
    try {
        $where = [];
        $params = [];
        if ($search !== '') {
            $pattern = '%' . $search . '%';
            $where[] = '(p.emri LIKE :name_search OR p.mbiemri LIKE :surname_search OR p.telefoni LIKE :phone_search OR p.nr_personal LIKE :personal_search)';
            $params['name_search'] = $pattern;
            $params['surname_search'] = $pattern;
            $params['phone_search'] = $pattern;
            $params['personal_search'] = $pattern;
        }
        if ($isDoctor) {
            $where[] = 'EXISTS (SELECT 1 FROM termini t WHERE t.id_pacienti = p.id_pacienti AND t.id_mjeku = :doctor_id)';
            $params['doctor_id'] = $doctorId;
        }
        $sql = 'SELECT p.id_pacienti, p.emri, p.mbiemri, p.datelindja, p.gjinia, p.grupi_gjakut, p.alergjite, p.telefoni, p.email FROM pacienti p';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.id_pacienti DESC';
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $patients = $statement->fetchAll();
    } catch (Throwable $exception) {
        error_log('Lista e pacientëve nuk u lexua: ' . $exception->getMessage());
        $databaseError = 'Nuk u arrit leximi i pacientëve. Kontrolloni nëse skema është importuar.';
        $pdo = null;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
    <div><p class="eyebrow">REGJISTRI I PACIENTËVE</p><h1><?= $isDoctor ? 'Pacientët e mi' : 'Pacientët' ?></h1><p><?= $isDoctor ? 'Pacientët që kanë termin me ju dhe qasje në kartelën e tyre.' : 'Kërkoni, shikoni profilin e plotë dhe menaxhoni të dhënat e pacientëve.' ?></p></div>
    <?php if (!$isDoctor): ?><div class="page-head-actions"><a class="button button-primary" href="<?= h(APP_BASE_URL) ?>/pacientet/shto.php"><span aria-hidden="true">+</span> Regjistro pacient</a></div><?php endif; ?>
</section>

<?php if ($flash): ?>
    <div class="notice <?= $flash['type'] === 'success' ? 'notice-success' : 'notice-warning' ?>" role="status"><?= h($flash['message']) ?></div>
<?php endif; ?>
<?php if (!$pdo instanceof PDO): ?>
    <?php include __DIR__ . '/../includes/database_notice.php'; ?>
<?php else: ?>
    <section class="content-card">
        <form class="search-form" action="<?= h(APP_BASE_URL) ?>/pacientet/lista.php" method="get">
            <label class="sr-only" for="kerko">Kërko sipas emrit, mbiemrit, telefonit ose nr. personal</label>
            <input id="kerko" name="kerko" type="search" value="<?= h($search) ?>" placeholder="Kërko sipas emrit, mbiemrit, telefonit ose nr. personal">
            <button class="button button-secondary" type="submit">Kërko</button>
            <?php if ($search !== ''): ?><a class="text-link" href="<?= h(APP_BASE_URL) ?>/pacientet/lista.php">Pastro</a><?php endif; ?>
        </form>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Pacienti</th><th>Mosha &amp; Gjaku</th><th>Telefoni &amp; Email</th><th>Alergji</th><th class="actions-heading">Veprimet</th></tr></thead>
                <tbody>
                <?php if ($patients): foreach ($patients as $patient): ?>
                    <?php $age = calculate_age($patient['datelindja'] ?? null); ?>
                    <tr>
                        <td>
                            <a class="text-link" href="<?= h(APP_BASE_URL) ?>/pacientet/profili.php?id=<?= h($patient['id_pacienti']) ?>">
                                <strong><?= h($patient['emri'] . ' ' . $patient['mbiemri']) ?></strong>
                            </a>
                            <small class="muted">ID <?= h($patient['id_pacienti']) ?></small>
                        </td>
                        <td class="tabular-nums">
                            <?= $age !== null ? h($age . ' vjeç') : '—' ?>
                            <?php if (!empty($patient['grupi_gjakut'])): ?><small class="muted">Gjaku: <?= h($patient['grupi_gjakut']) ?></small><?php endif; ?>
                        </td>
                        <td><?= h($patient['telefoni']) ?><small class="muted"><?= h($patient['email'] ?: '—') ?></small></td>
                        <td><?= !empty($patient['alergjite']) ? h($patient['alergjite']) : '—' ?></td>
                        <td class="actions-cell">
                            <a class="text-link" href="<?= h(APP_BASE_URL) ?>/pacientet/profili.php?id=<?= h($patient['id_pacienti']) ?>">Profili</a>
                            <?php if (!$isDoctor): ?>
                                <a class="text-link" href="<?= h(APP_BASE_URL) ?>/pacientet/ndrysho.php?id=<?= h($patient['id_pacienti']) ?>">Ndrysho</a>
                                <form class="inline-form" action="<?= h(APP_BASE_URL) ?>/pacientet/fshi.php" method="post" data-confirm="A dëshironi ta fshini këtë pacient?">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= h($patient['id_pacienti']) ?>">
                                    <button class="link-button danger-link" type="submit">Fshi</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td class="empty-state" colspan="5"><?= $search !== '' ? 'Nuk u gjet pacient me këtë kërkim.' : 'Lista është ende bosh. Regjistroni pacientin e parë.' ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="table-caption">Gjithsej <?= h(count($patients)) ?> <?= count($patients) === 1 ? 'pacient' : 'pacientë' ?><?= $search !== '' ? ' për kërkimin “' . h($search) . '”' : '' ?>.</p>
    </section>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
