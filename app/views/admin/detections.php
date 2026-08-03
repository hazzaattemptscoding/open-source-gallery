<?php
/**
 * Import a detection sidecar produced by the off-site pipeline.
 *
 * The result summary is the important half of this screen. An import that says
 * only "done" is useless: the photographer needs to know how many went live,
 * how many need review, and precisely which rows could not be resolved, because
 * those are the ones that will otherwise silently never appear in a gallery.
 */
require_once __DIR__ . '/partials/layout_header.php';
?>
<div class="dashboard">
  <h1>Import detections</h1>
  <p><a href="/admin">&larr; Back to dashboard</a></p>

  <?php if (!empty($summary)): ?>
    <?php if (!empty($summary['errors'])): ?>
      <div class="admin-section">
        <h3>Problems in the file</h3>
        <ul class="list-plain">
          <?php foreach (array_slice($summary['errors'], 0, 50) as $error): ?>
            <li><?= e($error) ?></li>
          <?php endforeach; ?>
          <?php if (count($summary['errors']) > 50): ?>
            <li><em><?= count($summary['errors']) - 50 ?> more not shown.</em></li>
          <?php endif; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if (isset($summary['applied'])): ?>
      <div class="admin-section">
        <h3>Result<?= !empty($summary['batch_id']) ? ' for batch ' . e($summary['batch_id']) : '' ?></h3>
        <ul class="list-plain">
          <li><strong><?= (int)$summary['applied'] ?></strong> attributions written of <?= (int)($summary['total'] ?? 0) ?> detections</li>
          <li><strong><?= (int)$summary['confirmed'] ?></strong> confident enough to show straight away</li>
          <li><strong><?= (int)$summary['review'] ?></strong> below the threshold, waiting in
            <a href="/admin/review">the review queue</a></li>
        </ul>

        <?php
        // Unresolved rows, listed rather than counted. These are the ones a
        // human has to act on, so hiding them behind a number would defeat the
        // purpose of showing a summary at all.
        $unresolved = [
            'Filenames not found in this session' => $summary['unknown_photo'] ?? [],
            'Numbers not in the entry list' => $summary['unknown_entrant'] ?? [],
            'Numbers used by more than one class' => $summary['ambiguous'] ?? [],
        ];
        ?>
        <?php foreach ($unresolved as $heading => $rows): ?>
          <?php if (!empty($rows)): ?>
            <h4><?= e($heading) ?> (<?= count($rows) ?>)</h4>
            <ul class="list-plain">
              <?php foreach (array_slice($rows, 0, 25) as $row): ?>
                <li><code><?= e($row) ?></code></li>
              <?php endforeach; ?>
              <?php if (count($rows) > 25): ?>
                <li><em><?= count($rows) - 25 ?> more not shown.</em></li>
              <?php endif; ?>
            </ul>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <form method="post" action="/admin/detections" enctype="multipart/form-data" class="form-narrow">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

    <div class="setting-field">
      <label class="setting-label" for="session_id">Session</label>
      <select name="session_id" id="session_id" required>
        <option value="">Choose the session these photos belong to</option>
        <?php foreach ($sessions as $session): ?>
          <option value="<?= (int)$session['id'] ?>">
            <?= e($session['event_title']) ?> / <?= e($session['name']) ?>
            <?= $session['class_name'] ? '(' . e($session['class_name']) . ')' : '(no class set)' ?>
            &mdash; <?= (int)$session['photo_count'] ?> photos
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <p class="hint">
      A session with a class set can resolve any number. Without one, a number
      used by more than one class in the same event cannot be resolved and is
      reported rather than guessed.
    </p>

    <div class="setting-field">
      <label class="setting-label" for="sidecar">Sidecar file (.json)</label>
      <input type="file" name="sidecar" id="sidecar" accept="application/json,.json">
    </div>

    <div class="setting-field">
      <label class="setting-label" for="sidecar_text">Or paste it</label>
      <textarea name="sidecar_text" id="sidecar_text" rows="8"
                placeholder='{"batch_id":"...","detections":[{"filename":"IMG_0001.jpg","number":"7","confidence":0.94,"method":"ocr"}]}'></textarea>
    </div>

    <button type="submit" class="btn-primary">Import</button>
  </form>

  <div class="admin-disclosure">
    <details>
      <summary>What the file has to contain</summary>
      <p>
        Detection runs on your own machine. This application never runs
        inference; it only ingests results. Each detection needs a filename, a
        kart number, a confidence between 0 and 1, and a method.
      </p>
      <ul class="list-plain">
        <li><code>ocr</code> &mdash; the number was read off the kart</li>
        <li><code>propagated</code> &mdash; inferred from a neighbouring frame in the same burst</li>
        <li><code>livery</code> &mdash; matched on helmet or livery rather than a number</li>
        <li><code>manual</code> &mdash; a human said so</li>
      </ul>
      <p>
        Confidence is required on every row. A missing value is rejected rather
        than assumed, because assuming high would push unchecked guesses live
        and assuming low would bury good detections.
      </p>
      <p>
        Anything at or above <?= ENTRANT_CONFIDENCE_THRESHOLD ?> appears in
        galleries immediately. Anything below waits in the review queue.
        Re-importing is safe: a detection never overwrites a human decision and
        never lowers an existing confidence.
      </p>
    </details>
  </div>
</div>
<?php require_once __DIR__ . '/partials/layout_footer.php'; ?>
