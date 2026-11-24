<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_roles('admin', 'recepsionist', 'mjek');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Kjo kërkesë nuk mbështetet.');
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$newStatus = trim((string) ($_POST['statusi'] ?? ''));
$allowed = ['konfirmuar', 'ne_pritje', 'perfunduar'];

if (!$id || $id < 1 || !in_array($newStatus, $allowed, true) || !valid_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Kërkesa për ndryshimin e statusit nuk është e vlefshme.');
    redirect_to('/terminet/lista.php');
}
if (!$pdo instanceof PDO) {
    set_flash('error', 'Databaza nuk është e disponueshme.');
    redirect_to('/terminet/lista.php');
}

try {
    if (auth_user_has_role('mjek')) {
        $doctorId = (int) (current_auth_user()['doctor_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE termini SET statusi = :status WHERE id_termini = :id AND id_mjeku = :doctor_id AND statusi <> 'anuluar'");
        $stmt->execute(['status' => $newStatus, 'id' => $id, 'doctor_id' => $doctorId]);
    } else {
        $stmt = $pdo->prepare("UPDATE termini SET statusi = :status WHERE id_termini = :id AND statusi <> 'anuluar'");
        $stmt->execute(['status' => $newStatus, 'id' => $id]);
    }
    set_flash(
        $stmt->rowCount() > 0 ? 'success' : 'error',
        $stmt->rowCount() > 0 ? ('Statusi i terminit u përditësua në “' . appointment_status_label($newStatus) . '”.') : 'Termini nuk u përditësua.'
    );
} catch (Throwable $exception) {
    error_log('Ndryshimi i statusit të terminit dështoi: ' . $exception->getMessage());
    set_flash('error', 'Ndryshimi i statusit dështoi.');
}

redirect_to('/terminet/lista.php');
