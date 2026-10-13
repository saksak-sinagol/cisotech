<?php ?>
<div id="settingsTab-itemCategories" class="settings-tab-panel">
<div class="panel">
  <div class="panel-header"><h2>Item Categories</h2></div>
  <div class="panel-body">
    <p class="field-hint" style="margin-bottom:12px;">A grouping level above Kind of Item — e.g. "Hardware" (PC, Laptop, Projector) and "Peripheral" (Keyboard, Mouse, Monitor). Every Item Type below must belong to one of these.</p>
    <button type="button" class="btn-primary" onclick="openSettingsModal('itemCategories')">+ Add Category</button>
    <table class="data-table" style="margin-top:16px;">
      <thead><tr><th>Name</th><th># Brand/Model</th><th></th></tr></thead>
      <tbody>
        <?php if ($itemCategories->num_rows > 0): while ($c = $itemCategories->fetch_assoc()): ?>
          <tr>
            <td><?php echo h($c['name']); ?></td>
            <td><?php echo (int)$c['type_count']; ?></td>
            <td>
              <form method="POST" onsubmit="return confirm('Delete this category?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_item_category">
                <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                <button type="submit" class="btn-danger btn-sm" <?php echo $c['type_count']>0?'disabled title="In use"':''; ?>>Delete</button>
              </form>
            </td>
          </tr>
        <?php endwhile; else: ?>
          <tr class="empty-row"><td colspan="3">No item categories yet. Click "+ Add Category" above to add your first one (e.g. "Hardware").</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>
