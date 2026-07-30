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
require_once __DIR__ . '/../../lib/csrf.php';

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
        // Validate CSRF token (reusable, not one-time-use, for multi-action form)
        if (!csrf_verify_reusable($_POST['csrf_token'] ?? null)) {
            $errors[] = 'Invalid security token. Please try again.';
        } elseif (isset($_POST['action']) && $_POST['action'] === 'reset') {
            // Handle reset action
            if (reset_customize_settings()) {
                audit_log($pdo, 'admin', 'customize_reset', 'settings', null, [], client_ip());
                header('Location: /admin/customize?success=1');
                exit;
            } else {
                $errors[] = 'Failed to reset customization settings.';
            }
        } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_logo') {
            // Handle logo deletion
            if (delete_logo($settings['site_logo_filename'] ?? '')) {
                $settings['site_logo_filename'] = '';
                save_customize_settings($settings);
                audit_log($pdo, 'admin', 'customize_delete_logo', 'settings', null, [], client_ip());
                touch(CUSTOMIZE_CONFIG_FILE);
                header('Location: /admin/customize?success=1');
                exit;
            } else {
                $errors[] = 'Failed to delete logo.';
            }
        } else {
            $formData = [];
            $formData['site_name'] = trim($_POST['site_name'] ?? '');

            // Validate and sanitize colors (hex format)
            $colorFields = ['text', 'text_muted', 'bg', 'bg_alt', 'border'];
            foreach ($colorFields as $field) {
                $value = trim($_POST[$field] ?? '');
                if ($value && !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                    $errors[] = "Invalid color format for $field. Use hex codes like #111111";
                }
                $formData[$field] = $value;
            }

            $formData['body_font'] = trim($_POST['body_font'] ?? 'Geist Sans');
            $formData['heading_font'] = trim($_POST['heading_font'] ?? 'Newsreader');
            $formData['mono_font'] = trim($_POST['mono_font'] ?? 'Geist Mono');
            $formData['heading_letter_spacing'] = trim($_POST['heading_letter_spacing'] ?? '-0.02em');
            $formData['max_content_width'] = trim($_POST['max_content_width'] ?? '1200px');
            $formData['spacing_multiplier'] = trim($_POST['spacing_multiplier'] ?? '1');
            $formData['grid_columns'] = (int)($_POST['grid_columns'] ?? 3);

            // Handle logo upload
            if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
                    $filename = upload_customize_photo($_FILES['site_logo'], 'logo');
                    if ($filename) {
                        $formData['site_logo_filename'] = $filename;
                        audit_log($pdo, 'admin', 'customize_upload_logo', 'settings', null, ['filename' => $filename], client_ip());
                    } else {
                        $errors[] = 'Failed to upload logo. Ensure it is a valid image (JPEG, PNG, WebP) under 5MB.';
                    }
                } else {
                    $errors[] = 'File upload error: ' . $_FILES['site_logo']['error'];
                }
            } else {
                // Preserve existing logo if not uploading new one
                $formData['site_logo_filename'] = $settings['site_logo_filename'] ?? '';
            }

            if (empty($errors) && save_customize_settings($formData)) {
                $settings = $formData;
                audit_log($pdo, 'admin', 'customize_site', 'settings', null, [], client_ip());
                // Touch file to invalidate CSS cache
                touch(CUSTOMIZE_CONFIG_FILE);
                header('Location: /admin/customize?success=1');
                exit;
            } elseif (empty($errors)) {
                $errors[] = 'Failed to save customization settings. Check file permissions.';
            }
        }
    }

    $availableFonts = get_available_fonts();
    $cssOverrides = get_customize_css_overrides($settings);

    $csrfToken = csrf_token();

    if ($preview_mode === 'public') {
        // Load sample events for preview
        $sampleEvents = [];
        try {
            $stmt = $pdo->query("SELECT id, title, slug, event_date FROM events ORDER BY event_date DESC LIMIT 6");
            $sampleEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Silently fail, preview will show empty state
        }

        render(__DIR__ . '/../../views/admin/customize_preview_public.php', [
            'pageTitle' => 'Customization Preview',
            'currentPage' => 'customize',
            'siteName' => $config['site']['name'] ?? 'Gallery',
            'settings' => $settings,
            'cssOverrides' => $cssOverrides,
            'sampleEvents' => $sampleEvents,
        ]);
    } else {
        render(__DIR__ . '/../../views/admin/customize.php', [
            'pageTitle' => 'Customization',
            'currentPage' => 'customize',
            'siteName' => $config['site']['name'] ?? 'Gallery',
            'settings' => $settings,
            'availableFonts' => $availableFonts,
            'cssOverrides' => $cssOverrides,
            'errors' => $errors,
            'success' => $success,
            'csrfToken' => $csrfToken,
        ]);
    }
}
