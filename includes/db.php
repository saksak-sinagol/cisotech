<?php

require_once __DIR__ . '/config.php';

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
    http_response_code(500);
    $dbAdminUrl = app_env('DB_ADMIN_URL');
    $databaseHelp = $dbAdminUrl !== null && $dbAdminUrl !== ''
        ? '<li>Open <a href="' . htmlspecialchars($dbAdminUrl, ENT_QUOTES, 'UTF-8') . '">your database administration page</a>, select the configured database, and import <code>database.sql</code>.</li>'
        : '<li>Confirm the configured database exists and import <code>database.sql</code> into it.</li>';
    die(
        '<div style="font-family:sans-serif;max-width:640px;margin:60px auto;padding:24px;' .
        'border:1px solid #C4675A;border-radius:8px;background:#fff5f4;color:#5b2b26;">' .
        '<h2 style="margin-top:0;">Database connection failed</h2>' .
        '<p>Could not connect to the configured MySQL database <code>' . htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8') . '</code>.</p>' .
        '<ol style="line-height:1.7;">' .
        '<li>Make sure the database server is running.</li>' .
        $databaseHelp .
        '<li>Set the connection values in the ignored <code>.env</code> file.</li>' .
        '</ol>' .
        '<p style="color:#8a5a54;font-size:13px;">Technical detail: ' . htmlspecialchars($conn->connect_error ?? 'unknown error', ENT_QUOTES, 'UTF-8') . '</p>' .
        '</div>'
    );
}

$conn->set_charset('utf8mb4');
