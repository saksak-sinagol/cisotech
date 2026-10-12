<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$messages_log = [];

function fkName19($conn, $table, $column) {
    $dbNameRes = $conn->query("SELECT DATABASE() AS db");
    $dbName = $dbNameRes->fetch_assoc()['db'];
    $res = $conn->prepare(
        "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
           AND REFERENCED_TABLE_NAME IS NOT NULL"
    );
    $res->bind_param('sss', $dbName, $table, $column);
    $res->execute();
    $row = $res->get_result()->fetch_assoc();
    return $row ? $row['CONSTRAINT_NAME'] : null;
}

function columnExists19($conn, $table, $column) {
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}

$targets = [
    ['work_logs', 'created_by'],
    ['inventory_items', 'created_by'],
    ['inventory_batches', 'added_by'],
    ['inventory_remarks', 'added_by'],
    ['deployments', 'deployed_by'],
];
foreach ($targets as [$table, $column]) {
    $fk = fkName19($conn, $table, $column);
    if ($fk) {
        $conn->query("ALTER TABLE `$table` DROP FOREIGN KEY `$fk`");
    }
    if (!columnExists19($conn, $table, $column)) {
        $messages_log[] = "$table.$column not found — skipped.";
        continue;
    }
    $conn->query("ALTER TABLE `$table` MODIFY COLUMN `$column` INT UNSIGNED DEFAULT NULL");
    $conn->query("ALTER TABLE `$table` ADD CONSTRAINT fk_{$table}_{$column}_v19 FOREIGN KEY (`$column`) REFERENCES users(id) ON DELETE SET NULL");
    $messages_log[] = "$table.$column: deleting a user account no longer deletes this history — it's kept, shown as \"Unknown User\".";
}

?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Migration v19 — C1SO TECH</title>
<style>
  body{font-family:'Segoe UI',sans-serif;background:#15171D;color:#E8E6E0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;}
  .box{background:#1C1F26;border:1px solid #333844;border-radius:8px;padding:30px 34px;max-width:620px;}
  h1{font-size:18px;margin:0 0 12px;color:#7EA3C4;}
  a{color:#7EA3C4;}
  p{line-height:1.6;font-size:14px;}
  ul{padding-left:18px; line-height:1.7;}
</style>
</head>
<body>
  <div class="box">
    <h1>✓ Migration v19 complete</h1>
    <ul>
      <?php foreach ($messages_log as $m) echo '<li>' . h($m) . '</li>'; ?>
    </ul>
    <p>History (work logs, inventory, restocks, remarks, deployments) is now preserved even after a staff account is deleted — the record stays, just shown under "Unknown User" instead of the deleted account's name.</p>
    <p><a href="inventory.php">Go to Inventory →</a> · <a href="deployments.php">Go to Deployments →</a></p>
  </div>
</body>
</html>
