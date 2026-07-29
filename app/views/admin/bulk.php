<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bulk Operations: <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<link rel="stylesheet" href="/assets/css/admin.css">
<style>
.bulk-container {
  max-width: 900px;
  margin: 0 auto;
  padding: 2rem;
}

.bulk-header {
  margin-bottom: 2rem;
}

.bulk-header h1 {
  margin: 0 0 0.5rem 0;
  font-size: 1.75rem;
  font-weight: 600;
  color: var(--text);
}

.bulk-header p {
  margin: 0;
  color: var(--text-muted);
  font-size: 0.95rem;
  line-height: 1.5;
}

.error-box {
  background: rgba(159, 47, 45, 0.08);
  border: 1px solid rgba(159, 47, 45, 0.2);
  border-left: 3px solid var(--error);
  padding: 1rem;
  margin-bottom: 2rem;
  border-radius: 3px;
}

.error-box p {
  margin: 0.5rem 0;
  color: var(--text);
  font-size: 0.95rem;
}

.error-box p:first-child {
  margin-top: 0;
}

.form-card {
  background: var(--bg);
  border: 1px solid var(--border);
  padding: 2rem;
}

.form-card h2 {
  margin: 0 0 1.5rem 0;
  font-size: 1.2rem;
  font-weight: 600;
  color: var(--text);
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--border);
}

.form-group {
  margin-bottom: 1.5rem;
}

.form-group:last-of-type {
  margin-bottom: 2rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 600;
  color: var(--text);
  font-size: 0.95rem;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--border);
  background: var(--bg);
  color: var(--text);
  font-family: inherit;
  font-size: 0.95rem;
  transition: border-color 200ms ease, box-shadow 200ms ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: var(--text);
  box-shadow: 0 0 0 2px rgba(17, 17, 17, 0.05);
}

.form-group textarea {
  min-height: 100px;
  resize: vertical;
  font-family: 'Geist Mono', monospace;
  line-height: 1.5;
}

.form-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
}

.form-row .form-group {
  margin-bottom: 1rem;
}

.form-row .form-group:last-of-type {
  margin-bottom: 1rem;
}

.operation-section {
  display: none;
  padding: 1.5rem;
  background: var(--bg-alt);
  border: 1px solid var(--border);
  margin-bottom: 1.5rem;
}

.operation-section.active {
  display: block;
}

.operation-section h3 {
  margin: 0 0 1rem 0;
  font-size: 1rem;
  font-weight: 600;
  color: var(--text);
}

button {
  padding: 0.75rem 1.5rem;
  background: var(--text);
  color: var(--bg);
  border: none;
  cursor: pointer;
  font-weight: 600;
  font-size: 0.95rem;
  transition: all 160ms var(--ease-out);
}

button:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(17, 17, 17, 0.1);
}

button:active {
  transform: scale(0.98);
}

.info-box {
  background: rgba(17, 17, 17, 0.02);
  border: 1px solid var(--border);
  border-left: 3px solid var(--text-muted);
  padding: 1rem;
  margin-bottom: 1.5rem;
  font-size: 0.85rem;
  color: var(--text-muted);
}

.info-box strong {
  color: var(--text);
  font-weight: 600;
}

@media (max-width: 600px) {
  .bulk-container {
    padding: 1rem;
  }

  .bulk-header h1 {
    font-size: 1.4rem;
  }

  .form-card {
    padding: 1.5rem;
  }

  .form-row {
    grid-template-columns: 1fr;
  }
}
</style>
<link rel="stylesheet" href="/api/styles.css">
</head>
<body>
<header class="site-header">
  <a href="/admin" class="site-title"><?= e($siteName) ?></a>
  <form method="post" action="/admin/logout" class="logout-form">
    <button type="submit">Log out</button>
  </form>
</header>

<main class="dashboard bulk-container">
  <div class="bulk-header">
    <h1>Bulk Photo Operations</h1>
    <p>Safely perform operations on up to <?= (int)$limits['max_per_operation'] ?> photos at once.</p>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="error-box">
      <?php foreach ($errors as $err): ?>
        <p>✗ <?= e($err) ?></p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="form-card">
    <h2>Select Operation</h2>

    <div class="info-box">
      <strong>Tip:</strong> Enter photo IDs separated by commas (e.g., 1,2,3,4,5). Maximum <?= (int)$limits['max_per_operation'] ?> photos per operation.
    </div>

    <form method="post">
      <div class="form-group">
        <label>Photo IDs (comma-separated):</label>
        <textarea name="photo_ids" placeholder="1,2,3,4,5" required></textarea>
      </div>

      <div class="form-group">
        <label>Operation:</label>
        <select name="action" id="operation" onchange="updateForm()">
          <option value="tag">Add Tags (kart, driver, class)</option>
          <?php if ($limits['can_bulk_price']): ?>
            <option value="price">Update Prices</option>
          <?php endif; ?>
          <option value="status">Change Status</option>
          <?php if ($limits['can_delete']): ?>
            <option value="delete">Delete Photos</option>
          <?php endif; ?>
        </select>
      </div>

      <div id="tag-options" class="operation-section active">
        <h3>Tag Options</h3>
        <div class="form-row">
          <div class="form-group">
            <label>Kart:</label>
            <input type="text" name="kart" placeholder="e.g., 42">
          </div>
          <div class="form-group">
            <label>Driver:</label>
            <input type="text" name="driver" placeholder="e.g., Driver Name">
          </div>
          <div class="form-group">
            <label>Class:</label>
            <input type="text" name="class" placeholder="e.g., Formula Ford">
          </div>
        </div>
      </div>

      <div id="price-options" class="operation-section">
        <h3>Price Options</h3>
        <div class="form-group">
          <label>Price (pence):</label>
          <input type="number" name="price_pence" placeholder="e.g., 1500" min="0">
        </div>
      </div>

      <div id="status-options" class="operation-section">
        <h3>Status Options</h3>
        <div class="form-group">
          <label>New Status:</label>
          <select name="status">
            <option value="draft">Draft</option>
            <option value="live">Live</option>
            <option value="archived">Archived</option>
          </select>
        </div>
      </div>

      <button type="submit">Execute Operation</button>
    </form>
  </div>
</main>

<script>
  function updateForm() {
    const op = document.getElementById('operation').value;
    document.getElementById('tag-options').classList.remove('active');
    document.getElementById('price-options').classList.remove('active');
    document.getElementById('status-options').classList.remove('active');

    if (op === 'tag') {
      document.getElementById('tag-options').classList.add('active');
    } else if (op === 'price') {
      document.getElementById('price-options').classList.add('active');
    } else if (op === 'status') {
      document.getElementById('status-options').classList.add('active');
    }
  }
</script>

</body>
</html>
