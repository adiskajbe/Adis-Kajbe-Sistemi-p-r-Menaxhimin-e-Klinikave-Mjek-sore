<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_roles('admin', 'mjek', 'pacient');

$pageTitle = 'Kartela mjekësore (EHR)';
$activePage = 'ehr';
$flash = take_flash();
$search = trim((string) ($_GET['kerko'] ?? ''));
$isDoctor = auth_user_has_role('mjek');
$isPatient = auth_user_has_role('pacient');
$authUser = current_auth_user();
$doctorId = (int) ($authUser['doctor_id'] ?? 0);
$patientId = (int) ($authUser['patient_id'] ?? 0);
$records = [];

if ($pdo instanceof PDO) {
    try {
        $where = [];
        $params = [];
        if ($isDoctor) {
            $where[] = 'k.id_mjeku = :doctor_id';
            $params['doctor_id'] = $doctorId;
        } elseif ($isPatient) {
            $where[] = 'k.id_pacienti = :patient_id';
            $params['patient_id'] = $patientId;
        }
        if ($search !== '') {
            $pattern = '%' . $search . '%';
            $where[] = '(p.emri LIKE :p_name OR p.mbiemri LIKE :p_surname OR k.diagnoza LIKE :diag OR k.kodi_icd LIKE :icd)';
            $params += [
                'p_name' => $pattern,
                'p_surname' => $pattern,
                'diag' => $pattern,
                'icd' => $pattern,
            ];
        }
        $sql = 'SELECT k.id_kartela, k.id_pacienti, k.data_vizites, k.diagnoza, k.kodi_icd, k.presioni_gjakut, k.pulsi, k.temperatura,
                       p.emri AS pacient_emri, p.mbiemri AS pacient_mbiemri,
                       m.emri AS mjek_emri, m.mbiemri AS mjek_mbiemri
                FROM kartela_mjekesore k
                INNER JOIN pacienti p ON p.id_pacienti = k.id_pacienti
                LEFT JOIN mjeku m ON m.id_mjeku = k.id_mjeku';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY k.data_vizites DESC, k.id_kartela DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll();
    } catch (Throwable $exception) {
        error_log('Lista e kartelave nuk u lexua: ' . $exception->getMessage());
        $databaseError = 'Nuk u arrit leximi i kartelave mjekësore.';
        $pdo = null;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
    <div>
        <p class="eyebrow">DOKUMENTIMI KLINIK (EHR)</p>
        <h1><?= $isPatient ? 'Historia ime mjekësore' : 'Kartela mjekësore (EHR)' ?></h1>
        <p><?= $isPatient ? 'Shikoni diagnozat, shenjat vitale dhe recetat e vizitave tuaja.' : 'Regjistrat klinikë të vizitave, shenjat vitale, diagnozat ICD dhe terapitë.' ?></p>
    </div>
    <?php if (!$isPatient): ?>
        <div class="page-head-actions">
            <a class="button button-primary" href="<?= h(APP_BASE_URL) ?>/kartela/shto.php"><span aria-hidden="true">+</span> Shkruaj vizitë EHR</a>
        </div>
    <?php endif; ?>
</section>

<?php if ($flash): ?><div class="notice <?= $flash['type'] === 'success' ? 'notice-success' : 'notice-warning' ?>" role="status"><?= h($flash['message']) ?></div><?php endif; ?>
<?php if (!$pdo instanceof PDO): ?>
    <?php include __DIR__ . '/../includes/database_notice.php'; ?>
<?php else: ?>
<section class="content-card">
    <form class="search-form" action="<?= h(APP_BASE_URL) ?>/kartela/lista.php" method="get">
        <label class="sr-only" for="kerko">Kërko sipas pacientit, diagnozës ose kodit ICD</label>
        <input id="kerko" name="kerko" type="search" value="<?= h($search) ?>" placeholder="<?= $isPatient ? 'Kërko diagnozë ose kod ICD' : 'Kërko pacientin, diagnozën ose kodin ICD' ?>">
        <button class="button button-secondary" type="submit">Kërko</button>
        <?php if ($search !== ''): ?><a class="text-link" href="<?= h(APP_BASE_URL) ?>/kartela/lista.php">Pastro</a><?php endif; ?>
    </form>
    <div class="table-wrap"><table>
        <thead><tr><th>Data e vizitës</th><?php if (!$isPatient): ?><th>Pacienti</th><?php endif; ?><th>Diagnoza &amp; ICD</th><th>Shenjat vitale</th><th>Mjeku</th><th class="actions-heading">Veprimet</th></tr></thead>
        <tbody>
        <?php if ($records): foreach ($records as $rec): ?>
            <tr>
                <td class="tabular-nums"><strong><?= h(format_date_sq($rec['data_vizites'], 'd.m.Y H:i')) ?></strong></td>
                <?php if (!$isPatient): ?>
                    <td><a class="text-link" href="<?= h(APP_BASE_URL) ?>/pacientet/profili.php?id=<?= h($rec['id_pacienti']) ?>"><?= h($rec['pacient_emri'] . ' ' . $rec['pacient_mbiemri']) ?></a></td>
                <?php endif; ?>
                <td><strong><?= h($rec['diagnoza']) ?></strong><?= $rec['kodi_icd'] ? '<small class="muted">ICD: ' . h($rec['kodi_icd']) . '</small>' : '' ?></td>
                <td class="tabular-nums">
                    <?php
                    $vitals = [];
                    if ($rec['presioni_gjakut']) { $vitals[] = 'TA ' . $rec['presioni_gjakut']; }
                    if ($rec['pulsi']) { $vitals[] = $rec['pulsi'] . ' bpm'; }
                    if ($rec['temperatura']) { $vitals[] = $rec['temperatura'] . ' °C'; }
                    echo h($vitals ? implode(' · ', $vitals) : '—');
                    ?>
                </td>
                <td><?= h($rec['mjek_emri'] ? ($rec['mjek_emri'] . ' ' . $rec['mjek_mbiemri']) : '—') ?></td>
                <td class="actions-cell">
                    <a class="text-link" href="<?= h(APP_BASE_URL) ?>/kartela/shiko.php?id=<?= h($rec['id_kartela']) ?>">Shiko</a>
                    <?php if (!$isPatient): ?><a class="text-link" href="<?= h(APP_BASE_URL) ?>/kartela/ndrysho.php?id=<?= h($rec['id_kartela']) ?>">Ndrysho</a><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td class="empty-state" colspan="<?= $isPatient ? 5 : 6 ?>"><?= $search !== '' ? 'Nuk u gjet asnjë kartelë me këtë kërkim.' : 'Nuk ka shënime mjekësore të regjistruara.' ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
    <p class="table-caption">Gjithsej <?= h(count($records)) ?> <?= count($records) === 1 ? 'kartelë' : 'kartela' ?>.</p>
</section>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
