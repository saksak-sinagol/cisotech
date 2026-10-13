<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/spec_schema.php';
require_once 'includes/deployment_rules.php';
require_login();

$pageTitle = 'Deployments';
$activePage = 'deployments';
$successMsg = '';
$errorMsg = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deploy_item' && can_use_deployments()) {
    require_csrf_token();

    $itemId = (int)($_POST['item_id'] ?? 0);
    $deptId = (int)($_POST['department_id'] ?? 0);
    $recipientName = trim($_POST['recipient_name'] ?? '');
    $poNumber = trim($_POST['po_number'] ?? '');
    $pnNumber = trim($_POST['pn_number'] ?? '');
    $qty    = max(1, (int)($_POST['quantity_deployed'] ?? 1));
    $date   = $_POST['deployed_date'] ?? date('Y-m-d');
    $time   = $_POST['deployed_time'] ?? date('H:i:s');
    $remarks = trim($_POST['remarks'] ?? '');
    $userId = current_user()['id'];

    $result = deploy_one_item($conn, $userId, $itemId, $deptId, $recipientName, $poNumber, $pnNumber, $qty, $date, $time, $remarks);
    if (!$result['ok']) {
        $errorMsg = $result['error'];
    } else {
        $successMsg = 'Item deployed successfully.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_deployment_cart' && can_use_deployments()) {
    require_csrf_token();

    header('Content-Type: application/json');
    $userId = current_user()['id'];
    $deptId = (int)($_POST['department_id'] ?? 0);
    $recipientName = trim($_POST['recipient_name'] ?? '');
    $date = $_POST['deployed_date'] ?? date('Y-m-d');
    $time = $_POST['deployed_time'] ?? date('H:i:s');
    $mrNumber = trim($_POST['mr_number'] ?? '');

    $deploymentType = $_POST['deployment_type'] ?? 'new';
    $deploymentType = normalize_deployment_type($deploymentType);
    $replacesDeploymentId = (int)($_POST['replaces_deployment_id'] ?? 0);
    $cartJson = $_POST['cart'] ?? '[]';
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

    $deployedCount = 0;
    $errors = [];
    $firstDeploymentId = null;
    foreach ($cart as $line) {
        $itemId = (int)($line['item_id'] ?? 0);

        $poNum = trim($line['po_number'] ?? '');
        $pnNum = trim($line['pn_number'] ?? '');
        $itemDetails = trim($line['item_details'] ?? '');
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
            if ($firstDeploymentId === null) $firstDeploymentId = $result['deployment_id'];
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

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['ajax'] ?? '') === 'items_by_category' && can_use_deployments()) {
    header('Content-Type: application/json');
    $typeId = (int)($_GET['type_id'] ?? 0);
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
    $itemId = (int)($_GET['item_id'] ?? 0);
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
    $itemId = (int)($_GET['item_id'] ?? 0);
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
    $deptId = (int)($_GET['department_id'] ?? 0);
    $recipientName = trim($_GET['recipient_name'] ?? '');
    if ($deptId === 0 || $recipientName === '') {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_returned' && can_use_deployments()) {
    require_csrf_token();

    $deployId = (int)($_POST['deployment_id'] ?? 0);
    $returnDate = $_POST['returned_date'] ?? date('Y-m-d');
    $returnTime = $_POST['returned_time'] ?? date('H:i:s');
    $confirmUsername = trim($_POST['confirm_username'] ?? '');
    $me = current_user();

    if ($confirmUsername === '' || strcasecmp($confirmUsername, $me['username']) !== 0) {
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

if (!empty($_SESSION['return_success'])) {
    $successMsg = $_SESSION['return_success'];
    unset($_SESSION['return_success']);
}

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

$availableItems = $conn->query(
    "SELECT i.id, i.item_name,
            (i.quantity_total - COALESCE((SELECT SUM(dd.quantity_deployed) FROM deployments dd WHERE dd.item_id=i.id AND dd.returned_date IS NULL),0)) AS available
     FROM inventory_items i
     HAVING available > 0
     ORDER BY i.item_name"
);
$departments = $conn->query("SELECT * FROM departments ORDER BY name");
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

include 'includes/header.php';
?>

<?php if ($successMsg): ?><div class="alert alert-success"><?php echo h($successMsg); ?></div><?php endif; ?>
<?php if ($errorMsg): ?><div class="alert alert-error"><?php echo h($errorMsg); ?></div><?php endif; ?>

<div id="deployDetailsBackdrop" class="login-modal-backdrop item-details-backdrop" onclick="closeDeployDetails()">
  <div class="login-modal-card item-details-card" onclick="event.stopPropagation();">
    <button type="button" class="login-modal-close" onclick="closeDeployDetails()">&times;</button>
    <h3 class="idt-title" style="margin-bottom:14px;">Item Details</h3>
    <div id="deployDetailsBody" style="white-space:pre-wrap; font-size:13.5px; line-height:1.6; color:var(--text-dim);"></div>
  </div>
</div>

<?php if (can_use_deployments()): ?>
<div class="panel">
  <div class="panel-header"><h2>Deploy Items</h2></div>
  <div class="panel-body">
    <button type="button" class="btn-primary" onclick="openDeployWizard()">+ Start / Continue Deployment</button>
  </div>
</div>

<div id="deployWizardBackdrop" class="login-modal-backdrop item-details-backdrop">
  <div class="login-modal-card item-details-card wiz-modal-card" onclick="event.stopPropagation();">
    <button type="button" class="login-modal-close" onclick="closeDeployWizard()">&times;</button>
    <h3 class="idt-title" style="margin-bottom:14px;">Deploy Items</h3>

    <div class="wiz-steps" id="wizSteps">
      <div class="wiz-step active" data-step="0"><span class="wiz-step-num">1</span> Type</div>
      <div class="wiz-step" data-step="1"><span class="wiz-step-num">2</span> Department</div>
      <div class="wiz-step" data-step="2"><span class="wiz-step-num">3</span> Faculty/Staff</div>
      <div class="wiz-step wiz-step-replace" data-step="2b" style="display:none;"><span class="wiz-step-num">4</span> Item to Replace</div>
      <div class="wiz-step" data-step="4"><span class="wiz-step-num">5</span> Items</div>
      <div class="wiz-step" data-step="5"><span class="wiz-step-num">6</span> Ready</div>
    </div>

    <div class="wiz-panel active" id="wizStep0">
      <p class="field-hint" style="margin-bottom:14px;">What kind of deployment is this?</p>
      <div class="wiz-choice-grid">
        <div class="wiz-choice-card" id="wizChoiceNew" onclick="wizSelectDeploymentType('new')">New Deployment</div>
        <div class="wiz-choice-card" id="wizChoiceReplacement" onclick="wizSelectDeploymentType('replacement')">Deployment for Replacement</div>
      </div>
    </div>

    <div class="wiz-panel" id="wizStep1">
      <p class="field-hint" style="margin-bottom:12px;">Select a department.</p>
      <div class="field">
        <label>Department</label>
        <select id="wizDeptSelect" onchange="wizSelectDepartment(this.value, this.options[this.selectedIndex].text)">
          <option value="">— Select department —</option>
          <?php foreach ($deptListPayload as $dept): ?>
            <option value="<?php echo $dept['id']; ?>"><?php echo h($dept['name']); ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (empty($deptListPayload)): ?><div class="field-hint">No departments on file yet. Ask an admin to add one on the Settings page.</div><?php endif; ?>
      </div>
      <div class="wiz-nav"><button type="button" class="btn-secondary btn-sm" onclick="wizGoStep(0)">← Back</button></div>
    </div>

    <div class="wiz-panel" id="wizStep2">
      <p class="field-hint" style="margin-bottom:12px;">Who is this item for? Select a name for <b id="wizStep2DeptName"></b>.</p>
      <div class="field">
        <label>Faculty/Staff</label>
        <select id="wizPersonSelect" onchange="wizSelectPerson(this.value)">
          <option value="">— Select name —</option>
        </select>
      </div>
      <div class="wiz-nav"><button type="button" class="btn-secondary btn-sm" onclick="wizGoStep(1)">← Back</button></div>
    </div>

    <div class="wiz-panel" id="wizStep2b">
      <p class="field-hint" style="margin-bottom:12px;">Select the currently deployed item that is being replaced for <b id="wizStep2bPersonName"></b>. It will be automatically marked as Returned.</p>
      <div class="wiz-select-list" id="wizReplaceList"></div>
      <div class="wiz-nav"><button type="button" class="btn-secondary btn-sm" onclick="wizGoStep(2)">← Back</button></div>
    </div>

    <div class="wiz-panel" id="wizStep4">
      <p class="field-hint" style="margin-bottom:12px;">Pick a category, then a kind of item, then the specific item and its PO Number — add as many as you need. <b>Category, Kind of Item, Item Model, PO Number, PN Number, and MR Number are all required.</b></p>
      <div class="field-row">
        <div class="field">
          <label>Category</label>
          <select id="wizItemCategorySelect" onchange="wizOnCategoryChange()">
            <option value="">— Select category —</option>
            <?php foreach ($itemCategoryListPayload as $ic): ?>
              <option value="<?php echo $ic['id']; ?>"><?php echo h($ic['name']); ?></option>
            <?php endforeach; ?>
          </select>
          <div class="field-hint">Don't see the category you need? <a href="settings.php" target="_blank">Add one in Settings</a>.</div>
        </div>
        <div class="field">
          <label>Kind of Item</label>
          <select id="wizCategorySelect" onchange="wizLoadItemsForCategory()" disabled>
            <option value="">— Select category first —</option>
          </select>
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Item Model (only items with stock left are shown)</label>
          <select id="wizItemSelect" onchange="wizLoadPnForItem(); wizLoadPresetSpecsForItem();">
            <option value="">— Select kind of item first —</option>
          </select>
        </div>
        <div class="field">
          <label>PN Number for this unit (required)</label>
          <select id="wizPnNumberSelect">
            <option value="">— Select item first —</option>
          </select>
          <div class="field-hint">Each unit's PO Number is shown next to its PN.</div>
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label>MR Number</label>
          <input type="text" id="wizMrNumber" placeholder="e.g. MR-2026-0032" oninput="wizOnMrNumberInput()" style="width:100%; box-sizing:border-box; background:var(--bg); border:1px solid var(--border); border-radius:4px; padding:9px 11px; color:var(--text); font-size:13px;">
          <div class="field-hint">Same MR Number applies to every item you add below.</div>
        </div>
      </div>


      <div id="wizComponentSection" style="display:none;">
        <div class="idt-section-title" style="margin-top:6px;">Components <span id="wizComponentParentLabel"></span></div>
        <p class="field-hint" style="margin-bottom:10px;">Every kind of item checked as a component of this Kind of Item (in Settings → Item Types → Components) is required here — pick the specific unit from stock. Each becomes its own deployment record and is deducted from that item's own stock, the same as any other deployed item.</p>
        <div id="wizComponentFieldsScroll" style="max-height:320px; overflow-y:auto; border:1px solid var(--border); border-radius:6px; padding:12px;">
          <div id="wizComponentFieldsContainer"></div>
        </div>
      </div>

      <div class="field-row">
        <div class="field" style="flex:1 1 100%;">
          <label>Details (optional)</label>
          <textarea id="wizItemDetails" rows="3" placeholder="e.g. unit condition, serial/asset tag, notes for this specific unit..." style="width:100%; box-sizing:border-box; background:var(--bg); border:1px solid var(--border); border-radius:4px; padding:9px 11px; color:var(--text); font-size:13px; font-family:inherit; resize:vertical;"></textarea>
        </div>
      </div>
      <div class="field-row">
        <div class="field" style="display:flex; align-items:flex-end;">
          <button type="button" class="btn-primary" style="width:100%;" onclick="wizAddCartLine()">+ Add This Item</button>
        </div>
      </div>

      <div class="idt-section-title" style="margin-top:20px;">Items Added So Far</div>
      <div id="wizCartList" class="idt-batches" style="max-height:220px; overflow-y:auto; border:1px solid var(--border); border-radius:6px; padding:8px;"></div>
      <div id="wizCartEmpty" class="idt-empty">No items added yet.</div>

      <div class="wiz-nav">
        <button type="button" class="btn-secondary btn-sm" onclick="wizGoStep(2)">← Back</button>
        <button type="button" class="btn-primary btn-sm" onclick="wizGoStep(5)">Done Adding Items →</button>
      </div>
    </div>

    <div class="wiz-panel" id="wizStep5">
      <p class="field-hint" style="margin-bottom:12px;">Review the deployment below, then confirm.</p>
      <div class="idt-stats" style="grid-template-columns:repeat(auto-fit, minmax(140px,1fr));">
        <div class="idt-stat"><div class="idt-stat-label">Type</div><div class="idt-stat-value" id="wizReviewType" style="font-size:14px;"></div></div>
        <div class="idt-stat"><div class="idt-stat-label">Department</div><div class="idt-stat-value" id="wizReviewDept" style="font-size:14px;"></div></div>
        <div class="idt-stat"><div class="idt-stat-label">Faculty/Staff</div><div class="idt-stat-value" id="wizReviewPerson" style="font-size:14px;"></div></div>
        <div class="idt-stat"><div class="idt-stat-label">MR Number</div><div class="idt-stat-value" id="wizReviewMrNumber" style="font-size:14px;"></div></div>
        <div class="idt-stat"><div class="idt-stat-label">Total Items</div><div class="idt-stat-value" id="wizReviewCount"></div></div>
      </div>
      <div class="idt-section-title">Items</div>
      <div id="wizReviewCartList" class="idt-batches" style="max-height:260px; overflow-y:auto; border:1px solid var(--border); border-radius:6px; padding:8px;"></div>

      <div class="field-row" style="margin-top:14px;">
        <div class="field" style="max-width:220px;">
          <label>Date Deployed</label>
          <input type="date" id="wizDeployDate" value="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="field" style="max-width:220px;">
          <label>Time Deployed</label>
          <input type="time" id="wizDeployTime" value="<?php echo date('H:i'); ?>">
        </div>
      </div>
      <div class="wiz-nav">
        <button type="button" class="btn-secondary btn-sm" onclick="wizGoStep(4)">← Back, Add More Items</button>
        <button type="button" class="btn-primary" id="wizConfirmBtn" onclick="wizConfirmDeployment()">✓ Ready for Deployment</button>
      </div>
    </div>

    <div class="wiz-panel" id="wizStepDone">
      <div style="text-align:center; padding:30px 10px;">
        <div style="font-size:40px; margin-bottom:10px;">✓</div>
        <h3 style="margin:0 0 6px;">Deployed!</h3>
        <p class="field-hint" id="wizDoneSummary" style="margin-bottom:20px;"></p>
        <button type="button" class="btn-primary" onclick="wizStartNew()">Start Another Deployment</button>
      </div>
    </div>

  </div>
</div>
<?php endif; ?>

<?php

function render_deploy_records_panel($title, $groupedDeploys, $consumptionByDeploy, $anchorId) {
    ?>
    <div class="panel" id="<?php echo h($anchorId); ?>" style="scroll-margin-top:20px;">
      <div class="panel-header">
        <h2><?php echo h($title); ?></h2>
      </div>
      <div class="panel-body">
        <?php if (empty($groupedDeploys)): ?>
          <div class="dash-search-empty">No records found.</div>
        <?php else: ?>
          <div class="user-card-grid">
            <?php foreach ($groupedDeploys as $userName => $entries): ?>
              <div class="user-card">
                <div class="user-card-header">
                  <span class="account-avatar"><?php echo strtoupper(substr($userName,0,1)); ?></span>
                  <span class="user-card-name"><?php echo h($userName); ?></span>
                  <?php if (!empty($entries[0]['deployed_by_role'])): ?>
                    <span class="account-role-badge role-<?php echo $entries[0]['deployed_by_role']; ?>"><?php echo h($entries[0]['deployed_by_role']); ?></span>
                  <?php endif; ?>
                  <span class="user-card-count"><?php echo count($entries); ?> <?php echo count($entries)===1?'record':'records'; ?></span>
                </div>
                <div class="user-card-body">
                  <?php foreach ($entries as $d): ?>
                    <div class="deploy-entry">
                      <div class="deploy-entry-top">
                        <span class="deploy-entry-date"><?php echo date('M j, Y', strtotime($d['deployed_date'])); ?><?php echo !empty($d['deployed_time']) ? ' · ' . date('g:i A', strtotime($d['deployed_time'])) : ''; ?></span>
                        <?php if ($d['returned_date']): ?>
                          <span class="badge b-green">Returned <?php echo date('M j, Y', strtotime($d['returned_date'])); ?><?php echo !empty($d['returned_time']) ? ' · ' . date('g:i A', strtotime($d['returned_time'])) : ''; ?></span>
                        <?php else: ?>
                          <span class="badge b-amber">Still deployed</span>
                        <?php endif; ?>
                        <?php if (!empty($d['po_number'])): ?><span class="badge">PO# <?php echo h($d['po_number']); ?></span><?php endif; ?>
                        <?php if (!empty($d['pn_number'])): ?><span class="badge">PN# <?php echo h($d['pn_number']); ?></span><?php endif; ?>
                        <?php if (!empty($d['mr_number'])): ?><span class="badge">MR# <?php echo h($d['mr_number']); ?></span><?php endif; ?>
                      </div>
                      <div class="deploy-entry-item"><?php echo h($d['item_name']); ?> <span style="color:var(--text-faint); font-weight:400;">×<?php echo (int)$d['quantity_deployed']; ?></span></div>
                      <div class="deploy-entry-meta">To <?php echo h($d['dept_name']); ?><?php echo !empty($d['recipient_name']) ? ' — ' . h($d['recipient_name']) : ''; ?><?php echo $d['remarks'] ? ' — ' . h($d['remarks']) : ''; ?></div>
                      <?php $consumed = $consumptionByDeploy[$d['id']] ?? []; if ($consumed): ?>
                        <div class="deploy-entry-meta" style="font-size:11px; color:var(--text-faint); margin-top:2px;">
                          Drawn from stock (oldest first):
                          <?php echo implode('; ', array_map(function($c) {
                            $label = date('M j, Y', strtotime($c['batch_date']));
                            if (!empty($c['po_number'])) $label .= ' · PO #' . h($c['po_number']);
                            return (int)$c['quantity'] . ' from batch dated ' . $label;
                          }, $consumed)); ?>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($d['item_details'])): ?>
                        <div class="deploy-entry-meta deploy-details-link" onclick="showDeployDetails(<?php echo (int)$d['id']; ?>)" style="cursor:pointer; text-decoration:underline; margin-top:4px;">View Details</div>
                        <div class="deploy-details-hidden" id="deployDetails<?php echo (int)$d['id']; ?>" style="display:none;"><?php echo h($d['item_details']); ?></div>
                      <?php endif; ?>
                      <?php if (!$d['returned_date']): ?>
                        <form method="POST" class="return-confirm-form" style="display:flex; gap:6px; align-items:center; margin-top:8px; flex-wrap:wrap;">
                          <input type="hidden" name="action" value="mark_returned">
                          <input type="hidden" name="deployment_id" value="<?php echo $d['id']; ?>">
                          <input type="hidden" name="confirm_username" class="return-confirm-username-field">
                          <input type="date" name="returned_date" value="<?php echo date('Y-m-d'); ?>" style="background:var(--bg); border:1px solid var(--border); border-radius:4px; padding:5px 7px; color:var(--text); font-size:12px;">
                          <input type="time" name="returned_time" value="<?php echo date('H:i'); ?>" style="background:var(--bg); border:1px solid var(--border); border-radius:4px; padding:5px 7px; color:var(--text); font-size:12px;">
                          <button type="submit" class="btn-secondary btn-sm">Return</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px; flex-wrap:wrap; gap:10px;">
  <div class="report-jumpnav">
    <a href="#dep-new">New Deployment</a>
    <a href="#dep-replacement">Replacement</a>
  </div>
  <div style="display:flex; gap:8px;">
    <a href="deployments.php?status=active" class="btn-secondary btn-sm">Active</a>
    <a href="deployments.php?status=returned" class="btn-secondary btn-sm">Returned</a>
    <a href="deployments.php?status=all" class="btn-secondary btn-sm">All</a>
  </div>
</div>

<?php render_deploy_records_panel('New Deployment Records', $groupedDeploysByType['new'], $consumptionByDeploy, 'dep-new'); ?>
<?php render_deploy_records_panel('Deployment for Replacement Records', $groupedDeploysByType['replacement'], $consumptionByDeploy, 'dep-replacement'); ?>

<style>
  .wiz-steps{ display:flex; gap:6px; flex-wrap:wrap; margin-bottom:20px; }
  .wiz-step{ display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text-faint); padding:6px 10px; border-radius:20px; border:1px solid var(--border-soft); }
  .wiz-step.active{ color:var(--accent-bright); border-color:var(--accent-bright); }
  .wiz-step.done{ color:var(--green); border-color:var(--green); }
  .wiz-step-num{ display:inline-flex; align-items:center; justify-content:center; width:18px; height:18px; border-radius:50%; background:var(--panel-2); font-size:10.5px; font-weight:700; }
  .wiz-step.active .wiz-step-num{ background:var(--accent-bright); color:var(--bg); }
  .wiz-step.done .wiz-step-num{ background:var(--green); color:var(--bg); }
  .wiz-panel{ display:none; }
  .wiz-panel.active{ display:block; }
  .wiz-choice-grid{ display:flex; flex-wrap:wrap; gap:10px; }
  .wiz-choice-card{
    padding:12px 18px; border:1px solid var(--border-soft); border-radius:8px; cursor:pointer;
    font-size:13px; font-weight:600; color:var(--text); text-align:center; min-width:90px;
    background:var(--panel-2); transition:border-color .12s;
  }
  .wiz-choice-card:hover{ border-color:var(--accent-bright); }
  .wiz-choice-card.selected{ border-color:var(--accent-bright); background:var(--accent-bright); color:var(--bg); }
  .wiz-nav{ display:flex; justify-content:space-between; margin-top:20px; gap:10px; }

  .wiz-select-list{
    display:flex; flex-direction:column; gap:6px; max-height:280px; overflow-y:auto;
    border:1px solid var(--border-soft); border-radius:8px; padding:6px; max-width:420px;
  }
  .wiz-select-item{
    padding:10px 12px; font-size:13px; cursor:pointer; border-radius:6px;
    background:var(--panel-2); border:1px solid transparent; transition:border-color .12s, background .12s;
  }
  .wiz-select-item:hover{ border-color:var(--accent-bright); }
  .wiz-select-item.selected{ background:var(--accent-bright); color:var(--bg); border-color:var(--accent-bright); }
  .wiz-select-empty{ padding:12px; font-size:12.5px; color:var(--text-faint); text-align:center; }
  .wiz-select-item-sub{ display:block; font-size:11px; color:var(--text-faint); margin-top:2px; }
  .wiz-select-item.selected .wiz-select-item-sub{ color:var(--bg); opacity:.8; }
  .wiz-selected-chip{
    display:inline-flex; align-items:center; gap:8px; margin-top:10px; padding:7px 12px;
    border:1px solid var(--accent-bright); border-radius:20px; font-size:12.5px; color:var(--accent-bright);
  }
  .wiz-cart-line{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; padding:9px 12px; font-size:12.5px; }
  .wiz-cart-remove{ margin-left:auto; background:none; border:none; color:var(--red); cursor:pointer; font-size:12px; }
  .wiz-search-box{
    width:100%; max-width:420px; box-sizing:border-box; margin-bottom:8px;
    background:var(--bg); border:1px solid var(--border); border-radius:6px;
    padding:8px 11px; color:var(--text); font-size:13px;
  }
  .wiz-modal-card{ max-width:700px; max-height:85vh; overflow-y:auto; }
  .report-jumpnav a{ margin-right:10px; }
</style>

<script>

  var wizPeopleByDept = <?php echo json_encode($peopleByDept, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
  var wizItemTypeList = <?php echo json_encode($itemTypeListPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

  var wizState = {
    currentStep: '0',
    deploymentType: 'new',
    departmentId: null,
    departmentName: '',
    recipientName: '',
    replacesDeploymentId: null,
    replacesLabel: '',
    refNumber: '',
    mrNumber: '',
    specKind: '',
    presetSpecs: {},
    cart: []
  };

  function wizEsc(s){
    var d = document.createElement('div');
    d.textContent = (s === null || s === undefined) ? '' : String(s);
    return d.innerHTML;
  }

  function wizFilterList(listId, query){
    var q = (query || '').toLowerCase().trim();
    var list = document.getElementById(listId);
    if (!list) return;
    var items = list.querySelectorAll('.wiz-select-item');
    var visibleCount = 0;
    items.forEach(function(el){
      var hay = el.getAttribute('data-search') || el.textContent.toLowerCase();
      var match = hay.indexOf(q) !== -1;
      el.style.display = match ? '' : 'none';
      if (match) visibleCount++;
    });
    var emptyEl = list.querySelector('.wiz-filter-empty');
    if (visibleCount === 0 && items.length > 0) {
      if (!emptyEl) {
        emptyEl = document.createElement('div');
        emptyEl.className = 'wiz-select-empty wiz-filter-empty';
        emptyEl.textContent = 'No matches.';
        list.appendChild(emptyEl);
      }
    } else if (emptyEl) {
      emptyEl.remove();
    }
  }

  var wizStepOrder = ['0','1','2','2b','4','5'];

  function wizGoStep(n){
    n = String(n);
    wizState.currentStep = n;
    document.querySelectorAll('.wiz-panel').forEach(function(p){ p.classList.remove('active'); });
    document.getElementById('wizStep' + n).classList.add('active');
    var nIdx = wizStepOrder.indexOf(n);
    document.querySelectorAll('.wiz-step').forEach(function(s){
      var step = s.getAttribute('data-step');
      var idx = wizStepOrder.indexOf(step);
      s.classList.remove('active','done');
      if (step === n) s.classList.add('active');
      else if (idx !== -1 && idx < nIdx) s.classList.add('done');
    });
    if (n === '5') wizRenderReview();
  }

  function openDeployWizard(){
    var backdrop = document.getElementById('deployWizardBackdrop');
    wizResetToStep0();
    backdrop.classList.add('open');
    requestAnimationFrame(function(){ backdrop.classList.add('show'); });
    wizGoStep('0');
  }

  function closeDeployWizard(){
    var backdrop = document.getElementById('deployWizardBackdrop');
    backdrop.classList.remove('show');
    setTimeout(function(){ backdrop.classList.remove('open'); }, 180);
  }

  function wizSelectDeploymentType(type){
    wizState.deploymentType = type;
    document.getElementById('wizChoiceNew').classList.toggle('selected', type === 'new');
    document.getElementById('wizChoiceReplacement').classList.toggle('selected', type === 'replacement');
    document.querySelectorAll('.wiz-step-replace').forEach(function(el){
      el.style.display = (type === 'replacement') ? 'flex' : 'none';
    });
    wizGoStep(1);
  }

  function wizOnCategoryChange(){
    var itemCatId = document.getElementById('wizItemCategorySelect').value;
    var kindSelect = document.getElementById('wizCategorySelect');
    if (!itemCatId) {
      kindSelect.innerHTML = '<option value="">— Select category first —</option>';
      kindSelect.disabled = true;
      wizLoadItemsForCategory();
      return;
    }
    var matches = wizItemTypeList.filter(function(t){ return String(t.category_id) === String(itemCatId); });
    if (!matches.length) {
      kindSelect.innerHTML = '<option value="">No kinds of item in this category yet</option>';
      kindSelect.disabled = true;
    } else {
      kindSelect.innerHTML = '<option value="">— Select kind of item —</option>' + matches.map(function(t){
        return '<option value="' + t.id + '" data-spec-kind="' + t.spec_kind + '">' + wizEsc(t.name) + '</option>';
      }).join('');
      kindSelect.disabled = false;
    }
    wizLoadItemsForCategory();
  }

  function wizSelectDepartment(id, name){
    wizState.departmentId = id;
    wizState.departmentName = name;
    wizState.recipientName = '';
    wizState.replacesDeploymentId = null;
    wizState.replacesLabel = '';
    document.getElementById('wizStep2DeptName').textContent = name;
    wizRenderPersonList();
    wizGoStep(2);
  }

  function wizRenderPersonList(){
    var selectEl = document.getElementById('wizPersonSelect');
    var people = wizPeopleByDept[wizState.departmentId] || [];
    if (people.length === 0) {
      selectEl.innerHTML = '<option value="">No faculty/staff on file for this department yet</option>';
      return;
    }
    selectEl.innerHTML = '<option value="">— Select name —</option>' + people.map(function(p){
      return '<option value="' + wizEsc(p.name) + '">' + wizEsc(p.name) + '</option>';
    }).join('');
  }

  function wizSelectPerson(name){
    wizState.recipientName = name;
    wizState.replacesDeploymentId = null;
    wizState.replacesLabel = '';
    if (!name) return;
    if (wizState.deploymentType === 'replacement') {
      document.getElementById('wizStep2bPersonName').textContent = name;
      wizLoadReplaceCandidates();
      wizGoStep('2b');
    } else {
      wizGoStep(4);
    }
  }

  function wizLoadReplaceCandidates(){
    var listEl = document.getElementById('wizReplaceList');
    listEl.innerHTML = '<div class="wiz-select-empty">Loading...</div>';
    var url = 'deployments.php?ajax=active_deployments_for_person&department_id=' + encodeURIComponent(wizState.departmentId) + '&recipient_name=' + encodeURIComponent(wizState.recipientName);
    fetch(url).then(function(r){ return r.json(); }).then(function(data){
      if (!data.ok || !data.items.length) {
        listEl.innerHTML = '<div class="wiz-select-empty">No currently deployed items found for this person.</div>';
        return;
      }
      listEl.innerHTML = data.items.map(function(it){
        var sub = (it.po_number ? 'PO# ' + it.po_number + ' — ' : '') + (it.pn_number ? 'PN# ' + it.pn_number + ' — ' : '') + it.deployed_date + (it.deployed_time ? ' ' + it.deployed_time : '');
        return '<div class="wiz-select-item" data-id="' + it.id + '" data-item-name="' + wizEsc(it.item_name) + '" data-search="' + wizEsc(it.item_name.toLowerCase()) + '">'
          + wizEsc(it.item_name)
          + '<span class="wiz-select-item-sub">' + wizEsc(sub) + '</span>'
          + '</div>';
      }).join('');
      listEl.querySelectorAll('.wiz-select-item').forEach(function(el){
        el.addEventListener('click', function(){
          wizSelectReplaceItem(el.getAttribute('data-id'), el.getAttribute('data-item-name'));
        });
      });
    }).catch(function(){
      listEl.innerHTML = '<div class="wiz-select-empty">Could not load deployed items. Please try again.</div>';
    });
  }

  function wizSelectReplaceItem(deploymentId, itemName){
    wizState.replacesDeploymentId = deploymentId;
    wizState.replacesLabel = itemName;
    document.querySelectorAll('#wizReplaceList .wiz-select-item').forEach(function(el){
      el.classList.toggle('selected', el.getAttribute('data-id') == deploymentId);
    });
    wizGoStep(4);
  }

  function wizOnMrNumberInput(){
    wizState.mrNumber = document.getElementById('wizMrNumber').value.trim();
  }

  function wizLoadItemsForCategory(){
    var kindSelect = document.getElementById('wizCategorySelect');
    var typeId = kindSelect.value;
    var itemSelect = document.getElementById('wizItemSelect');
    var selectedOption = kindSelect.options[kindSelect.selectedIndex];
    wizState.specKind = (typeId && selectedOption) ? (selectedOption.getAttribute('data-spec-kind') || '') : '';
    wizState.parentTypeId = typeId || '';
    wizState.parentTypeName = (typeId && selectedOption) ? selectedOption.text : '';
    wizUpdateComponentSectionVisibility();
    if (!typeId) {
      itemSelect.innerHTML = '<option value="">— Select kind of item first —</option>';
      return;
    }
    itemSelect.innerHTML = '<option value="">Loading...</option>';
    fetch('deployments.php?ajax=items_by_category&type_id=' + encodeURIComponent(typeId))
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data.ok || !data.items.length) {
          itemSelect.innerHTML = '<option value="">No items with stock in this kind of item</option>';
          return;
        }
        itemSelect.innerHTML = '<option value="">— Select item —</option>' + data.items.map(function(it){
          return '<option value="' + it.id + '" data-name="' + wizEsc(it.name) + '">' + wizEsc(it.name) + ' — ' + it.available + ' left</option>';
        }).join('');
        wizLoadPnForItem();
      });
  }

  function wizUpdateComponentSectionVisibility(){
    var section = document.getElementById('wizComponentSection');
    var label = document.getElementById('wizComponentParentLabel');
    var components = (wizState.parentTypeId && wizItemTypeComponentsMap[wizState.parentTypeId]) ? wizItemTypeComponentsMap[wizState.parentTypeId] : [];
    if (components.length) {
      section.style.display = 'block';
      label.textContent = '(' + wizState.parentTypeName + ')';
      wizRenderComponentFields(components);
    } else {
      section.style.display = 'none';
    }
  }

  function wizLoadPresetSpecsForItem(){
    var itemSelect = document.getElementById('wizItemSelect');
    var itemId = itemSelect.value;
    wizState.presetSpecs = {};
    if (!itemId) return;
    fetch('deployments.php?ajax=preset_specs_for_item&item_id=' + encodeURIComponent(itemId))
      .then(function(r){ return r.json(); })
      .then(function(data){
        wizState.presetSpecs = (data.ok && data.specs) ? data.specs : {};
      });
  }

  function wizLoadPnForItem(){
    var itemSelect = document.getElementById('wizItemSelect');
    var pnSelect = document.getElementById('wizPnNumberSelect');
    var itemId = itemSelect.value;
    if (!itemId) {
      pnSelect.innerHTML = '<option value="">— Select item first —</option>';
      return;
    }
    pnSelect.innerHTML = '<option value="">Loading...</option>';
    fetch('deployments.php?ajax=pn_numbers_for_item&item_id=' + encodeURIComponent(itemId))
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data.ok || !data.pn_numbers.length) {
          pnSelect.innerHTML = '<option value="">No available unit (PN) left for this item</option>';
          return;
        }
        var taken = wizState.cart.filter(function(l){ return String(l.item_id) === String(itemId); }).map(function(l){ return l.pn_number; });
        var free = data.pn_numbers.filter(function(u){ return taken.indexOf(u.pn) === -1; });
        if (!free.length) {
          pnSelect.innerHTML = '<option value="">All units for this item are already in your cart</option>';
          return;
        }
        pnSelect.innerHTML = '<option value="">— Select unit PN Number —</option>' + free.map(function(u){
          return '<option value="' + wizEsc(u.pn) + '" data-po="' + wizEsc(u.po) + '">' + wizEsc(u.pn) + ' — PO# ' + wizEsc(u.po) + (u.serial ? ' — S/N ' + wizEsc(u.serial) : '') + '</option>';
        }).join('');
      });
  }

  function wizAddCartLine(){
    var itemSelect = document.getElementById('wizItemSelect');
    var itemId = itemSelect.value;
    if (!itemId) { alert('Please select an item first.'); return; }
    var itemName = itemSelect.options[itemSelect.selectedIndex].getAttribute('data-name') || itemSelect.options[itemSelect.selectedIndex].text;
    var pnSelect = document.getElementById('wizPnNumberSelect');
    var pnNum = pnSelect.value;
    if (!pnNum) { alert('Please select a PN Number for this item — it is required.'); return; }
    var poNum = pnSelect.options[pnSelect.selectedIndex].getAttribute('data-po') || '';
    var mrBox = document.getElementById('wizMrNumber');
    var mrNum = mrBox.value.trim();
    if (!mrNum) { alert('Please enter an MR Number — it is required.'); return; }
    var kindSelect = document.getElementById('wizCategorySelect');
    var kindName = kindSelect.options[kindSelect.selectedIndex] ? kindSelect.options[kindSelect.selectedIndex].text : '';
    var itemCatSelect = document.getElementById('wizItemCategorySelect');
    var itemCatName = itemCatSelect.options[itemCatSelect.selectedIndex] ? itemCatSelect.options[itemCatSelect.selectedIndex].text : '';
    var detailsBox = document.getElementById('wizItemDetails');
    var itemDetails = detailsBox ? detailsBox.value.trim() : '';

    var componentLines = [];
    var hasComponents = (wizState.parentTypeId && wizItemTypeComponentsMap[wizState.parentTypeId] && wizItemTypeComponentsMap[wizState.parentTypeId].length);
    if (hasComponents) {
      var check = wizComponentsAllFilled();
      if (!check.ok) {
        alert('Please pick a unit for every required component: ' + check.missing.join(', '));
        return;
      }
      componentLines = wizCollectComponentLines();
    }

    wizState.mrNumber = mrNum;
    wizState.cart.push({
      item_id: itemId, item_name: itemName, category_name: itemCatName + ' — ' + kindName,
      po_number: poNum, pn_number: pnNum, item_details: itemDetails
    });
    wizState.cart = wizState.cart.concat(componentLines);
    pnSelect.innerHTML = '<option value="">— Select item first —</option>';
    if (detailsBox) detailsBox.value = '';
    wizResetComponentFields();
    wizUpdateComponentSectionVisibility();
    wizRenderCart();
  }

  function wizRemoveCartLine(idx){
    wizState.cart.splice(idx, 1);
    wizRenderCart();
  }

  function wizRenderCart(){
    var list = document.getElementById('wizCartList');
    var empty = document.getElementById('wizCartEmpty');
    if (wizState.cart.length === 0) {
      list.innerHTML = '';
      empty.style.display = 'block';
      return;
    }
    empty.style.display = 'none';
    list.innerHTML = wizState.cart.map(function(line, idx){
      return '<div class="wiz-cart-line" style="flex-wrap:wrap;">'
        + '<span><b>' + wizEsc(line.item_name) + '</b></span>'
        + '<span style="color:var(--text-faint);">' + wizEsc(line.category_name) + '</span>'
        + '<span class="idt-batch-po">PO# ' + wizEsc(line.po_number) + '</span>'
        + (line.pn_number ? '<span class="idt-batch-po">PN# ' + wizEsc(line.pn_number) + '</span>' : '')
        + (line.item_details ? '<span class="idt-batch-notes">' + wizEsc(line.item_details) + '</span>' : '')
        + '<button type="button" class="wiz-cart-remove" onclick="wizRemoveCartLine(' + idx + ')">Remove</button>'
        + (line.spec_notes ? '<div class="idt-batch-notes" style="flex-basis:100%; max-height:100px; overflow-y:auto; white-space:pre-wrap; margin-top:4px; padding-top:4px; border-top:1px dashed var(--border);">' + wizEsc(line.spec_notes) + '</div>' : '')
        + '</div>';
    }).join('');
  }

  var wizSpecKindLabels = { pc: 'PC/System Unit', laptop: 'Laptop', projector: 'Projector' };
  var wizItemTypeComponentsMap = <?php echo json_encode($itemTypeComponentsMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

  function wizRenderComponentFields(components){
    var container = document.getElementById('wizComponentFieldsContainer');
    var existing = {};
    document.querySelectorAll('#wizComponentFieldsContainer .wiz-comp-item').forEach(function(el){
      existing[el.getAttribute('data-comp-id')] = el.value;
    });
    var html = components.map(function(comp){
      return '<div class="field-row">'
        + '<div class="field"><label>' + wizEsc(comp.name) + '</label>'
        + '<select class="wiz-comp-item" data-comp-id="' + comp.id + '" data-comp-name="' + wizEsc(comp.name) + '" onchange="wizLoadPnForComponent(' + comp.id + ')">'
        + '<option value="">Loading stock...</option></select></div>'
        + '<div class="field"><label>PN Number</label>'
        + '<select class="wiz-comp-pn" data-comp-id="' + comp.id + '">'
        + '<option value="">— Select ' + wizEsc(comp.name) + ' first —</option></select></div>'
        + '</div>';
    }).join('');
    container.innerHTML = html;
    components.forEach(function(comp){ wizLoadComponentStock(comp.id, existing[comp.id]); });
  }

  function wizLoadComponentStock(typeId, previouslySelected){
    var itemSelect = document.querySelector('.wiz-comp-item[data-comp-id="' + typeId + '"]');
    if (!itemSelect) return;
    itemSelect.innerHTML = '<option value="">Loading...</option>';
    fetch('deployments.php?ajax=items_by_category&type_id=' + encodeURIComponent(typeId))
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data.ok || !data.items.length) {
          itemSelect.innerHTML = '<option value="">No stock available — required, restock first</option>';
          return;
        }
        itemSelect.innerHTML = '<option value="">— Select item —</option>' + data.items.map(function(it){
          return '<option value="' + it.id + '" data-name="' + wizEsc(it.name) + '"' + (String(it.id) === previouslySelected ? ' selected' : '') + '>' + wizEsc(it.name) + ' — ' + it.available + ' left</option>';
        }).join('');
        if (previouslySelected) wizLoadPnForComponent(typeId);
      });
  }

  function wizLoadPnForComponent(typeId){
    var itemSelect = document.querySelector('.wiz-comp-item[data-comp-id="' + typeId + '"]');
    var pnSelect = document.querySelector('.wiz-comp-pn[data-comp-id="' + typeId + '"]');
    var itemId = itemSelect.value;
    var compName = itemSelect.getAttribute('data-comp-name');
    if (!itemId) {
      pnSelect.innerHTML = '<option value="">— Select ' + wizEsc(compName) + ' first —</option>';
      return;
    }
    pnSelect.innerHTML = '<option value="">Loading...</option>';
    fetch('deployments.php?ajax=pn_numbers_for_item&item_id=' + encodeURIComponent(itemId))
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data.ok || !data.pn_numbers.length) {
          pnSelect.innerHTML = '<option value="">No available unit (PN) left for this item</option>';
          return;
        }
        pnSelect.innerHTML = '<option value="">— Select unit PN Number —</option>' + data.pn_numbers.map(function(u){
          return '<option value="' + wizEsc(u.pn) + '" data-po="' + wizEsc(u.po) + '">' + wizEsc(u.pn) + ' — PO# ' + wizEsc(u.po) + (u.serial ? ' — S/N ' + wizEsc(u.serial) : '') + '</option>';
        }).join('');
      });
  }

  function wizCollectComponentLines(){
    var lines = [];
    document.querySelectorAll('.wiz-comp-item').forEach(function(itemEl){
      var typeId = itemEl.getAttribute('data-comp-id');
      var compName = itemEl.getAttribute('data-comp-name');
      var itemId = itemEl.value;
      if (!itemId) return;
      var pnEl = document.querySelector('.wiz-comp-pn[data-comp-id="' + typeId + '"]');
      var poNum = pnEl.options[pnEl.selectedIndex] ? (pnEl.options[pnEl.selectedIndex].getAttribute('data-po') || '') : '';
      var itemName = itemEl.options[itemEl.selectedIndex].getAttribute('data-name') || itemEl.options[itemEl.selectedIndex].text;
      lines.push({
        item_id: itemId, item_name: itemName, category_name: compName,
        po_number: poNum, pn_number: pnEl.value, item_details: ''
      });
    });
    return lines;
  }

  function wizComponentsAllFilled(){
    var ok = true;
    var missing = [];
    document.querySelectorAll('.wiz-comp-item').forEach(function(itemEl){
      var typeId = itemEl.getAttribute('data-comp-id');
      var compName = itemEl.getAttribute('data-comp-name');
      var pnEl = document.querySelector('.wiz-comp-pn[data-comp-id="' + typeId + '"]');
      if (!itemEl.value || !pnEl.value) {
        ok = false;
        missing.push(compName);
      }
    });
    return { ok: ok, missing: missing };
  }

  function wizResetComponentFields(){
    document.getElementById('wizComponentFieldsContainer').innerHTML = '';
    document.getElementById('wizComponentSection').style.display = 'none';
  }

  function wizRenderReview(){
    var wizTypeLabels = { new: 'New Deployment', replacement: 'Deployment for Replacement' };
    document.getElementById('wizReviewType').textContent = wizTypeLabels[wizState.deploymentType] || 'New Deployment';
    document.getElementById('wizReviewDept').textContent = wizState.departmentName || '—';
    var personLabel = wizState.recipientName || '—';
    if (wizState.deploymentType === 'replacement' && wizState.replacesLabel) {
      personLabel += ' (replacing: ' + wizState.replacesLabel + ')';
    }
    document.getElementById('wizReviewPerson').textContent = personLabel;
    var mrReviewEl = document.getElementById('wizReviewMrNumber');
    if (mrReviewEl) mrReviewEl.textContent = wizState.mrNumber || '—';
    document.getElementById('wizReviewCount').textContent = wizState.cart.length;

    var list = document.getElementById('wizReviewCartList');
    if (wizState.cart.length === 0) {
      list.innerHTML = '<div class="idt-empty">No items added.</div>';
    } else {
      list.innerHTML = wizState.cart.map(function(line){
        return '<div class="wiz-cart-line" style="flex-wrap:wrap;">'
          + '<span><b>' + wizEsc(line.item_name) + '</b></span>'
          + '<span style="color:var(--text-faint);">' + wizEsc(line.category_name) + '</span>'
          + '<span class="idt-batch-po">PO# ' + wizEsc(line.po_number) + '</span>'
          + (line.pn_number ? '<span class="idt-batch-po">PN# ' + wizEsc(line.pn_number) + '</span>' : '')
          + (line.item_details ? '<span class="idt-batch-notes">' + wizEsc(line.item_details) + '</span>' : '')
          + (line.spec_notes ? '<div class="idt-batch-notes" style="flex-basis:100%; max-height:120px; overflow-y:auto; white-space:pre-wrap; margin-top:4px; padding-top:4px; border-top:1px dashed var(--border);">' + wizEsc(line.spec_notes) + '</div>' : '')
          + '</div>';
      }).join('');
    }
  }

  function wizConfirmDeployment(){
    if (wizState.cart.length === 0) { alert('Add at least one item before deploying.'); return; }
    if (wizState.deploymentType === 'replacement' && !wizState.replacesDeploymentId) { alert('Please select which deployed item is being replaced.'); return; }

    var btn = document.getElementById('wizConfirmBtn');
    btn.disabled = true;
    btn.textContent = 'Deploying...';

    var fd = new FormData();
    fd.append('action', 'submit_deployment_cart');
    fd.append('csrf_token', '<?php echo h(csrf_token()); ?>');
    fd.append('department_id', wizState.departmentId);
    fd.append('recipient_name', wizState.recipientName);
    fd.append('mr_number', wizState.mrNumber);
    fd.append('deployment_type', wizState.deploymentType);
    if (wizState.deploymentType === 'replacement') {
      fd.append('replaces_deployment_id', wizState.replacesDeploymentId);
    }
    fd.append('deployed_date', document.getElementById('wizDeployDate').value);
    fd.append('deployed_time', document.getElementById('wizDeployTime').value);
    fd.append('cart', JSON.stringify(wizState.cart));

    fetch('deployments.php', { method:'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(data){
        btn.disabled = false;
        btn.textContent = '✓ Ready for Deployment';
        if (data.ok) {
          document.getElementById('wizDoneSummary').textContent =
            data.deployed + ' item(s) deployed to ' + wizState.departmentName + (wizState.recipientName ? ' — ' + wizState.recipientName : '') + '.';
          document.querySelectorAll('.wiz-panel').forEach(function(p){ p.classList.remove('active'); });
          document.getElementById('wizStepDone').classList.add('active');
        } else {
          alert(data.error || 'Could not complete this deployment.');
        }
      })
      .catch(function(){
        btn.disabled = false;
        btn.textContent = '✓ Ready for Deployment';
        alert('Something went wrong. Please try again.');
      });
  }

  function wizStartNew(){
    wizResetToStep0();
  }

  function wizResetToStep0(){
    wizState = {
      currentStep: '0', deploymentType: 'new', departmentId: null, departmentName: '', recipientName: '',
      replacesDeploymentId: null, replacesLabel: '', mrNumber: '', specKind: '', presetSpecs: {}, cart: []
    };
    document.getElementById('wizChoiceNew').classList.remove('selected');
    document.getElementById('wizChoiceReplacement').classList.remove('selected');
    document.querySelectorAll('.wiz-step-replace').forEach(function(el){ el.style.display = 'none'; });
    document.getElementById('wizMrNumber').value = '';
    wizResetComponentFields();
    var pnSelectEl = document.getElementById('wizPnNumberSelect');
    if (pnSelectEl) pnSelectEl.innerHTML = '<option value="">— Select PO Number first —</option>';
    var itemDetailsEl = document.getElementById('wizItemDetails');
    if (itemDetailsEl) itemDetailsEl.value = '';
    document.getElementById('wizDeptSelect').value = '';
    document.getElementById('wizPersonSelect').innerHTML = '<option value="">— Select name —</option>';
    document.getElementById('wizReplaceList').innerHTML = '';
    document.getElementById('wizItemCategorySelect').value = '';
    document.getElementById('wizCategorySelect').innerHTML = '<option value="">— Select category first —</option>';
    document.getElementById('wizCategorySelect').disabled = true;
    document.getElementById('wizItemSelect').innerHTML = '<option value="">— Select kind of item first —</option>';
    document.getElementById('wizPnNumberSelect').innerHTML = '<option value="">— Select item first —</option>';
    wizRenderCart();

    fetch('deployments.php?ajax=current_ph_time')
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.ok) {
          document.getElementById('wizDeployDate').value = data.date;
          document.getElementById('wizDeployTime').value = data.time;
        }
      });
    wizGoStep(0);
  }

  var CURRENT_USERNAME = <?php echo json_encode(current_user()['username']); ?>;

  document.querySelectorAll('.return-confirm-form').forEach(function(form){
    form.addEventListener('submit', function(e){
      e.preventDefault();
      var typed = window.prompt('To confirm this return, please type your username (' + CURRENT_USERNAME + '):');
      if (typed === null) return;
      if (typed.trim().toLowerCase() !== CURRENT_USERNAME.toLowerCase()) {
        alert('That username did not match. Return not confirmed.');
        return;
      }
      form.querySelector('.return-confirm-username-field').value = typed.trim();
      form.submit();
    });
  });

  function showDeployDetails(deployId){
    var el = document.getElementById('deployDetails' + deployId);
    if (!el) return;
    var text = el.textContent || el.innerText || 'No details.';
    var backdrop = document.getElementById('deployDetailsBackdrop');
    document.getElementById('deployDetailsBody').textContent = text;
    backdrop.classList.add('open');
    requestAnimationFrame(function(){ backdrop.classList.add('show'); });
  }
  function closeDeployDetails(){
    var backdrop = document.getElementById('deployDetailsBackdrop');
    backdrop.classList.remove('show');
    setTimeout(function(){ backdrop.classList.remove('open'); }, 180);
  }

  document.addEventListener('DOMContentLoaded', function(){
    var backdrop = document.getElementById('deployWizardBackdrop');
    if (backdrop) {
      backdrop.addEventListener('click', closeDeployWizard);
    }
  });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
      var backdrop = document.getElementById('deployWizardBackdrop');
      if (backdrop && backdrop.classList.contains('open')) closeDeployWizard();
      var detailsBackdrop = document.getElementById('deployDetailsBackdrop');
      if (detailsBackdrop && detailsBackdrop.classList.contains('open')) closeDeployDetails();
    }
  });
</script>

<?php include 'includes/footer.php'; ?>
