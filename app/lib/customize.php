<?php
/**
 * Site customization: colors, fonts, logos, layout preferences.
 * Stored in config file with live CSS override injection.
 */

declare(strict_types=1);

const CUSTOMIZE_CONFIG_FILE = __DIR__ . '/../../storage/customize.json';

/**
 * Get current customization settings, merging with defaults.
 */
function get_customize_settings(): array {
    $defaults = [
        'site_name' => '',
        'site_logo_filename' => '',
        'text' => '#111111',
        'text_muted' => '#787774',
        'bg' => '#ffffff',
        'bg_alt' => '#f9f9f8',
        'border' => '#eaeaea',
        'body_font' => 'Geist Sans',
        'heading_font' => 'Newsreader',
        'mono_font' => 'Geist Mono',
        'heading_letter_spacing' => '-0.02em',
        'max_content_width' => '1200px',
        'spacing_multiplier' => '1',
        'grid_columns' => 3,
    ];

    if (!file_exists(CUSTOMIZE_CONFIG_FILE)) {
        return $defaults;
    }

    $json = file_get_contents(CUSTOMIZE_CONFIG_FILE);
    $stored = json_decode($json, true) ?? [];

    // Migrate old color field names to new ones
    if (isset($stored['text_color'])) {
        $stored['text'] = $stored['text_color'];
        unset($stored['text_color']);
    }
    if (isset($stored['text_muted_color'])) {
        $stored['text_muted'] = $stored['text_muted_color'];
        unset($stored['text_muted_color']);
    }
    if (isset($stored['bg_color'])) {
        $stored['bg'] = $stored['bg_color'];
        unset($stored['bg_color']);
    }
    if (isset($stored['bg_alt_color'])) {
        $stored['bg_alt'] = $stored['bg_alt_color'];
        unset($stored['bg_alt_color']);
    }
    if (isset($stored['border_color'])) {
        $stored['border'] = $stored['border_color'];
        unset($stored['border_color']);
    }
    // Remove old duplicate color fields
    unset($stored['primary_color'], $stored['secondary_color'], $stored['accent_color']);
    // Migrate logo field name
    if (isset($stored['site_logo_token']) && !isset($stored['site_logo_filename'])) {
        $stored['site_logo_filename'] = '';
        unset($stored['site_logo_token']);
    }

    return array_merge($defaults, $stored);
}

/**
 * Save customization settings, persisting to disk.
 */
