<?php

require_once __DIR__ . '/error_handling.php';
register_app_error_handlers();

require_once __DIR__ . '/http_security.php';

apply_security_headers();
enforce_same_origin_policy();

require_once __DIR__ . '/app_config.php';

$dbHost = app_env_required('DB_HOST');
$dbName = app_env_required('DB_NAME');
$dbUser = app_env_required('DB_USER');
$dbPass = app_env_required('DB_PASS');
$dbPort = filter_var(app_env_required('DB_PORT'), FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 65535],
]);
if ($dbPort === false) {
    throw new RuntimeException('DB_PORT must be an integer between 1 and 65535.');
}

mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);

if ($conn->connect_errno) {
    throw new RuntimeException('Database connection failed.', $conn->connect_errno);
}

$conn->set_charset('utf8mb4');
