<?php
/**
 * CSV exports. The four cards are the page; the compliance notes below them
 * are reference material an admin reads once, so they sit behind a disclosure
 * rather than competing with the download buttons.
 */
$pageTitle = 'Export';
$currentPage = 'export';
require_once __DIR__ . '/partials/layout_header.php';
?>
<main class="dashboard">
  <h1>Data export</h1>
  <p class="page-intro">Download gallery data as CSV for backup, analysis, or compliance. Every export is recorded in the audit log.</p>

  <div class="export-grid">
    <div class="export-card">
      <h3>Orders</h3>
      <p>Complete transaction history</p>
      <p class="export-fields">Order IDs, emails, amounts, status, Stripe details</p>
      <a href="/admin/export/orders" class="export-button">Download CSV</a>
    </div>

    <div class="export-card">
      <h3>Photos</h3>
      <p>Photo inventory and metadata</p>
      <p class="export-fields">Photo IDs, events, captions, prices, tags</p>
      <a href="/admin/export/photos" class="export-button">Download CSV</a>
    </div>

    <div class="export-card">
      <h3>Customers</h3>
      <p>Customer purchase summary</p>
      <p class="export-fields">Emails, order counts, lifetime value, date ranges</p>
      <a href="/admin/export/customers" class="export-button">Download CSV</a>
    </div>

    <div class="export-card">
      <h3>Events</h3>
      <p>Event performance metrics</p>
      <p class="export-fields">Event names, photo counts, orders, revenue</p>
      <a href="/admin/export/events" class="export-button">Download CSV</a>
    </div>
  </div>

  <details class="admin-disclosure">
    <summary>Handling personal data in these exports</summary>
    <p>
      Use the <strong>Customers</strong> export to answer data subject access requests. Exports are
      timestamped and logged in the audit trail, which is what you produce as the compliance record.
    </p>
    <p>
      The Orders export contains download tokens and customer IP addresses. Both are needed for
      security and audit purposes, and both make the file sensitive: store it somewhere access-controlled
      and delete it when you are done with it.
    </p>
  </details>
</main>

<?php require_once __DIR__ . '/partials/layout_footer.php'; ?>
