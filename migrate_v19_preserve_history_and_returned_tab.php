<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$messages_log = [];

function fkExists19($conn, $table, $constraintName) {
    $res = $conn->query(
        "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = '$table'
           AND CONSTRAINT_NAME = '$constraintName' AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
    );
    return $res && $res->num_rows > 0;
}

function preserveHistory19($conn, $table, $column, $constraintName, &$messages_log) {
    if (fkExists19($conn, $table, $constraintName)) {
        $conn->query("ALTER TABLE `$table` DROP FOREIGN KEY `$constraintName`");
    }
    $conn->query("ALTER TABLE `$table` MODIFY COLUMN `$column` INT UNSIGNED DEFAULT NULL");
    $conn->query("ALTER TABLE `$table` ADD CONSTRAINT `$constraintName` FOREIGN KEY (`$column`) REFERENCES users(id) ON DELETE SET NULL");
    $messages_log[] = "$table.$column now keeps its history when the user account is deleted (was ON DELETE CASCADE, now ON DELETE SET NULL).";
}

preserveHistory19($conn, 'work_logs', 'created_by', 'fk_worklog_user', $messages_log);
preserveHistory19($conn, 'inventory_items', 'created_by', 'fk_item_user', $messages_log);
preserveHistory19($conn, 'inventory_batches', 'added_by', 'fk_batch_user', $messages_log);
preserveHistory19($conn, 'inventory_remarks', 'added_by', 'fk_remark_user', $messages_log);
preserveHistory19($conn, 'deployments', 'deployed_by', 'fk_deploy_user', $messages_log);

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
    <p>Deleting a staff or admin account will no longer delete anything they ever logged, restocked, or deployed — it stays in history, shown as "Unknown User" if the account is gone.</p>
    <p><a href="inventory.php">Go to Inventory →</a> · <a href="deployments.php">Go to Deployments →</a></p>
  </div>
</body>
</html>
