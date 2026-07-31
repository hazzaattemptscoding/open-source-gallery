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
    /*
     * Colour and font defaults are deliberately EMPTY, and must stay that way.
     *
     * They used to hold real values (#ffffff, #111111, 'Geist Sans', ...). Because
     * get_customize_css_overrides() emits a rule for every non-empty setting, that
     * meant every install — including ones whose owner had never opened the
     * Customization page — served an override block that replaced the whole
     * palette with flat hex. It overwrote the OKLCH tokens in podium-ink.css and,
     * because the block lands after that file's dark-mode media query, it disabled
     * dark mode outright. The shipped design was never what anyone actually saw.
     *
     * Empty here means "the admin has expressed no preference", so the design
     * system in podium-ink.css renders as authored. The Customization form shows
     * the shipped values as placeholders so the admin can still see what they are.
     *
     * Structural settings below keep real defaults: they describe layout choices
     * with no equivalent in the stylesheet, and they do not fight the token system.
     */
    $defaults = [
        'site_name' => '',
        'site_logo_filename' => '',
        'text' => '',
        'text_muted' => '',
        'bg' => '',
        'bg_alt' => '',
        'border' => '',
        'accent' => '',
        'accent_hover' => '',
        'body_font' => '',
        'heading_font' => '',
        'mono_font' => '',
        'heading_letter_spacing' => '',
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
 * Escape a value for interpolation into a CSS declaration.
 *
 * NOT the same job as e(). e() is htmlspecialchars, which is the wrong tool in a
 * stylesheet twice over: it fails to neutralise the characters that actually
 * break out of a CSS declaration ({ } ; and comment markers), and it mangles
 * legitimate values — a font name containing an apostrophe came out as
 * "Foo&#039;s Sans" and rendered as the fallback.
 *
 * Strategy is allowlist, not blocklist: strip anything outside the small set of
 * characters a colour, length, or font name legitimately needs. An empty return
 * means the caller should omit the declaration entirely rather than emit a
 * broken one.
 */
function css_safe_value(string $value): string {
    // Alphanumerics, spaces, and the punctuation used by lengths, hex colours,
    // decimals, negative values, and multi-word font names.
    $clean = preg_replace('/[^A-Za-z0-9 \-_.,#%()]/', '', $value);
    return trim((string)$clean);
}

/**
 * Quote a font family name for CSS, unless it is a bare CSS-wide keyword or
 * generic family (sans-serif, serif, monospace, system-ui) which must NOT be
 * quoted or the browser treats it as a family literally named "serif".
 */
function css_font_family(string $name): string {
    $clean = css_safe_value($name);
    if ($clean === '') {
        return '';
    }
    $generic = ['serif', 'sans-serif', 'monospace', 'cursive', 'fantasy',
                'system-ui', 'ui-monospace', 'ui-serif', 'ui-sans-serif'];
    return in_array(strtolower($clean), $generic, true) ? $clean : "'$clean'";
}

/**
 * Generate CSS overrides for all customization settings.
 * Used for live preview and public site injection.
 *
 * Two rules govern this function, both learned from bugs it used to have:
 *
 * 1. Emit NOTHING for a setting the admin has not explicitly chosen. The
 *    defaults in get_customize_settings() used to carry real values (#ffffff,
 *    #111111, 'Geist Sans'), so every install — including ones that had never
 *    opened the Customization page — got its whole palette overwritten with flat
 *    hex. That silently disabled the OKLCH token system and dark mode for
 *    everyone. Colour and font defaults are now empty; the shipped design in
 *    podium-ink.css is what an uncustomised site renders.
 *
 * 2. Scope colour overrides to light mode. This block is appended after
 *    podium-ink.css, which means it lands after that file's
 *    @media (prefers-color-scheme: dark) rule and beats it on source order at
 *    equal specificity. Unscoped, a single customised colour forced light values
 *    onto dark-mode visitors — set only --bg and you got light-on-light text.
 *    Wrapping in `light` confines customisation to the scheme it was picked in
 *    and leaves the shipped dark palette intact. (The `light` query also matches
 *    visitors who have expressed no preference, so the common case is covered.)
 */
function get_customize_css_overrides(array $settings): string {
    $css = '';

    $colorMappings = [
        'text' => '--text',
        'text_muted' => '--text-muted',
        'bg' => '--bg',
        'bg_alt' => '--bg-alt',
        'border' => '--border',
        'accent' => '--accent',
        'accent_hover' => '--accent-hover',
    ];

    $colorRules = '';
    foreach ($colorMappings as $key => $varName) {
        if (!empty($settings[$key])) {
            $value = css_safe_value($settings[$key]);
            if ($value !== '') {
                $colorRules .= "    $varName: $value;\n";
            }
        }
    }

    // Only emit the wrapper when there is something to put in it — an empty
    // :root {} block on every uncustomised install is noise in a file that is
    // served on every page view.
    if ($colorRules !== '') {
        $css .= "@media (prefers-color-scheme: light) {\n";
        $css .= "  :root {\n" . $colorRules . "  }\n";
        $css .= "}\n\n";
    }

    // Fonts override the family tokens rather than re-declaring body/h1-h6.
    // podium-ink.css routes every font-family through these three tokens, so
    // setting the token reaches every element that uses it, including ones a
    // hardcoded selector list here would miss.
    $fontTokens = '';
    if (!empty($settings['body_font'])) {
        $family = css_font_family($settings['body_font']);
        if ($family !== '') {
            $fontTokens .= "  --font-body: $family, system-ui, sans-serif;\n";
        }
    }
    if (!empty($settings['heading_font'])) {
        $family = css_font_family($settings['heading_font']);
        if ($family !== '') {
            $fontTokens .= "  --font-heading: $family, Georgia, serif;\n";
        }
    }
    if (!empty($settings['mono_font'])) {
        $family = css_font_family($settings['mono_font']);
        if ($family !== '') {
            $fontTokens .= "  --font-mono: $family, ui-monospace, monospace;\n";
        }
    }
    if ($fontTokens !== '') {
        $css .= ":root {\n" . $fontTokens . "}\n\n";
    }

    if (!empty($settings['heading_letter_spacing'])) {
        $spacing = css_safe_value($settings['heading_letter_spacing']);
        if ($spacing !== '') {
            $css .= "h1, h2, h3, h4, h5, h6 {\n";
            $css .= "  letter-spacing: $spacing;\n";
            $css .= "}\n\n";
        }
    }

    if (!empty($settings['max_content_width'])) {
        $width = css_safe_value($settings['max_content_width']);
        if ($width !== '') {
            $css .= ".event-list-page, .cart-page, main {\n";
            $css .= "  max-width: $width;\n";
            $css .= "}\n\n";
        }
    }

    if (!empty($settings['spacing_multiplier']) && $settings['spacing_multiplier'] != '1') {
        $multiplier = (float)$settings['spacing_multiplier'];
        $css .= "/* Spacing multiplier: $multiplier */\n";
        $css .= ":root {\n";
        for ($i = 1; $i <= 8; $i++) {
            $baseValue = [0.25, 0.5, 0.75, 1, 1.5, 2, 3, 4][$i - 1];
            $newValue = $baseValue * $multiplier;
            $css .= "  --space-$i: {$newValue}rem;\n";
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
