<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$messages_log = [];

function tableExists18($conn, $table) {
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return $res && $res->num_rows > 0;
}

if (!tableExists18($conn, 'item_type_components')) {
    $conn->query(
        "CREATE TABLE item_type_components (
          id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          parent_type_id   INT UNSIGNED NOT NULL,
          component_type_id INT UNSIGNED NOT NULL,
          created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_parent_component (parent_type_id, component_type_id),
          CONSTRAINT fk_itc_parent FOREIGN KEY (parent_type_id) REFERENCES item_types(id) ON DELETE CASCADE,
          CONSTRAINT fk_itc_component FOREIGN KEY (component_type_id) REFERENCES item_types(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $messages_log[] = "Created item_type_components (which Kind of Item entries are pickable components of a parent Kind of Item, e.g. Keyboard/Mouse/Monitor/GPU as components of PC/System Unit).";
} else {
    $messages_log[] = "item_type_components already present.";
}

$defaultParents = ['PC/System Unit', 'Laptop', 'Projector'];
$defaultComponents = ['Keyboard', 'Mouse', 'Monitor'];
foreach ($defaultParents as $parentName) {
    $pRes = $conn->prepare("SELECT id FROM item_types WHERE LOWER(name) = LOWER(?)");
    $pRes->bind_param('s', $parentName);
    $pRes->execute();
    $parent = $pRes->get_result()->fetch_assoc();
    if (!$parent) {
        $messages_log[] = "\"$parentName\" not found in Item Types yet — skipped seeding its components.";
        continue;
    }
    foreach ($defaultComponents as $compName) {
        $cRes = $conn->prepare("SELECT id FROM item_types WHERE LOWER(name) = LOWER(?)");
        $cRes->bind_param('s', $compName);
        $cRes->execute();
        $comp = $cRes->get_result()->fetch_assoc();
        if (!$comp) continue;
        if ((int)$comp['id'] === (int)$parent['id']) continue;
        $check = $conn->prepare("SELECT 1 FROM item_type_components WHERE parent_type_id = ? AND component_type_id = ?");
        $check->bind_param('ii', $parent['id'], $comp['id']);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            $ins = $conn->prepare("INSERT INTO item_type_components (parent_type_id, component_type_id) VALUES (?, ?)");
            $ins->bind_param('ii', $parent['id'], $comp['id']);
            $ins->execute();
            $messages_log[] = "Set \"$compName\" as a component of \"$parentName\".";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Migration v18 — C1SO TECH</title>
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
    <h1>✓ Migration v18 complete</h1>
    <ul>
      <?php foreach ($messages_log as $m) echo '<li>' . h($m) . '</li>'; ?>
    </ul>
    <p>Go to Settings → Item Types to add or remove components for any Kind of Item (e.g. add "GPU", "CPU", "RAM" as components of PC/System Unit).</p>
    <p><a href="settings.php">Go to Settings →</a> · <a href="deployments.php">Go to Deployments →</a></p>
  </div>
</body>
</html>
