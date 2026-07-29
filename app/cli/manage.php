#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Gallery management CLI tool.
 * Provides visibility and control over performance, caching, jobs, and system health.
 *
 * Usage: php app/cli/manage.php <command> [options]
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/cli_helpers.php';

$command = $argv[1] ?? 'help';
$args = array_slice($argv, 2);

$pdo = get_db();

$commands = [
    'perf' => 'Performance monitoring (slow queries, cache stats)',
    'cache' => 'Cache management (warm, invalidate, stats)',
    'jobs' => 'Job queue management (view, retry, clear)',
    'db' => 'Database optimization (analyze, optimize tables)',
    'batch' => 'Batch operations (export, import, bulk)',
    'health' => 'System health checks',
    'help' => 'Show this help message',
];

match($command) {
    'perf' => handle_perf($pdo, $args),
    'cache' => handle_cache($pdo, $args),
    'jobs' => handle_jobs($pdo, $args),
    'db' => handle_db($pdo, $args),
    'batch' => handle_batch($pdo, $args),
    'health' => handle_health($pdo, $args),
    'help' => show_help($commands),
    default => error("Unknown command: $command"),
};

function handle_perf(PDO $pdo, array $args): void {
    $subcommand = $args[0] ?? 'stats';

    match($subcommand) {
        'stats' => perf_stats($pdo),
        'slow-queries' => perf_slow_queries($pdo),
        'cache-stats' => perf_cache_stats($pdo),
        'top-photos' => perf_top_photos($pdo),
        default => error("Unknown perf subcommand: $subcommand"),
    };
}

function perf_stats(PDO $pdo): void {
    echo "╔═══════════════════════════════════════════════════════════╗\n";
    echo "║              PERFORMANCE SUMMARY                          ║\n";
    echo "╚═══════════════════════════════════════════════════════════╝\n\n";

    $stmt = $pdo->query('SELECT COUNT(*) FROM jobs WHERE status = "pending"');
    $pendingJobs = $stmt->fetchColumn();

    $stmt = $pdo->query('SELECT COUNT(*) FROM jobs WHERE status = "running"');
    $runningJobs = $stmt->fetchColumn();

    $stmt = $pdo->query('SELECT COUNT(*) FROM jobs WHERE status = "failed"');
    $failedJobs = $stmt->fetchColumn();

    $stmt = $pdo->query('SELECT COUNT(*) FROM photos WHERE status = "live"');
    $livePhotos = $stmt->fetchColumn();

    $stmt = $pdo->query('SELECT COUNT(*) FROM orders WHERE status = "completed"');
    $completedOrders = $stmt->fetchColumn();

    $stmt = $pdo->query('SELECT SUM(view_count) FROM photos');
    $totalViews = $stmt->fetchColumn() ?? 0;

    echo "📊 Job Queue:\n";
    echo "   Pending:  " . number_format($pendingJobs) . "\n";
    echo "   Running:  " . number_format($runningJobs) . "\n";
    echo "   Failed:   " . number_format($failedJobs) . "\n\n";

    echo "📸 Content:\n";
    echo "   Live photos: " . number_format($livePhotos) . "\n";
    echo "   Total views: " . number_format($totalViews) . "\n\n";

    echo "💰 Sales:\n";
    echo "   Completed orders: " . number_format($completedOrders) . "\n\n";

    perf_cache_stats($pdo);
}

function perf_slow_queries(PDO $pdo): void {
    echo "╔═══════════════════════════════════════════════════════════╗\n";
    echo "║              SLOW QUERY ANALYSIS                          ║\n";
    echo "╚═══════════════════════════════════════════════════════════╝\n\n";

    echo "⚠️  Enable slow query logging in MySQL config:\n";
    echo "   slow_query_log = 1\n";
    echo "   long_query_time = 1\n";
    echo "   slow_query_log_file = /var/log/mysql/slow.log\n\n";

    echo "Common slow queries to watch for:\n";
    echo "   • COUNT(DISTINCT) in search (use FOUND_ROWS instead)\n";
    echo "   • JOINs without proper indexes\n";
    echo "   • Derivative generation with missing indexes\n";
    echo "   • Bulk operations without multi-row INSERT\n\n";

    echo "Run: SHOW PROCESSLIST; to see currently running queries.\n";
}

