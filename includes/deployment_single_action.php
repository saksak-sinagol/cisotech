<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deploy_item' && can_use_deployments()) {
    require_csrf_token();

    $validTextInput = request_strings_are_valid($_POST, [
        'recipient_name' => 150,
        'po_number' => 100,
        'pn_number' => 100,
        'deployed_date' => 10,
        'deployed_time' => 8,
        'remarks' => 2000,
    ]);
    if (!$validTextInput) {
        $errorMsg = 'One or more deployment fields are invalid.';
    } else {
    $itemId = request_positive_int($_POST, 'item_id') ?? 0;
    $deptId = request_nonnegative_int($_POST, 'department_id') ?? 0;
    $recipientName = request_string($_POST, 'recipient_name', 150, false) ?? '';
    $poNumber = request_string($_POST, 'po_number', 100, false) ?? '';
    $pnNumber = request_string($_POST, 'pn_number', 100, false) ?? '';
    $qty = array_key_exists('quantity_deployed', $_POST)
        ? (request_positive_int($_POST, 'quantity_deployed') ?? 0)
        : 1;
    $date = request_string($_POST, 'deployed_date', 10, false) ?: date('Y-m-d');
    $time = request_string($_POST, 'deployed_time', 8, false) ?: date('H:i:s');
    $remarks = request_string($_POST, 'remarks', 2000, false) ?? '';
    $userId = current_user()['id'];

    if ($itemId === 0 || $deptId === 0 || $qty === 0 || !valid_request_date($date) || !valid_request_time($time)) {
        $errorMsg = 'Please provide a valid item, department, quantity, date, and time.';
    } else {
        $result = deploy_one_item($conn, $userId, $itemId, $deptId, $recipientName, $poNumber, $pnNumber, $qty, $date, $time, $remarks);
        if (!$result['ok']) {
            $errorMsg = $result['error'];
        } else {
            $successMsg = 'Item deployed successfully.';
        }
    }
    }
}
