<?php

function app_request_id(): string
{
    static $requestId;
    if ($requestId === null) {
        $requestId = bin2hex(random_bytes(16));
    }
    return $requestId;
}

function app_error_log_record(
    string $level,
    string $event,
    string $errorType,
    string $file,
    int $line,
    ?int $errorCode = null
): array
{
    return [
        'timestamp' => gmdate('c'),
        'level' => $level,
        'event' => $event,
        'request_id' => app_request_id(),
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        'route' => basename($_SERVER['SCRIPT_NAME'] ?? 'unknown'),
        'user_id' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
        'client_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'error_type' => $errorType,
        'error_code' => $errorCode,
        'file' => $file,
        'line' => $line,
    ];
}

function app_log_error(
    string $level,
    string $event,
    string $errorType,
    string $file,
    int $line,
    ?int $errorCode = null
): void
{
    $record = app_error_log_record($level, $event, $errorType, $file, $line, $errorCode);
    $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    error_log($json === false ? '{"level":"error","event":"log_encoding_failed"}' : $json);
}

function app_request_expects_json(): bool
{
    $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    return str_contains($accept, 'application/json')
        || ($script === 'deployments.php' && isset($_GET['ajax']))
        || (
            $script === 'deployments.php'
            && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && ($_POST['action'] ?? '') === 'submit_deployment_cart'
        );
}

function app_render_server_error(): void
{
    if (headers_sent()) {
        return;
    }

    http_response_code(500);
    $requestId = app_request_id();
    if (app_request_expects_json()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => 'We could not complete your request. Please try again later.',
            'request_id' => $requestId,
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        return;
    }

    header('Content-Type: text/html; charset=utf-8');
    $safeRequestId = htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">' .
        '<meta name="viewport" content="width=device-width, initial-scale=1.0">' .
        '<title>Request could not be completed</title><style>' .
        'body{font:16px/1.5 system-ui,sans-serif;background:#f5f6f8;color:#242833;margin:0;padding:8vh 20px}' .
        'main{max-width:560px;margin:auto;padding:32px;background:#fff;border:1px solid #d9dde5;border-radius:10px}' .
        'h1{font-size:1.4rem;margin-top:0}p{color:#535b69}.request-id{font:13px monospace;color:#687080}' .
        'a{color:#28659b}</style></head><body><main>' .
        '<h1>We could not complete your request</h1>' .
        '<p>An unexpected problem occurred. Please try again. If the problem continues, contact your administrator and provide this reference number.</p>' .
        '<p class="request-id">Reference: ' . $safeRequestId . '</p>' .
        '<p><a href="login.php">Return to sign in</a></p>' .
        '</main></body></html>';
}

function app_handle_uncaught_exception(Throwable $exception): void
{
    app_log_error(
        'error',
        'uncaught_exception',
        get_class($exception),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getCode()
    );
    app_render_server_error();
}

function app_handle_php_error(int $severity, string $message, string $file, int $line): bool
{
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException('PHP runtime error', 0, $severity, $file, $line);
}

function app_handle_shutdown_error(): void
{
    $error = error_get_last();
    if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        return;
    }

    app_log_error('critical', 'fatal_error', 'PHP fatal error', $error['file'], $error['line']);
    app_render_server_error();
}

function register_app_error_handlers(): void
{
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
    set_error_handler('app_handle_php_error');
    set_exception_handler('app_handle_uncaught_exception');
    register_shutdown_function('app_handle_shutdown_error');
}
