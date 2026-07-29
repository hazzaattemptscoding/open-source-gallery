<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bulk Operations: Admin</title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<style>
.bulk-container { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
.form-group { margin-bottom: 1.5rem; }
.form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; }
button { padding: 0.75rem 1.5rem; background: #000; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
</style>
</head>
<body>
<header class="site-header">
  <a href="/admin" class="site-title">Bulk Operations</a>
</header>

<div class="bulk-container">
  <h1>Bulk Photo Operations</h1>
  <p>Safely perform operations on up to <?= (int)$limits['max_per_operation'] ?> photos at once.</p>

  <?php if (!empty($errors)): ?>
    <div style="background: #ffebee; padding: 1rem; border-radius: 4px; margin-bottom: 2rem;">
      <?php foreach ($errors as $err): ?>
        <p>✗ <?= e($err) ?></p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" style="margin-top: 2rem;">
    <input type="hidden" name="action" value="tag">

    <div class="form-group">
      <label>Photo IDs (comma-separated):</label>
      <textarea name="photo_ids" placeholder="1,2,3,4,5" required style="height: 100px;"></textarea>
    </div>

    <div class="form-group">
      <label>Operation:</label>
      <select name="action" id="operation" onchange="updateForm()">
        <option value="tag">Add Tags</option>
        <?php if ($limits['can_bulk_price']): ?>
          <option value="price">Update Prices</option>
        <?php endif; ?>
        <option value="status">Change Status</option>
        <?php if ($limits['can_delete']): ?>
          <option value="delete">Delete Photos</option>
        <?php endif; ?>
      </select>
    </div>

    <div id="tag-options" class="form-group">
      <label>Kart:</label>
      <input type="text" name="kart" placeholder="e.g., 42">
      <label style="margin-top: 1rem;">Driver:</label>
      <input type="text" name="driver" placeholder="e.g., Driver Name">
      <label style="margin-top: 1rem;">Class:</label>
      <input type="text" name="class" placeholder="e.g., Formula Ford">
    </div>

    <div id="price-options" class="form-group" style="display: none;">
      <label>Price (pence):</label>
      <input type="number" name="price_pence" placeholder="e.g., 1500" min="0">
    </div>

    <div id="status-options" class="form-group" style="display: none;">
      <label>Status:</label>
      <select name="status">
        <option value="draft">Draft</option>
        <option value="live">Live</option>
        <option value="archived">Archived</option>
      </select>
    </div>

    <button type="submit">Execute Operation</button>
  </form>

  <script>
    function updateForm() {
      const op = document.getElementById('operation').value;
      document.getElementById('tag-options').style.display = op === 'tag' ? 'block' : 'none';
      document.getElementById('price-options').style.display = op === 'price' ? 'block' : 'none';
      document.getElementById('status-options').style.display = op === 'status' ? 'block' : 'none';
    }
  </script>
</div>

</body>
</html>