function perf_cache_stats(PDO $pdo): void {
    $cacheDir = __DIR__ . '/../../storage/cache';
    $zipDir = __DIR__ . '/../../storage/zips';

    echo "💾 Cache Status:\n";

    if (is_dir($cacheDir)) {
        $files = scandir($cacheDir);
        $count = count($files) - 2; // exclude . and ..
        $size = 0;
        foreach ($files as $f) {
            if ($f !== '.' && $f !== '..') {
                $size += filesize("$cacheDir/$f");
            }
        }
        echo "   Cache files:    " . number_format($count) . " files\n";
        echo "   Cache size:     " . format_bytes($size) . "\n";
    } else {
        echo "   Cache directory not found\n";
    }

    if (is_dir($zipDir)) {
        $files = scandir($zipDir);
        $count = count($files) - 2;
        $size = 0;
        foreach ($files as $f) {
            if ($f !== '.' && $f !== '..') {
                $size += filesize("$zipDir/$f");
            }
        }
        echo "   Pre-built ZIPs:  " . number_format($count) . " files\n";
        echo "   ZIP cache size:  " . format_bytes($size) . "\n";
    } else {
        echo "   ZIP cache directory not found\n";
    }

    echo "\n";
}

function perf_top_photos(PDO $pdo): void {
    echo "╔═══════════════════════════════════════════════════════════╗\n";
    echo "║              TOP 10 PHOTOS BY VIEWS                       ║\n";
    echo "╚═══════════════════════════════════════════════════════════╝\n\n";

    $stmt = $pdo->query('
        SELECT p.id, p.original_filename, p.view_count,
               e.title as event_title, COUNT(o.id) as times_sold
        FROM photos p
        LEFT JOIN events e ON p.event_id = e.id
        LEFT JOIN order_items oi ON p.id = oi.photo_id
        LEFT JOIN orders o ON oi.order_id = o.id AND o.status = "completed"
        WHERE p.status = "live"
        GROUP BY p.id
        ORDER BY p.view_count DESC
        LIMIT 10
    ');
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($photos)) {
        echo "No photos found.\n";
        return;
    }

    echo sprintf("%-4s %-40s %-15s %-15s %-8s\n", 'ID', 'Filename', 'Views', 'Event', 'Sold');
    echo str_repeat("─", 82) . "\n";

    foreach ($photos as $p) {
        echo sprintf(
            "%-4d %-40s %-15s %-15s %-8d\n",
            $p['id'],
            substr($p['original_filename'], 0, 40),
            number_format($p['view_count']),
            substr($p['event_title'] ?? 'N/A', 0, 15),
            $p['times_sold'] ?? 0
        );
    }
    echo "\n";
}

function handle_cache(PDO $pdo, array $args): void {
    $subcommand = $args[0] ?? 'stats';

    match($subcommand) {
        'stats' => perf_cache_stats($pdo),
        'warm' => cache_warm($pdo),
        'clear' => cache_clear($args[1] ?? null),
        'invalidate' => cache_invalidate($pdo, $args[1] ?? null),
        default => error("Unknown cache subcommand: $subcommand"),
    };
}

function cache_warm(PDO $pdo): void {
    echo "🔥 Warming cache...\n";

    require_once __DIR__ . '/../lib/search.php';
    require_once __DIR__ . '/../lib/cache.php';

    $stmt = $pdo->query('SELECT id FROM events WHERE is_published = 1 LIMIT 5');
    $events = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($events as $eventId) {
        $filters = [];
        $results = search_photos($pdo, '', ['event_id' => $eventId], 1, 20);
        echo "  ✓ Warmed event $eventId\n";
    }

    echo "✅ Cache warming complete\n";
}

function cache_clear(?string $pattern): void {
    $cacheDir = __DIR__ . '/../../storage/cache';

    if (!is_dir($cacheDir)) {
        error("Cache directory not found");
    }

    $files = scandir($cacheDir);
    $cleared = 0;

    foreach ($files as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }

        if ($pattern && strpos($f, $pattern) === false) {
            continue;
        }

        unlink("$cacheDir/$f");
        $cleared++;
    }

    echo "🗑️  Cleared $cleared cache file(s)\n";
}

