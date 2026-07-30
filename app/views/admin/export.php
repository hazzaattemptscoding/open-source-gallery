<?php
$pageTitle = 'Export';
$currentPage = 'export';
require_once __DIR__ . '/partials/layout_header.php';
?>
<main class="dashboard">
  <h1>Data Export</h1>

  <div class="info-box">
    <h3>Export your data</h3>
    <p>Download your gallery data as CSV files for backup, analysis, or compliance. All exports are logged in the audit trail.</p>
    <ul>
      <li><strong>Orders:</strong> Complete transaction history with customer emails and payment details</li>
      <li><strong>Photos:</strong> All uploaded photos with metadata, tags, and pricing</li>
      <li><strong>Customers:</strong> Customer summary with order counts and total revenue</li>
      <li><strong>Events:</strong> Event performance data with photo counts and revenue</li>
    </ul>
  </div>

  <div class="export-grid">
    <div class="export-card">
      <h3>Orders</h3>
      <p>Complete transaction history</p>
      <p style="font-size: 0.85rem; color: #999;">
        Order IDs, emails, amounts, status, Stripe details
      </p>
      <a href="/admin/export/orders" class="export-button">Download CSV</a>
    </div>

    <div class="export-card">
      <h3>Photos</h3>
      <p>Photo inventory and metadata</p>
      <p style="font-size: 0.85rem; color: #999;">
        Photo IDs, events, captions, prices, tags
      </p>
      <a href="/admin/export/photos" class="export-button">Download CSV</a>
    </div>

    <div class="export-card">
      <h3>Customers</h3>
      <p>Customer purchase summary</p>
      <p style="font-size: 0.85rem; color: #999;">
        Emails, order counts, lifetime value, date ranges
      </p>
      <a href="/admin/export/customers" class="export-button">Download CSV</a>
    </div>

    <div class="export-card">
      <h3>Events</h3>
      <p>Event performance metrics</p>
      <p style="font-size: 0.85rem; color: #999;">
        Event names, photo counts, orders, revenue
      </p>
      <a href="/admin/export/events" class="export-button">Download CSV</a>
    </div>
  </div>

  <div class="info-box">
    <h3>GDPR & Data Compliance</h3>
    <p>
      Use the <strong>Customers</strong> export to handle data subject access requests (DSAR).
      All exports are timestamped and logged in the audit trail for compliance records.
    </p>
    <p style="margin-bottom: 0;">
      <strong>Note:</strong> Download tokens and customer IPs in the Orders export are necessary for security and audit purposes.
      Keep exported CSV files in a secure location.
    </p>
  </div>

</main>


<?php require_once __DIR__ . '/partials/layout_footer.php'; ?>
