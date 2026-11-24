<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_roles('admin');

header('Content-Type: application/json; charset=utf-8');

if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['error' => 'Databaza nuk është e disponueshme.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $monthlyRevenue = $pdo->query(
        "SELECT DATE_FORMAT(data_fatures, '%Y-%m') AS muaji,
                COALESCE(SUM(shuma_totale), 0) AS faturuar,
                COALESCE(SUM(shuma_paguar), 0) AS arketuar
         FROM fatura
         WHERE statusi <> 'anuluar'
           AND data_fatures >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY DATE_FORMAT(data_fatures, '%Y-%m')
         ORDER BY muaji ASC"
    )->fetchAll();

    $statusBreakdown = $pdo->query(
        'SELECT statusi, COUNT(*) AS numri FROM termini GROUP BY statusi'
    )->fetchAll(PDO::FETCH_KEY_PAIR);

    $topServices = $pdo->query(
        'SELECT pershkrimi, COUNT(*) AS raste, COALESCE(SUM(nentotali), 0) AS xhiro
         FROM fatura_artikuj
         GROUP BY pershkrimi
         ORDER BY xhiro DESC
         LIMIT 6'
    )->fetchAll();

    echo json_encode([
        'te_ardhurat_mujore' => $monthlyRevenue,
        'statuset_termineve' => $statusBreakdown,
        'sherbimet_kryesore' => $topServices,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('API analitika dështoi: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Nuk u arrit gjenerimi i analitikës.'], JSON_UNESCAPED_UNICODE);
}
