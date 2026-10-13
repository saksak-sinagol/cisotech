<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_admin_for_write();
enforce_sensitive_rate_limit($conn);

$pageTitle = 'Settings';
$activePage = 'settings';

$successMsg = '';
$errorMsg = '';
$userId = current_user()['id'];

$reopenModal = '';

require_once 'includes/settings_actions.php';

$itemTypes = $conn->query("SELECT t.*, ic.name AS category_name, (SELECT COUNT(*) FROM inventory_items i WHERE i.item_type_id = t.id) AS item_count FROM item_types t LEFT JOIN item_categories ic ON ic.id = t.category_id ORDER BY ic.name, t.name");

$allItemTypesForComponents = [];
$atRes = $conn->query("SELECT id, name FROM item_types ORDER BY name");
while ($atRow = $atRes->fetch_assoc()) {
    $allItemTypesForComponents[] = ['id' => (int)$atRow['id'], 'name' => $atRow['name']];
}
$componentsByParent = [];
$compRes = $conn->query("SELECT parent_type_id, component_type_id FROM item_type_components");
while ($compRow = $compRes->fetch_assoc()) {
    $componentsByParent[(int)$compRow['parent_type_id']][] = (int)$compRow['component_type_id'];
}
$itemCategories = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM item_types t WHERE t.category_id = c.id) AS type_count FROM item_categories c ORDER BY c.name");
$itemCategoryOptions = $conn->query("SELECT id, name FROM item_categories ORDER BY name");
$departments = $conn->query("SELECT * FROM departments ORDER BY name");
$categories = $conn->query("SELECT * FROM categories ORDER BY name");

$deptOptions = $conn->query("SELECT id, name FROM departments ORDER BY name");
$deptPeople = $conn->query("SELECT p.*, d.name AS dept_name FROM department_people p JOIN departments d ON d.id = p.department_id ORDER BY d.name, p.name");

include 'includes/header.php';
?>

<?php if ($successMsg): ?><div class="alert alert-success" role="status"><?php echo h($successMsg); ?></div><?php endif; ?>
<?php if ($errorMsg): ?><div class="alert alert-error" role="alert"><?php echo h($errorMsg); ?></div><?php endif; ?>

<!-- <p style="font-family:monospace; font-size:11px; color:#7EA3C4; margin-bottom:8px;">build: settings-tabs-v4</p> -->

<style>
  .settings-tabs{ display:flex; gap:6px; flex-wrap:wrap; border-bottom:1px solid var(--border); margin-bottom:20px; padding-bottom:0; }
  .settings-tab-btn{
    background:none; border:1px solid transparent; border-bottom:none;
    color:var(--text-faint); font-size:13.5px; font-weight:600;
    padding:10px 16px; border-radius:8px 8px 0 0; cursor:pointer;
    margin-bottom:-1px;
  }
  .settings-tab-btn:hover{ color:var(--text); }
  .settings-tab-btn.active{
    color:var(--accent-bright); border-color:var(--border); background:var(--panel);
    border-bottom:1px solid var(--panel);
  }
  .settings-tab-panel{ display:none; }
  .settings-tab-panel.active{ display:block; }
</style>

<div class="settings-tabs">
  <button type="button" class="settings-tab-btn" data-tab="itemCategories" onclick="showSettingsTab('itemCategories')">Item Categories</button>
  <button type="button" class="settings-tab-btn" data-tab="itemTypes" onclick="showSettingsTab('itemTypes')">Model/Brand</button>
  <?php if (is_superadmin()): ?>
  <button type="button" class="settings-tab-btn" data-tab="departments" onclick="showSettingsTab('departments')">Departments</button>
  <button type="button" class="settings-tab-btn" data-tab="categories" onclick="showSettingsTab('categories')">Work Log Categories</button>
  <?php endif; ?>
</div>

