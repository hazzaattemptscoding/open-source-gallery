<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/rate_limit.php';

/**
 * Admin system API — programmatic access to system operations.
 * All endpoints require admin auth. JSON request/response.
 */

function admin_api_health_controller(PDO $pdo, array $config): void {
    enforce_admin($pdo);
    header('Content-Type: application/json');

    $checks = [];

    // Database
    try {
        $pdo->query('SELECT 1');
        $checks['database'] = ['status' => 'ok'];
    } catch (Exception $e) {
        $checks['database'] = ['status' => 'error', 'message' => 'Connection failed'];
    }

    // Storage
    $dirs = [
        'hires' => __DIR__ . '/../../storage/hires',
        'derivatives' => __DIR__ . '/../../storage/derivatives',
        'cache' => __DIR__ . '/../../storage/cache',
        'zips' => __DIR__ . '/../../storage/zips',
    ];

    foreach ($dirs as $name => $path) {
        $checks["storage_$name"] = [
            'status' => (is_dir($path) && is_writable($path)) ? 'ok' : 'error',
            'writable' => is_writable($path),
        ];
    }

    // Jobs
    $stmt = $pdo->query('SELECT status, COUNT(*) as count FROM jobs GROUP BY status');
    $jobs = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $jobs[$row['status']] = (int)$row['count'];
    }
    $checks['jobs'] = $jobs;

    // Cache
    $cacheDir = __DIR__ . '/../../storage/cache';
    if (is_dir($cacheDir)) {
        $files = array_diff(scandir($cacheDir), ['.', '..']);
        $size = 0;
        foreach ($files as $f) {
            $size += filesize("$cacheDir/$f");
        }
        $checks['cache'] = ['files' => count($files), 'size_bytes' => $size];
    }

    echo json_encode(['health' => $checks], JSON_PRETTY_PRINT);
}

function admin_api_jobs_controller(PDO $pdo, array $config): void {
    enforce_admin($pdo);
    header('Content-Type: application/json');

    $input = json_decode((string)file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? 'list';

    match($action) {
        'list' => api_jobs_list($pdo, $input),
        'status' => api_jobs_status($pdo),
        'retry' => api_jobs_retry($pdo, $input),
        'clear' => api_jobs_clear($pdo, $input),
        default => api_error('Unknown action'),
    };
}

function api_jobs_list(PDO $pdo, array $input): void {
    $status = $input['status'] ?? 'pending';
    $limit = min((int)($input['limit'] ?? 50), 500);

    $stmt = $pdo->prepare('
        SELECT id, type, status, attempts, created_at, run_after
        FROM jobs
        WHERE status = ?
        ORDER BY created_at DESC
        LIMIT ?
    ');
    $stmt->execute([$status, $limit]);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['jobs' => $jobs], JSON_PRETTY_PRINT);
}

function api_jobs_status(PDO $pdo): void {
    $stmt = $pdo->query('SELECT status, COUNT(*) as count FROM jobs GROUP BY status');
    $stats = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $stats[$row['status']] = (int)$row['count'];
    }

    echo json_encode(['status' => $stats], JSON_PRETTY_PRINT);
}

function api_jobs_retry(PDO $pdo, array $input): void {
    $jobId = (int)($input['job_id'] ?? 0);

    if ($jobId <= 0) {
        api_error('Invalid job_id');
        return;
    }

    $stmt = $pdo->prepare('UPDATE jobs SET status = "pending", locked_at = NULL WHERE id = ? AND status = "failed"');
    $result = $stmt->execute([$jobId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['ok' => true, 'job_id' => $jobId]);
    } else {
        api_error('Job not found or not in failed state');
    }
}

