<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
if (PHP_SAPI === 'cli') {
    $targetDb = app_env('DB_NAME') ?? '';
    if (!str_starts_with($targetDb, 'cisotech_test_')) {
        fwrite(STDERR, "CLI runs are only allowed against cisotech_test_* databases.\n");
        exit(1);
    }
} else {
    require_superadmin();
}

$indexes = [
    'work_logs' => [
        'idx_work_logs_status' => '(status)',
    ],
    'inventory_batches' => [
        'idx_batches_item_date_id' => '(item_id, batch_date, id)',
    ],
    'inventory_remarks' => [
        'idx_remarks_item_date_id' => '(item_id, remark_date, id)',
    ],
    'inventory_disposal_flags' => [
        'idx_disposal_item_activity_status' => '(item_id, last_activity_date, status)',
        'idx_disposal_status_flagged_id' => '(status, flagged_date, id)',
    ],
    'deployments' => [
        'idx_deploy_item_returned_date_id' => '(item_id, returned_date, deployed_date, id)',
        'idx_deploy_dept_recipient_returned' => '(department_id, recipient_name, returned_date)',
        'idx_deploy_returned_type_date_id' => '(returned_date, deployment_type, deployed_date, id)',
    ],
    'messages' => [
        'idx_msg_reverse_conversation' => '(recipient_id, sender_id, created_at)',
    ],
    'activity_log' => [
        'idx_activity_module_created_id' => '(module, created_at, id)',
    ],
];

$results = [];
foreach ($indexes as $table => $tableIndexes) {
    foreach ($tableIndexes as $name => $columns) {
        $check = $conn->prepare(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1'
        );
        $check->bind_param('ss', $table, $name);
        $check->execute();

        if ($check->get_result()->fetch_assoc()) {
            $results[] = "$table.$name already exists";
            continue;
        }

        $conn->query("ALTER TABLE `$table` ADD INDEX `$name` $columns");
        $results[] = "$table.$name added";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Migration v21 — C1SO TECH</title></head>
<body>
  <h1>Migration v21 complete</h1>
  <ul>
    <?php foreach ($results as $result): ?>
      <li><?php echo htmlspecialchars($result, ENT_QUOTES, 'UTF-8'); ?></li>
    <?php endforeach; ?>
  </ul>
  <p>Remove this migration script after use.</p>
</body>
</html>
