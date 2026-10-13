<?php

$filterStatus = $_GET['status'] ?? 'active';
$where = '';
if ($filterStatus === 'active') $where = 'WHERE d.returned_date IS NULL';
elseif ($filterStatus === 'returned') $where = 'WHERE d.returned_date IS NOT NULL';

$deployRes = $conn->query(
    "SELECT d.*, i.item_name, dep.name AS dept_name, u.full_name AS deployed_by_name, u.role AS deployed_by_role
     FROM deployments d
     JOIN inventory_items i ON i.id = d.item_id
     JOIN departments dep ON dep.id = d.department_id
     LEFT JOIN users u ON u.id = d.deployed_by
     $where
     ORDER BY u.full_name, d.deployed_date DESC, d.id DESC"
);

$groupedDeploysByType = ['new' => [], 'replacement' => []];
$allDeployIds = [];
while ($d = $deployRes->fetch_assoc()) {
    $type = $d['deployment_type'] ?? 'new';
    if (!isset($groupedDeploysByType[$type])) $type = 'new';
    $key = $d['deployed_by_name'] ?: 'Unknown';
    $groupedDeploysByType[$type][$key][] = $d;
    $allDeployIds[] = (int)$d['id'];
}

$consumptionByDeploy = [];
if (!empty($allDeployIds)) {
    $idList = implode(',', array_map('intval', $allDeployIds));
    $cres = $conn->query(
        "SELECT c.deployment_id, c.quantity, b.batch_date, b.pn_number, b.po_number
         FROM deployment_batch_consumption c
         JOIN inventory_batches b ON b.id = c.batch_id
         WHERE c.deployment_id IN ($idList)
         ORDER BY b.batch_date ASC, b.id ASC"
    );
    while ($c = $cres->fetch_assoc()) {
        $consumptionByDeploy[$c['deployment_id']][] = $c;
    }
}

$itemCategoryListPayload = [];
$itemCatRows = $conn->query("SELECT * FROM item_categories ORDER BY name");
while ($icRow = $itemCatRows->fetch_assoc()) {
    $itemCategoryListPayload[] = ['id' => (int)$icRow['id'], 'name' => $icRow['name']];
}
$itemTypes = $conn->query("SELECT * FROM item_types ORDER BY name");


$deptListPayload = [];
$deptRows = $conn->query("SELECT * FROM departments ORDER BY name");
while ($dRow = $deptRows->fetch_assoc()) {
    $deptListPayload[] = ['id' => (int)$dRow['id'], 'name' => $dRow['name']];
}
$peopleByDept = [];
$peopleRows = $conn->query("SELECT * FROM department_people ORDER BY name");
while ($pRow = $peopleRows->fetch_assoc()) {
    $peopleByDept[$pRow['department_id']][] = ['id' => (int)$pRow['id'], 'name' => $pRow['name']];
}
$itemTypeListPayload = [];
$itemTypes->data_seek(0);
while ($tRow = $itemTypes->fetch_assoc()) {
    $itemTypeListPayload[] = [
        'id' => (int)$tRow['id'],
        'name' => $tRow['name'],
        'category_id' => $tRow['category_id'] !== null ? (int)$tRow['category_id'] : null,
        'spec_kind' => spec_schema_key_for_type_name($tRow['name']) ?? '',
    ];
}

$itemTypeComponentsMap = [];
$compMapRes = $conn->query(
    "SELECT itc.parent_type_id, t.id AS component_id, t.name AS component_name
     FROM item_type_components itc
     JOIN item_types t ON t.id = itc.component_type_id
     ORDER BY t.name"
);
while ($cmRow = $compMapRes->fetch_assoc()) {
    $itemTypeComponentsMap[(int)$cmRow['parent_type_id']][] = [
        'id' => (int)$cmRow['component_id'],
        'name' => $cmRow['component_name'],
    ];
}
