<?php
declare(strict_types=1);

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect_to(string $path): void
{
    header('Location: ' . APP_BASE_URL . $path, true, 303);
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    if (!isset($_SESSION['flash_message'])) {
        return null;
    }

    $flash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function valid_csrf_token($submittedToken): bool
{
    return is_string($submittedToken)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submittedToken);
}

function valid_patient_fields(array $input): array
{
    $values = [
        'emri' => trim((string) ($input['emri'] ?? '')),
        'mbiemri' => trim((string) ($input['mbiemri'] ?? '')),
        'telefoni' => trim((string) ($input['telefoni'] ?? '')),
        'email' => trim((string) ($input['email'] ?? '')),
    ];
    $errors = [];

    if ($values['emri'] === '' || mb_strlen($values['emri']) > 100) {
        $errors[] = 'Emri është i detyrueshëm dhe duhet të ketë deri në 100 karaktere.';
    }
    if ($values['mbiemri'] === '' || mb_strlen($values['mbiemri']) > 100) {
        $errors[] = 'Mbiemri është i detyrueshëm dhe duhet të ketë deri në 100 karaktere.';
    }
    if ($values['telefoni'] === '' || mb_strlen($values['telefoni']) > 30) {
        $errors[] = 'Numri i telefonit është i detyrueshëm dhe duhet të ketë deri në 30 karaktere.';
    }
    if ($values['email'] !== '' && (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 190)) {
        $errors[] = 'Shkruani një adresë email të vlefshme, deri në 190 karaktere.';
    }

    return [$values, $errors];
}

function valid_doctor_fields(array $input): array
{
    $values = [
        'emri' => trim((string) ($input['emri'] ?? '')),
        'mbiemri' => trim((string) ($input['mbiemri'] ?? '')),
        'specializimi' => trim((string) ($input['specializimi'] ?? '')),
        'telefoni' => trim((string) ($input['telefoni'] ?? '')),
        'email' => trim((string) ($input['email'] ?? '')),
    ];
    $errors = [];

    if ($values['emri'] === '' || mb_strlen($values['emri']) > 100) {
        $errors[] = 'Emri është i detyrueshëm dhe duhet të ketë deri në 100 karaktere.';
    }
    if ($values['mbiemri'] === '' || mb_strlen($values['mbiemri']) > 100) {
        $errors[] = 'Mbiemri është i detyrueshëm dhe duhet të ketë deri në 100 karaktere.';
    }
    if ($values['specializimi'] === '' || mb_strlen($values['specializimi']) > 150) {
        $errors[] = 'Specializimi është i detyrueshëm dhe duhet të ketë deri në 150 karaktere.';
    }
    if ($values['telefoni'] === '' || mb_strlen($values['telefoni']) > 30) {
        $errors[] = 'Numri i telefonit është i detyrueshëm dhe duhet të ketë deri në 30 karaktere.';
    }
    if ($values['email'] !== '' && (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 190)) {
        $errors[] = 'Shkruani një adresë email të vlefshme, deri në 190 karaktere.';
    }

    return [$values, $errors];
}

function valid_appointment_fields(array $input): array
{
    $patientId = filter_var((string) ($input['id_pacienti'] ?? ''), FILTER_VALIDATE_INT);
    $doctorId = filter_var((string) ($input['id_mjeku'] ?? ''), FILTER_VALIDATE_INT);
    $values = [
        'id_pacienti' => $patientId !== false && $patientId > 0 ? (int) $patientId : 0,
        'id_mjeku' => $doctorId !== false && $doctorId > 0 ? (int) $doctorId : 0,
        'data_ora' => trim((string) ($input['data_ora'] ?? '')),
        'statusi' => trim((string) ($input['statusi'] ?? 'planifikuar')),
        'shenime' => trim((string) ($input['shenime'] ?? '')),
    ];
    $errors = [];

    if ($values['id_pacienti'] < 1) {
        $errors[] = 'Zgjidhni një pacient të vlefshëm.';
    }
    if ($values['id_mjeku'] < 1) {
        $errors[] = 'Zgjidhni një mjek të vlefshëm.';
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $values['data_ora']);
    $dateErrors = DateTimeImmutable::getLastErrors();
    $dateIsValid = $date !== false
        && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
        && $date->format('Y-m-d\\TH:i') === $values['data_ora'];
    if (!$dateIsValid) {
        $errors[] = 'Zgjidhni një datë dhe orë të vlefshme.';
    }

    $statuses = ['planifikuar', 'perfunduar', 'anuluar'];
    if (!in_array($values['statusi'], $statuses, true)) {
        $errors[] = 'Statusi i terminit nuk është i vlefshëm.';
    } elseif ($dateIsValid && $values['statusi'] === 'planifikuar' && $date <= new DateTimeImmutable('now')) {
        $errors[] = 'Termini i planifikuar nuk mund të caktohet në të kaluarën.';
    }
    if (mb_strlen($values['shenime']) > 2000) {
        $errors[] = 'Shënimet duhet të kenë deri në 2000 karaktere.';
    }

    return [$values, $errors];
}

function patient_exists(PDO $pdo, int $id): bool
{
    $statement = $pdo->prepare('SELECT 1 FROM pacienti WHERE id_pacienti = :id');
    $statement->execute(['id' => $id]);
    return (bool) $statement->fetchColumn();
}

function doctor_exists(PDO $pdo, int $id): bool
{
    $statement = $pdo->prepare('SELECT 1 FROM mjeku WHERE id_mjeku = :id');
    $statement->execute(['id' => $id]);
    return (bool) $statement->fetchColumn();
}

function appointment_time_conflicts(PDO $pdo, int $doctorId, string $dateTime, ?int $exceptId = null): bool
{
    $sql = "SELECT COUNT(*) FROM termini
            WHERE id_mjeku = :doctor AND data_ora = :date_time AND statusi = 'planifikuar'";
    $params = ['doctor' => $doctorId, 'date_time' => $dateTime];
    if ($exceptId !== null) {
        $sql .= ' AND id_termini <> :except_id';
        $params['except_id'] = $exceptId;
    }
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return (int) $statement->fetchColumn() > 0;
}

function appointment_datetime_for_database(string $value): string
{
    return str_replace('T', ' ', $value) . ':00';
}

function appointment_datetime_for_input(string $value): string
{
    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
    return $date ? $date->format('Y-m-d\\TH:i') : '';
}