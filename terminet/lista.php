<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Terminet';
$activePage = 'appointments';
$appointments = [];
$search = trim((string) ($_GET['kerko'] ?? ''));
$fromDate = trim((string) ($_GET['nga'] ?? ''));
$toDate = trim((string) ($_GET['deri'] ?? ''));
$doctorFilter = trim((string) ($_GET['mjeku'] ?? ''));
$statusFilter = (string) ($_GET['statusi'] ?? '');
$viewMode = (($_GET['pamja'] ?? 'lista') === 'java') ? 'java' : 'lista';
$filterError = '';
$viewNotice = '';

$validDate = static function (string $value): bool {
    if ($value === '') {
        return true;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    return $date !== false
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $date->format('Y-m-d') === $value;
};
if (!$validDate($fromDate)) {
    $fromDate = '';
    $filterError = 'Data “Nga” nuk është e vlefshme.';
}
if (!$validDate($toDate)) {
    $toDate = '';
    $filterError = 'Data “Deri” nuk është e vlefshme.';
}
if ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate) {
    $filterError = 'Data “Nga” duhet të jetë para datës “Deri”.';
}
if ($viewMode === 'java') {
    if ($fromDate === '' && $toDate === '') {
        $fromDate = (new DateTimeImmutable('monday this week'))->format('Y-m-d');
        $toDate = (new DateTimeImmutable($fromDate))->modify('+6 days')->format('Y-m-d');
    } elseif ($fromDate === '' && $toDate !== '') {
        $fromDate = (new DateTimeImmutable($toDate))->modify('-6 days')->format('Y-m-d');
    } elseif ($fromDate !== '' && $toDate === '') {
        $toDate = (new DateTimeImmutable($fromDate))->modify('+6 days')->format('Y-m-d');
    }
    if ($filterError === '' && $fromDate !== '' && $toDate !== '') {
        $rangeDays = (new DateTimeImmutable($fromDate))->diff(new DateTimeImmutable($toDate))->days;
        if ($rangeDays > 6) {
            $toDate = (new DateTimeImmutable($fromDate))->modify('+6 days')->format('Y-m-d');
            $viewNotice = 'Pamja javore kufizohet në shtatë ditë duke filluar nga data “Nga”.';
        }
    }
}
$validStatuses = ['planifikuar', 'konfirmuar', 'ne_pritje', 'perfunduar', 'anuluar'];
if ($statusFilter !== '' && !in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}
$flash = take_flash();
$isDoctor = auth_user_has_role('mjek');
$isReceptionist = auth_user_has_role('recepsionist');
$isPatient = auth_user_has_role('pacient');
$canManage = auth_user_has_role('admin', 'recepsionist');
$authUser = current_auth_user();
$doctorId = (int) ($authUser['doctor_id'] ?? 0);
$patientId = (int) ($authUser['patient_id'] ?? 0);
$doctors = [];
if ($isDoctor || $isPatient) {
    $doctorFilter = '';
} else {
    $doctorIdFilter = filter_var($doctorFilter, FILTER_VALIDATE_INT);
    if ($doctorFilter !== '' && ($doctorIdFilter === false || $doctorIdFilter < 1)) {
        $doctorFilter = '';
    }
}
$hasFilters = $search !== '' || $fromDate !== '' || $toDate !== '' || $doctorFilter !== '' || $statusFilter !== '';