function cache_invalidate(PDO $pdo, ?string $eventId): void {
    require_once __DIR__ . '/../lib/cache.php';

    if ($eventId) {
        cache_delete("search_facets_all");
        echo "✅ Invalidated search facets\n";
    } else {
        cache_delete("search_facets_all");
        echo "✅ Invalidated all caches\n";
    }
}

function handle_jobs(PDO $pdo, array $args): void {
    $subcommand = $args[0] ?? 'status';

    match($subcommand) {
        'status' => jobs_status($pdo),
        'pending' => jobs_list($pdo, 'pending'),
        'failed' => jobs_list($pdo, 'failed'),
        'retry' => jobs_retry($pdo, $args[1] ?? null),
        'clear-failed' => jobs_clear_failed($pdo),
        default => error("Unknown jobs subcommand: $subcommand"),
    };
}

function jobs_status(PDO $pdo): void {
    echo "╔═══════════════════════════════════════════════════════════╗\n";
    echo "║              JOB QUEUE STATUS                             ║\n";
    echo "╚═══════════════════════════════════════════════════════════╝\n\n";

    $stmt = $pdo->query('SELECT status, COUNT(*) as count FROM jobs GROUP BY status');
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($stats as $stat) {
        $icon = match($stat['status']) {
            'pending' => '⏳',
            'running' => '🔄',
            'completed' => '✅',
            'failed' => '❌',
            default => '•',
        };

        echo "$icon {$stat['status']}: " . number_format($stat['count']) . "\n";
    }

    echo "\n";

    $stmt = $pdo->query('SELECT type, COUNT(*) as count FROM jobs WHERE status != "completed" GROUP BY type');
    $byType = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($byType)) {
        echo "By type:\n";
        foreach ($byType as $t) {
            echo "  • {$t['type']}: " . number_format($t['count']) . "\n";
        }
    }

    echo "\n";
}

function jobs_list(PDO $pdo, string $status): void {
    echo "╔═══════════════════════════════════════════════════════════╗\n";
    echo "║              JOBS - " . strtoupper($status) . "\n";
    echo "╚═══════════════════════════════════════════════════════════╝\n\n";

    $stmt = $pdo->query("
        SELECT id, type, status, attempts, created_at, run_after
        FROM jobs
        WHERE status = '$status'
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($jobs)) {
        echo "No $status jobs found.\n";
        return;
    }

    echo sprintf("%-8s %-15s %-10s %-10s %-20s\n", 'ID', 'Type', 'Attempts', 'Status', 'Created');
    echo str_repeat("─", 63) . "\n";

    foreach ($jobs as $j) {
        echo sprintf(
            "%-8d %-15s %-10d %-10s %-20s\n",
            $j['id'],
            $j['type'],
            $j['attempts'],
            $j['status'],
            substr($j['created_at'], 0, 19)
        );
    }

    echo "\n";
}

function jobs_retry(PDO $pdo, ?string $jobId): void {
    if (!$jobId || !is_numeric($jobId)) {
        error("Usage: manage.php jobs retry <job_id>");
    }

    $stmt = $pdo->prepare('UPDATE jobs SET status = "pending", locked_at = NULL WHERE id = ? AND status = "failed"');
    $result = $stmt->execute([$jobId]);

    if ($stmt->rowCount() > 0) {
        echo "✅ Retrying job $jobId\n";
    } else {
        error("Job $jobId not found or not in failed state");
    }
}

function jobs_clear_failed(PDO $pdo): void {
    $stmt = $pdo->query('SELECT COUNT(*) FROM jobs WHERE status = "failed"');
    $count = $stmt->fetchColumn();

    if ($count === 0) {
        echo "No failed jobs to clear.\n";
        return;
    }

    $pdo->query('DELETE FROM jobs WHERE status = "failed"');
    echo "🗑️  Deleted $count failed job(s)\n";
}

function handle_db(PDO $pdo, array $args): void {
    $subcommand = $args[0] ?? 'analyze';

    match($subcommand) {
        'analyze' => db_analyze($pdo),
        'optimize' => db_optimize($pdo),
        'indexes' => db_show_indexes($pdo),
        'stats' => db_stats($pdo),
        default => error("Unknown db subcommand: $subcommand"),
    };
}

