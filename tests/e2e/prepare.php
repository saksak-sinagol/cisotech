<?php

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/app_config.php';

$databaseName = app_env_required('DB_NAME');
$configuredTestDatabase = app_env_required('CISOTECH_E2E_DB_NAME');
$databaseHost = app_env_required('DB_HOST');
$username = app_env_required('CISOTECH_E2E_USERNAME');
$password = app_env_required('CISOTECH_E2E_PASSWORD');

if (!preg_match('/^cisotech_test_e2e_[a-z0-9_]+$/', $databaseName) || $databaseName !== $configuredTestDatabase) {
    throw new RuntimeException('Refusing to prepare a database outside the cisotech_test_e2e_ namespace.');
}
if (!in_array($databaseHost, ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Refusing to prepare a non-loopback E2E database host.');
}
if (strlen($username) > 50 || $username === '') {
    throw new RuntimeException('CISOTECH_E2E_USERNAME must be 1 to 50 characters.');
}
if ($password === '') {
    throw new RuntimeException('CISOTECH_E2E_PASSWORD must not be empty.');
}

$tablesResult = $conn->query('SHOW TABLES');
$tables = [];
while ($row = $tablesResult->fetch_row()) {
    $tables[] = '`' . str_replace('`', '``', $row[0]) . '`';
}

$conn->query('SET FOREIGN_KEY_CHECKS = 0');
foreach ($tables as $table) {
    $conn->query('TRUNCATE TABLE ' . $table);
}
$conn->query('SET FOREIGN_KEY_CHECKS = 1');

$fullName = 'Playwright E2E Admin';
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$role = 'superadmin';
$status = 'active';
$statement = $conn->prepare('INSERT INTO users (full_name, username, password_hash, role, status) VALUES (?, ?, ?, ?, ?)');
$statement->bind_param('sssss', $fullName, $username, $passwordHash, $role, $status);
$statement->execute();
