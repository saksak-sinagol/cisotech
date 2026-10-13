<?php ?>
<div id="settingsTab-itemTypes" class="settings-tab-panel">
<div class="panel">
  <div class="panel-header"><h2>Inventory Item Types (Kinds of Item)</h2></div>
  <div class="panel-body">
    <p class="field-hint" style="margin-bottom:12px;">Add or remove the kinds of item used across Inventory (e.g. Projector, PC Parts, Laptop, Monitor). Each one belongs to a Category.</p>
    <?php if ($itemCategoryOptions->num_rows === 0): ?>
      <div class="alert alert-error" role="alert" style="margin-bottom:12px;">Add at least one Item Category first (see the "Item Categories" tab) before adding an item type.</div>
    <?php endif; ?>
    <button type="button" class="btn-primary" onclick="openSettingsModal('itemTypes')" <?php echo $itemCategoryOptions->num_rows===0?'disabled':''; ?>>+ Item</button>
    <table class="data-table" style="margin-top:16px;">
      <thead><tr><th>Name</th><th>Category</th><th># Items</th><th># Components</th><th></th></tr></thead>
      <tbody>
        <?php if ($itemTypes->num_rows > 0): $itemTypes->data_seek(0); while ($t = $itemTypes->fetch_assoc()): ?>
          <tr>
            <td><?php echo h($t['name']); ?></td>
            <td><?php echo $t['category_name'] ? h($t['category_name']) : '<span style="color:var(--text-faint);">— none —</span>'; ?></td>
            <td><?php echo (int)$t['item_count']; ?></td>
            <td><?php echo count($componentsByParent[(int)$t['id']] ?? []); ?></td>
            <td style="display:flex; gap:6px;">
              <button type="button" class="btn-secondary btn-sm" onclick='openComponentsModal(<?php echo (int)$t["id"]; ?>, <?php echo json_encode(h($t["name"])); ?>)'>Components</button>
              <form method="POST" onsubmit="return confirm('Delete this item type?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_item_type">
                <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                <button type="submit" class="btn-danger btn-sm" <?php echo $t['item_count']>0?'disabled title="In use"':''; ?>>Delete</button>
              </form>
            </td>
          </tr>
        <?php endwhile; else: ?>
          <tr class="empty-row"><td colspan="5">No item types yet. Click "+ Add Item Type" above to add your first one (e.g. "Projector").</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <p class="field-hint" style="margin-top:10px;">"Components" lets a Kind of Item (e.g. PC/System Unit) declare which other Kind of Item entries can be picked as part of it when deploying (e.g. Keyboard, Mouse, Monitor, GPU) — each becomes its own stock-linked picker on the Deployment wizard.</p>
  </div>
</div>
</div>