function db_analyze(PDO $pdo): void {
    echo "╔═══════════════════════════════════════════════════════════╗\n";
    echo "║              DATABASE ANALYSIS                            ║\n";
    echo "╚═══════════════════════════════════════════════════════════╝\n\n";

    $stmt = $pdo->query("SELECT table_name, table_rows, data_free FROM information_schema.TABLES WHERE table_schema = DATABASE()");
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Table fragmentation:\n";
    foreach ($tables as $t) {
        if ($t['data_free'] > 0) {
            echo "  ⚠️  {$t['table_name']}: " . format_bytes($t['data_free']) . " wasted space\n";
        }
    }

    echo "\nRun: manage.php db optimize\n\n";

    echo "Key indexes:\n";
    $stmt = $pdo->query('SHOW INDEX FROM photos');
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "  photos: " . count($indexes) . " indexes\n";

    $stmt = $pdo->query('SHOW INDEX FROM events');
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "  events: " . count($indexes) . " indexes\n";

    echo "\n";
}

function db_optimize(PDO $pdo): void {
    echo "🔧 Optimizing tables...\n";

    $tables = ['photos', 'events', 'orders', 'jobs', 'photo_tags', 'sessions'];

    foreach ($tables as $table) {
        $pdo->query("OPTIMIZE TABLE $table");
        echo "  ✓ Optimized $table\n";
    }

    echo "\n✅ Optimization complete\n";
}

function db_show_indexes(PDO $pdo): void {
    echo "╔═══════════════════════════════════════════════════════════╗\n";
    echo "║              DATABASE INDEXES                             ║\n";
    echo "╚═══════════════════════════════════════════════════════════╝\n\n";

    $stmt = $pdo->query("SELECT table_name, index_name, column_name FROM information_schema.STATISTICS WHERE table_schema = DATABASE() ORDER BY table_name, index_name");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $currentTable = null;
    foreach ($indexes as $idx) {
        if ($idx['table_name'] !== $currentTable) {
            if ($currentTable !== null) {
                echo "\n";
            }
            $currentTable = $idx['table_name'];
            echo "📑 {$currentTable}:\n";
        }
        echo "  • {$idx['index_name']}: {$idx['column_name']}\n";
    }

    echo "\n";
}

function db_stats(PDO $pdo): void {
    echo "📊 Database Statistics:\n\n";

    $tables = [
        'photos' => 'Live photos',
        'events' => 'Published events',
        'orders' => 'Total orders',
        'jobs' => 'Queued jobs',
    ];

    foreach ($tables as $table => $label) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "  $label: " . number_format($count) . "\n";
    }

    echo "\n";
}

function handle_batch(PDO $pdo, array $args): void {
    $subcommand = $args[0] ?? 'help';

    match($subcommand) {
        'export-orders' => batch_export_orders($pdo, $args[1] ?? null),
        'export-photos' => batch_export_photos($pdo),
        'reprocess-derivatives' => batch_reprocess_derivatives($pdo),
        'help' => batch_help(),
        default => error("Unknown batch subcommand: $subcommand"),
    };
}

