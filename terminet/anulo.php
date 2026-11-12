<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Kjo kërkesë nuk mbështetet.');
}
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1 || !valid_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Kërkesa për anulim nuk është e vlefshme.');
    redirect_to('/terminet/lista.php');
}
if (!$pdo instanceof PDO) {
    set_flash('error', 'Databaza nuk është e disponueshme.');
    redirect_to('/terminet/lista.php');
}
try {
    $statement = $pdo->prepare("UPDATE termini SET statusi = 'anuluar' WHERE id_termini = :id AND statusi = 'planifikuar'");
    $statement->execute(['id' => $id]);
    $cancelled = $statement->rowCount() > 0;
    set_flash($cancelled ? 'success' : 'error', $cancelled ? 'Termini u anulua.' : 'Termini nuk u gjet ose nuk është më i planifikuar.');
} catch (Throwable $exception) {
    error_log('Anulimi i terminit dështoi: ' . $exception->getMessage());
    set_flash('error', 'Termini nuk mund të anulohej. Provoni përsëri.');
}
redirect_to('/terminet/lista.php');
