<?php

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['ajax'] ?? '') === 'items_by_category' && can_use_deployments()) {
    header('Content-Type: application/json');
    $typeId = request_positive_int($_GET, 'type_id');
    if ($typeId === null) {
        json_bad_request('A valid item type is required.');
    }
    $stmt = $conn->prepare(
        "SELECT i.id, i.item_name,
                (i.quantity_total - COALESCE((SELECT SUM(dd.quantity_deployed) FROM deployments dd WHERE dd.item_id=i.id AND dd.returned_date IS NULL),0)) AS available
         FROM inventory_items i
         WHERE i.item_type_id = ?
         HAVING available > 0
         ORDER BY i.item_name"
    );
    $stmt->bind_param('i', $typeId);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = ['id' => (int)$row['id'], 'name' => $row['item_name'], 'available' => (int)$row['available']];
    }
    echo json_encode(['ok' => true, 'items' => $items]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['ajax'] ?? '') === 'pn_numbers_for_item' && can_use_deployments()) {
    header('Content-Type: application/json');
    $itemId = request_positive_int($_GET, 'item_id');
    if ($itemId === null) {
        json_bad_request('A valid inventory item is required.');
    }
    $stmt = $conn->prepare(
        "SELECT s.pn_number, s.serial_number, b.po_number
         FROM inventory_batch_serials s
         JOIN inventory_batches b ON b.id = s.batch_id
         LEFT JOIN deployments d ON d.id = s.deployment_id AND d.returned_date IS NULL
         WHERE b.item_id = ?
           AND s.pn_number IS NOT NULL AND s.pn_number <> ''
           AND d.id IS NULL
         ORDER BY b.batch_date ASC, s.id ASC"
    );
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $res = $stmt->get_result();
    $numbers = [];
    while ($row = $res->fetch_assoc()) {
        $numbers[] = ['pn' => $row['pn_number'], 'serial' => $row['serial_number'] ?? '', 'po' => $row['po_number'] ?? ''];
    }
    echo json_encode(['ok' => true, 'pn_numbers' => $numbers]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['ajax'] ?? '') === 'preset_specs_for_item' && can_use_deployments()) {
    header('Content-Type: application/json');
    $itemId = request_positive_int($_GET, 'item_id');
    if ($itemId === null) {
        json_bad_request('A valid inventory item is required.');
    }
    $specs = load_spec_values($conn, 'inventory_item_spec_values', 'item_id', $itemId);
    echo json_encode(['ok' => true, 'specs' => $specs]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['ajax'] ?? '') === 'current_ph_time' && can_use_deployments()) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'date' => date('Y-m-d'), 'time' => date('H:i')]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['ajax'] ?? '') === 'active_deployments_for_person' && can_use_deployments()) {
    header('Content-Type: application/json');
    $deptId = request_nonnegative_int($_GET, 'department_id');
    $recipientName = request_string($_GET, 'recipient_name', 150, false);
    if (
        (isset($_GET['department_id']) && $deptId === null)
        || !request_strings_are_valid($_GET, ['recipient_name' => 150])
    ) {
        json_bad_request('Invalid department or recipient.');
    }
    if ($deptId === null || $deptId === 0 || $recipientName === '') {
        echo json_encode(['ok' => true, 'items' => []]);
        exit;
    }
    $stmt = $conn->prepare(
        "SELECT d.id, i.item_name, d.po_number, d.pn_number, d.deployed_date, d.deployed_time
         FROM deployments d
         JOIN inventory_items i ON i.id = d.item_id
         WHERE d.department_id = ? AND d.recipient_name = ? AND d.returned_date IS NULL
         ORDER BY d.deployed_date DESC, d.id DESC"
    );
    $stmt->bind_param('is', $deptId, $recipientName);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = [
            'id'              => (int)$row['id'],
            'item_name'       => $row['item_name'],
            'po_number'       => $row['po_number'] ?: '',
            'pn_number'       => $row['pn_number'] ?: '',
            'deployed_date'   => $row['deployed_date'] ? date('M j, Y', strtotime($row['deployed_date'])) : '',
            'deployed_time'   => $row['deployed_time'] ? date('g:i A', strtotime($row['deployed_time'])) : '',
        ];
    }
    echo json_encode(['ok' => true, 'items' => $items]);
    exit;
}
