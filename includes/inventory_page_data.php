<?php

$filterType = $_GET['type'] ?? '';
$search = trim($_GET['q'] ?? '');

$where = [];
$params = [];
$types = '';
if ($filterType !== '') { $where[] = 'i.item_type_id = ?'; $params[] = (int)$filterType; $types .= 'i'; }
if ($search !== '') {
    $where[] = 'i.item_name LIKE ?';
    $like = '%' . $search . '%';
    $params[] = $like;
    $types .= 's';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT i.*, t.name AS type_name,
          COALESCE((SELECT SUM(d.quantity_deployed) FROM deployments d WHERE d.item_id = i.id AND d.returned_date IS NULL), 0) AS deployed_qty
        FROM inventory_items i
        JOIN item_types t ON t.id = i.item_type_id
        $whereSql
        ORDER BY t.name, i.item_name";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$itemsRes = $stmt->get_result();

$allItems = [];
$allItemIds = [];
while ($row = $itemsRes->fetch_assoc()) {
    $allItems[] = $row;
    $allItemIds[] = (int)$row['id'];
}

$batchesByItem = [];
if (!empty($allItemIds)) {
    $idList = implode(',', array_map('intval', $allItemIds));
    $bres = $conn->query(
        "SELECT b.*, u.full_name AS added_by_name
         FROM inventory_batches b
         LEFT JOIN users u ON u.id = b.added_by
         WHERE b.item_id IN ($idList)
         ORDER BY b.batch_date DESC, b.id DESC"
    );
    while ($b = $bres->fetch_assoc()) {
        $batchesByItem[$b['item_id']][] = $b;
    }
}

$remarksByItem = [];
if (!empty($allItemIds)) {
    $idList = implode(',', array_map('intval', $allItemIds));
    $rres = $conn->query(
        "SELECT r.*, u.full_name AS added_by_name
         FROM inventory_remarks r
         LEFT JOIN users u ON u.id = r.added_by
         WHERE r.item_id IN ($idList)
         ORDER BY r.remark_date DESC, r.id DESC"
    );
    while ($r = $rres->fetch_assoc()) {
        $remarksByItem[$r['item_id']][] = $r;
    }
}

$activeDeploysByItem = [];
if (!empty($allItemIds)) {
    $idList = implode(',', array_map('intval', $allItemIds));
    $ares = $conn->query(
        "SELECT d.item_id, d.quantity_deployed, d.deployment_type, d.deployed_date, d.deployed_time,
                dep.name AS dept_name, d.recipient_name
         FROM deployments d
         JOIN departments dep ON dep.id = d.department_id
         WHERE d.item_id IN ($idList) AND d.returned_date IS NULL
         ORDER BY d.deployed_date DESC, d.id DESC"
    );
    while ($a = $ares->fetch_assoc()) {
        $activeDeploysByItem[$a['item_id']][] = $a;
    }
}

$drawsByBatch = [];
if (!empty($allItemIds)) {
    $idList = implode(',', array_map('intval', $allItemIds));
    $dres = $conn->query(
        "SELECT c.batch_id, c.quantity, dep.name AS dept_name, d.deployed_date, d.returned_date
         FROM deployment_batch_consumption c
         JOIN inventory_batches b ON b.id = c.batch_id
         JOIN deployments d ON d.id = c.deployment_id
         JOIN departments dep ON dep.id = d.department_id
         WHERE b.item_id IN ($idList)
         ORDER BY d.deployed_date ASC, d.id ASC"
    );
    while ($dr = $dres->fetch_assoc()) {
        $drawsByBatch[$dr['batch_id']][] = $dr;
    }
}

$serialsByBatch = [];
if (!empty($allItemIds)) {
    $idList = implode(',', array_map('intval', $allItemIds));
    $sres = $conn->query(
        "SELECT s.batch_id, s.serial_number, s.pn_number,
                (d.id IS NOT NULL) AS is_deployed
         FROM inventory_batch_serials s
         JOIN inventory_batches b ON b.id = s.batch_id
         LEFT JOIN deployments d ON d.id = s.deployment_id AND d.returned_date IS NULL
         WHERE b.item_id IN ($idList)
         ORDER BY s.id ASC"
    );
    while ($sr = $sres->fetch_assoc()) {
        $serialsByBatch[$sr['batch_id']][] = [
            'sn' => $sr['serial_number'] ?? '',
            'pn' => $sr['pn_number'] ?? '',
            'deployed' => (bool)$sr['is_deployed'],
        ];
    }
}

$itemTypes = $conn->query("SELECT * FROM item_types ORDER BY name");
$itemTypeCount = $itemTypes->num_rows;

$invTabRaw = $_GET['tab'] ?? 'items';
$invTab = in_array($invTabRaw, ['deployed', 'returned'], true) ? $invTabRaw : 'items';
$deployedItemsRows = [];
$returnedItemsRows = [];
$specValuesByDeployment = [];
if ($invTab === 'deployed') {
    $deployedRes = $conn->query(
        "SELECT d.id, d.mr_number, d.quantity_deployed, d.deployed_date, d.deployed_time, d.remarks,
                d.department_id, d.recipient_name,
                i.item_name, t.name AS type_name, t.id AS type_id,
                dep.name AS dept_name, u.full_name AS deployed_by_name
         FROM deployments d
         JOIN inventory_items i ON i.id = d.item_id
         JOIN item_types t ON t.id = i.item_type_id
         JOIN departments dep ON dep.id = d.department_id
         LEFT JOIN users u ON u.id = d.deployed_by
         WHERE d.returned_date IS NULL
         ORDER BY d.deployed_date DESC, d.id DESC"
    );
    while ($row = $deployedRes->fetch_assoc()) {
        $deployedItemsRows[] = $row;
    }

    if (!empty($deployedItemsRows)) {
        $depIdList = implode(',', array_map(function($r){ return (int)$r['id']; }, $deployedItemsRows));
        $svRes = $conn->query("SELECT deployment_id, spec_key, spec_value FROM deployment_spec_values WHERE deployment_id IN ($depIdList)");
        while ($sv = $svRes->fetch_assoc()) {
            $specValuesByDeployment[$sv['deployment_id']][$sv['spec_key']] = $sv['spec_value'];
        }
    }
}

if ($invTab === 'returned') {
    $returnedRes = $conn->query(
        "SELECT d.id, d.mr_number, d.quantity_deployed, d.deployed_date, d.deployed_time,
                d.returned_date, d.returned_time, d.remarks,
                i.item_name, t.name AS type_name, t.id AS type_id,
                dep.name AS dept_name, d.recipient_name,
                u.full_name AS deployed_by_name
         FROM deployments d
         JOIN inventory_items i ON i.id = d.item_id
         JOIN item_types t ON t.id = i.item_type_id
         JOIN departments dep ON dep.id = d.department_id
         LEFT JOIN users u ON u.id = d.deployed_by
         WHERE d.returned_date IS NOT NULL
         ORDER BY d.returned_date DESC, d.id DESC"
    );
    while ($row = $returnedRes->fetch_assoc()) {
        $returnedItemsRows[] = $row;
    }
}

$deployedKindPills = [];
foreach ($deployedItemsRows as $row) {
    $deployedKindPills[$row['type_id']] = $row['type_name'];
}
$returnedKindPills = [];
foreach ($returnedItemsRows as $row) {
    $returnedKindPills[$row['type_id']] = $row['type_name'];
}

$peripheralTypeIdsForDisplay = [];
$periphDisplayRes = $conn->query("SELECT id, LOWER(name) AS lname FROM item_types WHERE LOWER(name) IN ('keyboard','mouse','monitor')");
while ($pr = $periphDisplayRes->fetch_assoc()) {
    $peripheralTypeIdsForDisplay[(int)$pr['id']] = $pr['lname'];
}
