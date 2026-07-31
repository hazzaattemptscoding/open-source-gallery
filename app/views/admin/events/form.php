<?php
$pageTitle = 'Events';
$currentPage = 'events';
require_once __DIR__ . '/../partials/layout_header.php';
?>
<div class="dashboard">
  <h1><?= e($isNew ? 'Create event' : 'Edit event') ?></h1>
  <p><a href="/admin/events">← Back to events</a></p>

  <?php if ($error): ?>
    <div class="error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" class="form-narrow">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

    <label for="title">Event title</label>
    <input type="text" id="title" name="title" value="<?= e($event['title'] ?? '') ?>" required maxlength="190">
    <p class="hint">Shown to customers. Example: Club100 Rye - Round 6</p>

    <label for="venue">Venue</label>
    <input type="text" id="venue" name="venue" value="<?= e($event['venue'] ?? '') ?>" maxlength="190">

    <label for="slug">Event slug (URL-safe, unique)</label>
    <input type="text" id="slug" name="slug" value="<?= e($event['slug'] ?? '') ?>" required pattern="[a-z0-9-]+">
    <p class="hint">Lowercase, hyphens, no spaces. Example: club100-rye-june</p>

    <label for="event_date">Date</label>
    <input type="date" id="event_date" name="event_date" value="<?= e($event['event_date'] ?? '') ?>" required>

    <label for="is_published">Published</label>
    <input type="checkbox" id="is_published" name="is_published" value="1" <?= ($event['is_published'] ?? false) ? 'checked' : '' ?>>

    <label for="price_single_pence">Single photo price (pence, <?= e($currencyCode) ?>)</label>
    <input type="number" id="price_single_pence" name="price_single_pence" value="<?= e($event['price_single_pence'] ?? '') ?>" min="0" required>
    <p class="hint">Pre-filled from the site default. Every event must offer a single-photo price.</p>

    <label for="price_session_pence">Session bundle price (pence, <?= e($currencyCode) ?>)</label>
    <input type="number" id="price_session_pence" name="price_session_pence" value="<?= e($event['price_session_pence'] ?? '') ?>" min="0">
    <p class="hint">All photos in one session. Leave blank to disable.</p>

    <label for="price_event_pence">Full event price (pence, <?= e($currencyCode) ?>)</label>
    <input type="number" id="price_event_pence" name="price_event_pence" value="<?= e($event['price_event_pence'] ?? '') ?>" min="0">
    <p class="hint">All photos in all sessions. Leave blank to disable.</p>

    <button type="submit"><?= e($isNew ? 'Create event' : 'Update event') ?></button>
  </form>

  <?php if (!$isNew): ?>
    <section class="entries-section">
      <h2>Entry list</h2>
      <p class="hint">
        The kart numbers and classes here fill the tagging screen's lookup and the
        gallery's kart filter. Driver names are used for tagging only and are never
        shown on a public page.
      </p>

      <?php if ($entriesError): ?>
        <div class="error"><?= e($entriesError) ?></div>
      <?php elseif ($entriesOk !== null): ?>
        <div class="success">
          Imported <?= (int)$entriesOk ?> entr<?= $entriesOk === 1 ? 'y' : 'ies' ?>.
          <?php if ($entriesNotes): ?><br><?= e($entriesNotes) ?><?php endif; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="/admin/events/<?= (int)$event['id'] ?>/entries" enctype="multipart/form-data" class="form-narrow">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

        <label for="entries_csv">Paste rows</label>
        <textarea id="entries_csv" name="entries_csv" rows="6"
                  placeholder="Kart,Driver,Class&#10;23,Sam Taylor,Junior Rotax&#10;7,Alex Reed,Senior Rotax"></textarea>
        <p class="hint">
          One entry per line: kart number, driver, class. A header row is optional,
          and its columns can be in any order. Kart number is the only required field.
        </p>

        <label for="entries_file">Or upload a CSV</label>
        <input type="file" id="entries_file" name="entries_file" accept=".csv,text/csv">

        <fieldset class="entries-mode">
          <legend>If entries already exist</legend>
          <label><input type="radio" name="import_mode" value="replace" checked> Replace them</label>
          <label><input type="radio" name="import_mode" value="append"> Keep them and add new ones</label>
        </fieldset>

        <button type="submit">Import entries</button>
      </form>

      <?php if ($entries): ?>
        <h3><?= count($entries) ?> current entr<?= count($entries) === 1 ? 'y' : 'ies' ?></h3>
        <div class="table-wrapper">
          <table class="admin-table">
            <thead>
              <tr><th>Kart</th><th>Driver</th><th>Class</th></tr>
            </thead>
            <tbody>
              <?php foreach ($entries as $entry): ?>
                <tr>
                  <td><?= e($entry['kart_number']) ?></td>
                  <td><?= e($entry['driver_name']) ?></td>
                  <td><?= e($entry['class']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="hint">No entries yet. Tagging autocomplete and the kart filter stay empty until you import some.</p>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../partials/layout_footer.php'; ?>
