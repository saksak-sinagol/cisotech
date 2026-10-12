<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$messages_log = [];

function fkExists19($conn, $table, $constraintName) {
    $dbNameRes = $conn->query("SELECT DATABASE() AS db");
    $dbName = $dbNameRes->fetch_assoc()['db'];
    $res = $conn->prepare(
        "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?"
    );
    $res->bind_param('sss', $dbName, $table, $constraintName);
    $res->execute();
    return $res->get_result()->fetch_assoc() !== null;
}

$fixes = [
    ['table' => 'work_logs',           'column' => 'created_by', 'old_fk' => 'fk_worklog_user',   'new_fk' => 'fk_worklog_user_sn'],
    ['table' => 'inventory_items',     'column' => 'created_by', 'old_fk' => 'fk_item_user',       'new_fk' => 'fk_item_user_sn'],
    ['table' => 'inventory_batches',   'column' => 'added_by',   'old_fk' => 'fk_batch_user',      'new_fk' => 'fk_batch_user_sn'],
    ['table' => 'inventory_remarks',   'column' => 'added_by',   'old_fk' => 'fk_remark_user',     'new_fk' => 'fk_remark_user_sn'],
    ['table' => 'deployments',         'column' => 'deployed_by','old_fk' => 'fk_deploy_user',     'new_fk' => 'fk_deploy_user_sn'],
];

foreach ($fixes as $fix) {
    $table = $fix['table'];
    $column = $fix['column'];

    $colInfo = $conn->query("SHOW COLUMNS FROM `$table` WHERE Field = '$column'")->fetch_assoc();
    if ($colInfo && $colInfo['Null'] === 'NO') {
        $conn->query("ALTER TABLE `$table` MODIFY COLUMN `$column` INT UNSIGNED DEFAULT NULL");
    }

    if (fkExists19($conn, $table, $fix['old_fk'])) {
        $conn->query("ALTER TABLE `$table` DROP FOREIGN KEY `{$fix['old_fk']}`");
        $conn->query("ALTER TABLE `$table` ADD CONSTRAINT `{$fix['new_fk']}` FOREIGN KEY (`$column`) REFERENCES users(id) ON DELETE SET NULL");
        $messages_log[] = "$table.$column: now keeps its history and shows \"Unknown User\" instead of being deleted when the account that created it is removed.";
    } elseif (fkExists19($conn, $table, $fix['new_fk'])) {
        $messages_log[] = "$table.$column already fixed.";
    } else {
        $messages_log[] = "$table.$column: could not find the expected foreign key to fix (it may already be set up differently) — please check manually if needed.";
    }
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
    <p>Previously, deleting a staff/admin account also deleted every Work Log entry, Inventory item, Restock batch, remark, and Deployment they had created. This is now fixed — those records stay, and just show "Unknown User" for who made them if that account is later removed.</p>
    <ul>
      <?php foreach ($messages_log as $m) echo '<li>' . h($m) . '</li>'; ?>
    </ul>
    <p><a href="deployments.php">Go to Deployments →</a> · <a href="inventory.php">Go to Inventory →</a></p>
  </div>
</body>
</html>
