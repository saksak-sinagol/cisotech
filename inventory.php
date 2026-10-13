<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/spec_schema.php';
require_once 'includes/inventory_rules.php';
require_once 'includes/inventory_service.php';
require_login();
enforce_sensitive_rate_limit($conn);

$pageTitle = 'Inventory';
$activePage = 'inventory';
$successMsg = '';
$errorMsg = '';
$userId = current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_item' && is_admin()) {
    require_csrf_token();

    $poNumber    = request_string($_POST, 'po_number', 100, false) ?? '';
    $dateAdded   = request_string($_POST, 'date_added', 10, false) ?: date('Y-m-d');
    $timeAdded   = request_string($_POST, 'time_added', 8, false) ?: date('H:i:s');
    $threshold   = normalize_low_stock_threshold($_POST['low_stock_threshold'] ?? 5);
    $details     = request_string($_POST, 'details', 2000, false) ?? '';

    $itemNames   = $_POST['item_name'] ?? [];
    $itemTypeIds = $_POST['item_type_id'] ?? [];
    $quantities  = $_POST['quantity_total'] ?? [];
    $unitPnsByRow = $_POST['unit_pn'] ?? [];
    $serialsByRow = $_POST['serial_numbers'] ?? [];
    $specValuesByRow = $_POST['spec_values'] ?? [];

    if (!valid_request_date($dateAdded) || !valid_request_time($timeAdded)) {
        $errorMsg = 'Please enter a valid date and time.';
    } elseif (
        !is_array($itemNames) || count($itemNames) > 100
        || !is_array($itemTypeIds) || !is_array($quantities)
        || !is_array($unitPnsByRow) || !is_array($serialsByRow) || !is_array($specValuesByRow)
    ) {
        $errorMsg = 'The submitted inventory rows are invalid.';
    } elseif ($poNumber === '') {
        $errorMsg = 'PO Number is required.';
    } elseif (empty($itemNames)) {
        $errorMsg = 'Add at least one item.';
    } else {
        $results = [];
        $rowError = '';
        foreach ($itemNames as $idx => $rawName) {
            $itemName = is_string($rawName) ? trim($rawName) : '';
            $itemTypeId = request_positive_int(['item_type_id' => $itemTypeIds[$idx] ?? null], 'item_type_id') ?? 0;
            $qty        = normalize_inventory_quantity($quantities[$idx] ?? 1);
            if (strlen($itemName) > 150) {
                $rowError = 'Item names must be 150 characters or fewer.';
                continue;
            }
            if ($itemName === '' || $itemTypeId === 0) {
                continue;
            }
            $unitPnValidation = validate_unit_property_numbers($unitPnsByRow[$idx] ?? [], $qty);
            if (!$unitPnValidation['valid']) {
                $rowError = $unitPnValidation['error'];
                continue;
            }
            $rowUnitPns = $unitPnValidation['unit_pns'];
            $pnNumber = $rowUnitPns[0];
            $rowSerials = $serialsByRow[$idx] ?? [];
            $rowSpecValues = $specValuesByRow[$idx] ?? [];
            $results[] = add_or_restock_item_row(
                $conn, $userId, $itemName, $itemTypeId, $qty, $details,
                $pnNumber, $poNumber, $threshold, $dateAdded, $timeAdded, $rowSerials, $rowSpecValues, $rowUnitPns
            );
        }

        if (empty($results)) {
            $errorMsg = $rowError !== '' ? $rowError : 'Item model, kind of item, and a PN Number for every unit are required for at least one row.';
        } else {
            $summary = summarize_inventory_restock_results($results);
            $successMsg = $summary['message'] . " under PO #$poNumber." . ($rowError !== '' ? ' (Some rows were skipped: ' . $rowError . ')' : '');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_remark' && is_admin()) {
    require_csrf_token();

    $itemId = request_positive_int($_POST, 'item_id') ?? 0;
    $remarkText = request_string($_POST, 'remark_text', 2000) ?? '';
    $remarkDate = request_string($_POST, 'remark_date', 10) ?? '';

    $nameRes = $conn->prepare("SELECT item_name FROM inventory_items WHERE id=?");
    $nameRes->bind_param('i', $itemId);
    $nameRes->execute();
    $row = $nameRes->get_result()->fetch_assoc();

    if (!$row) {
        $errorMsg = 'Item not found.';
    } elseif (!valid_request_date($remarkDate)) {
        $errorMsg = 'Please enter a valid remark date.';
    } elseif ($remarkText === '') {
        $errorMsg = 'Please enter a remark.';
    } else {
        $stmt = $conn->prepare("INSERT INTO inventory_remarks (item_id, remark, remark_date, added_by) VALUES (?,?,?,?)");
        $stmt->bind_param('issi', $itemId, $remarkText, $remarkDate, $userId);
        $stmt->execute();
        log_activity($conn, 'Added remark to inventory item', "{$row['item_name']}: $remarkText", 'inventory');
        $successMsg = "Remark added to \"{$row['item_name']}\".";
    }
}

run_disposal_autoflag($conn);

require_once 'includes/inventory_page_data.php';

include 'includes/header.php';
?>

<?php if ($successMsg): ?><div class="alert alert-success" role="status"><?php echo h($successMsg); ?></div><?php endif; ?>
<?php if ($errorMsg): ?><div class="alert alert-error" role="alert"><?php echo h($errorMsg); ?></div><?php endif; ?>

<div class="activity-tabs">
  <!-- <a href="inventory.php?" class="activity-tab <?php echo $invTab==='add'?'active':''; ?>">Add Item</a> -->
  <a href="inventory.php?tab=items" class="activity-tab <?php echo $invTab==='items'?'active':''; ?>">Inventory Items</a>
  <a href="inventory.php?tab=deployed" class="activity-tab <?php echo $invTab==='deployed'?'active':''; ?>">Deployed Items <span class="activity-tab-count"><?php echo count($deployedItemsRows); ?></span></a>
  <a href="inventory.php?tab=returned" class="activity-tab <?php echo $invTab==='returned'?'active':''; ?>">Returned Items <span class="activity-tab-count"><?php echo count($returnedItemsRows); ?></span></a>
</div>

<?php if ($invTab === 'deployed'): ?>

<div class="panel">
  <div class="panel-header">
    <h2>Deployed Items</h2>
  </div>
  <div class="panel-body">
    <?php if (!empty($deployedKindPills)): ?>
    <div class="activity-tabs" id="deployedKindPills" style="margin-bottom:14px;">
      <button type="button" class="activity-tab active" data-kind="all" onclick="filterDeployedByKind('all', this)">All Kinds</button>
      <?php foreach ($deployedKindPills as $typeId => $typeName): ?>
        <button type="button" class="activity-tab" data-kind="<?php echo (int)$typeId; ?>" onclick="filterDeployedByKind('<?php echo (int)$typeId; ?>', this)"><?php echo h($typeName); ?></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($deployedItemsRows)): ?>
      <table class="data-table"><tbody><tr class="empty-row"><td colspan="7">Nothing currently deployed.</td></tr></tbody></table>
    <?php else: ?>
      <table class="data-table" id="deployedItemsTable">
        <thead>
          <tr>
            <th>MR No.</th>
            <th>Item</th>
            <th>Kind of Item</th>
            <th>Location</th>
            <th>Date Deployed</th>
            <th>Remarks</th>
            <th>Deployed By</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($deployedItemsRows as $d): ?>
            <tr data-kind-id="<?php echo (int)$d['type_id']; ?>" class="row-clickable" onclick="showDeployedSpecs(<?php echo (int)$d['id']; ?>)" title="Click to view unit specifications">
              <td><?php echo h($d['mr_number'] ?: '—'); ?></td>
              <td><?php echo h($d['item_name']); ?> <span style="color:var(--text-faint);">×<?php echo (int)$d['quantity_deployed']; ?></span></td>
              <td><?php echo h($d['type_name']); ?></td>
              <td><?php echo h($d['dept_name']); ?></td>
              <td><?php echo date('M j, Y', strtotime($d['deployed_date'])); ?><?php echo !empty($d['deployed_time']) ? ' · ' . date('g:i A', strtotime($d['deployed_time'])) : ''; ?></td>
              <td><?php echo h($d['remarks'] ?: '—'); ?></td>
              <td><?php echo h($d['deployed_by_name'] ?: 'Unknown User'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<script>

  function filterDeployedByKind(kindId, btn){
    document.querySelectorAll('#deployedKindPills .activity-tab').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
    document.querySelectorAll('#deployedItemsTable tbody tr').forEach(function(row){
      if (kindId === 'all' || row.getAttribute('data-kind-id') === String(kindId)) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    });
  }

  var deployedSpecsData = <?php
    $specsPayload = [];
    foreach ($deployedItemsRows as $d) {
        $savedValues = $specValuesByDeployment[$d['id']] ?? [];
        $specKind = spec_schema_key_for_type_name($d['type_name']);
        $fields = [];
        if ($specKind !== null && isset(SPEC_SCHEMA[$specKind])) {
            foreach (SPEC_SCHEMA[$specKind] as $fieldKey => $meta) {
                if (!empty($savedValues[$fieldKey])) {
                    $fields[] = ['label' => $meta[0], 'value' => $savedValues[$fieldKey]];
                }
            }
        }

        $peripherals = [];
        if (!empty($peripheralTypeIdsForDisplay)) {
            foreach ($deployedItemsRows as $sib) {
                if ($sib['id'] === $d['id']) continue;
                if (!isset($peripheralTypeIdsForDisplay[$sib['type_id']])) continue;
                if ($sib['department_id'] == $d['department_id']
                    && $sib['recipient_name'] === $d['recipient_name']
                    && $sib['mr_number'] === $d['mr_number']
                    && $sib['deployed_date'] === $d['deployed_date']
                    && $sib['deployed_time'] === $d['deployed_time']) {
                    $peripherals[] = ucfirst($peripheralTypeIdsForDisplay[$sib['type_id']]) . ': ' . $sib['item_name'];
                }
            }
        }

        $specsPayload[$d['id']] = [
            'item'        => $d['item_name'],
            'kind'        => $d['type_name'],
            'dept'        => $d['dept_name'],
            'mr'          => $d['mr_number'] ?: '',
            'fields'      => $fields,
            'peripherals' => $peripherals,
        ];
    }
    echo json_encode($specsPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
  ?>;

  function showDeployedSpecs(deploymentId){
    var d = deployedSpecsData[deploymentId];
    if (!d) return;
    var bodyHtml = '';
    if (d.fields.length === 0 && d.peripherals.length === 0) {
      bodyHtml = '<div class="idt-empty">No unit specifications were recorded for this deployment.</div>';
    } else {
      bodyHtml += '<div style="max-height:320px; overflow-y:auto; padding:2px;">';
      if (d.fields.length > 0) {
        bodyHtml += '<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px 16px; padding:10px; background:var(--panel-2); border:1px solid var(--border-soft); border-radius:6px;">'
          + d.fields.map(function(f){
              return '<div><div style="font-size:11px; color:var(--text-faint); text-transform:uppercase; letter-spacing:.03em; margin-bottom:2px;">' + escHtml(f.label) + '</div><div style="font-size:13px;">' + escHtml(f.value) + '</div></div>';
            }).join('')
          + '</div>';
      }
      if (d.peripherals.length > 0) {
        bodyHtml += '<div style="margin-top:12px;"><div class="idt-section-title" style="margin-top:0;">Peripherals</div>'
          + '<div style="font-size:13px; line-height:1.7;">' + d.peripherals.map(function(p){ return escHtml(p); }).join('<br>') + '</div></div>';
      }
      bodyHtml += '</div>';
    }
    document.getElementById('deployedSpecsTitle').textContent = d.item + (d.mr ? ' — MR #' + d.mr : '');
    document.getElementById('deployedSpecsSubtitle').textContent = d.kind + ' · ' + d.dept;
    document.getElementById('deployedSpecsBody').innerHTML = bodyHtml;
    var backdrop = document.getElementById('deployedSpecsBackdrop');
    backdrop.classList.add('open');
    requestAnimationFrame(function(){ backdrop.classList.add('show'); });
  }

  function closeDeployedSpecs(){
    var backdrop = document.getElementById('deployedSpecsBackdrop');
    backdrop.classList.remove('show');
    setTimeout(function(){ backdrop.classList.remove('open'); }, 180);
  }
</script>

<div id="deployedSpecsBackdrop" class="login-modal-backdrop item-details-backdrop" onclick="if(event.target===this) closeDeployedSpecs();">
  <div class="login-modal-card item-details-card" style="max-width:520px;">
    <button type="button" class="login-modal-close" onclick="closeDeployedSpecs()">&times;</button>
    <h3 class="idt-title" id="deployedSpecsTitle" style="margin-bottom:2px;"></h3>
    <div class="field-hint" id="deployedSpecsSubtitle" style="margin-bottom:14px;"></div>
    <div class="idt-section-title">Unit Specifications</div>
    <div id="deployedSpecsBody"></div>
  </div>
</div>

<?php elseif ($invTab === 'returned'): ?>

<div class="panel">
  <div class="panel-header">
    <h2>Returned Items</h2>
  </div>
  <div class="panel-body">
    <?php if (!empty($returnedKindPills)): ?>
    <div class="activity-tabs" id="returnedKindPills" style="margin-bottom:14px;">
      <button type="button" class="activity-tab active" data-kind="all" onclick="filterReturnedByKind('all', this)">All Kinds</button>
      <?php foreach ($returnedKindPills as $typeId => $typeName): ?>
        <button type="button" class="activity-tab" data-kind="<?php echo (int)$typeId; ?>" onclick="filterReturnedByKind('<?php echo (int)$typeId; ?>', this)"><?php echo h($typeName); ?></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($returnedItemsRows)): ?>
      <table class="data-table"><tbody><tr class="empty-row"><td colspan="8">Nothing has been returned yet.</td></tr></tbody></table>
    <?php else: ?>
      <table class="data-table" id="returnedItemsTable">
        <thead>
          <tr>
            <th>MR No.</th>
            <th>Item</th>
            <th>Kind of Item</th>
            <th>Was Deployed To</th>
            <th>Date Deployed</th>
            <th>Date Returned</th>
            <th>Remarks</th>
            <th>Deployed By</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($returnedItemsRows as $r): ?>
            <tr data-kind-id="<?php echo (int)$r['type_id']; ?>">
              <td><?php echo h($r['mr_number'] ?: '—'); ?></td>
              <td><?php echo h($r['item_name']); ?> <span style="color:var(--text-faint);">×<?php echo (int)$r['quantity_deployed']; ?></span></td>
              <td><?php echo h($r['type_name']); ?></td>
              <td><?php echo h($r['dept_name']); ?><?php echo !empty($r['recipient_name']) ? ' — ' . h($r['recipient_name']) : ''; ?></td>
              <td><?php echo date('M j, Y', strtotime($r['deployed_date'])); ?><?php echo !empty($r['deployed_time']) ? ' · ' . date('g:i A', strtotime($r['deployed_time'])) : ''; ?></td>
              <td><?php echo date('M j, Y', strtotime($r['returned_date'])); ?><?php echo !empty($r['returned_time']) ? ' · ' . date('g:i A', strtotime($r['returned_time'])) : ''; ?></td>
              <td><?php echo h($r['remarks'] ?: '—'); ?></td>
              <td><?php echo h($r['deployed_by_name'] ?: 'Unknown User'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<script>
  function filterReturnedByKind(kindId, btn){
    document.querySelectorAll('#returnedKindPills .activity-tab').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
    document.querySelectorAll('#returnedItemsTable tbody tr').forEach(function(row){
      row.style.display = (kindId === 'all' || row.getAttribute('data-kind-id') === String(kindId)) ? '' : 'none';
    });
  }
</script>

<?php else: ?>

<?php if ($itemTypeCount === 0): ?>
  <div class="alert alert-error" role="alert">
    No item types/kinds have been defined yet (e.g. "Projector", "PC Parts").
    <?php if (is_admin()): ?>
      <a href="settings.php" style="color:inherit; text-decoration:underline;">Go to Settings to add one first</a>.
    <?php else: ?>
      Ask your administrator to add item types under Settings before you can add inventory.
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if (is_admin()): ?>
<div class="panel">
  <div class="panel-header">
    <h2>Add / Restock Inventory Item</h2>
  </div>
  <div class="panel-body">
    <form method="POST" id="restockForm">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="add_item">
      <div class="field-row">
        <div class="field">
          <label>PO Number</label>
          <input type="text" name="po_number" placeholder="Provide here the PO number" required>
        </div>
        <div class="field">
          <label>GRPO Number</label>
          <input type="text" name="grpo_number" placeholder="Provide here the GRPO number">
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Date Added</label>
          <input type="date" name="date_added" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
        <div class="field">
          <label>Time Added</label>
          <input type="time" name="time_added" value="<?php echo date('H:i'); ?>" required>
        </div>
        <div class="field">
          <label>Low Stock Threshold</label>
          <input type="number" name="low_stock_threshold" value="5" min="0" required>
          <div class="field-hint">Applies to any new item created below.</div>
        </div>
      </div>

      <div id="restockItemRows"></div>

      <div class="form-actions" style="justify-content:flex-start; margin-bottom:16px;">
        <button type="button" class="btn-secondary btn-sm" onclick="restockAddRow()">+ Add another item</button>
      </div>

      <div class="field-row">
        <div class="field" style="flex:1 1 100%;">
          <label>Details (optional)</label>
          <textarea name="details" rows="4" placeholder="e.g. Delivered by supplier, batch 1 of 2, general delivery notes..." style="width:100%; box-sizing:border-box; background:var(--bg); border:1px solid var(--border); border-radius:4px; padding:9px 11px; color:var(--text); font-size:13px; font-family:inherit; resize:vertical;"></textarea>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn-primary" data-loading-text="Saving..." <?php echo $itemTypeCount===0?'disabled':''; ?>>Save</button>
      </div>
    </form>
  </div>
</div>

<template id="restockRowTemplate">
  <div class="restock-item-row" style="border:1px solid var(--border); border-radius:6px; padding:12px; margin-bottom:12px; position:relative;">
    <button type="button" class="btn-secondary btn-sm restock-remove-row" style="position:absolute; top:8px; right:8px;" onclick="restockRemoveRow(this)">Remove</button>
    <div class="field-row">
      <div class="field">
        <label>Kind of Item</label>
        <select class="restock-type-select" name="item_type_id[]" required onchange="restockRenderSpecs(this.closest('.restock-item-row'))">
          <option value="">— Select —</option>
          <?php $itemTypes->data_seek(0); while ($t = $itemTypes->fetch_assoc()): ?>
            <option value="<?php echo $t['id']; ?>"><?php echo h($t['name']); ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="field">
        <label>Item Model</label>
        <input type="text" name="item_name[]" placeholder="e.g. Epson EB-X05 Projector" required>
      </div>
      <div class="field">
        <label>Quantity</label>
        <input type="number" class="restock-qty-input" name="quantity_total[]" value="1" min="1" required>
      </div>
    </div>
    <div class="restock-specs-wrap"></div>
    <div class="restock-serials-wrap">
      <label style="display:block; margin-bottom:6px; font-size:12.5px; color:var(--text-dim);">Per-unit PN Number (required) and Serial Number (optional) — one line per unit</label>
      <div class="restock-serials-list"></div>
    </div>
  </div>
</template>
<?php endif; ?>

<div class="panel">
  <div class="panel-header">
    <h2>Inventory Items</h2>
    <form method="GET" style="display:flex; gap:8px; flex-wrap:wrap;">
      <input type="text" name="q" placeholder="Search item name..." value="<?php echo h($search); ?>" style="background:var(--bg); border:1px solid var(--border); border-radius:4px; padding:7px 10px; color:var(--text); font-size:12.5px;">
      <select name="type" style="background:var(--bg); border:1px solid var(--border); border-radius:4px; padding:7px 10px; color:var(--text-dim); font-size:12.5px;">
        <option value="">All Kinds</option>
        <?php $itemTypes->data_seek(0); while ($t = $itemTypes->fetch_assoc()): ?>
          <option value="<?php echo $t['id']; ?>" <?php echo ($filterType == $t['id'])?'selected':''; ?>><?php echo h($t['name']); ?></option>
        <?php endwhile; ?>
      </select>
      <button type="submit" class="btn-secondary btn-sm">Filter</button>
      <a href="inventory.php" class="btn-secondary btn-sm">Reset</a>
    </form>
  </div>
  <div class="panel-body" style="padding:0;">
    <?php if (empty($allItems)): ?>
      <table class="data-table"><tbody><tr class="empty-row"><td colspan="9">No inventory items found.</td></tr></tbody></table>
    <?php else: ?>
        <table class="data-table">
          <thead>
            <tr>
              <th>Kind of Item</th>
              <th>Item Model</th>
              <th>Total Qty</th>
              <th>Deployed</th>
              <th>Left</th>
              <th>Status</th>
              <th>Date Added</th>
              <th>PN # / PO #</th>
              <?php if (is_admin()): ?><th>Remarks</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allItems as $i):
              $left = max(0, $i['quantity_total'] - $i['deployed_qty']);
              $isLow = $left <= $i['low_stock_threshold'];
              $statusLabel = $isLow ? 'Low Stock' : 'Good Stock';
              $statusClass = $isLow ? 'b-red' : 'b-green';
              $itemBatches = $batchesByItem[$i['id']] ?? [];

              $poNumbers = array_values(array_unique(array_filter(array_map(function($b){ return $b['po_number'] ?? ''; }, $itemBatches))));
              $batchCount = count($itemBatches);
              if ($batchCount > 1) {
                  $pnCellHtml = '<span class="po-multi-badge">' . $batchCount . ' batches — click to view</span>';
              } elseif ($poNumbers) {
                  $pnCellHtml = 'PO ' . h($poNumbers[0]);
              } else {
                  $pnCellHtml = '—';
              }
            ?>
              <tr class="clickable-row" onclick="openItemDetails(<?php echo (int)$i['id']; ?>)">
                <td><?php echo h($i['type_name']); ?></td>
                <td><?php echo h($i['item_name']); ?></td>
                <td><?php echo (int)$i['quantity_total']; ?></td>
                <td><?php echo (int)$i['deployed_qty']; ?></td>
                <td><b><?php echo $left; ?></b></td>
                <td><span class="badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                <td><?php echo date('M j, Y', strtotime($i['date_added'])); ?></td>
                <td style="font-family:var(--font-mono); font-size:12px; color:var(--text-dim);"><?php echo $pnCellHtml; ?></td>
                <?php if (is_admin()): ?>
                <td style="white-space:nowrap;" onclick="event.stopPropagation();">
                  <button type="button" class="btn-secondary btn-sm" onclick="document.getElementById('remark-<?php echo $i['id']; ?>').classList.toggle('open-row')">Remarks</button>
                </td>
                <?php endif; ?>
              </tr>
              <?php if (is_admin()): ?>
              <tr id="remark-<?php echo $i['id']; ?>" class="expand-row" onclick="event.stopPropagation();">
                <td colspan="9" style="background:var(--panel-2);">
                  <form method="POST" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; padding:10px 0;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="add_remark">
                    <input type="hidden" name="item_id" value="<?php echo $i['id']; ?>">
                    <div class="field" style="margin-bottom:0;">
                      <label>Date</label>
                      <input type="date" name="remark_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="field" style="margin-bottom:0; flex:1; min-width:220px;">
                      <label>Remark</label>
                      <input type="text" name="remark_text" placeholder="e.g. Unit checked, still working fine" required>
                    </div>
                    <button type="submit" class="btn-primary btn-sm" data-loading-text="Saving...">Add Remark</button>
                  </form>
                </td>
              </tr>
              <?php endif; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
    <?php endif; ?>
  </div>
</div>

<?php endif; ?>

<div id="itemDetailsBackdrop" class="login-modal-backdrop item-details-backdrop">
  <div class="login-modal-card item-details-card" onclick="event.stopPropagation();">
    <button type="button" class="login-modal-close" onclick="closeItemDetails()">&times;</button>
    <div id="itemDetailsBody">

    </div>
  </div>
</div>

<?php include 'includes/inventory_frontend.php'; ?>

<?php include 'includes/footer.php'; ?>
