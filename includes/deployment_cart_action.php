<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_deployment_cart' && can_use_deployments()) {
    require_csrf_token();

    header('Content-Type: application/json');
    $userId = current_user()['id'];
    $deptId = request_nonnegative_int($_POST, 'department_id') ?? 0;
    $recipientName = request_string($_POST, 'recipient_name', 150, false) ?? '';
    $date = request_string($_POST, 'deployed_date', 10, false) ?: date('Y-m-d');
    $time = request_string($_POST, 'deployed_time', 8, false) ?: date('H:i:s');
    $mrNumber = request_string($_POST, 'mr_number', 100, false) ?? '';
    if (!request_strings_are_valid($_POST, [
        'recipient_name' => 150,
        'deployed_date' => 10,
        'deployed_time' => 8,
        'mr_number' => 100,
        'cart' => 65536,
        'deployment_type' => 20,
    ])) {
        json_bad_request();
    }

    $deploymentTypeInput = request_string($_POST, 'deployment_type', 20, false) ?: 'new';
    $deploymentType = normalize_deployment_type($deploymentTypeInput);
    $replacesDeploymentId = request_positive_int($_POST, 'replaces_deployment_id') ?? 0;
    $cartJson = request_string($_POST, 'cart', 65536, false);

    if (
        $cartJson === null
        || !in_array($deploymentTypeInput, ['new', 'replacement'], true)
        || !valid_request_date($date)
        || !valid_request_time($time)
    ) {
        json_bad_request();
    }
    if ($cartJson === '') {
        $cartJson = '[]';
    }
    $cart = json_decode($cartJson, true);
    if ($deptId === 0) {
        echo json_encode(['ok' => false, 'error' => 'Please select a department.']);
        exit;
    }

    $deptCheck = $conn->prepare("SELECT id FROM departments WHERE id = ?");
    $deptCheck->bind_param('i', $deptId);
    $deptCheck->execute();
    if (!$deptCheck->get_result()->fetch_assoc()) {
        echo json_encode(['ok' => false, 'error' => 'Please select a valid department.']);
        exit;
    }
    if ($recipientName === '') {
        echo json_encode(['ok' => false, 'error' => 'Please select a faculty/staff name.']);
        exit;
    }

    $personCheck = $conn->prepare("SELECT 1 FROM department_people WHERE department_id = ? AND name = ? LIMIT 1");
    $personCheck->bind_param('is', $deptId, $recipientName);
    $personCheck->execute();
    if (!$personCheck->get_result()->fetch_assoc()) {
        echo json_encode(['ok' => false, 'error' => 'Please select a valid faculty/staff name for this department.']);
        exit;
    }
    if ($mrNumber === '') {
        echo json_encode(['ok' => false, 'error' => 'Please enter an MR (Material Request) Number.']);
        exit;
    }

    if ($deploymentType === 'replacement') {
        if ($replacesDeploymentId === 0) {
            echo json_encode(['ok' => false, 'error' => 'Please select which deployed item is being replaced.']);
            exit;
        }
        $oldCheck = $conn->prepare(
            "SELECT id FROM deployments WHERE id = ? AND department_id = ? AND recipient_name = ? AND returned_date IS NULL"
        );
        $oldCheck->bind_param('iis', $replacesDeploymentId, $deptId, $recipientName);
        $oldCheck->execute();
        if (!$oldCheck->get_result()->fetch_assoc()) {
            echo json_encode(['ok' => false, 'error' => 'The item selected for replacement was not found or is no longer active.']);
            exit;
        }
    } else {
        $replacesDeploymentId = null;
    }
    if (!is_array($cart) || empty($cart)) {
        echo json_encode(['ok' => false, 'error' => 'Add at least one item before deploying.']);
        exit;
    }
    if (!array_is_list($cart) || count($cart) > 100) {
        json_bad_request('A deployment cart can contain no more than 100 items.');
    }
    foreach ($cart as $line) {
        if (
            !is_array($line)
            || request_positive_int($line, 'item_id') === null
            || request_string($line, 'po_number', 100, false) === null
            || request_string($line, 'pn_number', 100, false) === null
            || request_string($line, 'item_details', 2000, false) === null
        ) {
            json_bad_request('A deployment cart row contains invalid values.');
        }
    }

    $deployedCount = 0;
    $errors = [];
    foreach ($cart as $line) {
        $itemId = request_positive_int($line, 'item_id') ?? 0;
        $poNum = request_string($line, 'po_number', 100, false);
        $pnNum = request_string($line, 'pn_number', 100, false);
        $itemDetails = request_string($line, 'item_details', 2000, false);
        if ($poNum === '') {
            $errors[] = 'Please select a PO Number for every item in the cart.';
            continue;
        }
        if ($pnNum === '') {
            $errors[] = 'Please select a PN Number for every item in the cart.';
            continue;
        }

        $unitCheck = $conn->prepare(
            "SELECT s.id FROM inventory_batch_serials s
             JOIN inventory_batches b ON b.id = s.batch_id
             LEFT JOIN deployments d ON d.id = s.deployment_id AND d.returned_date IS NULL
             WHERE b.item_id = ? AND b.po_number = ? AND s.pn_number = ? AND d.id IS NULL
             ORDER BY s.id ASC LIMIT 1"
        );
        $unitCheck->bind_param('iss', $itemId, $poNum, $pnNum);
        $unitCheck->execute();
        $unitRow = $unitCheck->get_result()->fetch_assoc();
        if (!$unitRow) {
            $errors[] = 'That PN Number is not an available unit for the selected item and PO Number (it may already be deployed).';
            continue;
        }
        $unitId = (int)$unitRow['id'];

        $result = deploy_one_item($conn, $userId, $itemId, $deptId, $recipientName, $poNum, $pnNum, 1, $date, $time, '', $deploymentType, $replacesDeploymentId, $itemDetails, $mrNumber);
        if ($result['ok']) {
            $deployedCount++;
            $tag = $conn->prepare("UPDATE inventory_batch_serials SET deployment_id = ? WHERE id = ?");
            $tag->bind_param('ii', $result['deployment_id'], $unitId);
            $tag->execute();
        } else {
            $errors[] = $result['error'];
        }
    }

    if ($deployedCount > 0) {
        echo json_encode(['ok' => true, 'deployed' => $deployedCount, 'errors' => $errors]);
    } else {
        echo json_encode(['ok' => false, 'error' => $errors[0] ?? 'Could not deploy the selected items.']);
    }
    exit;
}
