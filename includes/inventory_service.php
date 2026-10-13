<?php

function recalc_item_quantity($conn, $itemId) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(quantity),0) AS total FROM inventory_batches WHERE item_id = ?");
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_assoc()['total'];
    $upd = $conn->prepare("UPDATE inventory_items SET quantity_total = ? WHERE id = ?");
    $upd->bind_param('ii', $total, $itemId);
    $upd->execute();
    return $total;
}

function add_or_restock_item_row($conn, $userId, $itemName, $itemTypeId, $qty, $details, $pnNumber, $poNumber, $threshold, $dateAdded, $timeAdded, $serials = [], $specValues = [], $unitPns = []) {
    $check = $conn->prepare("SELECT id FROM inventory_items WHERE item_name = ? AND item_type_id = ?");
    $check->bind_param('si', $itemName, $itemTypeId);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();

    $isRestock = (bool)$existing;
    if ($existing) {
        $itemId = $existing['id'];
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO inventory_items (item_name, item_type_id, quantity_total, low_stock_threshold, date_added, created_by)
             VALUES (?,?,?,?,?,?)"
        );
        $zero = 0;
        $stmt->bind_param('siiisi', $itemName, $itemTypeId, $zero, $threshold, $dateAdded, $userId);
        $stmt->execute();
        $itemId = $stmt->insert_id;
    }

    $bstmt = $conn->prepare("INSERT INTO inventory_batches (item_id, quantity, batch_date, batch_time, notes, pn_number, po_number, added_by) VALUES (?,?,?,?,?,?,?,?)");
    $bstmt->bind_param('iisssssi', $itemId, $qty, $dateAdded, $timeAdded, $details, $pnNumber, $poNumber, $userId);
    $bstmt->execute();
    $batchId = $bstmt->insert_id;
    recalc_item_quantity($conn, $itemId);

    $sstmt = $conn->prepare("INSERT INTO inventory_batch_serials (batch_id, serial_number, pn_number) VALUES (?, ?, ?)");
    for ($u = 0; $u < $qty; $u++) {
        $serial = trim($serials[$u] ?? '');
        $serialOrNull = $serial === '' ? null : $serial;
        $unitPn = trim($unitPns[$u] ?? '');
        $unitPnOrNull = $unitPn === '' ? null : $unitPn;
        $sstmt->bind_param('iss', $batchId, $serialOrNull, $unitPnOrNull);
        $sstmt->execute();
    }

    if (!empty($specValues)) {
        $specKey = spec_schema_key_for_type_name(item_type_name_for_id($conn, $itemTypeId));
        if ($specKey !== null && isset(SPEC_SCHEMA[$specKey])) {
            $validSpecValues = [];
            foreach (SPEC_SCHEMA[$specKey] as $fieldKey => $meta) {
                if (isset($specValues[$fieldKey]) && trim((string)$specValues[$fieldKey]) !== '') {
                    $validSpecValues[$fieldKey] = trim((string)$specValues[$fieldKey]);
                }
            }
            if (!empty($validSpecValues)) {
                save_spec_values($conn, 'inventory_item_spec_values', 'item_id', $itemId, $validSpecValues);
            }
        }
    }

    $action = $isRestock ? 'Restocked inventory item' : 'Added inventory item';
    log_activity($conn, $action, "$itemName +$qty on $dateAdded (PO #$poNumber)", 'inventory');

    return ['item_name' => $itemName, 'qty' => $qty, 'is_restock' => $isRestock];
}

function item_type_name_for_id($conn, $itemTypeId) {
    static $cache = [];
    if (isset($cache[$itemTypeId])) return $cache[$itemTypeId];
    $stmt = $conn->prepare("SELECT name FROM item_types WHERE id = ?");
    $stmt->bind_param('i', $itemTypeId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $cache[$itemTypeId] = $row['name'] ?? '';
    return $cache[$itemTypeId];
}

function run_disposal_autoflag($conn) {
    $rows = $conn->query(
        "SELECT candidate.id, candidate.item_name, candidate.last_activity
         FROM (
             SELECT i.id, i.item_name,
                    GREATEST(
                      COALESCE((SELECT MAX(b.batch_date) FROM inventory_batches b WHERE b.item_id = i.id), i.date_added),
                      COALESCE((SELECT MAX(d.deployed_date) FROM deployments d WHERE d.item_id = i.id), '1970-01-01'),
                      COALESCE((SELECT MAX(d.returned_date) FROM deployments d WHERE d.item_id = i.id), '1970-01-01')
                    ) AS last_activity
             FROM inventory_items i
         ) AS candidate
         LEFT JOIN inventory_disposal_flags existing
           ON existing.item_id = candidate.id
           AND existing.last_activity_date = candidate.last_activity
           AND existing.status = 'pending'
         WHERE existing.id IS NULL"
    );
    if (!$rows) return;
    $today = date('Y-m-d');
    while ($r = $rows->fetch_assoc()) {
        $lastActivity = $r['last_activity'];
        $daysInactive = (int)floor((strtotime($today) - strtotime($lastActivity)) / 86400);
        if ($daysInactive < 30) continue;

        $ins = $conn->prepare(
            "INSERT INTO inventory_disposal_flags (item_id, flagged_date, last_activity_date, days_inactive, status)
             VALUES (?,?,?,?,'pending')"
        );
        $ins->bind_param('issi', $r['id'], $today, $lastActivity, $daysInactive);
        $ins->execute();
        log_activity($conn, 'Flagged for disposal review', "{$r['item_name']} — {$daysInactive} days with no activity", 'inventory');
    }
}
