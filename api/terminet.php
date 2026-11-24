<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_roles('admin', 'mjek', 'recepsionist', 'pacient');

header('Content-Type: application/json; charset=utf-8');

if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['error' => 'Databaza nuk është e disponueshme.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$authUser = current_auth_user();
$role = (string) ($authUser['role'] ?? '');
$doctorId = (int) ($authUser['doctor_id'] ?? 0);
$patientId = (int) ($authUser['patient_id'] ?? 0);

$from = trim((string) ($_GET['nga'] ?? ''));
$to = trim((string) ($_GET['deri'] ?? ''));

try {
    $notesSelect = ($role === 'recepsionist' || $role === 'pacient') ? "'' AS shenime," : 't.shenime,';
    $sql = 'SELECT t.id_termini, t.id_pacienti, t.id_mjeku, t.data_ora, t.kohezgjatja_min, t.lloji_vizites, t.statusi, ' . $notesSelect . '
                   p.emri AS pacient_emri, p.mbiemri AS pacient_mbiemri,
                   m.emri AS mjek_emri, m.mbiemri AS mjek_mbiemri, m.specializimi
            FROM termini t
            INNER JOIN pacienti p ON p.id_pacienti = t.id_pacienti
            INNER JOIN mjeku m ON m.id_mjeku = t.id_mjeku';

    $where = [];
    $params = [];

    if ($role === 'mjek') {
        $where[] = 't.id_mjeku = :did';
        $params['did'] = $doctorId;
    } elseif ($role === 'pacient') {
        $where[] = 't.id_pacienti = :pid';
        $params['pid'] = $patientId;
    }

    if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $where[] = 't.data_ora >= :from_dt';
        $params['from_dt'] = $from . ' 00:00:00';
    }
    if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $where[] = 't.data_ora < DATE_ADD(:to_dt, INTERVAL 1 DAY)';
        $params['to_dt'] = $to;
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY t.data_ora ASC LIMIT 200';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    echo json_encode(['terminet' => $rows, 'total' => count($rows)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('API terminet dështoi: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Nuk u arrit leximi i termineve.'], JSON_UNESCAPED_UNICODE);
}