function batch_export_orders(PDO $pdo, ?string $filename): void {
    $filename = $filename ?? 'orders_' . date('Y-m-d_Hi') . '.csv';

    $stmt = $pdo->query('
        SELECT o.id, o.customer_email, o.total_pence, o.created_at, COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.status = "completed"
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ');
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $handle = fopen($filename, 'w');
    fputcsv($handle, ['Order ID', 'Email', 'Amount', 'Items', 'Date']);

    foreach ($orders as $o) {
        fputcsv($handle, [
            $o['id'],
            $o['customer_email'],
            number_format($o['total_pence'] / 100, 2),
            $o['item_count'],
            $o['created_at'],
        ]);
    }

    fclose($handle);
    echo "✅ Exported " . count($orders) . " orders to $filename\n";
}

function batch_export_photos(PDO $pdo): void {
    $filename = 'photos_' . date('Y-m-d_Hi') . '.csv';

    $stmt = $pdo->query('
        SELECT p.id, p.original_filename, e.title as event, p.view_count,
               COUNT(oi.id) as times_sold, p.status, p.created_at
        FROM photos p
        LEFT JOIN events e ON p.event_id = e.id
        LEFT JOIN order_items oi ON p.id = oi.photo_id
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ');
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $handle = fopen($filename, 'w');
    fputcsv($handle, ['ID', 'Filename', 'Event', 'Views', 'Sold', 'Status', 'Created']);

    foreach ($photos as $p) {
        fputcsv($handle, [
            $p['id'],
            $p['original_filename'],
            $p['event'] ?? 'N/A',
            $p['view_count'],
            $p['times_sold'],
            $p['status'],
            $p['created_at'],
        ]);
    }

    fclose($handle);
    echo "✅ Exported " . count($photos) . " photos to $filename\n";
}

function batch_reprocess_derivatives(PDO $pdo): void {
    require_once __DIR__ . '/../lib/derivatives.php';

    echo "🔄 Queueing derivative reprocessing...\n";

    $stmt = $pdo->query('SELECT id FROM photos WHERE status = "live" LIMIT 100');
    $photoIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $count = 0;
    foreach ($photoIds as $photoId) {
        $stmt = $pdo->prepare('
            INSERT IGNORE INTO jobs (type, payload, status, run_after)
            VALUES (?, ?, ?, NOW())
        ');
        $stmt->execute([
            'derivative',
            json_encode(['photo_id' => $photoId]),
            'pending',
        ]);
        $count++;
    }

    echo "✅ Queued $count derivative jobs\n";
}

function batch_help(): void {
    echo "Batch operations:\n";
    echo "  export-orders [filename]  - Export orders to CSV\n";
    echo "  export-photos             - Export photos to CSV\n";
    echo "  reprocess-derivatives     - Queue derivative regeneration\n";
}

function handle_health(PDO $pdo, array $args): void {
    echo "╔═══════════════════════════════════════════════════════════╗\n";
    echo "║              SYSTEM HEALTH CHECK                          ║\n";
    echo "╚═══════════════════════════════════════════════════════════╝\n\n";

    $checks = [];

    // Database
    try {
        $pdo->query('SELECT 1');
        $checks[] = ['✅ Database', 'Connected'];
    } catch (Exception $e) {
        $checks[] = ['❌ Database', 'Connection failed'];
    }

    // Storage directories
    $dirs = [
        __DIR__ . '/../../storage/hires' => 'High-res storage',
        __DIR__ . '/../../storage/derivatives' => 'Derivatives',
        __DIR__ . '/../../storage/cache' => 'Cache',
        __DIR__ . '/../../storage/zips' => 'ZIP cache',
    ];

    foreach ($dirs as $path => $label) {
        if (is_dir($path) && is_writable($path)) {
            $checks[] = ["✅ $label", 'Writable'];
        } else {
            $checks[] = ["⚠️  $label", 'Not writable or missing'];
        }
    }

    // Job queue
    $stmt = $pdo->query('SELECT COUNT(*) FROM jobs WHERE status = "failed"');
    $failed = $stmt->fetchColumn();
    $checks[] = [$failed > 0 ? '⚠️  Failed jobs' : '✅ Failed jobs', number_format($failed)];

    // Pending jobs
    $stmt = $pdo->query('SELECT COUNT(*) FROM jobs WHERE status = "pending" AND locked_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)');
    $stuck = $stmt->fetchColumn();
    $checks[] = [$stuck > 0 ? '⚠️  Stuck jobs' : '✅ Stuck jobs', number_format($stuck)];

    // Display checks
    foreach ($checks as [$name, $status]) {
        echo sprintf("%-30s %s\n", $name, $status);
    }

    echo "\n";
}

function show_help(array $commands): void {
    echo "╔═══════════════════════════════════════════════════════════╗\n";
    echo "║        Gallery Management CLI Tool                        ║\n";
    echo "╚═══════════════════════════════════════════════════════════╝\n\n";

    echo "Usage: php app/cli/manage.php <command> [subcommand] [options]\n\n";

    echo "Commands:\n";
    foreach ($commands as $cmd => $desc) {
        echo sprintf("  %-10s %s\n", $cmd, $desc);
    }

    echo "\nExamples:\n";
    echo "  php app/cli/manage.php perf stats          # Show performance summary\n";
    echo "  php app/cli/manage.php jobs status         # Job queue status\n";
    echo "  php app/cli/manage.php cache warm          # Warm cache\n";
    echo "  php app/cli/manage.php health              # System health\n";
    echo "  php app/cli/manage.php db optimize         # Optimize tables\n";
    echo "  php app/cli/manage.php batch export-orders # Export orders to CSV\n\n";
}

function error(string $msg): void {
    echo "❌ Error: $msg\n";
    exit(1);
}

function format_bytes(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}
