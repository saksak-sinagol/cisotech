<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$messages_log = [];

function columnExists17($conn, $table, $column) {
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}

if (!columnExists17($conn, 'inventory_batch_serials', 'pn_number')) {
    $conn->query("ALTER TABLE inventory_batch_serials ADD COLUMN pn_number VARCHAR(100) DEFAULT NULL AFTER serial_number");
    $messages_log[] = "Added inventory_batch_serials.pn_number (each physical unit now has its own PN Number).";
} else {
    $messages_log[] = "inventory_batch_serials.pn_number already present.";
}

if (!columnExists17($conn, 'inventory_batch_serials', 'deployment_id')) {
    $conn->query("ALTER TABLE inventory_batch_serials ADD COLUMN deployment_id INT UNSIGNED DEFAULT NULL AFTER pn_number");
    $conn->query("ALTER TABLE inventory_batch_serials ADD CONSTRAINT fk_batchserial_deployment FOREIGN KEY (deployment_id) REFERENCES deployments(id) ON DELETE SET NULL");
    $messages_log[] = "Added inventory_batch_serials.deployment_id (marks which deployment a unit was picked for).";
} else {
    $messages_log[] = "inventory_batch_serials.deployment_id already present.";
}

$conn->query(
    "UPDATE inventory_batch_serials s
     JOIN inventory_batches b ON b.id = s.batch_id
     SET s.pn_number = b.pn_number
     WHERE s.pn_number IS NULL AND b.pn_number IS NOT NULL AND b.pn_number <> ''"
);
$messages_log[] = "Copied each existing batch's PN Number onto its units so older stock still shows a PN.";

$conn->query(
    "INSERT INTO inventory_batch_serials (batch_id, serial_number, pn_number)
     SELECT b.id, NULL, b.pn_number
     FROM inventory_batches b
     WHERE NOT EXISTS (SELECT 1 FROM inventory_batch_serials s WHERE s.batch_id = b.id)
       AND b.pn_number IS NOT NULL AND b.pn_number <> ''
       AND b.quantity = 1"
);
$messages_log[] = "Created a unit row for older single-quantity batches that had a PN but no unit rows.";

?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Migration v17 — C1SO TECH</title>
<style>
  body{font-family:'Segoe UI',sans-serif;background:#15171D;color:#E8E6E0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;}
  .box{background:#1C1F26;border:1px solid #333844;border-radius:8px;padding:30px 34px;max-width:600px;}
  h1{font-size:18px;margin:0 0 12px;color:#7EA3C4;}
  a{color:#7EA3C4;}
  p{line-height:1.6;font-size:14px;}
  ul{padding-left:18px; line-height:1.7;}
</style>
</head>
<body>
  <div class="box">
    <h1>✓ Migration v17 complete</h1>
    <ul>
      <?php foreach ($messages_log as $m) echo '<li>' . h($m) . '</li>'; ?>
    </ul>
    <p><a href="inventory.php">Go to Inventory →</a> · <a href="deployments.php">Go to Deployments →</a></p>
  </div>
</body>
</html>
