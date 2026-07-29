<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Audit Log: Admin</title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<style>
.filter-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background-color: #f9f9f9;
    border-radius: 4px;
}
.filter-form input,
.filter-form select {
    padding: 0.75rem;
    border: 1px solid var(--border);
    border-radius: 4px;
    font-family: inherit;
    font-size: 0.95rem;
}
.filter-form button {
    padding: 0.75rem 1.5rem;
    background-color: #1a1a1a;
    color: white;
    border: none;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
}
.filter-form button:hover {
    background-color: #333;
}
.clear-filter {
    background-color: #999 !important;
    padding: 0.75rem 1rem;
    font-size: 0.9rem;
}
.clear-filter:hover {
    background-color: #666 !important;
}
.log-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}
.log-table thead {
    background-color: #f5f5f5;
    border-bottom: 2px solid var(--border);
}
.log-table th {
    padding: 1rem;
    text-align: left;
    font-weight: 600;
}
.log-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
}
.log-table tr:hover {
    background-color: #f9f9f9;
}
.action-badge {
    display: inline-block;
    padding: 0.4rem 0.8rem;
    border-radius: 3px;
    font-size: 0.85rem;
    font-weight: 500;
}
.action-login {
    background-color: #e8f5e9;
    color: #2e7d32;
}
.action-logout {
    background-color: #f3e5f5;
    color: #6a1b9a;
}
.action-create {
    background-color: #e3f2fd;
    color: #1565c0;
}
.action-update {
    background-color: #fff3e0;
    color: #e65100;
}
.action-delete {
    background-color: #ffebee;
    color: #c62828;
}
.action-export {
    background-color: #e0f2f1;
    color: #00695c;
}
.action-webhook {
    background-color: #f1f8e9;
    color: #558b2f;
}
.action-failed {
    background-color: #fce4ec;
    color: #ad1457;
}
.action-default {
    background-color: #eeeeee;
    color: #424242;
}
.pagination {
    display: flex;
    gap: 0.5rem;
    margin-top: 2rem;
    justify-content: center;
}
.pagination a,
.pagination span {
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--border);
    border-radius: 3px;
    text-decoration: none;
    color: inherit;
}
.pagination a:hover {
    background-color: #f5f5f5;
}
.pagination .current {
    background-color: #1a1a1a;
    color: white;
    border-color: #1a1a1a;
}
.stat-bar {
    margin-bottom: 2rem;
    padding: 1rem;
    background-color: #f5f5f5;
    border-radius: 4px;
    font-size: 0.95rem;
    color: var(--text-muted);
}
</style>
</head>
<body>

<header class="site-header">
  <a href="/admin" class="site-title">Audit Log</a>
  <form method="post" action="/admin/logout" class="logout-form">
    <button type="submit">Log out</button>
  </form>
</header>