if ($pdo instanceof PDO) {
    try {
        if (!$isDoctor && !$isPatient) {
            $doctors = $pdo->query('SELECT id_mjeku, emri, mbiemri FROM mjeku ORDER BY emri, mbiemri')->fetchAll();
        }
        $notesSelect = ($isReceptionist || $isPatient) ? "'' AS shenime," : 't.shenime,';
        $baseSql = 'SELECT t.id_termini, t.id_pacienti, t.id_mjeku, t.data_ora, t.kohezgjatja_min, t.lloji_vizites, t.arsyeja, t.statusi, ' . $notesSelect . '
                           p.emri AS pacient_emri, p.mbiemri AS pacient_mbiemri,
                           m.emri AS mjek_emri, m.mbiemri AS mjek_mbiemri, m.specializimi
                    FROM termini t
                    INNER JOIN pacienti p ON p.id_pacienti = t.id_pacienti
                    INNER JOIN mjeku m ON m.id_mjeku = t.id_mjeku';
        $where = [];
        $params = [];
        if ($isDoctor) {
            $where[] = 't.id_mjeku = :doctor_id';
            $params['doctor_id'] = $doctorId;
        }
        if ($isPatient) {
            $where[] = 't.id_pacienti = :patient_id';
            $params['patient_id'] = $patientId;
        }
        if ($filterError === '') {
            if ($fromDate !== '') {
                $where[] = 't.data_ora >= :date_from';
                $params['date_from'] = $fromDate . ' 00:00:00';
            }
            if ($toDate !== '') {
                $where[] = 't.data_ora < DATE_ADD(:date_to, INTERVAL 1 DAY)';
                $params['date_to'] = $toDate;
            }
        }
        if ($doctorFilter !== '') {
            $where[] = 't.id_mjeku = :filter_doctor_id';
            $params['filter_doctor_id'] = (int) $doctorFilter;
        }
        if ($statusFilter !== '') {
            $where[] = 't.statusi = :filter_status';
            $params['filter_status'] = $statusFilter;
        }
        if ($search !== '') {
            $pattern = '%' . $search . '%';
            $where[] = '(p.emri LIKE :patient_name OR p.mbiemri LIKE :patient_surname
                         OR m.emri LIKE :doctor_name OR m.mbiemri LIKE :doctor_surname
                         OR t.lloji_vizites LIKE :visit_type OR t.statusi LIKE :status)';
            $params += [
                'patient_name' => $pattern, 'patient_surname' => $pattern,
                'doctor_name' => $pattern, 'doctor_surname' => $pattern,
                'visit_type' => $pattern, 'status' => $pattern,
            ];
        }
        $sql = $baseSql . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . " ORDER BY CASE WHEN t.statusi IN ('planifikuar', 'konfirmuar', 'ne_pritje') AND t.data_ora >= CURDATE() THEN 0 ELSE 1 END,
                        CASE WHEN t.statusi IN ('planifikuar', 'konfirmuar', 'ne_pritje') AND t.data_ora >= CURDATE() THEN t.data_ora END ASC,
                        t.data_ora DESC, t.id_termini DESC";
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $appointments = $statement->fetchAll();
    } catch (Throwable $exception) {
        error_log('Lista e termineve nuk u lexua: ' . $exception->getMessage());
        $databaseError = 'Nuk u arrit leximi i termineve. Kontrolloni skemën e databazës.';
        $pdo = null;
    }
}

