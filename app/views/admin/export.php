<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Export Data — Admin</title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<style>
.export-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}
.export-card {
    padding: 1.5rem;
    border: 1px solid var(--border);
    border-radius: 4px;
    text-align: center;
}
.export-card h3 {
    margin-top: 0;
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
}
.export-card p {
    margin: 0.5rem 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}
.export-button {
    display: inline-block;
    margin-top: 1rem;
    padding: 0.75rem 1.5rem;
    background-color: #1a1a1a;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    font-weight: 500;
    font-size: 0.95rem;
    transition: background-color 0.2s;
}
.export-button:hover {
    background-color: #333;
}
.info-box {
    background-color: #f5f5f5;
    padding: 1.5rem;
    border-radius: 4px;
    margin-bottom: 2rem;
    border-left: 4px solid #1a1a1a;
}
.info-box h3 {
    margin-top: 0;
}
.info-box ul {
    margin: 1rem 0 0 0;
    padding-left: 1.5rem;
}
</style>
<link rel="stylesheet" href="/api/styles.css">
</head>
<body>

<header class="site-header">
  <a href="/admin" class="site-title">Export Data</a>
  <form method="post" action="/admin/logout" class="logout-form">
    <button type="submit">Log out</button>
  </form>
</header>

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

</body>
</html>
