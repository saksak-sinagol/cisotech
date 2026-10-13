<?php

function apply_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");

    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

function enforce_same_origin_policy(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        return;
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $expectedOrigin = $scheme . '://' . strtolower($host);
    $parsedOrigin = parse_url($origin);

    if (
        !is_array($parsedOrigin)
        || !isset($parsedOrigin['scheme'], $parsedOrigin['host'])
        || isset($parsedOrigin['user'])
        || isset($parsedOrigin['pass'])
        || isset($parsedOrigin['path'])
        || isset($parsedOrigin['query'])
        || isset($parsedOrigin['fragment'])
        || strtolower($origin) !== $expectedOrigin
    ) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Cross-origin requests are not allowed.');
    }
}

function request_string(array $source, string $name, int $maxLength, bool $required = true): ?string
{
    if (!array_key_exists($name, $source)) {
        return $required ? null : '';
    }

    if (!is_string($source[$name]) && !is_int($source[$name])) {
        return null;
    }

    $value = trim((string)$source[$name]);
    if (($required && $value === '') || strlen($value) > $maxLength) {
        return null;
    }

    return $value;
}

function request_strings_are_valid(array $source, array $limits): bool
{
    foreach ($limits as $name => $maxLength) {
        if (array_key_exists($name, $source) && request_string($source, $name, $maxLength, false) === null) {
            return false;
        }
    }
    return true;
}

function request_positive_int(array $source, string $name): ?int
{
    $value = $source[$name] ?? null;
    if (!is_int($value) && (!is_string($value) || !preg_match('/^[1-9][0-9]*$/D', $value))) {
        return null;
    }

    $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $validated === false ? null : $validated;
}

function request_nonnegative_int(array $source, string $name): ?int
{
    $value = $source[$name] ?? null;
    if (!is_int($value) && (!is_string($value) || !preg_match('/^(?:0|[1-9][0-9]*)$/D', $value))) {
        return null;
    }

    $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    return $validated === false ? null : $validated;
}

function valid_request_date(string $value): bool
{
    if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $value, $matches)) {
        return false;
    }

    return checkdate((int)$matches[2], (int)$matches[3], (int)$matches[1]);
}

function valid_request_time(string $value): bool
{
    return preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/D', $value) === 1;
}

function json_bad_request(string $message = 'Invalid request parameters.'): void
{
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

function consume_rate_limit(mysqli $conn, string $bucket, string $identity, int $limit, int $windowSeconds): array
{
    if ($limit < 1 || $windowSeconds < 1) {
        throw new InvalidArgumentException('Rate-limit thresholds must be positive integers.');
    }

    $now = time();
    $key = hash('sha256', $bucket . "\0" . $identity);
    $stmt = $conn->prepare(
        'INSERT INTO request_rate_limits (rate_key, window_started_at, request_count)
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE
           request_count = IF(window_started_at <= VALUES(window_started_at) - ?, 1, request_count + 1),
           window_started_at = IF(window_started_at <= VALUES(window_started_at) - ?, VALUES(window_started_at), window_started_at)'
    );
    if (!$stmt) {
        throw new RuntimeException('Could not prepare rate-limit counter update: ' . $conn->error);
    }
    $stmt->bind_param('siii', $key, $now, $windowSeconds, $windowSeconds);
    if (!$stmt->execute()) {
        throw new RuntimeException('Could not update rate-limit counter: ' . $stmt->error);
    }
    $stmt->close();

    $stmt = $conn->prepare('SELECT window_started_at, request_count FROM request_rate_limits WHERE rate_key = ?');
    if (!$stmt) {
        throw new RuntimeException('Could not prepare rate-limit counter lookup: ' . $conn->error);
    }
    $stmt->bind_param('s', $key);
    if (!$stmt->execute()) {
        throw new RuntimeException('Could not read rate-limit counter: ' . $stmt->error);
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        throw new RuntimeException('Rate-limit counter could not be read after it was updated.');
    }

    if ($now % 3600 === 0) {
        if (!$conn->query('DELETE FROM request_rate_limits WHERE window_started_at < ' . ($now - 86400))) {
            throw new RuntimeException('Could not clean up expired rate-limit counters: ' . $conn->error);
        }
    }

    $retryAfter = max(1, (int)$row['window_started_at'] + $windowSeconds - $now);
    return [
        'allowed' => (int)$row['request_count'] <= $limit,
        'retry_after' => $retryAfter,
    ];
}

function enforce_rate_limit(mysqli $conn, string $bucket, string $identity, int $limit, int $windowSeconds): void
{
    $result = consume_rate_limit($conn, $bucket, $identity, $limit, $windowSeconds);
    if ($result['allowed']) {
        return;
    }

    http_response_code(429);
    header('Retry-After: ' . $result['retry_after']);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Too many requests. Please wait before trying again.');
}

function enforce_login_rate_limit(mysqli $conn, string $username): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    enforce_rate_limit($conn, 'login', strtolower($username) . "\0" . $ip, 5, 900);
}

function enforce_sensitive_rate_limit(mysqli $conn): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !isset($_SESSION['user_id'])) {
        return;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $identity = (string)(int)$_SESSION['user_id'] . "\0" . $ip;
    enforce_rate_limit($conn, 'authenticated-write', $identity, 30, 60);
}
