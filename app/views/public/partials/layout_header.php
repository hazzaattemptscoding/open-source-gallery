<?php
require_once __DIR__ . '/../../../lib/seo.php';
require_once __DIR__ . '/../../../lib/settings.php';

// site.language: was set in Settings, read by nothing -- every page declared
// English regardless. get_setting()'s own try/catch degrades to the 'en'
// default if settings_registry isn't reachable, so a DB hiccup here can't
// break the page the way a missing require would.
$htmlLang = e(get_setting($GLOBALS['pdo'], 'site', 'language', 'en'));
?>
<!doctype html>
<html lang="<?= $htmlLang ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php

// Default meta description if not provided
if (!isset($metaDescription)) {
    $metaDescription = 'Professional sports photography gallery and sales platform';
}
if (!isset($metaUrl)) {
    $metaUrl = site_base_url($GLOBALS['config']) ?? 'https://example.com';
}
if (!isset($metaImageUrl)) {
    $metaImageUrl = '';
}

// Use generate_meta_tags for SEO if title and description are set
if (isset($pageTitle) && $metaDescription) {
    echo generate_meta_tags(
        $GLOBALS['config'],
        $pageTitle,
        $metaDescription,
        $metaUrl,
        $metaImageUrl
    );
} else {
    // Fallback: just a simple title
    echo '<title>' . e($pageTitle ?? $siteName) . '</title>';
}

// Generate structured data if event is provided (for event pages).
// seo.php is already required at the top of this file; generate_event_schema()
// takes the config first, then the event, then an optional image URL.
if (isset($event)) {
    echo generate_event_schema($GLOBALS['config'], $event, $metaImageUrl);
}
?>
<link rel="stylesheet" href="/api/styles.css">
<?= isset($extraStyles) ? $extraStyles : '' ?>
</head>
<body>
<header class="site-header">
  <a href="/" class="site-title"><?= e($siteName) ?></a>
  <?php if (isset($showCart) && $showCart): ?>
  <a href="/cart" class="cart-badge" id="cartBadge">Cart (<span id="cartCount">0</span>)</a>
  <?php endif; ?>
</header>
