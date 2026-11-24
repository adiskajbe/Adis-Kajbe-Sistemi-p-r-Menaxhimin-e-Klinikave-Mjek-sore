<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_roles('admin', 'recepsionist', 'pacient');

$pageTitle = 'Faturimi & Financat';
$activePage = 'billing';
$isPatient = auth_user_has_role('pacient');
$canManage = auth_user_has_role('admin', 'recepsionist');
$isAdmin = auth_user_has_role('admin');
$authUser = current_auth_user();
$patientId = (int) ($authUser['patient_id'] ?? 0);

$search = trim((string) ($_GET['kerko'] ?? ''));
$statusFilter = trim((string) ($_GET['statusi'] ?? ''));
$validStatuses = ['papaguar', 'pjeserisht', 'paguar', 'anuluar'];
if ($statusFilter !== '' && !in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}
$hasFilters = $search !== '' || $statusFilter !== '';

$invoices = [];
$summary = ['total_fatura' => 0.0, 'total_paguar' => 0.0, 'mbetja' => 0.0, 'numri' => 0];
$flash = take_flash();

if ($pdo instanceof PDO) {
    try {
        $where = [];
        $params = [];

        if ($isPatient) {
            if ($patientId < 1) {
                $where[] = '1 = 0';
            } else {
                $where[] = 'f.id_pacienti = :patient_id';
                $params['patient_id'] = $patientId;
            }
        }
        if ($statusFilter !== '') {
            $where[] = 'f.statusi = :status_filter';
            $params['status_filter'] = $statusFilter;
        }
        if ($search !== '') {
            $pattern = '%' . $search . '%';
            $where[] = '(f.nr_fatures LIKE :s_nr OR p.emri LIKE :s_emri OR p.mbiemri LIKE :s_mbiemri OR f.pershkrimi LIKE :s_desc)';
            $params += [
                's_nr' => $pattern,
                's_emri' => $pattern,
                's_mbiemri' => $pattern,
                's_desc' => $pattern,
            ];
        }

        $sql = 'SELECT f.*, p.emri AS pacient_emri, p.mbiemri AS pacient_mbiemri, p.telefoni
                FROM fatura f
                INNER JOIN pacienti p ON p.id_pacienti = f.id_pacienti'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY f.data_fatures DESC, f.id_fatura DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $invoices = $stmt->fetchAll();

        foreach ($invoices as $inv) {
            if ($inv['statusi'] !== 'anuluar') {
                $total = (float) $inv['shuma_totale'];
                $paid = (float) $inv['shuma_paguar'];
                $summary['total_fatura'] += $total;
                $summary['total_paguar'] += $paid;
                $summary['mbetja'] += max(0.0, $total - $paid);
            }
            $summary['numri']++;
        }
    } catch (Throwable $exception) {
        error_log('Leximi i faturave dështoi: ' . $exception->getMessage());
        $databaseError = 'Nuk u arrit leximi i faturave nga databaza.';
        $pdo = null;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
    <div>
        <p class="eyebrow"><?= $isPatient ? 'FINANCAT E MIA' : 'ARKËTIMI &amp; FATURIMI' ?></p>
        <h1><?= $isPatient ? 'Faturat e mia' : 'Faturimi &amp; Financat' ?></h1>
        <p><?= $isPatient ? 'Historiku i faturave dhe pagesave tuaja në klinikë.' : 'Menaxhimi i faturave, arkëtimeve dhe shërbimeve të faturuara.' ?></p>
    </div>
    <?php if ($canManage): ?>
        <div class="page-head-actions">
            <?php if ($isAdmin): ?>
                <a class="button button-secondary" href="<?= h(APP_BASE_URL) ?>/faturimi/sherbimet.php">Katalogu i shërbimeve</a>
            <?php endif; ?>
            <a class="button button-primary" href="<?= h(APP_BASE_URL) ?>/faturimi/shto.php"><span aria-hidden="true">+</span> Krijo faturë</a>
        </div>
    <?php endif; ?>
</section>

<?php if ($flash): ?>
    <div class="notice <?= $flash['type'] === 'success' ? 'notice-success' : 'notice-warning' ?>" role="status"><?= h($flash['message']) ?></div>
<?php endif; ?>

<?php if (!$pdo instanceof PDO): ?>
    <?php include __DIR__ . '/../includes/database_notice.php'; ?>
<?php else: ?>
    <section class="kpi-grid" aria-label="Përmbledhja financiare">
        <article class="kpi-card">
            <span class="kpi-label">Fatura të listuara</span>
            <strong class="kpi-value tabular-nums"><?= h($summary['numri']) ?></strong>
            <span class="kpi-meta">Sipas filtrit aktiv</span>
        </article>
        <article class="kpi-card">
            <span class="kpi-label">Vlera e faturuar</span>
            <strong class="kpi-value tabular-nums"><?= h(format_money($summary['total_fatura'])) ?></strong>
            <span class="kpi-meta">Pa përfshirë faturat e anuluara</span>
        </article>
        <article class="kpi-card">
            <span class="kpi-label">Shuma e paguar</span>
            <strong class="kpi-value tabular-nums"><?= h(format_money($summary['total_paguar'])) ?></strong>
            <span class="kpi-meta">Pagesa të regjistruara</span>
        </article>
        <article class="kpi-card">
            <span class="kpi-label">Mbetja për pagesë</span>
            <strong class="kpi-value tabular-nums"><?= h(format_money($summary['mbetja'])) ?></strong>
            <span class="kpi-meta">Balanci i hapur</span>
        </article>
    </section>

    <section class="content-card mt-section">
        <form class="appointment-filters" action="<?= h(APP_BASE_URL) ?>/faturimi/lista.php" method="get">
            <div class="filter-field filter-search">
                <label for="kerko">Kërko</label>
                <input id="kerko" name="kerko" type="search" value="<?= h($search) ?>" placeholder="<?= $isPatient ? 'Nr. i faturës ose shënimet' : 'Nr. faturës, emri i pacientit ose përshkrimi' ?>">
            </div>
            <div class="filter-field">
                <label for="statusi">Statusi</label>
                <select id="statusi" name="statusi">
                    <option value="">Të gjitha</option>
                    <?php foreach ($validStatuses as $st): ?>
                        <option value="<?= h($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= h(invoice_status_label($st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions">
                <button class="button button-secondary" type="submit">Filtro</button>
                <?php if ($hasFilters): ?><a class="filter-clear" href="<?= h(APP_BASE_URL) ?>/faturimi/lista.php">Pastro</a><?php endif; ?>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nr. Faturës</th>
                        <th>Data</th>
                        <?php if (!$isPatient): ?><th>Pacienti</th><?php endif; ?>
                        <th>Totali</th>
                        <th>Paguar</th>
                        <th>Mbetja</th>
                        <th>Statusi</th>
                        <th class="actions-heading">Veprimet</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($invoices): ?>
                    <?php foreach ($invoices as $inv): ?>
                        <?php $remaining = max(0.0, (float) $inv['shuma_totale'] - (float) $inv['shuma_paguar']); ?>
                        <tr>
                            <td class="tabular-nums"><strong><?= h($inv['nr_fatures']) ?></strong></td>
                            <td class="tabular-nums"><?= h(format_date_sq($inv['data_fatures'])) ?></td>
                            <?php if (!$isPatient): ?>
                                <td>
                                    <a class="text-link" href="<?= h(APP_BASE_URL) ?>/pacientet/profili.php?id=<?= h($inv['id_pacienti']) ?>">
                                        <?= h($inv['pacient_emri'] . ' ' . $inv['pacient_mbiemri']) ?>
                                    </a>
                                </td>
                            <?php endif; ?>
                            <td class="tabular-nums"><?= h(format_money($inv['shuma_totale'])) ?></td>
                            <td class="tabular-nums"><?= h(format_money($inv['shuma_paguar'])) ?></td>
                            <td class="tabular-nums"><strong><?= h(format_money($remaining)) ?></strong></td>
                            <td><span class="status-pill status-<?= h($inv['statusi']) ?>"><?= h(invoice_status_label($inv['statusi'])) ?></span></td>
                            <td class="actions-cell">
                                <a class="text-link" href="<?= h(APP_BASE_URL) ?>/faturimi/shiko.php?id=<?= h($inv['id_fatura']) ?>">Hap faturën</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td class="empty-state" colspan="<?= $isPatient ? 7 : 8 ?>"><?= $hasFilters ? 'Nuk u gjet faturë sipas filtrave.' : 'Nuk ka fatura të regjistruara.' ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