<main class="dashboard">
  <h1>Audit Log</h1>

  <div class="stat-bar">
    Showing <?= e((($page - 1) * $perPage) + 1) ?> to <?= e(min($page * $perPage, $totalCount)) ?> of <?= e($totalCount) ?> entries
  </div>

  <!-- Filters -->
  <form method="get" class="filter-form">
    <div>
      <input type="text" name="action" placeholder="Action (e.g., login, photo_upload)" value="<?= e($filters['action']) ?>">
    </div>

    <div>
      <select name="admin_id">
        <option value="">All admins</option>
        <?php foreach ($admins as $admin): ?>
          <option value="<?= e($admin['id']) ?>" <?= $filters['admin_id'] == $admin['id'] ? 'selected' : '' ?>>
            <?= e($admin['email']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <input type="date" name="dateFrom" value="<?= e($filters['dateFrom']) ?>" placeholder="From date">
    </div>

    <div>
      <input type="date" name="dateTo" value="<?= e($filters['dateTo']) ?>" placeholder="To date">
    </div>

    <div style="display: flex; gap: 0.5rem;">
      <button type="submit">Filter</button>
      <?php if (array_filter($filters)): ?>
        <a href="?page=1" class="clear-filter">Clear</a>
      <?php endif; ?>
    </div>
  </form>

  <!-- Table -->
  <div style="overflow-x: auto;">
    <table class="log-table">
      <thead>
        <tr>
          <th style="width: 15%;">When</th>
          <th style="width: 15%;">Action</th>
          <th style="width: 15%;">Admin</th>
          <th style="width: 25%;">Details</th>
          <th style="width: 15%;">IP Address</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $log): ?>
          <tr>
            <td>
              <span title="<?= e($log['created_at']) ?>">
                <?= e(date('M d, Y H:i:s', strtotime($log['created_at']))) ?>
              </span>
            </td>
            <td>
              <span class="action-badge action-<?= get_action_class(e($log['action'])) ?>">
                <?= e(describe_action($log['action'])) ?>
              </span>
            </td>
            <td>
              <?php if ($log['admin_email']): ?>
                <?= e($log['admin_email']) ?>
              <?php else: ?>
                <span style="color: var(--text-muted);">(system)</span>
              <?php endif; ?>
            </td>
            <td style="color: var(--text-muted); font-size: 0.85rem;">
              <?= e(substr($log['details'] ?? '', 0, 80)) ?>
              <?php if (strlen($log['details'] ?? '') > 80): ?>
                ...
              <?php endif; ?>
            </td>
            <td style="font-family: monospace; font-size: 0.85rem; color: var(--text-muted);">
              <?= e($log['ip_address'] ?? '—') ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (empty($logs)): ?>
    <p style="text-align: center; padding: 2rem; color: var(--text-muted);">
      No audit log entries found matching your filters.
    </p>
  <?php endif; ?>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="?page=1<?= build_filter_params($filters) ?>">First</a>
        <a href="?page=<?= $page - 1 ?><?= build_filter_params($filters) ?>">← Previous</a>
      <?php endif; ?>

      <?php
        $startPage = max(1, $page - 2);
        $endPage = min($totalPages, $page + 2);
        if ($startPage > 1): ?>
          <span>...</span>
        <?php endif;

        for ($p = $startPage; $p <= $endPage; $p++):
          if ($p === $page): ?>
            <span class="current"><?= $p ?></span>
          <?php else: ?>
            <a href="?page=<?= $p ?><?= build_filter_params($filters) ?>"><?= $p ?></a>
          <?php endif;
        endfor;

        if ($endPage < $totalPages): ?>
          <span>...</span>
        <?php endif; ?>

      <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?><?= build_filter_params($filters) ?>">Next →</a>
        <a href="?page=<?= $totalPages ?><?= build_filter_params($filters) ?>">Last</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div style="margin-top: 3rem; padding: 1.5rem; background-color: #f9f9f9; border-radius: 4px;">
    <h3 style="margin-top: 0;">About Audit Logs</h3>
    <p>
      All mutations, logins, exports, and webhooks are logged here. This is your security and compliance record.
      Keep these logs for regulatory purposes and investigate any unusual activity.
    </p>
    <ul>
      <li><strong>Action:</strong> What happened (login, photo_upload, export, etc.)</li>
      <li><strong>Admin:</strong> Who did it (email, or system for webhooks)</li>
      <li><strong>Details:</strong> Additional context (order ID, photo count, etc.)</li>
      <li><strong>IP Address:</strong> Where the request came from</li>
    </ul>
  </div>

</main>

</body>
</html>

<?php
function get_action_class(string $action): string {
    if (strpos($action, 'login') !== false) return 'login';
    if (strpos($action, 'logout') !== false) return 'logout';
    if (strpos($action, 'create') !== false) return 'create';
    if (strpos($action, 'update') !== false) return 'update';
    if (strpos($action, 'delete') !== false) return 'delete';
    if (strpos($action, 'export') !== false) return 'export';
    if (strpos($action, 'webhook') !== false) return 'webhook';
    if (strpos($action, 'failed') !== false) return 'failed';
    return 'default';
}

function describe_action(string $action): string {
    require_once __DIR__ . '/../../controllers/admin/audit_log.php';
    return describe_action($action);
}

function build_filter_params(array $filters): string {
    $params = [];
    if (!empty($filters['action'])) $params[] = 'action=' . urlencode($filters['action']);
    if (!empty($filters['admin_id'])) $params[] = 'admin_id=' . urlencode($filters['admin_id']);
    if (!empty($filters['dateFrom'])) $params[] = 'dateFrom=' . urlencode($filters['dateFrom']);
    if (!empty($filters['dateTo'])) $params[] = 'dateTo=' . urlencode($filters['dateTo']);
    return $params ? '&' . implode('&', $params) : '';
}
?>
