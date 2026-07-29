<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Management — Admin</title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<style>
.admins-container {
    max-width: 1000px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.admin-form {
    background: #f9f9f9;
    border: 1px solid #eee;
    border-radius: 4px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.admin-form h2 {
    margin-top: 0;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 1rem;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #000;
    box-shadow: 0 0 0 2px rgba(0,0,0,0.1);
}

.form-actions {
    display: flex;
    gap: 1rem;
}

.form-actions button {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
}

.btn-primary {
    background: #000;
    color: #fff;
}

.btn-primary:hover {
    background: #333;
}

.btn-secondary {
    background: #f0f0f0;
    color: #000;
}

.btn-secondary:hover {
    background: #e0e0e0;
}

.error-message {
    background: #fff3f3;
    border: 1px solid #ffcccc;
    border-radius: 4px;
    padding: 1rem;
    margin-bottom: 1.5rem;
    color: #c33;
}

.error-message ul {
    margin: 0;
    padding-left: 1.5rem;
}

.error-message li {
    margin: 0.5rem 0;
}

.success-message {
    background: #f3fff3;
    border: 1px solid #ccffcc;
    border-radius: 4px;
    padding: 1rem;
    margin-bottom: 1.5rem;
    color: #3c3;
}

.admins-list {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 4px;
    overflow: hidden;
}

.admin-row {
    display: grid;
    grid-template-columns: 1fr 200px 200px 100px;
    gap: 1rem;
    padding: 1rem;
    border-bottom: 1px solid #eee;
    align-items: center;
}

.admin-row:last-child {
    border-bottom: none;
}

.admin-row header {
    display: grid;
    grid-template-columns: 1fr 200px 200px 100px;
    gap: 1rem;
    padding: 1rem;
    background: #f9f9f9;
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
}

.admin-email {
    font-weight: 500;
}

.admin-created {
    font-size: 0.9rem;
    color: #666;
}

.admin-role-select {
    padding: 0.5rem;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 0.9rem;
}

.admin-current {
    background: #f0f0f0;
}

.admin-current .admin-email::after {
    content: ' (you)';
    font-size: 0.8rem;
    color: #999;
    font-weight: normal;
}

.admin-actions {
    display: flex;
    gap: 0.5rem;
}

.admin-actions button {
    padding: 0.5rem 0.75rem;
    font-size: 0.85rem;
    border: none;
    border-radius: 3px;
    cursor: pointer;
}

.btn-danger {
    background: #fee;
    color: #c33;
}

.btn-danger:hover {
    background: #fdd;
}

.btn-danger:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .admin-row,
    .admin-row header {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>

<header class="site-header">
  <a href="/admin" class="site-title">Admin Management</a>
  <form method="post" action="/admin/logout" class="logout-form">
    <button type="submit">Log out</button>
  </form>
</header>

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
              <input type="hidden" name="action" value="update_role">
              <input type="hidden" name="admin_id" value="<?= e($admin['id']) ?>">
              <select name="role_id" class="admin-role-select" onchange="this.form.submit()">
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
            <form method="post" style="display: inline;" onsubmit="return confirm('Delete this admin?');">
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

</body>
</html>
