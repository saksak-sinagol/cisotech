<?php ?>
<div id="settingsTab-departments" class="settings-tab-panel">
<div class="panel">
  <div class="panel-header"><h2>Departments</h2></div>
  <div class="panel-body">
    <p class="field-hint" style="margin-bottom:12px;">Add or remove departments that items can be deployed to.</p>
    <button type="button" class="btn-primary" onclick="openSettingsModal('departments')">+ Add Department</button>
    <table class="data-table" style="margin-top:16px;">
      <thead><tr><th>Name</th><th></th></tr></thead>
      <tbody>
        <?php if ($departments->num_rows > 0): while ($d = $departments->fetch_assoc()): ?>
          <tr>
            <td><?php echo h($d['name']); ?></td>
            <td>
              <form method="POST" onsubmit="return confirm('Delete this department? This also removes its faculty/staff list.');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_department">
                <input type="hidden" name="id" value="<?php echo $d['id']; ?>">
                <button type="submit" class="btn-danger btn-sm">Delete</button>
              </form>
            </td>
          </tr>
        <?php endwhile; else: ?>
          <tr class="empty-row"><td colspan="2">No departments yet. Click "+ Add Department" above to add your first one.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="panel">
  <div class="panel-header"><h2>Faculty / Staff (per Department)</h2></div>
  <div class="panel-body">
    <p class="field-hint" style="margin-bottom:12px;">These are the names that show up when deploying an item to a department — add at least one name per department here, or the Deployment wizard will have nothing to select.</p>
    <button type="button" class="btn-primary" onclick="openSettingsModal('deptPeople')">+ Add Faculty/Staff</button>
    <table class="data-table" style="margin-top:16px;">
      <thead><tr><th>Name</th><th>Department</th><th></th></tr></thead>
      <tbody>
        <?php if ($deptPeople->num_rows > 0): while ($p = $deptPeople->fetch_assoc()): ?>
          <tr>
            <td><?php echo h($p['name']); ?></td>
            <td><?php echo h($p['dept_name']); ?></td>
            <td>
              <form method="POST" onsubmit="return confirm('Remove this name?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_department_person">
                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                <button type="submit" class="btn-danger btn-sm">Delete</button>
              </form>
            </td>
          </tr>
        <?php endwhile; else: ?>
          <tr class="empty-row"><td colspan="3">No faculty/staff added yet. Add a department first, then click "+ Add Faculty/Staff" above.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>
