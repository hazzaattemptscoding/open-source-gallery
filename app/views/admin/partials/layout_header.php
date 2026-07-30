<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Admin') ?> — <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<link rel="stylesheet" href="/assets/css/admin.css">
<link rel="stylesheet" href="/api/styles.css">
<?= isset($extraStyles) ? $extraStyles : '' ?>
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="admin-sidebar-logo">
      <a href="/admin"><?= e($siteName) ?></a>
    </div>

    <!-- Manage content -->
    <nav class="admin-nav-group">
      <span class="admin-nav-group-title">Manage content</span>
      <a href="/admin/events" class="admin-nav-link <?= $currentPage === 'events' ? 'active' : '' ?>">
        <span class="admin-nav-link-icon">📅</span>
        <span>Events</span>
      </a>
      <a href="/admin/upload" class="admin-nav-link <?= $currentPage === 'upload' ? 'active' : '' ?>">
        <span class="admin-nav-link-icon">⬆️</span>
        <span>Upload photos</span>
      </a>
      <a href="/admin/bulk" class="admin-nav-link <?= $currentPage === 'bulk' ? 'active' : '' ?>">
        <span class="admin-nav-link-icon">🏷️</span>
        <span>Bulk Operations</span>
      </a>
      <a href="/admin/migrations" class="admin-nav-link <?= $currentPage === 'migrations' ? 'active' : '' ?>">
        <span class="admin-nav-link-icon">🔄</span>
        <span>Migrations</span>
      </a>
    </nav>

    <!-- Monitor -->
    <nav class="admin-nav-group">
      <span class="admin-nav-group-title">Monitor</span>
      <a href="/admin/health" class="admin-nav-link <?= $currentPage === 'health' ? 'active' : '' ?>">
        <span class="admin-nav-link-icon">💚</span>
        <span>System health</span>
      </a>
      <a href="/admin/stats" class="admin-nav-link <?= $currentPage === 'stats' ? 'active' : '' ?>">
        <span class="admin-nav-link-icon">📊</span>
        <span>Sales dashboard</span>
      </a>
      <a href="/admin/analytics" class="admin-nav-link <?= $currentPage === 'analytics' ? 'active' : '' ?>">
        <span class="admin-nav-link-icon">📈</span>
        <span>Analytics</span>
      </a>
      <a href="/admin/orders" class="admin-nav-link <?= $currentPage === 'orders' ? 'active' : '' ?>">
        <span class="admin-nav-link-icon">🛒</span>
        <span>Order history</span>
      </a>
      <a href="/admin/audit-log" class="admin-nav-link <?= $currentPage === 'audit-log' ? 'active' : '' ?>">
        <span class="admin-nav-link-icon">📝</span>
        <span>Audit log</span>
      </a>
    </nav>

    <!-- Design & Configuration -->
    <nav class="admin-nav-group">
      <span class="admin-nav-group-title">Design & Configuration</span>
      <a href="/admin/customize" class="admin-nav-link <?= $currentPage === 'customize' ? 'active' : '' ?>">
        <span class="admin-nav-link-icon">🎨</span>
        <span>Customization</span>
      </a>
      <a href="/admin/settings" class="admin-nav-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
        <span class="admin-nav-link-icon">⚙️</span>
        <span>Settings</span>
      </a>
      <a href="/admin/emails" class="admin-nav-link <?= $currentPage === 'emails' ? 'active' : '' ?>">
        <span class="admin-nav-link-icon">📧</span>
        <span>Email Management</span>
      </a>
      <a href="/admin/watermarks" class="admin-nav-link <?= $currentPage === 'watermarks' ? 'active' : '' ?>">
        <span class="admin-nav-link-icon">💧</span>
        <span>Watermarks</span>
      </a>
      <a href="/admin/print_orders" class="admin-nav-link <?= $currentPage === 'print_orders' ? 'active' : '' ?>">
        <span class="admin-nav-link-icon">🖨️</span>
        <span>Print Orders</span>
      </a>
      <a href="/admin/export" class="admin-nav-link <?= $currentPage === 'export' ? 'active' : '' ?>">
        <span class="admin-nav-link-icon">💾</span>
        <span>Export Data</span>
      </a>
    </nav>

    <!-- Security -->
    <nav class="admin-nav-group">
      <span class="admin-nav-group-title">Security</span>
      <a href="/admin/admins" class="admin-nav-link <?= $currentPage === 'admins' ? 'active' : '' ?>">
        <span class="admin-nav-link-icon">👥</span>
        <span>Admin users</span>
      </a>
      <a href="/admin/my-sessions" class="admin-nav-link <?= $currentPage === 'my-sessions' ? 'active' : '' ?>">
        <span class="admin-nav-link-icon">🔐</span>
        <span>My Sessions</span>
      </a>
    </nav>

    <!-- Logout -->
    <nav class="admin-nav-group" style="margin-top: 3rem; border-top: 1px solid var(--border); padding-top: 2rem;">
      <form method="post" action="/admin/logout" style="margin: 0;">
        <button type="submit" class="admin-nav-link" style="width: 100%; text-align: left; border: none; background: none; padding-left: 2rem;">
          <span class="admin-nav-link-icon">🚪</span>
          <span>Log out</span>
        </button>
      </form>
    </nav>
  </aside>

  <div class="admin-content">
    <div class="admin-main">
