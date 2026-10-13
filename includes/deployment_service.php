<?php

function deploy_one_item($conn, $userId, $itemId, $deptId, $recipientName, $poNumber, $pnNumber, $qty, $date, $time, $remarks, $deploymentType = 'new', $replacesDeploymentId = null, $itemDetails = '', $mrNumber = '') {
    $itemRow = $conn->prepare(
        "SELECT item_name, quantity_total,
                COALESCE((SELECT SUM(d.quantity_deployed) FROM deployments d WHERE d.item_id = i.id AND d.returned_date IS NULL), 0) AS deployed_qty
         FROM inventory_items i WHERE i.id = ?"
    );
    $itemRow->bind_param('i', $itemId);
    $itemRow->execute();
    $item = $itemRow->get_result()->fetch_assoc();

    $validationError = deployment_validation_error($item, $deptId, $poNumber, $pnNumber, $qty);
    if ($validationError !== null) {
        return [
            'ok' => false,
            'error' => $validationError,
            'item_name' => $item['item_name'] ?? null,
            'deployment_id' => null,
        ];
    }

    $stmt = $conn->prepare(
        "INSERT INTO deployments (item_id, department_id, recipient_name, po_number, pn_number, mr_number, quantity_deployed, deployment_type, replaces_deployment_id, deployed_date, deployed_time, remarks, item_details, deployed_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );

    $stmt->bind_param('iissssisissssi', $itemId, $deptId, $recipientName, $poNumber, $pnNumber, $mrNumber, $qty, $deploymentType, $replacesDeploymentId, $date, $time, $remarks, $itemDetails, $userId);
    $stmt->execute();
    $deploymentId = $stmt->insert_id;

    if ($deploymentType === 'replacement' && $replacesDeploymentId) {
        $ret = $conn->prepare("UPDATE deployments SET returned_date = ?, returned_time = ? WHERE id = ? AND returned_date IS NULL");
        $ret->bind_param('ssi', $date, $time, $replacesDeploymentId);
        $ret->execute();
    }

    $batchesRes = $conn->prepare(
        "SELECT b.id, b.quantity,
                b.quantity - COALESCE((
                  SELECT SUM(c.quantity) FROM deployment_batch_consumption c
                  JOIN deployments dd ON dd.id = c.deployment_id
                  WHERE c.batch_id = b.id AND dd.returned_date IS NULL
                ), 0) AS batch_left
         FROM inventory_batches b
         WHERE b.item_id = ?
         ORDER BY b.batch_date ASC, b.id ASC"
    );
    $batchesRes->bind_param('i', $itemId);
    $batchesRes->execute();
    $batches = $batchesRes->get_result()->fetch_all(MYSQLI_ASSOC);

    $batchPlan = plan_deployment_batch_consumption($qty, $batches);
    foreach ($batchPlan['allocations'] as $allocation) {
        $cstmt = $conn->prepare("INSERT INTO deployment_batch_consumption (deployment_id, batch_id, quantity) VALUES (?,?,?)");
        $cstmt->bind_param('iii', $deploymentId, $allocation['batch_id'], $allocation['quantity']);
        $cstmt->execute();
    }

    $deptNameRes = $conn->prepare("SELECT name FROM departments WHERE id=?");
    $deptNameRes->bind_param('i', $deptId);
    $deptNameRes->execute();
    $deptName = $deptNameRes->get_result()->fetch_assoc()['name'] ?? '';

    log_activity(
        $conn, 'Deployed item',
        "{$item['item_name']} x$qty to $deptName" . ($recipientName ? " (for $recipientName)" : '') . " (PO #$poNumber, PN #$pnNumber" . ($mrNumber !== '' ? ", MR #$mrNumber" : '') . ")",
        'deployment'
    );

    return ['ok' => true, 'error' => null, 'item_name' => $item['item_name'], 'deployment_id' => $deploymentId];
}