$calendarDays = [];
if ($viewMode === 'java') {
    $weekdayNames = [1 => 'E hënë', 2 => 'E martë', 3 => 'E mërkurë', 4 => 'E enjte', 5 => 'E premte', 6 => 'E shtunë', 7 => 'E diel'];
    $calendarStart = $fromDate !== '' && $filterError === ''
        ? new DateTimeImmutable($fromDate)
        : new DateTimeImmutable('monday this week');
    for ($offset = 0; $offset < 7; $offset++) {
        $date = $calendarStart->modify('+' . $offset . ' days');
        $key = $date->format('Y-m-d');
        $calendarDays[$key] = [
            'key' => $key,
            'label' => $weekdayNames[(int) $date->format('N')],
            'date' => $date->format('d.m.'),
            'is_today' => $date->format('Y-m-d') === (new DateTimeImmutable('today'))->format('Y-m-d'),
            'appointments' => [],
        ];
    }
    foreach ($appointments as $appointment) {
        $dateKey = substr((string) $appointment['data_ora'], 0, 10);
        if (isset($calendarDays[$dateKey])) {
            $calendarDays[$dateKey]['appointments'][] = $appointment;
        }
    }
}
$viewParams = array_filter([
    'kerko' => $search,
    'nga' => $fromDate,
    'deri' => $toDate,
    'mjeku' => $doctorFilter,
    'statusi' => $statusFilter,
], static fn ($value): bool => $value !== '');
$viewListUrl = APP_BASE_URL . '/terminet/lista.php?' . http_build_query($viewParams + ['pamja' => 'lista']);
$viewWeekUrl = APP_BASE_URL . '/terminet/lista.php?' . http_build_query($viewParams + ['pamja' => 'java']);
$tableColumns = $isPatient ? 4 : ($isReceptionist ? 5 : 6);
include __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
    <div><p class="eyebrow"><?= $isPatient ? 'VIZITAT E MIA' : 'ORGANIZIMI &amp; KALENDARI' ?></p><h1><?= $isPatient ? 'Terminet e mia' : ($isDoctor ? 'Orari im' : 'Terminet &amp; Kalendari') ?></h1><p><?= $isPatient ? 'Kontrolloni datën, orën, mjekun dhe statusin e vizitave tuaja.' : ($isDoctor ? 'Terminet dhe pacientët e lidhur me ju.' : 'Menaxhoni orarin ditor, check-in e pacientëve dhe kalendarin javor.') ?></p></div>
    <?php if ($canManage || ($isPatient && $patientId > 0)): ?><div class="page-head-actions"><a class="button button-primary" href="<?= h(APP_BASE_URL) ?>/terminet/shto.php"><span aria-hidden="true">+</span> <?= $isPatient ? 'Rezervo termin' : 'Cakto termin' ?></a></div><?php endif; ?>
</section>
<?php if ($flash): ?><div class="notice <?= $flash['type'] === 'success' ? 'notice-success' : 'notice-warning' ?>" role="status"><?= h($flash['message']) ?></div><?php endif; ?>
<?php if (!$pdo instanceof PDO): ?>
    <?php include __DIR__ . '/../includes/database_notice.php'; ?>