function api_jobs_clear(PDO $pdo, array $input): void {
    $status = $input['status'] ?? 'failed';

    if (!in_array($status, ['failed', 'completed'], true)) {
        api_error('Invalid status');
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM jobs WHERE status = ?');
    $stmt->execute([$status]);
    $count = $stmt->rowCount();

    echo json_encode(['ok' => true, 'deleted' => $count]);
}

function admin_api_cache_controller(PDO $pdo, array $config): void {
    enforce_admin($pdo);
    header('Content-Type: application/json');

    $input = json_decode((string)file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? 'stats';

    match($action) {
        'stats' => api_cache_stats($pdo),
        'clear' => api_cache_clear($input),
        'warm' => api_cache_warm($pdo),
        default => api_error('Unknown action'),
    };
}

function api_cache_stats(PDO $pdo): void {
    $cacheDir = __DIR__ . '/../../storage/cache';
    $zipDir = __DIR__ . '/../../storage/zips';

    $cacheStats = ['files' => 0, 'size_bytes' => 0];
    if (is_dir($cacheDir)) {
        $files = array_diff(scandir($cacheDir), ['.', '..']);
        $cacheStats['files'] = count($files);
        foreach ($files as $f) {
            $cacheStats['size_bytes'] += filesize("$cacheDir/$f");
        }
    }

    $zipStats = ['files' => 0, 'size_bytes' => 0];
    if (is_dir($zipDir)) {
        $files = array_diff(scandir($zipDir), ['.', '..']);
        $zipStats['files'] = count($files);
        foreach ($files as $f) {
            $zipStats['size_bytes'] += filesize("$zipDir/$f");
        }
    }

    echo json_encode(['cache' => $cacheStats, 'zips' => $zipStats], JSON_PRETTY_PRINT);
}

function api_cache_clear(array $input): void {
    $cacheDir = __DIR__ . '/../../storage/cache';
    $pattern = $input['pattern'] ?? null;

    if (!is_dir($cacheDir)) {
        api_error('Cache directory not found');
        return;
    }

    $files = array_diff(scandir($cacheDir), ['.', '..']);
    $cleared = 0;

    foreach ($files as $f) {
        if ($pattern && strpos($f, $pattern) === false) {
            continue;
        }

        unlink("$cacheDir/$f");
        $cleared++;
    }

    echo json_encode(['ok' => true, 'cleared' => $cleared]);
}

function api_cache_warm(PDO $pdo): void {
    require_once __DIR__ . '/../../lib/search.php';
    require_once __DIR__ . '/../../lib/cache.php';

    $stmt = $pdo->query('SELECT id FROM events WHERE is_published = 1 LIMIT 5');
    $events = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $warmed = 0;
    foreach ($events as $eventId) {
        $results = search_photos($pdo, '', ['event_id' => $eventId], 1, 20);
        $warmed++;
    }

    echo json_encode(['ok' => true, 'warmed' => $warmed]);
}

function admin_api_perf_controller(PDO $pdo, array $config): void {
    enforce_admin($pdo);
    header('Content-Type: application/json');

    $input = json_decode((string)file_get_contents('php://input'), true) ?? [];
    $metric = $input['metric'] ?? 'summary';

    match($metric) {
        'summary' => api_perf_summary($pdo),
        'top-photos' => api_perf_top_photos($pdo),
        'slow-queries' => api_perf_slow_queries($pdo),
        default => api_error('Unknown metric'),
    };
}

function api_perf_summary(PDO $pdo): void {
    $stmt = $pdo->query('SELECT COUNT(*) FROM jobs WHERE status = "pending"');
    $pendingJobs = (int)$stmt->fetchColumn();

    $stmt = $pdo->query('SELECT COUNT(*) FROM jobs WHERE status = "running"');
    $runningJobs = (int)$stmt->fetchColumn();

    $stmt = $pdo->query('SELECT COUNT(*) FROM jobs WHERE status = "failed"');
    $failedJobs = (int)$stmt->fetchColumn();

    $stmt = $pdo->query('SELECT COUNT(*) FROM photos WHERE status = "live"');
    $livePhotos = (int)$stmt->fetchColumn();

    $stmt = $pdo->query('SELECT COUNT(*) FROM orders WHERE status = "paid"');
    $completedOrders = (int)$stmt->fetchColumn();

    $stmt = $pdo->query('SELECT SUM(view_count) FROM photos');
    $totalViews = (int)($stmt->fetchColumn() ?? 0);

    echo json_encode([
        'jobs' => [
            'pending' => $pendingJobs,
            'running' => $runningJobs,
            'failed' => $failedJobs,
        ],
        'content' => [
            'live_photos' => $livePhotos,
            'total_views' => $totalViews,
        ],
        'sales' => [
            'completed_orders' => $completedOrders,
        ],
    ], JSON_PRETTY_PRINT);
}

function api_perf_top_photos(PDO $pdo): void {
    $stmt = $pdo->query('
        SELECT p.id, p.original_filename, p.view_count,
               e.title as event_title, COUNT(o.id) as times_sold
        FROM photos p
        LEFT JOIN events e ON p.event_id = e.id
        LEFT JOIN order_items oi ON p.id = oi.photo_id
        LEFT JOIN orders o ON oi.order_id = o.id AND o.status = "paid"
        WHERE p.status = "live"
        GROUP BY p.id
        ORDER BY p.view_count DESC
        LIMIT 10
    ');
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['photos' => $photos], JSON_PRETTY_PRINT);
}

function api_perf_slow_queries(PDO $pdo): void {
    echo json_encode([
        'note' => 'Enable slow query logging in MySQL config',
        'config' => [
            'slow_query_log' => 1,
            'long_query_time' => 1,
        ],
    ], JSON_PRETTY_PRINT);
}

function admin_api_batch_controller(PDO $pdo, array $config): void {
    enforce_admin($pdo);
    header('Content-Type: application/json');

    $input = json_decode((string)file_get_contents('php://input'), true) ?? [];
    $operation = $input['operation'] ?? null;

    match($operation) {
        'export-orders' => api_batch_export_orders($pdo),
        'reprocess-derivatives' => api_batch_reprocess_derivatives($pdo, $input),
        default => api_error('Unknown operation'),
    };
}

function api_batch_export_orders(PDO $pdo): void {
    $stmt = $pdo->query('
        SELECT o.id, o.email, o.total_pence, o.created_at, COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.status = "paid"
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ');
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'count' => count($orders),
        'orders' => $orders,
    ], JSON_PRETTY_PRINT);
}

function api_batch_reprocess_derivatives(PDO $pdo, array $input): void {
    $limit = min((int)($input['limit'] ?? 100), 1000);

    $stmt = $pdo->prepare("SELECT id FROM photos WHERE status = 'live' LIMIT ?");
    $stmt->execute([$limit]);
    $photoIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $count = 0;
    foreach ($photoIds as $photoId) {
        $stmt = $pdo->prepare('
            INSERT IGNORE INTO jobs (type, payload, status, run_after)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            'derivative',
            json_encode(['photo_id' => $photoId]),
            'pending',
        ]);
        $count++;
    }

    echo json_encode(['ok' => true, 'queued' => $count]);
}

function api_error(string $message): void {
    http_response_code(400);
    echo json_encode(['error' => $message]);
    exit;
}
