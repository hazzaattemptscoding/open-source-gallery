<?php
/**
 * Admin health check: system status dashboard.
 * Monitors database, cron, email, derivatives, and recent errors.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/auth.php';

function admin_health_check_controller(PDO $pdo, array $config): void {
    require_admin();

    $health = [
        'database' => check_database_health($pdo),
        'cron' => check_cron_health($pdo),
        'email' => check_email_health($config),
        'derivatives' => check_derivative_health($pdo),
        'storage' => check_storage_health(),
        'recent_errors' => get_recent_errors($pdo),
        'stats' => get_quick_stats($pdo),
    ];

    render(__DIR__ . '/../../views/admin/health.php', [
        'health' => $health,
        'siteName' => $config['site']['name'] ?? 'Gallery',
    ]);
}

/**
 * Check database connectivity and table status.
 */
function check_database_health(PDO $pdo): array {
    try {
        $stmt = $pdo->query('SELECT COUNT(*) FROM orders');
        $orders = $stmt->fetchColumn();

        $stmt = $pdo->query('SELECT COUNT(*) FROM photos');
        $photos = $stmt->fetchColumn();

        $stmt = $pdo->query('SELECT COUNT(*) FROM admin_users');
        $admins = $stmt->fetchColumn();

        return [
            'status' => 'ok',
            'message' => 'Database connected',
            'stats' => [
                'orders' => $orders,
                'photos' => $photos,
                'admins' => $admins,
            ],
        ];
    } catch (Throwable $e) {
        return [
            'status' => 'error',
            'message' => 'Database connection failed: ' . $e->getMessage(),
            'stats' => [],
        ];
    }
}

/**
 * Check cron job execution status.
 */
function check_cron_health(PDO $pdo): array {
    try {
        $stmt = $pdo->query(<<<'SQL'
            SELECT COUNT(*) as count, status
            FROM jobs
            GROUP BY status
        SQL);

        $jobCounts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $jobCounts[$row['status']] = (int)$row['count'];
        }

        $pending = $jobCounts['pending'] ?? 0;
        $failed = $jobCounts['failed'] ?? 0;

        if ($failed > 0) {
            return [
                'status' => 'warning',
                'message' => "Cron running but $failed jobs failed",
                'stats' => $jobCounts,
            ];
        } elseif ($pending > 5) {
            return [
                'status' => 'warning',
                'message' => "$pending jobs pending (cron may be slow)",
                'stats' => $jobCounts,
            ];
        } else {
            return [
                'status' => 'ok',
                'message' => 'Cron working normally',
                'stats' => $jobCounts,
            ];
        }
    } catch (Throwable $e) {
        return [
            'status' => 'error',
            'message' => 'Could not query jobs: ' . $e->getMessage(),
            'stats' => [],
        ];
    }
}

/**
 * Check email configuration status.
 */
function check_email_health(array $config): array {
    $smtpHost = $config['smtp']['host'] ?? '';
    $fromEmail = $config['smtp']['from_email'] ?? '';

    if (empty($fromEmail)) {
        return [
            'status' => 'warning',
            'message' => 'Email not configured (from_email empty)',
            'method' => 'Not configured',
        ];
    }

    if (!empty($smtpHost)) {
        return [
            'status' => 'ok',
            'message' => 'SMTP configured',
            'method' => "SMTP: {$smtpHost}:{$config['smtp']['port']}",
        ];
    } else {
        return [
            'status' => 'ok',
            'message' => 'Using mail() function',
            'method' => 'mail() (shared hosting)',
        ];
    }
}

/**
 * Check derivative generation status.
 */
function check_derivative_health(PDO $pdo): array {
    try {
        $stmt = $pdo->query(<<<'SQL'
            SELECT
                COUNT(*) as pending
            FROM jobs
            WHERE type = 'derivative' AND status = 'pending'
        SQL);

        $pending = (int)$stmt->fetchColumn();

        if ($pending > 100) {
            return [
                'status' => 'warning',
                'message' => "$pending derivatives pending (queue backing up)",
                'pending' => $pending,
            ];
        } elseif ($pending > 0) {
            return [
                'status' => 'ok',
                'message' => "$pending derivatives generating",
                'pending' => $pending,
            ];
        } else {
            return [
                'status' => 'ok',
                'message' => 'No pending derivatives',
                'pending' => 0,
            ];
        }
    } catch (Throwable $e) {
        return [
            'status' => 'error',
            'message' => 'Could not check derivatives: ' . $e->getMessage(),
            'pending' => 0,
        ];
    }
}

/**
 * Check disk space and storage quota.
 */
function check_storage_health(): array {
    try {
        $storageDir = __DIR__ . '/../../storage';
        $hiresDir = $storageDir . '/hires';
        $mediaDir = __DIR__ . '/../../public/media/d';

        $hiresSize = get_directory_size($hiresDir);
        $mediaSize = get_directory_size($mediaDir);
        $totalSize = $hiresSize + $mediaSize;

        $diskSpace = disk_free_space($storageDir);
        $status = $totalSize > 50 * 1024 * 1024 * 1024 ? 'warning' : 'ok';

        if ($diskSpace === false || $diskSpace < 1024 * 1024 * 1024) {
            $status = 'error';
        }

        return [
            'status' => $status,
            'message' => 'Storage OK',
            'hires_size' => format_bytes($hiresSize),
            'derivatives_size' => format_bytes($mediaSize),
            'total_size' => format_bytes($totalSize),
            'free_space' => $diskSpace !== false ? format_bytes($diskSpace) : 'Unknown',
        ];
    } catch (Throwable $e) {
        return [
            'status' => 'warning',
            'message' => 'Could not check storage',
            'hires_size' => '?',
            'derivatives_size' => '?',
            'total_size' => '?',
            'free_space' => '?',
        ];
    }
}

/**
 * Get recent errors from audit log.
 */
function get_recent_errors(PDO $pdo): array {
    try {
        $stmt = $pdo->query(<<<'SQL'
            SELECT action, details, created_at
            FROM audit_log
            WHERE action LIKE '%failed%' OR action LIKE '%error%'
            ORDER BY created_at DESC
            LIMIT 5
        SQL);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Get quick statistics for dashboard.
 */
function get_quick_stats(PDO $pdo): array {
    try {
        $stats = [];

        // Recent orders (last 24 hours)
        $stmt = $pdo->query(<<<'SQL'
            SELECT COUNT(*) as count
            FROM orders
            WHERE status = 'completed' AND created_at > DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 DAY)
        SQL);
        $stats['orders_24h'] = (int)$stmt->fetchColumn();

        // Recently uploaded photos
        $stmt = $pdo->query(<<<'SQL'
            SELECT COUNT(*) as count
            FROM photos
            WHERE created_at > DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 DAY)
        SQL);
        $stats['photos_24h'] = (int)$stmt->fetchColumn();

        return $stats;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Get total size of directory recursively.
 */
function get_directory_size(string $dir): int {
    $size = 0;
    try {
        if (!is_dir($dir)) return 0;

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
    } catch (Throwable $e) {
        // Silently ignore permission errors
    }

    return $size;
}

/**
 * Format bytes to human-readable size.
 */
function format_bytes(int|float $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = (float)$bytes;

    foreach ($units as $unit) {
        if ($bytes < 1024.0) {
            return round($bytes, 1) . ' ' . $unit;
        }
        $bytes /= 1024.0;
    }

    return round($bytes, 1) . ' PB';
}
