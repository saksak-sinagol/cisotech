<?php

require_once 'includes/db.php';
require_once 'includes/auth.php';

$setupIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
enforce_rate_limit($conn, 'bootstrap-setup', $setupIp, 5, 3600);

$defaultUsername = app_bootstrap_username('BOOTSTRAP_ADMIN_USERNAME');
$defaultPassword = app_bootstrap_password('BOOTSTRAP_ADMIN_PASSWORD');
$superUsername = app_bootstrap_username('BOOTSTRAP_SUPERADMIN_USERNAME');
$superPassword = app_bootstrap_password('BOOTSTRAP_SUPERADMIN_PASSWORD');
if (strcasecmp($defaultUsername, $superUsername) === 0) {
    throw new RuntimeException('Administrator and superadmin bootstrap usernames must differ.');
}
if (hash_equals($defaultPassword, $superPassword)) {
    throw new RuntimeException('Administrator and superadmin bootstrap passwords must differ.');
}

$hash = password_hash($defaultPassword, PASSWORD_DEFAULT);

$stmt = $conn->prepare("SELECT id, password_hash FROM users WHERE username = ?");
$stmt->bind_param('s', $defaultUsername);
$stmt->execute();
$res = $stmt->get_result();

$msg = '';

if ($row = $res->fetch_assoc()) {

    if (empty($row['password_hash']) || !password_get_info($row['password_hash'])['algo']) {
        $upd = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $upd->bind_param('si', $hash, $row['id']);
        $upd->execute();
        $msg = "Default admin password has been set/reset.";
    } else {
        $msg = "Admin account.";
    }
} else {
    $ins = $conn->prepare("INSERT INTO users (full_name, username, password_hash, role, status) VALUES (?, ?, ?, 'admin', 'active')");
    $full = 'System Administrator';
    $ins->bind_param('sss', $full, $defaultUsername, $hash);
    $ins->execute();
    $msg = "Default admin account created.";
}

$superHash = password_hash($superPassword, PASSWORD_DEFAULT);
$sStmt = $conn->prepare("SELECT id, password_hash FROM users WHERE username = ?");
$sStmt->bind_param('s', $superUsername);
$sStmt->execute();
$sRes = $sStmt->get_result();

$superMsg = '';
if ($sRow = $sRes->fetch_assoc()) {
    if (empty($sRow['password_hash']) || !password_get_info($sRow['password_hash'])['algo']) {
        $supd = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $supd->bind_param('si', $superHash, $sRow['id']);
        $supd->execute();
        $superMsg = "Default superadmin password has been set/reset.";
    } else {
        $superMsg = "Superadmin account.";
    }
} else {
    $sIns = $conn->prepare("INSERT INTO users (full_name, username, password_hash, role, status) VALUES (?, ?, ?, 'superadmin', 'active')");
    $sFull = 'Super Administrator';
    $sIns->bind_param('sss', $sFull, $superUsername, $superHash);
    $sIns->execute();
    $superMsg = "Default superadmin account created.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Setup  C1SO TECH</title>
<style>
  body{font-family:'Segoe UI',sans-serif;background:#15171D;color:#E8E6E0;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}
  .box{background:#1C1F26;border:1px solid #333844;border-radius:8px;padding:30px 34px;max-width:440px;}
  h1{font-size:18px;margin:0 0 12px;color:#7EA3C4;}
  code{background:#242832;padding:2px 6px;border-radius:4px;color:#D9A441;}
  a{color:#7EA3C4;}
  p{line-height:1.6;font-size:14px;}
</style>
</head>
<body>
  <div class="box">
    <h1> Setup</h1>
    <p><?php echo h($msg); ?></p>
    <p>Use the administrator credentials configured in the ignored <code>.env</code> file.</p>
    <hr style="border-color:#333844; margin:18px 0;">
    <p><?php echo h($superMsg); ?></p>
    <p>Use the super administrator credentials configured in the ignored <code>.env</code> file.</p>
    <p><a href="login.php">Go to Login </a></p>
  </div>
</body>
</html>
