<?php
/**
 * "Find your photos": one large input, and the class picker that appears when a
 * kart number turns out to belong to more than one driver.
 *
 * This is deliberately its own page rather than a filter on the grid. The
 * search is the primary path for a visitor who came here to find one specific
 * driver, and burying it in a filter bar is what made the previous version read
 * as a gallery you have to scroll rather than a gallery you can search.
 */
$metaDescription = 'Find your photos from ' . ($event['title'] ?? '') . ' by kart number.';
$metaUrl = site_base_url($GLOBALS['config']) . '/e/' . rawurlencode($event['slug']) . '/find';
require __DIR__ . '/partials/layout_header.php';
?>

<main class="find-page">
  <nav class="find-breadcrumb">
    <a href="/e/<?= e($event['slug']) ?>">&larr; <?= e($event['title']) ?></a>
  </nav>

  <h1 class="find-title">Find your photos</h1>
  <p class="find-lede">Enter your kart number. No account needed.</p>

  <form class="find-form" method="get" action="/e/<?= e($event['slug']) ?>/find">
    <label class="find-label" for="number">Kart number</label>
    <div class="find-input-row">
      <input
        type="text"
        id="number"
        name="number"
        class="find-input"
        value="<?= e($number) ?>"
        inputmode="numeric"
        autocomplete="off"
        autocapitalize="off"
        spellcheck="false"
        placeholder="e.g. 42"
        required
        autofocus>
      <button type="submit" class="find-submit">Find</button>
    </div>
  </form>

  <?php if ($searched && empty($matches)): ?>
    <div class="find-empty">
      <p class="find-empty-title">No driver found with kart number <?= e($number) ?>.</p>
      <p>
        Check the number and try again. If the entry list for this event has not
        been loaded yet, photos may still be waiting to be tagged.
      </p>
      <p><a href="/e/<?= e($event['slug']) ?>">Browse all photos instead</a></p>
    </div>
  <?php endif; ?>

  <?php if (count($matches) > 1): ?>
    <?php /*
      The disambiguation step. This is the composite-key problem made visible:
      the same number belongs to a different driver in each class, so the
      visitor picks which one rather than the software guessing and quietly
      showing them a stranger's photos.
    */ ?>
    <section class="find-disambiguation">
      <h2 class="find-disambiguation-title">
        Kart <?= e($number) ?> is racing in <?= count($matches) ?> classes
      </h2>
      <p class="find-disambiguation-hint">Choose your class.</p>

      <ul class="find-choices">
        <?php foreach ($matches as $match): ?>
          <li>
            <a class="find-choice" href="/e/<?= e($event['slug']) ?>/d/<?= e($match['share_token']) ?>">
              <span class="find-choice-number">#<?= e($match['number']) ?></span>
              <span class="find-choice-class"><?= e($match['class_name']) ?></span>
              <span class="find-choice-count">
                <?= (int)$match['photo_count'] === 1 ? '1 photo' : (int)$match['photo_count'] . ' photos' ?>
              </span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>
</main>

<?php require __DIR__ . '/partials/layout_footer.php'; ?>
