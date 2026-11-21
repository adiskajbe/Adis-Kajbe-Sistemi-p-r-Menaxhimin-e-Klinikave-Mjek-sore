<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/bootstrap.php';

if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['error' => 'Databaza nuk është e disponueshme.']);
    exit;
}

try {
    $sql = 'SELECT t.id_termini, t.data_ora, t.statusi,
                   p.emri AS pacient_emri, p.mbiemri AS pacient_mbiemri,
                   m.emri AS mjek_emri, m.mbiemri AS mjek_mbiemri
            FROM termini t
            INNER JOIN pacienti p ON p.id_pacienti = t.id_pacienti
            INNER JOIN mjeku m ON m.id_mjeku = t.id_mjeku';
    if (auth_user_has_role('mjek')) {
        $sql .= ' WHERE t.id_mjeku = :doctor_id';
        $statement = $pdo->prepare($sql . ' ORDER BY t.data_ora DESC');
        $statement->execute(['doctor_id' => (int) (current_auth_user()['doctor_id'] ?? 0)]);
    } else {
        $statement = $pdo->query($sql . ' ORDER BY t.data_ora DESC');
    }
    $appointments = $statement->fetchAll();
    http_response_code(200);
    echo json_encode(['status' => 'success', 'count' => count($appointments), 'data' => $appointments], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $exception) {
    error_log('API Error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Ndodhi një gabim gjatë leximit të të dhënave.']);
}
