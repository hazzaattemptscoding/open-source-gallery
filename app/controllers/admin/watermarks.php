<?php
/**
 * Admin: Watermark customization.
 * Control watermark position, opacity, style across photo sizes.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/audit.php';
require_once __DIR__ . '/../../lib/csrf.php';

function admin_watermarks_controller(PDO $pdo, array $config): void {
    require_admin();
    if (!can_manage_events($pdo)) {
        http_response_code(403);
        echo '403 Forbidden';
        exit;
    }

    $errors = [];
    $success = $_GET['success'] ?? false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo 'CSRF verification failed.';
            return;
        }

        $action = $_POST['action'] ?? 'update';

        try {
            if ($action === 'save_preset') {
                // Save current settings as a preset
                $presetName = trim($_POST['preset_name'] ?? '');
                if (!$presetName) {
                    $errors[] = 'Preset name is required.';
                } else {
                    $preset = [
                        'name' => $presetName,
                        'position' => $_POST['position'] ?? 'bottom_right',
                        'opacity' => (float)($_POST['opacity'] ?? 0.8),
                        'text' => $_POST['text'] ?? '',
                        'enabled' => isset($_POST['enabled']) ? 1 : 0,
                        'apply_to_sizes' => $_POST['apply_to_sizes'] ?? 'sm,md,lg',
                    ];

                    $stmt = $pdo->prepare('SELECT presets FROM watermark_settings WHERE id = 1');
                    $stmt->execute();
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $presets = $row ? (json_decode($row['presets'] ?? '[]', true) ?: []) : [];

                    // Replace if preset exists, otherwise add
                    $presets = array_filter($presets, fn($p) => $p['name'] !== $presetName);
                    $presets[] = $preset;

                    $stmt = $pdo->prepare('UPDATE watermark_settings SET presets = ? WHERE id = 1');
                    $stmt->execute([json_encode($presets)]);
                    audit_log($pdo, 'admin', 'watermark_preset_save', 'presets', null, ['name' => $presetName], client_ip());
                    $success = 'preset_saved';
                }
            } elseif ($action === 'load_preset') {
                $presetName = $_POST['preset_name'] ?? '';
                $stmt = $pdo->prepare('SELECT presets FROM watermark_settings WHERE id = 1');
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $presets = $row ? (json_decode($row['presets'] ?? '[]', true) ?: []) : [];
                // array_values before [0]: array_filter preserves the original
                // keys, so indexing [0] found a preset only when it happened to
                // be the first one saved. Every other preset silently failed to
                // load and the page just redirected as though it had worked.
                $matches = array_values(array_filter($presets, fn($p) => $p['name'] === $presetName));
                $preset = $matches[0] ?? null;

                if (!$preset) {
                    $errors[] = 'That preset no longer exists.';
                }

                if ($preset) {
                    $stmt = $pdo->prepare(<<<'SQL'
                        UPDATE watermark_settings
                        SET position = ?, opacity = ?, text = ?, enabled = ?, apply_to_sizes = ?
                        WHERE id = 1
                    SQL);
                    $stmt->execute([$preset['position'], $preset['opacity'], $preset['text'], $preset['enabled'], $preset['apply_to_sizes']]);
                    audit_log($pdo, 'admin', 'watermark_preset_load', 'presets', null, ['name' => $presetName], client_ip());
                    $success = 'preset_loaded';
                }
            } elseif ($action === 'delete_preset') {
                $presetName = $_POST['preset_name'] ?? '';
                $stmt = $pdo->prepare('SELECT presets FROM watermark_settings WHERE id = 1');
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $presets = $row ? (json_decode($row['presets'] ?? '[]', true) ?: []) : [];
                $presets = array_filter($presets, fn($p) => $p['name'] !== $presetName);

                $stmt = $pdo->prepare('UPDATE watermark_settings SET presets = ? WHERE id = 1');
                $stmt->execute([json_encode(array_values($presets))]);
                audit_log($pdo, 'admin', 'watermark_preset_delete', 'presets', null, ['name' => $presetName], client_ip());
                $success = 'preset_deleted';
            } else {
                // Update current settings
                $stmt = $pdo->prepare(<<<'SQL'
                    UPDATE watermark_settings
                    SET position = ?, opacity = ?, text = ?, enabled = ?, apply_to_sizes = ?
                    WHERE id = 1
                SQL);

                $stmt->execute([
                    $_POST['position'] ?? 'bottom_right',
                    (float)($_POST['opacity'] ?? 0.8),
                    $_POST['text'] ?? '',
                    isset($_POST['enabled']) ? 1 : 0,
                    $_POST['apply_to_sizes'] ?? 'sm,md,lg',
                ]);
                audit_log($pdo, 'admin', 'update_watermark', 'settings', null, [], client_ip());
                $success = 'settings_updated';
            }

            // Only redirect on success. Errors collected above (an unknown
            // preset, a blank name) have to survive to the render below, and a
            // redirect to ?success= would throw them away.
            if (!$errors) {
                header('Location: /admin/watermarks?success=' . urlencode((string)$success));
                exit;
            }
        } catch (Throwable $e) {
            error_log('watermarks: ' . $e->getMessage());
            $errors[] = 'Failed to update watermark settings';
        }
    }

    try {
        $stmt = $pdo->query('SELECT id, position, opacity, text, enabled, apply_to_sizes, updated_at, presets FROM watermark_settings LIMIT 1');
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        $presets = $settings ? (json_decode($settings['presets'] ?? '[]', true) ?: []) : [];
    } catch (Throwable $e) {
        // This catch hid a missing `presets` column for the whole life of the
        // presets feature: the query threw, settings came back null, and the
        // page rendered as though there were simply no presets yet. Log it, and
        // tell the admin the page is degraded rather than quietly lying.
        error_log('watermarks: settings query failed: ' . $e->getMessage());
        $errors[] = 'Could not load watermark settings. Check that migrations are up to date on the Migrations page.';
        $settings = null;
        $presets = [];
    }

    render(__DIR__ . '/../../views/admin/watermarks.php', [
        'pageTitle' => 'Watermarks',
        'currentPage' => 'watermarks',
        'siteName' => $config['site']['name'] ?? 'Gallery',
        'csrfToken' => csrf_token(),
        'settings' => $settings,
        'presets' => $presets,
        'errors' => $errors,
        'success' => $success,
    ]);
}
