<?php
$pageTitle = 'Admins';
$currentPage = 'admins';
require_once __DIR__ . '/partials/layout_header.php';
?>
<div class="admins-container">

  <?php if (!empty($errors)): ?>
    <div class="error-message">
      <strong>Error:</strong>
      <ul>
        <?php foreach ($errors as $error): ?>
          <li><?= e($error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="success-message">
      ✓ Changes saved successfully
    </div>
  <?php endif; ?>

  <!-- Create New Admin -->
  <div class="admin-form">
    <h2>Create New Admin</h2>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="action" value="create">

      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" required>
      </div>

      <div class="form-group">
        <label for="password">Password (min 8 characters)</label>
        <input type="password" id="password" name="password" required>
      </div>

      <div class="form-group">
        <label for="role">Role</label>
        <select id="role" name="role_id">
          <?php foreach ($roles as $role): ?>
            <option value="<?= e($role['id']) ?>">
              <?= e($role['display_name']) ?>
              — <?= e($role['description']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn-primary">Create Admin</button>
      </div>
    </form>
  </div>

  <!-- Admin List -->
  <h2>Existing Admins (<?= count($admins) ?>)</h2>
  <div class="admins-list">
    <div class="admin-row header">
      <div>Email</div>
      <div>Role</div>
      <div>Created</div>
      <div>Actions</div>
    </div>

    <?php foreach ($admins as $admin): ?>
      <div class="admin-row <?= $admin['id'] == $currentAdminId ? 'admin-current' : '' ?>">
        <div>
          <div class="admin-email"><?= e($admin['email']) ?></div>
          <div class="admin-created"><?= date('M d, Y', strtotime($admin['created_at'])) ?></div>
        </div>

        <div>
          <?php if ($admin['id'] != $currentAdminId): ?>
            <form method="post" style="display: inline;">
              <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
              <input type="hidden" name="action" value="update_role">
              <input type="hidden" name="admin_id" value="<?= e($admin['id']) ?>">
              <select name="role_id" class="admin-role-select" data-submit-on-change>
                <?php foreach ($roles as $role): ?>
                  <option value="<?= e($role['id']) ?>" <?= $admin['admin_role_id'] == $role['id'] ? 'selected' : '' ?>>
                    <?= e($role['display_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </form>
          <?php else: ?>
            <span><?= e($admin['role_name']) ?></span>
          <?php endif; ?>
        </div>

        <div><?= e(date('M d, Y', strtotime($admin['created_at']))) ?></div>

        <div class="admin-actions">
          <?php if ($admin['id'] != $currentAdminId): ?>
            <form method="post" style="display: inline;" data-confirm="Delete this admin?">
              <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="admin_id" value="<?= e($admin['id']) ?>">
              <button type="submit" class="btn-danger">Delete</button>
            </form>
          <?php else: ?>
            <span style="color: #999;">—</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Role Guide -->
  <div style="margin-top: 3rem; padding: 1.5rem; background: #f9f9f9; border-radius: 4px;">
    <h3 style="margin-top: 0;">Role Permissions Guide</h3>
    <ul>
      <li><strong>Admin:</strong> Full access to all features (settings, exports, admin management)</li>
      <li><strong>Uploader:</strong> Can create events, upload photos, view analytics</li>
      <li><strong>Viewer:</strong> Read-only access to dashboard and reports</li>
    </ul>
  </div>

</div>


<?php require_once __DIR__ . '/partials/layout_footer.php'; ?>
