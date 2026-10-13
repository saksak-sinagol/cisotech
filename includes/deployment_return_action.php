<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_returned' && can_use_deployments()) {
    require_csrf_token();

    if (!request_strings_are_valid($_POST, [
        'returned_date' => 10,
        'returned_time' => 8,
        'confirm_username' => 50,
    ])) {
        $errorMsg = 'One or more return fields are invalid.';
    } else {
    $deployId = request_positive_int($_POST, 'deployment_id') ?? 0;
    $returnDate = request_string($_POST, 'returned_date', 10, false) ?: date('Y-m-d');
    $returnTime = request_string($_POST, 'returned_time', 8, false) ?: date('H:i:s');
    $confirmUsername = request_string($_POST, 'confirm_username', 50, false) ?? '';
    $me = current_user();

    if ($deployId === 0 || !valid_request_date($returnDate) || !valid_request_time($returnTime)) {
        $errorMsg = 'Please provide a valid deployment, return date, and time.';
    } elseif ($confirmUsername === '' || strcasecmp($confirmUsername, $me['username']) !== 0) {
        $errorMsg = 'Return not confirmed — the username typed did not match your account. Please try again.';
    } else {
        $infoRes = $conn->prepare(
            "SELECT d.item_id, i.item_name, dep.name AS dept_name
             FROM deployments d JOIN inventory_items i ON i.id=d.item_id JOIN departments dep ON dep.id=d.department_id
             WHERE d.id=?"
        );
        $infoRes->bind_param('i', $deployId);
        $infoRes->execute();
        $info = $infoRes->get_result()->fetch_assoc();

        $stmt = $conn->prepare("UPDATE deployments SET returned_date = ?, returned_time = ? WHERE id = ?");
        $stmt->bind_param('ssi', $returnDate, $returnTime, $deployId);
        $stmt->execute();

        if ($info) {
            log_activity($conn, 'Marked item returned', "{$info['item_name']} from {$info['dept_name']} (confirmed by @{$confirmUsername})", 'deployment');
            $_SESSION['return_success'] = "\"{$info['item_name']}\" from {$info['dept_name']} was returned successfully — " . date('M j, Y \a\t g:i:s A', strtotime("$returnDate $returnTime")) . '.';
        }
        header('Location: deployments.php');
        exit;
    }
    }
}
