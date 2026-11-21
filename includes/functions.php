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

function current_auth_user(): ?array
{
    return isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])
        ? $_SESSION['auth_user']
        : null;
}

function auth_user_has_role(string ...$roles): bool
{
    $user = current_auth_user();
    return $user !== null && in_array((string) ($user['role'] ?? ''), $roles, true);
}

function require_roles(string ...$roles): void
{
    if (auth_user_has_role(...$roles)) {
        return;
    }
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="sq"><meta charset="utf-8"><title>Qasje e ndaluar</title>'
        . '<body><main><h1>Qasje e ndaluar</h1><p>Nuk keni leje për këtë modul.</p>'
        . '<a href="' . h(APP_BASE_URL) . '/index.php">Kthehu te paneli</a></main></body></html>';
    exit;
}

function password_policy_error(string $password): ?string
{
    if (mb_strlen($password, 'UTF-8') < 12 || strlen($password) > 72) {
        return 'Fjalëkalimi duhet të ketë të paktën 12 karaktere dhe jo më shumë se 72 bajte.';
    }
    $classes = 0;
    $classes += preg_match('/[a-z]/', $password) === 1 ? 1 : 0;
    $classes += preg_match('/[A-Z]/', $password) === 1 ? 1 : 0;
    $classes += preg_match('/[0-9]/', $password) === 1 ? 1 : 0;
    $classes += preg_match('/[^a-zA-Z0-9]/', $password) === 1 ? 1 : 0;
    if ($classes < 3) {
        return 'Përdorni të paktën 3 lloje karakteresh: shkronja të vogla/të mëdha, numra ose simbole.';
    }
    return null;
}

function auth_client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
}

function login_rate_limit_buckets(string $identity, string $ip): array
{
    $buckets = [];
    $normalizedIdentity = strtolower(trim($identity));
    if ($normalizedIdentity !== '') {
        $buckets[] = ['type' => 'identity', 'hash' => hash('sha256', $normalizedIdentity)];
    }
    if ($ip !== '') {
        $buckets[] = ['type' => 'ip', 'hash' => hash('sha256', $ip)];
    }
    return $buckets;
}

function login_is_rate_limited(PDO $pdo, string $identity, string $ip): bool
{
    $statement = $pdo->prepare(
        'SELECT 1 FROM login_rate_limits
         WHERE bucket_type = :bucket_type AND bucket_hash = :bucket_hash
           AND locked_until > CURRENT_TIMESTAMP LIMIT 1'
    );
    foreach (login_rate_limit_buckets($identity, $ip) as $bucket) {
        $statement->execute(['bucket_type' => $bucket['type'], 'bucket_hash' => $bucket['hash']]);
        if ($statement->fetchColumn()) {
            return true;
        }
    }
    return false;
}

function record_failed_login(PDO $pdo, string $identity, string $ip): void
{
    $buckets = login_rate_limit_buckets($identity, $ip);
    if (!$buckets) {
        return;
    }
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $insert = $pdo->prepare(
            'INSERT IGNORE INTO login_rate_limits (bucket_type, bucket_hash, failed_attempts, locked_until)
             VALUES (:bucket_type, :bucket_hash, 0, NULL)'
        );
        $select = $pdo->prepare(
            'SELECT failed_attempts, locked_until, (locked_until > CURRENT_TIMESTAMP) AS is_locked
             FROM login_rate_limits
             WHERE bucket_type = :bucket_type AND bucket_hash = :bucket_hash FOR UPDATE'
        );
        $update = $pdo->prepare(
            'UPDATE login_rate_limits SET failed_attempts = :failed_attempts,
             locked_until = IF(:is_locked = 1, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE), NULL),
             updated_at = CURRENT_TIMESTAMP
             WHERE bucket_type = :bucket_type AND bucket_hash = :bucket_hash'
        );
        foreach ($buckets as $bucket) {
            $params = ['bucket_type' => $bucket['type'], 'bucket_hash' => $bucket['hash']];
            $insert->execute($params);
            $select->execute($params);
            $current = $select->fetch();
            if (!$current) {
                continue;
            }
            if ((int) $current['is_locked'] === 1) {
                continue;
            }
            $attempts = $current['locked_until'] !== null ? 1 : ((int) $current['failed_attempts'] + 1);
            $attemptLimit = $bucket['type'] === 'ip' ? 20 : 5;
            $update->execute($params + ['failed_attempts' => $attempts, 'is_locked' => $attempts >= $attemptLimit ? 1 : 0]);
        }
        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function clear_login_rate_limits(PDO $pdo, string $identity, string $ip): void
{
    $statement = $pdo->prepare(
        'DELETE FROM login_rate_limits WHERE bucket_type = :bucket_type AND bucket_hash = :bucket_hash'
    );
    foreach (login_rate_limit_buckets($identity, $ip) as $bucket) {
        $statement->execute(['bucket_type' => $bucket['type'], 'bucket_hash' => $bucket['hash']]);
    }
}

function log_login_attempt(PDO $pdo, ?int $userId, string $identity, string $ip, string $status): void
{
    $identifier = mb_substr(trim($identity), 0, 190, 'UTF-8');
    $statement = $pdo->prepare(
        'INSERT INTO login_logs (id_perdorues, email_or_username, ip_address, status)
         VALUES (:user_id, :identifier, :ip_address, :status)'
    );
    $statement->execute([
        'user_id' => $userId,
        'identifier' => $identifier,
        'ip_address' => $ip,
        'status' => $status,
    ]);
}

function absolute_app_url(string $path): string
{
    $configuredUrl = trim((string) (getenv('APP_URL') ?: ''));
    if ($configuredUrl !== '') {
        $parts = parse_url($configuredUrl);
        if (is_array($parts) && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && !empty($parts['host']) && empty($parts['user']) && empty($parts['pass'])
            && empty($parts['query']) && empty($parts['fragment'])) {
            return rtrim($configuredUrl, '/') . '/' . ltrim($path, '/');
        }
    }
    return 'http://localhost' . APP_BASE_URL . '/' . ltrim($path, '/');
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
