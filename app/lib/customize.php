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
        'site_logo_token' => '',
        'primary_color' => '#111111',
        'secondary_color' => '#ffffff',
        'accent_color' => '#666666',
        'text_color' => '#111111',
        'text_muted_color' => '#787774',
        'bg_color' => '#ffffff',
        'bg_alt_color' => '#f9f9f8',
        'border_color' => '#eaeaea',
        'body_font' => 'Geist Sans',
        'heading_font' => 'Newsreader',
        'mono_font' => 'Geist Mono',
        'heading_letter_spacing' => '-0.02em',
        'max_content_width' => '1200px',
        'spacing_multiplier' => '1',
    ];

    if (!file_exists(CUSTOMIZE_CONFIG_FILE)) {
        return $defaults;
    }

    $json = file_get_contents(CUSTOMIZE_CONFIG_FILE);
    $stored = json_decode($json, true) ?? [];

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
 * Generate CSS overrides for all customization settings.
 * Used for live preview and public site injection.
 */
function get_customize_css_overrides(array $settings): string {
    $css = ":root {\n";

    $colorMappings = [
        'primary_color' => '--text',
        'secondary_color' => '--bg',
        'accent_color' => '--text-muted',
        'text_color' => '--text',
        'text_muted_color' => '--text-muted',
        'bg_color' => '--bg',
        'bg_alt_color' => '--bg-alt',
        'border_color' => '--border',
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

    return $css;
}

/**
 * Upload and register a site logo/photo.
 * Returns public_token or false on failure.
 */
function upload_customize_photo(array $fileUpload, string $purpose = 'logo'): ?string {
    if (!isset($fileUpload['error']) || $fileUpload['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    if (!in_array($fileUpload['type'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return null;
    }

    $tmpPath = $fileUpload['tmp_name'];
    $filename = bin2hex(random_bytes(12)) . '.jpg';
    $storagePath = __DIR__ . '/../../storage/uploads/' . $filename;

    if (!is_dir(dirname($storagePath))) {
        mkdir(dirname($storagePath), 0755, true);
    }

    if (!move_uploaded_file($tmpPath, $storagePath)) {
        return null;
    }

    return bin2hex(random_bytes(16));
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
