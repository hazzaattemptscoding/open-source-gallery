<?php
$pageTitle = 'Audit Log';
$currentPage = 'audit-log';
require_once __DIR__ . '/partials/layout_header.php';
?>
<h1>Audit Log</h1>

  <div class="audit-stat-bar">
    Showing <?= e((($page - 1) * $perPage) + 1) ?> to <?= e(min($page * $perPage, $totalCount)) ?> of <?= e($totalCount) ?> entries
  </div>

  <!-- Filters -->
  <form method="get" class="audit-filter-form">
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

    <div class="filter-actions">
      <button type="submit">Filter</button>
      <?php if (array_filter($filters)): ?>
        <a href="?page=1" class="clear-filter">Clear</a>
      <?php endif; ?>
    </div>
  </form>

  <!-- Table -->
  <div class="table-scroll">
    <table class="log-table">
      <thead>
        <tr>
          <th class="log-col-when">When</th>
          <th class="log-col-action">Action</th>
          <th class="log-col-admin">Admin</th>
          <th class="log-col-details">Details</th>
          <th class="log-col-ip">IP Address</th>
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
                <span class="log-cell-muted">(system)</span>
              <?php endif; ?>
            </td>
            <td class="log-details-cell">
              <?= e(substr($log['details'] ?? '', 0, 80)) ?>
              <?php if (strlen($log['details'] ?? '') > 80): ?>
                ...
              <?php endif; ?>
            </td>
            <td class="log-ip-cell">
              <?= e($log['ip_address'] ?? '—') ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (empty($logs)): ?>
    <p class="log-empty">
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

  <div class="audit-about">
    <h3>About Audit Logs</h3>
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

<?php
require_once __DIR__ . '/partials/layout_footer.php';
?>

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

function build_filter_params(array $filters): string {
    $params = [];
    if (!empty($filters['action'])) $params[] = 'action=' . urlencode($filters['action']);
    if (!empty($filters['admin_id'])) $params[] = 'admin_id=' . urlencode($filters['admin_id']);
    if (!empty($filters['dateFrom'])) $params[] = 'dateFrom=' . urlencode($filters['dateFrom']);
    if (!empty($filters['dateTo'])) $params[] = 'dateTo=' . urlencode($filters['dateTo']);
    return $params ? '&' . implode('&', $params) : '';
}
?>
