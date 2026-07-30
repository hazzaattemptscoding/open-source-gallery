<?php
$pageTitle = 'Bulk';
$currentPage = 'bulk';
require_once __DIR__ . '/partials/layout_header.php';
?>
<main class="dashboard bulk-container">
  <div class="bulk-header">
    <h1>Bulk Photo Operations</h1>
    <p>Safely perform operations on up to <?= (int)$limits['max_per_operation'] ?> photos at once.</p>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="error-box">
      <?php foreach ($errors as $err): ?>
        <p><strong>Error:</strong> <?= e($err) ?></p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="form-card">
    <h2>Select Operation</h2>

    <div class="info-box">
      <strong>Tip:</strong> Enter photo IDs separated by commas (e.g., 1,2,3,4,5). Maximum <?= (int)$limits['max_per_operation'] ?> photos per operation.
    </div>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
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
            <option value="live">Live</option>
            <option value="hidden">Hidden</option>
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


<?php require_once __DIR__ . '/partials/layout_footer.php'; ?>
