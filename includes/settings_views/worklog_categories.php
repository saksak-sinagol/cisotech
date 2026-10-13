<?php ?>
<div id="settingsTab-categories" class="settings-tab-panel">
<div class="panel">
  <div class="panel-header"><h2>Work Log Categories</h2></div>
  <div class="panel-body">
    <p class="field-hint" style="margin-bottom:12px;">Manage/delete categories here. New categories are usually added from the Work Log page, but you can also add one here.</p>
    <button type="button" class="btn-primary" onclick="openSettingsModal('categories')">+ Add Category</button>
    <table class="data-table" style="margin-top:16px;">
      <thead><tr><th>Name</th><th></th></tr></thead>
      <tbody>
        <?php if ($categories->num_rows > 0): while ($c = $categories->fetch_assoc()): ?>
          <tr>
            <td><?php echo h($c['name']); ?></td>
            <td>
              <form method="POST" onsubmit="return confirm('Delete this category?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_category">
                <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                <button type="submit" class="btn-danger btn-sm">Delete</button>
              </form>
            </td>
          </tr>
        <?php endwhile; else: ?>
          <tr class="empty-row"><td colspan="2">No categories yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>
