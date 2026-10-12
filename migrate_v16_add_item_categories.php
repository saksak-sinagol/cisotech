<?php

require_once 'includes/db.php';
require_once 'includes/auth.php';

$messages_log = [];

function tableExists16($conn, $table) {
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return $res && $res->num_rows > 0;
}

function columnExists16($conn, $table, $column) {
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}

if (!tableExists16($conn, 'item_categories')) {
    $conn->query(
        "CREATE TABLE item_categories (
          id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          name       VARCHAR(100) NOT NULL UNIQUE,
          created_by INT UNSIGNED DEFAULT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_itemcat_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $messages_log[] = "Created item_categories (a new grouping level above Kind of Item — e.g. \"Hardware\", \"Peripheral\").";
} else {
    $messages_log[] = "item_categories already present.";
}

if (!columnExists16($conn, 'item_types', 'category_id')) {
    $conn->query("ALTER TABLE item_types ADD COLUMN category_id INT UNSIGNED DEFAULT NULL AFTER name");
    $conn->query("ALTER TABLE item_types ADD CONSTRAINT fk_itemtype_category FOREIGN KEY (category_id) REFERENCES item_categories(id) ON DELETE SET NULL");
    $messages_log[] = "Added item_types.category_id (each Kind of Item now belongs to a Category).";
} else {
    $messages_log[] = "item_types.category_id already present.";
}

$conn->query("ALTER TABLE deployment_spec_values MODIFY COLUMN spec_value TEXT DEFAULT NULL");
$messages_log[] = "Widened deployment_spec_values.spec_value to TEXT (Unit Specifications is now one free-text note, which needs more than 255 characters).";

$conn->query("ALTER TABLE inventory_item_spec_values MODIFY COLUMN spec_value TEXT DEFAULT NULL");
$messages_log[] = "Widened inventory_item_spec_values.spec_value to TEXT (same reason, for the Restock form's preset specs).";

$conn->query("ALTER TABLE inventory_batches MODIFY COLUMN pn_number VARCHAR(100) DEFAULT NULL");
$messages_log[] = "Confirmed inventory_batches.pn_number stays nullable (PN Number is no longer entered at restock time — it's entered later, at deployment time).";

$defaultCategories = ['Hardware', 'Peripheral'];
$categoryIds = [];
foreach ($defaultCategories as $catName) {
    $check = $conn->prepare("SELECT id FROM item_categories WHERE LOWER(name) = LOWER(?)");
    $check->bind_param('s', $catName);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    if ($existing) {
        $categoryIds[$catName] = (int)$existing['id'];
        $messages_log[] = "\"$catName\" category already exists.";
    } else {
        $ins = $conn->prepare("INSERT INTO item_categories (name) VALUES (?)");
        $ins->bind_param('s', $catName);
        $ins->execute();
        $categoryIds[$catName] = $ins->insert_id;
        $messages_log[] = "Added \"$catName\" category.";
    }
}

$kindToCategory = [
    'PC/System Unit' => 'Hardware',
    'Laptop'          => 'Hardware',
    'Projector'       => 'Hardware',
    'Keyboard'        => 'Peripheral',
    'Mouse'           => 'Peripheral',
    'Monitor'         => 'Peripheral',
];
foreach ($kindToCategory as $kindName => $catName) {
    $check = $conn->prepare("SELECT id, category_id FROM item_types WHERE LOWER(name) = LOWER(?)");
    $check->bind_param('s', $kindName);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    if ($existing && $existing['category_id'] === null) {
        $upd = $conn->prepare("UPDATE item_types SET category_id = ? WHERE id = ?");
        $upd->bind_param('ii', $categoryIds[$catName], $existing['id']);
        $upd->execute();
        $messages_log[] = "Assigned \"$kindName\" to the \"$catName\" category.";
    } elseif ($existing) {
        $messages_log[] = "\"$kindName\" already has a category assigned — left as-is.";
    } else {
        $messages_log[] = "\"$kindName\" not found in Item Types yet — nothing to assign (add it in Settings, then set its category there).";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Migration v16 — C1SO TECH</title>
<style>
  body{font-family:'Segoe UI',sans-serif;background:#15171D;color:#E8E6E0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;}
  .box{background:#1C1F26;border:1px solid #333844;border-radius:8px;padding:30px 34px;max-width:600px;}
  h1{font-size:18px;margin:0 0 12px;color:#7EA3C4;}
  a{color:#7EA3C4;}
  code{background:#242832;padding:2px 6px;border-radius:4px;color:#D9A441;}
  p{line-height:1.6;font-size:14px;}
  ul{padding-left:18px; line-height:1.7;}
</style>
</head>
<body>
  <div class="box">
    <h1>✓ Migration v16 complete</h1>
    <ul>
      <?php foreach ($messages_log as $m) echo '<li>' . h($m) . '</li>'; ?>
    </ul>
    <p><a href="settings.php">Go to Settings →</a> · <a href="inventory.php">Go to Inventory →</a> · <a href="deployments.php">Go to Deployments →</a></p>
  </div>
</body>
</html>