function save_customize_settings(array $settings): bool {
    $dir = dirname(CUSTOMIZE_CONFIG_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return file_put_contents(CUSTOMIZE_CONFIG_FILE, $json) !== false;
}

/**
 * Reset customization to defaults and delete uploads.
 */
function reset_customize_settings(): bool {
    // Delete uploaded files
    $uploadDir = __DIR__ . '/../../storage/customize/';
    if (is_dir($uploadDir)) {
        $files = glob($uploadDir . '*.{jpg,jpeg,png,webp}', GLOB_BRACE);
        foreach ($files as $file) {
            @unlink($file);
        }
    }

    // Delete config file (will revert to defaults on next load)
    return !file_exists(CUSTOMIZE_CONFIG_FILE) || @unlink(CUSTOMIZE_CONFIG_FILE);
}

/**
 * Generate CSS overrides for all customization settings.
 * Used for live preview and public site injection.
 */
function get_customize_css_overrides(array $settings): string {
    $css = ":root {\n";

    $colorMappings = [
        'text' => '--text',
        'text_muted' => '--text-muted',
        'bg' => '--bg',
        'bg_alt' => '--bg-alt',
        'border' => '--border',
    ];

    foreach ($colorMappings as $key => $varName) {
        if (!empty($settings[$key])) {
            $css .= "  $varName: " . e($settings[$key]) . ";\n";
        }
    }

    $css .= "}\n\n";

    if (!empty($settings['body_font'])) {
        $css .= "body, button, input, select, textarea {\n";
        $css .= "  font-family: '" . e($settings['body_font']) . "', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;\n";
        $css .= "}\n\n";
    }

    if (!empty($settings['heading_font'])) {
        $css .= "h1, h2, h3, h4, h5, h6 {\n";
        $css .= "  font-family: '" . e($settings['heading_font']) . "', serif;\n";
        if (!empty($settings['heading_letter_spacing'])) {
            $css .= "  letter-spacing: " . e($settings['heading_letter_spacing']) . ";\n";
        }
        $css .= "}\n\n";
    }

    if (!empty($settings['mono_font'])) {
        $css .= "code, pre, .detail-value, .migration-item {\n";
        $css .= "  font-family: '" . e($settings['mono_font']) . "', 'SF Mono', monospace;\n";
        $css .= "}\n\n";
    }

    if (!empty($settings['max_content_width'])) {
        $css .= ".event-list-page, .cart-page, main {\n";
        $css .= "  max-width: " . e($settings['max_content_width']) . ";\n";
        $css .= "}\n\n";
    }

    if (!empty($settings['spacing_multiplier']) && $settings['spacing_multiplier'] != '1') {
        $multiplier = (float)$settings['spacing_multiplier'];
        $css .= "/* Spacing multiplier: $multiplier */\n";
        $css .= ":root {\n";
        for ($i = 1; $i <= 8; $i++) {
            $baseValue = [0.25, 0.5, 0.75, 1, 1.5, 2, 3, 4][$i - 1];
            $newValue = $baseValue * $multiplier;
            $css .= "  --space-$i: ${newValue}rem;\n";
        }
        $css .= "}\n\n";
    }

    // Apply grid column customization - adjust minmax size for responsive grids
    if (!empty($settings['grid_columns'])) {
        $cols = (int)$settings['grid_columns'];
        if ($cols >= 2 && $cols <= 5) {
            // Calculate minmax width to achieve desired column count at typical viewport widths
            $widths = [2 => '450px', 3 => '280px', 4 => '200px', 5 => '160px'];
            $minmaxWidth = $widths[$cols] ?? '280px';
            $css .= "/* Photo grid: targeting ~$cols columns */\n";
            $css .= ".photo-grid, .search-results { grid-template-columns: repeat(auto-fill, minmax($minmaxWidth, 1fr)) !important; }\n\n";
        }
    }

    return $css;
}

/**
 * Upload and register a site logo/photo.
 * Returns filename on success, null on failure.
 */
function upload_customize_photo(array $fileUpload, string $purpose = 'logo'): ?string {
    if (!isset($fileUpload['error']) || $fileUpload['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    if (!in_array($fileUpload['type'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return null;
    }

    // Check file size (max 5MB)
    if ($fileUpload['size'] > 5 * 1024 * 1024) {
        return null;
    }

    $tmpPath = $fileUpload['tmp_name'];
    $filename = 'logo-' . bin2hex(random_bytes(8)) . '.jpg';
    $dir = __DIR__ . '/../../storage/customize/';
    $storagePath = $dir . $filename;

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    if (!move_uploaded_file($tmpPath, $storagePath)) {
        return null;
    }

    return $filename;
}

/**
 * Get logo URL if one is set.
 */
function get_logo_url(string $filename): ?string {
    if (empty($filename)) {
        return null;
    }
    $path = __DIR__ . '/../../storage/customize/' . $filename;
    if (file_exists($path)) {
        return '/storage/customize/' . $filename;
    }
    return null;
}

/**
 * Delete the site logo file.
 */
function delete_logo(string $filename): bool {
    if (empty($filename)) {
        return true;
    }
    // Prevent directory traversal
    if (strpos($filename, '/') !== false || strpos($filename, '..') !== false) {
        return false;
    }
    $path = __DIR__ . '/../../storage/customize/' . $filename;
    return !file_exists($path) || @unlink($path);
}

/**
 * List available Google Fonts (curated selection for editorial design).
 */
function get_available_fonts(): array {
    return [
        'display' => [
            'Newsreader' => 'Newsreader',
            'Instrument Serif' => 'Instrument Serif',
            'Playfair Display' => 'Playfair Display',
            'Merriweather' => 'Merriweather',
            'Lora' => 'Lora',
        ],
        'body' => [
            'Geist Sans' => 'Geist Sans',
            'Inter' => 'Inter',
            'Roboto' => 'Roboto',
            'Source Sans Pro' => 'Source Sans Pro',
            'Work Sans' => 'Work Sans',
        ],
        'mono' => [
            'Geist Mono' => 'Geist Mono',
            'JetBrains Mono' => 'JetBrains Mono',
            'IBM Plex Mono' => 'IBM Plex Mono',
            'Courier Prime' => 'Courier Prime',
        ],
    ];
}
