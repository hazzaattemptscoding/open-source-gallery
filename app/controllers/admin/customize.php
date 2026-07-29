<?php
/**
 * Admin: Site customization (colors, fonts, logos, layout).
 * Live preview + persistent storage of design customizations.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/customize.php';
require_once __DIR__ . '/../../lib/audit.php';

function admin_customize_controller(PDO $pdo, array $config): void {
    require_admin();
    if (!can_edit_settings($pdo)) {
        http_response_code(403);
        echo '403 Forbidden';
        exit;
    }

    $errors = [];
    $success = $_GET['success'] ?? false;
    $preview_mode = $_GET['preview'] ?? 'customize';

    $settings = get_customize_settings();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $formData = [];

        $formData['site_name'] = trim($_POST['site_name'] ?? '');
        $formData['primary_color'] = trim($_POST['primary_color'] ?? '#111111');
        $formData['secondary_color'] = trim($_POST['secondary_color'] ?? '#ffffff');
        $formData['accent_color'] = trim($_POST['accent_color'] ?? '#666666');
        $formData['text_color'] = trim($_POST['text_color'] ?? '#111111');
        $formData['text_muted_color'] = trim($_POST['text_muted_color'] ?? '#787774');
        $formData['bg_color'] = trim($_POST['bg_color'] ?? '#ffffff');
        $formData['bg_alt_color'] = trim($_POST['bg_alt_color'] ?? '#f9f9f8');
        $formData['border_color'] = trim($_POST['border_color'] ?? '#eaeaea');
        $formData['body_font'] = trim($_POST['body_font'] ?? 'Geist Sans');
        $formData['heading_font'] = trim($_POST['heading_font'] ?? 'Newsreader');
        $formData['mono_font'] = trim($_POST['mono_font'] ?? 'Geist Mono');
        $formData['heading_letter_spacing'] = trim($_POST['heading_letter_spacing'] ?? '-0.02em');
        $formData['max_content_width'] = trim($_POST['max_content_width'] ?? '1200px');
        $formData['spacing_multiplier'] = trim($_POST['spacing_multiplier'] ?? '1');

        if (save_customize_settings($formData)) {
            $settings = $formData;
            audit_log($pdo, 'customize_site', 'Updated site customization settings');
            header('Location: /admin/customize?success=1');
            exit;
        } else {
            $errors[] = 'Failed to save customization settings. Check file permissions.';
        }
    }

    $availableFonts = get_available_fonts();
    $cssOverrides = get_customize_css_overrides($settings);

    if ($preview_mode === 'public') {
        render(__DIR__ . '/../../views/admin/customize_preview_public.php', [
            'siteName' => $config['site']['name'] ?? 'Gallery',
            'settings' => $settings,
            'cssOverrides' => $cssOverrides,
        ]);
    } else {
        render(__DIR__ . '/../../views/admin/customize.php', [
            'siteName' => $config['site']['name'] ?? 'Gallery',
            'settings' => $settings,
            'availableFonts' => $availableFonts,
            'cssOverrides' => $cssOverrides,
            'errors' => $errors,
            'success' => $success,
        ]);
    }
}
