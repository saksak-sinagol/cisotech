<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_superadmin();

$conn->query(
    'CREATE TABLE IF NOT EXISTS request_rate_limits (
       rate_key CHAR(64) PRIMARY KEY,
       window_started_at BIGINT UNSIGNED NOT NULL,
       request_count INT UNSIGNED NOT NULL
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Migration v20 — C1SO TECH</title></head>
<body>
  <h1>Migration v20 complete</h1>
  <p>Request rate-limit storage is ready. Remove this migration script after use.</p>
</body>
</html>