<?php else: ?>
<section class="content-card">
    <div class="list-toolbar">
        <div class="view-switch" role="group" aria-label="Ndrysho pamjen e termineve">
            <a class="view-option<?= $viewMode === 'lista' ? ' is-active' : '' ?>" href="<?= h($viewListUrl) ?>"<?= $viewMode === 'lista' ? ' aria-current="page"' : '' ?>>Lista</a>
            <a class="view-option<?= $viewMode === 'java' ? ' is-active' : '' ?>" href="<?= h($viewWeekUrl) ?>"<?= $viewMode === 'java' ? ' aria-current="page"' : '' ?>>Kalendari javor</a>
        </div>
        <span class="filter-summary tabular-nums"><?= h(count($appointments)) ?> <?= count($appointments) === 1 ? 'termin' : 'termine' ?></span>
    </div>
    <form class="appointment-filters<?= ($isDoctor || $isPatient) ? ' doctor-filters' : '' ?>" action="<?= h(APP_BASE_URL) ?>/terminet/lista.php" method="get">
        <input type="hidden" name="pamja" value="<?= h($viewMode) ?>">
        <div class="filter-field filter-search">
            <label for="kerko">Kërko</label>
            <input id="kerko" name="kerko" type="search" value="<?= h($search) ?>" placeholder="<?= $isPatient ? 'Mjeku ose lloji i vizitës' : 'Pacienti, mjeku ose lloji i vizitës' ?>">
        </div>
        <div class="filter-field">
            <label for="nga">Nga data</label>
            <input id="nga" name="nga" type="date" value="<?= h($fromDate) ?>">
        </div>
        <div class="filter-field">
            <label for="deri">Deri më</label>
            <input id="deri" name="deri" type="date" value="<?= h($toDate) ?>">
        </div>
        <?php if (!$isDoctor && !$isPatient): ?>
            <div class="filter-field">
                <label for="mjeku">Mjeku</label>
                <select id="mjeku" name="mjeku">
                    <option value="">Të gjithë mjekët</option>
                    <?php foreach ($doctors as $doctor): ?>
                        <option value="<?= h($doctor['id_mjeku']) ?>" <?= (string) $doctor['id_mjeku'] === $doctorFilter ? 'selected' : '' ?>><?= h($doctor['emri'] . ' ' . $doctor['mbiemri']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="filter-field">
            <label for="statusi">Statusi</label>
            <select id="statusi" name="statusi">
                <option value="">Të gjitha</option>
                <?php foreach ($validStatuses as $st): ?>
                    <option value="<?= h($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= h(appointment_status_label($st)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button class="button button-secondary" type="submit">Filtro</button>
            <?php if ($hasFilters || $filterError !== ''): ?><a class="filter-clear" href="<?= h(APP_BASE_URL) ?>/terminet/lista.php">Pastro</a><?php endif; ?>
        </div>
    </form>
    <?php if ($filterError !== ''): ?><div class="notice notice-warning filter-notice" role="alert"><?= h($filterError) ?></div><?php endif; ?>
    <?php if ($viewNotice !== ''): ?><div class="notice notice-info filter-notice" role="status"><?= h($viewNotice) ?></div><?php endif; ?>
    <?php if ($viewMode === 'lista'): ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Data dhe ora</th><?php if (!$isPatient): ?><th>Pacienti</th><?php endif; ?><th>Mjeku &amp; Lloji</th><th>Statusi</th><?php if (!$isReceptionist && !$isPatient): ?><th>Shënime</th><?php endif; ?><th class="actions-heading">Veprimet</th></tr></thead>
        <tbody>
        <?php if ($appointments): foreach ($appointments as $appointment): ?>
            <tr>
                <td class="tabular-nums"><strong><?= h(format_date_sq($appointment['data_ora'], 'd.m.Y H:i')) ?></strong><small class="muted"><?= h($appointment['kohezgjatja_min'] ?? 30) ?> min</small></td>
                <?php if (!$isPatient): ?><td><a class="text-link" href="<?= h(APP_BASE_URL) ?>/pacientet/profili.php?id=<?= h($appointment['id_pacienti']) ?>"><?= h($appointment['pacient_emri'] . ' ' . $appointment['pacient_mbiemri']) ?></a></td><?php endif; ?>
                <td><strong><?= h($appointment['mjek_emri'] . ' ' . $appointment['mjek_mbiemri']) ?></strong><small class="muted"><?= h($appointment['lloji_vizites'] ?: $appointment['specializimi']) ?></small></td>
                <td><span class="status-pill status-<?= h($appointment['statusi']) ?>"><?= h(appointment_status_label($appointment['statusi'])) ?></span></td>
                <?php if (!$isReceptionist && !$isPatient): ?><td><?= h($appointment['shenime'] ?: ($appointment['arsyeja'] ?: '—')) ?></td><?php endif; ?>
                <td class="actions-cell">
                    <?php if ($canManage): ?>
                        <?php if ($appointment['statusi'] === 'planifikuar'): ?>
                            <form class="inline-form" action="<?= h(APP_BASE_URL) ?>/terminet/statusi.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= h($appointment['id_termini']) ?>"><input type="hidden" name="statusi" value="konfirmuar">
                                <button class="link-button" type="submit">Konfirmo</button>
                            </form>
                        <?php elseif ($appointment['statusi'] === 'konfirmuar'): ?>
                            <form class="inline-form" action="<?= h(APP_BASE_URL) ?>/terminet/statusi.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= h($appointment['id_termini']) ?>"><input type="hidden" name="statusi" value="ne_pritje">
                                <button class="link-button" type="submit">Në pritje</button>
                            </form>
                        <?php endif; ?>
                        <?php if (auth_user_has_role('admin')): ?>
                            <a class="text-link" href="<?= h(APP_BASE_URL) ?>/terminet/ndrysho.php?id=<?= h($appointment['id_termini']) ?>">Ndrysho</a>
                        <?php endif; ?>
                        <a class="text-link" href="<?= h(APP_BASE_URL) ?>/faturimi/shto.php?id_pacienti=<?= h($appointment['id_pacienti']) ?>&id_termini=<?= h($appointment['id_termini']) ?>">Faturë</a>
                        <?php if (in_array($appointment['statusi'], ['planifikuar', 'konfirmuar', 'ne_pritje'], true)): ?>
                            <form class="inline-form" action="<?= h(APP_BASE_URL) ?>/terminet/anulo.php" method="post" data-confirm="A dëshironi ta anuloni termin?">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= h($appointment['id_termini']) ?>">
                                <button class="link-button danger-link" type="submit">Anulo</button>
                            </form>
                        <?php endif; ?>
                        <?php if (auth_user_has_role('admin')): ?>
                            <form class="inline-form" action="<?= h(APP_BASE_URL) ?>/terminet/fshi.php" method="post" data-confirm="Fshirja është e përhershme. A dëshironi të vazhdoni?">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= h($appointment['id_termini']) ?>">
                                <button class="link-button danger-link" type="submit">Fshi</button>
                            </form>
                        <?php endif; ?>
                    <?php elseif ($isDoctor): ?>
                        <?php if (in_array($appointment['statusi'], ['planifikuar', 'konfirmuar', 'ne_pritje'], true)): ?>
                            <a class="text-link" href="<?= h(APP_BASE_URL) ?>/kartela/shto.php?id_pacienti=<?= h($appointment['id_pacienti']) ?>&id_termini=<?= h($appointment['id_termini']) ?>">Shkruaj EHR</a>
                            <form class="inline-form" action="<?= h(APP_BASE_URL) ?>/terminet/statusi.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= h($appointment['id_termini']) ?>"><input type="hidden" name="statusi" value="perfunduar">
                                <button class="link-button" type="submit">Përfundo</button>
                            </form>
                        <?php else: ?>—<?php endif; ?>
                    <?php elseif ($isPatient): ?>
                        <?php if ($appointment['statusi'] === 'planifikuar'): ?>
                            <form class="inline-form" action="<?= h(APP_BASE_URL) ?>/terminet/anulo.php" method="post" data-confirm="A dëshironi ta anuloni këtë termin?">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= h($appointment['id_termini']) ?>">
                                <button class="link-button danger-link" type="submit">Anulo</button>
                            </form>
                        <?php else: ?>—<?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; else: ?><tr><td class="empty-state" colspan="<?= h($tableColumns) ?>"><?= $hasFilters ? 'Nuk u gjet termin për filtrat e zgjedhur.' : ($isPatient ? 'Nuk keni termine të regjistruara ende.' : 'Nuk ka termine. Caktoni termin e parë.') ?></td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?php else: ?>
    <div class="calendar-week" role="list" aria-label="Orari javor">
        <?php foreach ($calendarDays as $day): ?>
            <section class="calendar-day<?= $day['is_today'] ? ' is-today' : '' ?>" role="listitem">
                <h2><span><?= h($day['label']) ?></span><time datetime="<?= h($day['key']) ?>"><?= h($day['date']) ?></time></h2>
                <div class="calendar-appointments">
                    <?php foreach ($day['appointments'] as $appointment): ?>
                        <article class="calendar-appointment status-border-<?= h($appointment['statusi']) ?>">
                            <strong class="tabular-nums"><?= h(format_date_sq($appointment['data_ora'], 'H:i')) ?><?= $isPatient ? ' · Vizita juaj' : ' · ' . h($appointment['pacient_emri'] . ' ' . $appointment['pacient_mbiemri']) ?></strong>
                            <span><?= h($appointment['mjek_emri'] . ' ' . $appointment['mjek_mbiemri']) ?></span>
                            <span class="status-pill status-<?= h($appointment['statusi']) ?>"><?= h(appointment_status_label($appointment['statusi'])) ?></span>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$day['appointments']): ?><p class="calendar-empty">Pa termine</p><?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
