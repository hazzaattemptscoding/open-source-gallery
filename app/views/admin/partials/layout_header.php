<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Admin') ?> — <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<link rel="stylesheet" href="/assets/css/admin-refined.css">
<link rel="stylesheet" href="/assets/css/progress-widget.css">
<link rel="stylesheet" href="/api/styles.css">
<?= isset($extraStyles) ? $extraStyles : '' ?>
</head>
<?php
/**
 * Sidebar navigation, grouped by the job the admin is doing rather than by
 * which controller happens to own the page. Declared as data so the active-page
 * test and the markup are each written once instead of once per link.
 *
 * Each entry is [$pageKey, $href, $label]. $pageKey matches the $currentPage
 * the controller sets, which is what marks a link active and decides which
 * group must stay expanded.
 */
$navGroups = [
    'Photo Management' => [
        ['upload', '/admin/upload', 'Upload photos'],
        ['bulk', '/admin/bulk', 'Bulk operations'],
        ['watermarks', '/admin/watermarks', 'Watermarks'],
    ],
    'Event Management' => [
        ['events', '/admin/events', 'Events'],
        ['migrations', '/admin/migrations', 'Migrations'],
    ],
    'Monitoring' => [
        ['health', '/admin/health', 'System health'],
        ['stats', '/admin/stats', 'Sales dashboard'],
        ['analytics', '/admin/analytics', 'Analytics'],
        ['orders', '/admin/orders', 'Order history'],
        ['audit-log', '/admin/audit-log', 'Audit log'],
    ],
    'Configuration' => [
        ['settings', '/admin/settings/site', 'Settings'],
        ['emails', '/admin/emails', 'Email management'],
        ['customize', '/admin/customize', 'Customization'],
        ['print_orders', '/admin/print_orders', 'Print orders'],
        ['export', '/admin/export', 'Export data'],
    ],
    'Security' => [
        ['admins', '/admin/admins', 'Admin users'],
    ],
];

// Views that never set $currentPage (or set it late) would otherwise emit an
// undefined-variable warning on every link comparison below.
$currentPage = $currentPage ?? '';
?>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="admin-sidebar-logo">
      <a href="/admin"><?= e($siteName) ?></a>
    </div>

    <?php foreach ($navGroups as $groupTitle => $links): ?>
      <?php
      /*
       * Groups render expanded so the sidebar looks and behaves exactly as it
       * did before any JavaScript runs — no flash of collapsed navigation, and
       * the whole menu still works with JS off. admin-common.js then re-applies
       * whatever the admin last collapsed, except for the group holding the
       * page they are on, which always stays open.
       */
      ?>
      <details class="admin-nav-group" data-nav-group="<?= e($groupTitle) ?>" open>
        <summary class="admin-nav-group-title"><?= e($groupTitle) ?></summary>
        <nav>
          <?php foreach ($links as [$pageKey, $href, $label]): ?>
            <a href="<?= e($href) ?>" class="admin-nav-link <?= $currentPage === $pageKey ? 'active' : '' ?>"<?= $currentPage === $pageKey ? ' aria-current="page"' : '' ?>>
              <span class="admin-nav-link-icon"></span>
              <span><?= e($label) ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
      </details>
    <?php endforeach; ?>

    <div class="admin-nav-group admin-nav-group-logout">
      <form method="post" action="/admin/logout" class="admin-logout-form">
        <button type="submit" class="admin-nav-link admin-logout-button">
          <span class="admin-nav-link-icon"></span>
          <span>Log out</span>
        </button>
      </form>
    </div>
  </aside>

  <div class="admin-content">
    <div class="admin-main">