<?php require 'includes/settings_views/item_categories.php'; ?><?php require 'includes/settings_views/item_types.php'; ?><?php require 'includes/settings_views/departments.php'; ?><?php require 'includes/settings_views/worklog_categories.php'; ?><script>

  var SETTINGS_TAB_KEY = 'c1sotech_settings_active_tab';

  function showSettingsTab(tab){
    document.querySelectorAll('.settings-tab-panel').forEach(function(p){ p.classList.remove('active'); });
    document.querySelectorAll('.settings-tab-btn').forEach(function(b){ b.classList.remove('active'); });
    var panel = document.getElementById('settingsTab-' + tab);
    var btn = document.querySelector('.settings-tab-btn[data-tab="' + tab + '"]');
    if (panel) panel.classList.add('active');
    if (btn) btn.classList.add('active');
    try { sessionStorage.setItem(SETTINGS_TAB_KEY, tab); } catch (e) {}
  }

  (function initSettingsTabs(){
    var saved = null;
    try { saved = sessionStorage.getItem(SETTINGS_TAB_KEY); } catch (e) {}
    var valid = ['itemCategories','itemTypes','departments','categories'];
    showSettingsTab(valid.indexOf(saved) !== -1 ? saved : 'itemCategories');
  })();
</script>

<div id="settingsModalBackdrop" class="login-modal-backdrop item-details-backdrop" onclick="closeSettingsModal()">
  <div class="login-modal-card item-details-card" onclick="event.stopPropagation();" style="max-width:440px;">
    <button type="button" class="login-modal-close" onclick="closeSettingsModal()">&times;</button>

    <div id="settingsModal-itemCategories" class="settings-modal-pane" style="display:none;">
      <h3 class="idt-title" style="margin-bottom:18px;">Add Item Category</h3>
      <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add_item_category">
        <div class="field">
          <label for="stgItemCatName">Category Name</label>
          <input type="text" id="stgItemCatName" name="name" placeholder="e.g. Hardware, Peripheral" required autofocus>
        </div>
        <div class="form-actions">
          <button type="button" class="btn-secondary" onclick="closeSettingsModal()">Cancel</button>
          <button type="submit" class="btn-primary">Add Category</button>
        </div>
      </form>
    </div>

    <div id="settingsModal-itemTypes" class="settings-modal-pane" style="display:none;">
      <h3 class="idt-title" style="margin-bottom:18px;">Add Model/Brand</h3>
      <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add_item_type">
        <div class="field">
          <label for="stgItemTypeName">Item Brand/Model</label>
          <input type="text" id="stgItemTypeName" name="name" placeholder="" required autofocus>
        </div>
        <div class="field">
          <label for="stgItemTypeCategory">Category</label>
          <select id="stgItemTypeCategory" name="category_id" required>
            <option value="">— Select —</option>
            <?php $itemCategoryOptions->data_seek(0); while ($c = $itemCategoryOptions->fetch_assoc()): ?>
              <option value="<?php echo $c['id']; ?>"><?php echo h($c['name']); ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-actions">
          <button type="button" class="btn-secondary" onclick="closeSettingsModal()">Cancel</button>
          <button type="submit" class="btn-primary">Submit</button>
        </div>
      </form>
    </div>

    <div id="settingsModal-departments" class="settings-modal-pane" style="display:none;">
      <h3 class="idt-title" style="margin-bottom:18px;">Add Department</h3>
      <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add_department">
        <div class="field">
          <label for="stgDeptName">Department Name</label>
          <input type="text" id="stgDeptName" name="name" placeholder="e.g. Registrar's Office, MIS Department" required autofocus>
        </div>
        <div class="form-actions">
          <button type="button" class="btn-secondary" onclick="closeSettingsModal()">Cancel</button>
          <button type="submit" class="btn-primary">Add Department</button>
        </div>
      </form>
    </div>

    <div id="settingsModal-deptPeople" class="settings-modal-pane" style="display:none;">
      <h3 class="idt-title" style="margin-bottom:18px;">Add Faculty/Staff</h3>
      <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add_department_person">
        <div class="field">
          <label for="stgDeptPersonDept">Department</label>
          <select id="stgDeptPersonDept" name="department_id" required>
            <option value="">Select department…</option>
            <?php $deptOptions->data_seek(0); while ($do = $deptOptions->fetch_assoc()): ?>
              <option value="<?php echo $do['id']; ?>"><?php echo h($do['name']); ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="field">
          <label for="stgDeptPersonName">Faculty/Staff Name</label>
          <input type="text" id="stgDeptPersonName" name="name" placeholder="e.g. Juan Dela Cruz" required>
        </div>
        <div class="form-actions">
          <button type="button" class="btn-secondary" onclick="closeSettingsModal()">Cancel</button>
          <button type="submit" class="btn-primary">Add Name</button>
        </div>
      </form>
    </div>

    <div id="settingsModal-categories" class="settings-modal-pane" style="display:none;">
      <h3 class="idt-title" style="margin-bottom:18px;">Add Work Log Category</h3>
      <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add_category">
        <div class="field">
          <label for="stgCategoryName">Category Name</label>
          <input type="text" id="stgCategoryName" name="name" placeholder="e.g. Maintenance, Troubleshooting" required autofocus>
        </div>
        <div class="form-actions">
          <button type="button" class="btn-secondary" onclick="closeSettingsModal()">Cancel</button>
          <button type="submit" class="btn-primary">Add Category</button>
        </div>
      </form>
    </div>

  </div>
