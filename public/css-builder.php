<?php
/**
 * Dynamic CSS endpoint that includes customization overrides.
 * Served to browsers with far-future cache headers for performance.
 */

declare(strict_types=1);

// Minimal bootstrap - only load customization lib, not full app
require_once __DIR__ . '/../app/lib/customize.php';

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=3600');

// Output base CSS
readfile(__DIR__ . '/assets/css/podium-ink.css');

// Append customization overrides
$settings = get_customize_settings();
echo "\n/* Customization Overrides */\n\n";
echo get_customize_css_overrides($settings);
