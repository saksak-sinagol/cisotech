<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/spec_schema.php';
require_once 'includes/deployment_rules.php';
require_once 'includes/deployment_service.php';
require_login();
enforce_sensitive_rate_limit($conn);

$pageTitle = 'Deployments';
$activePage = 'deployments';
$successMsg = '';
$errorMsg = '';

require_once 'includes/deployment_single_action.php';

require_once 'includes/deployment_cart_action.php';

require_once 'includes/deployment_ajax.php';

require_once 'includes/deployment_return_action.php';

if (!empty($_SESSION['return_success'])) {
    $successMsg = $_SESSION['return_success'];
    unset($_SESSION['return_success']);
}

require_once 'includes/deployment_page_data.php';

include 'includes/header.php';
?>

<?php if ($successMsg): ?><div class="alert alert-success" role="status"><?php echo h($successMsg); ?></div><?php endif; ?>
<?php if ($errorMsg): ?><div class="alert alert-error" role="alert"><?php echo h($errorMsg); ?></div><?php endif; ?>

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

<?php include 'includes/deployment_frontend.php'; ?>

<?php include 'includes/footer.php'; ?>
