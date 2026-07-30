<?php
$pageTitle = 'Admin';
$currentPage = 'dashboard';
require_once __DIR__ . '/partials/layout_header.php';
?>

<h1><?= e($siteName) ?> admin</h1>
<p>Logged in as <?= e($adminEmail) ?>.</p>

<?php if (!$totpEnabled): ?>
  <p class="error">Two-factor authentication is not enabled on this account. <a href="/admin/totp/enroll">Set it up now</a>.</p>
<?php endif; ?>

<?php if (!$setupComplete && !empty($setupChecklist)): ?>
  <div class="setup-checklist">
    <h3>Setup Incomplete</h3>
    <p>Finish setting up your gallery. Click any item to complete it.</p>
    <ul>
      <?php foreach ($setupChecklist as $item): ?>
        <li class="checklist-item checklist-<?= e($item['status']) ?>">
          <span class="icon" aria-hidden="true">
            <?php if ($item['status'] === 'complete'): ?>✓
            <?php elseif ($item['status'] === 'skipped'): ?>○
            <?php else: ?>●
            <?php endif; ?>
          </span>
          <a href="<?= e($item['url']) ?>" class="label">
            <?= e($item['label']) ?>
            <?php if ($item['status'] === 'pending'): ?>
              <span class="badge">Pending</span>
            <?php elseif ($item['status'] === 'skipped'): ?>
              <span class="badge skipped">Skipped</span>
            <?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="panel">
  <h2>Manage content</h2>
  <ul class="list-plain">
    <li><a href="/admin/events">→ Events</a>: Create and manage events, sessions, prices</li>
    <li><a href="/admin/upload">→ Upload photos</a>: Chunked upload with derivative generation</li>
    <li><a href="/search">→ Search & Filtering</a>: Full-text search with faceted filters</li>
    <li><a href="/admin/bulk">→ Bulk Operations</a>: Batch tag, price, delete, organize photos</li>
    <li><a href="/admin/migrations">→ Migrations</a>: Apply pending database schema changes</li>
  </ul>
</div>

<div class="panel">
  <h2>Monitor</h2>
  <ul class="list-plain">
    <li><a href="/admin/health">→ System health</a>: Database, cron, email, storage status</li>
    <li><a href="/admin/stats">→ Sales dashboard</a>: Orders, revenue, top photos</li>
    <li><a href="/admin/analytics">→ Analytics</a>: Revenue trends, charts, customer insights</li>
    <li><a href="/admin/reporting">→ Advanced Reporting</a>: Customer segments, cohorts, LTV analysis</li>
    <li><a href="/admin/orders">→ Order history</a>: Browse orders, customer emails, status</li>
    <li><a href="/admin/audit-log">→ Audit log</a>: All mutations, logins, exports, webhooks</li>
  </ul>
</div>

<div class="panel">
  <h2>Design & Configuration</h2>
  <ul class="list-plain">
    <li><a href="/admin/customize">→ Site Customization</a>: Colors, fonts, logos, layout with live preview</li>
    <li><a href="/admin/settings">→ Settings</a>: Stripe keys, email configuration, site details</li>
    <li><a href="/admin/emails">→ Email Management</a>: Queue, templates, delivery tracking</li>
    <li><a href="/admin/watermarks">→ Watermark Settings</a>: Position, opacity, custom text</li>
    <li><a href="/admin/print_orders">→ Print Orders</a>: Manage print fulfillment orders and tracking</li>
    <li><a href="/admin/export">→ Export Data</a>: Download orders, photos, customers, events as CSV</li>
  </ul>
</div>

<div class="panel">
  <h2>Security</h2>
  <ul class="list-plain">
    <li><a href="/admin/admins">→ Admin users</a>: Create and manage admin accounts with roles</li>
    <li><a href="/admin/my-sessions">→ My Sessions</a>: View and revoke your active sessions</li>
  </ul>
</div>

<div class="panel">
  <h2>Developer</h2>
  <ul class="list-plain">
    <li><strong>REST API v1</strong> — Photo listing and detail endpoints</li>
    <li>Endpoints: <code>/api/v1/photos</code> · <code>/api/v1/photos/{id}</code></li>
    <li>Auth: Pass <code>?api_key=YOUR_KEY</code> or <code>Authorization: Bearer YOUR_KEY</code></li>
    <li>See docs/api.md for full documentation</li>
  </ul>
</div>

<?php require_once __DIR__ . '/partials/layout_footer.php'; ?>