</div>

<div id="componentsModalBackdrop" class="login-modal-backdrop item-details-backdrop" onclick="if(event.target===this) closeComponentsModal();">
  <div class="login-modal-card item-details-card" style="max-width:480px;">
    <button type="button" class="login-modal-close" onclick="closeComponentsModal()">&times;</button>
    <h3 class="idt-title" style="margin-bottom:6px;">Components of <span id="componentsModalParentName"></span></h3>
    <p class="field-hint" style="margin-bottom:14px;">Check every Kind of Item that can be picked as a component when deploying a <span id="componentsModalParentName2"></span> — each becomes its own stock-linked picker on the Deployment wizard.</p>
    <form method="POST" id="componentsModalForm">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="set_item_type_components">
      <input type="hidden" name="parent_type_id" id="componentsModalParentId">
      <div id="componentsModalList" style="max-height:320px; overflow-y:auto; border:1px solid var(--border); border-radius:6px; padding:10px;"></div>
      <div class="form-actions" style="margin-top:14px;">
        <button type="button" class="btn-secondary" onclick="closeComponentsModal()">Cancel</button>
        <button type="submit" class="btn-primary">Save Components</button>
      </div>
    </form>
  </div>
</div>

<script>

  function openSettingsModal(which){
    document.querySelectorAll('.settings-modal-pane').forEach(function(p){ p.style.display = 'none'; });
    var pane = document.getElementById('settingsModal-' + which);
    if (pane) pane.style.display = 'block';
    var backdrop = document.getElementById('settingsModalBackdrop');
    backdrop.classList.add('open');
    requestAnimationFrame(function(){ backdrop.classList.add('show'); });
    var firstInput = pane ? pane.querySelector('input[type=text], select') : null;
    if (firstInput) setTimeout(function(){ firstInput.focus(); }, 150);
  }

  function closeSettingsModal(){
    var backdrop = document.getElementById('settingsModalBackdrop');
    backdrop.classList.remove('show');
    setTimeout(function(){ backdrop.classList.remove('open'); }, 180);
  }

  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') { closeSettingsModal(); closeComponentsModal(); }
  });

  var allItemTypesForComponents = <?php echo json_encode($allItemTypesForComponents, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
  var componentsByParent = <?php echo json_encode($componentsByParent, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

  function openComponentsModal(parentTypeId, parentName){
    document.getElementById('componentsModalParentId').value = parentTypeId;
    document.getElementById('componentsModalParentName').textContent = parentName;
    document.getElementById('componentsModalParentName2').textContent = parentName;
    var selected = componentsByParent[parentTypeId] || [];
    var others = allItemTypesForComponents.filter(function(t){ return String(t.id) !== String(parentTypeId); });
    var list = document.getElementById('componentsModalList');
    if (!others.length) {
      list.innerHTML = '<div class="idt-empty">No other Kind of Item entries exist yet — add one first (e.g. "Keyboard", "GPU").</div>';
    } else {
      list.innerHTML = others.map(function(t){
        var checked = selected.indexOf(t.id) !== -1 ? ' checked' : '';
        return '<label style="display:flex; align-items:center; gap:8px; padding:6px 0; cursor:pointer;">'
          + '<input type="checkbox" name="component_type_ids[]" value="' + t.id + '"' + checked + '> ' + t.name
          + '</label>';
      }).join('');
    }
    var backdrop = document.getElementById('componentsModalBackdrop');
    backdrop.classList.add('open');
    requestAnimationFrame(function(){ backdrop.classList.add('show'); });
  }

  function closeComponentsModal(){
    var backdrop = document.getElementById('componentsModalBackdrop');
    backdrop.classList.remove('show');
    setTimeout(function(){ backdrop.classList.remove('open'); }, 180);
  }
</script>

<?php include 'includes/footer.php'; ?>
