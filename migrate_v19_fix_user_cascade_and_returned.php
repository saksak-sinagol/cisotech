<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$messages_log = [];

function fkInfo19($conn, $table, $column) {
    $dbName = $conn->query("SELECT DATABASE()")->fetch_row()[0];
    $res = $conn->query(
        "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column'
           AND REFERENCED_TABLE_NAME IS NOT NULL"
    );
    return $res && $res->num_rows > 0 ? $res->fetch_assoc()['CONSTRAINT_NAME'] : null;
}

$fixes = [
    ['work_logs', 'created_by', 'fk_worklog_user'],
    ['inventory_items', 'created_by', 'fk_item_user'],
    ['inventory_batches', 'added_by', 'fk_batch_user'],
    ['inventory_remarks', 'added_by', 'fk_remark_user'],
    ['deployments', 'deployed_by', 'fk_deploy_user'],
];

foreach ($fixes as [$table, $column, $expectedName]) {
    $conn->query("ALTER TABLE `$table` MODIFY COLUMN `$column` INT UNSIGNED DEFAULT NULL");
    $fkName = fkInfo19($conn, $table, $column);
    if ($fkName) {
        $conn->query("ALTER TABLE `$table` DROP FOREIGN KEY `$fkName`");
    }
    $conn->query("ALTER TABLE `$table` ADD CONSTRAINT `$expectedName` FOREIGN KEY (`$column`) REFERENCES users(id) ON DELETE SET NULL");
    $messages_log[] = "Fixed $table.$column — deleting a user account no longer deletes their $table history (shows as an unknown/former user instead).";
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
    <p><b>Important:</b> this fixes the behavior going forward. It does not undo any history that was already lost from a past account deletion.</p>
    <p><a href="inventory.php">Go to Inventory →</a> · <a href="deployments.php">Go to Deployments →</a> · <a href="activity.php">Go to Activity Log →</a></p>
  </div>
</body>
</html>
