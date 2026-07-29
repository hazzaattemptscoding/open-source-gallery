<?php
/**
 * Public search controller.
 * Handles full-text search and advanced filtering of photos.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/search.php';
require_once __DIR__ . '/../../lib/cache_headers.php';
require_once __DIR__ . '/../../lib/validation.php';
require_once __DIR__ . '/../../lib/rate_limit.php';

function public_search_controller(PDO $pdo, array $config): void {
    $clientIp = get_client_ip();
    if (!check_rate_limit($pdo, 'search', 'ip:' . $clientIp, 60, 30)) {
        http_response_code(429);
        render(__DIR__ . '/../../views/errors/429.php', []);
        return;
    }

    $query = (string)($_GET['q'] ?? '');
    $page = validate_page((int)($_GET['page'] ?? 1));

    // Build filters from query params
    $filters = [];
    if (!empty($_GET['event'])) {
        $filters['event_id'] = (int)$_GET['event'];
    }
    if (!empty($_GET['kart'])) {
        $filters['kart'] = (string)$_GET['kart'];
    }
    if (!empty($_GET['driver'])) {
        $filters['driver'] = (string)$_GET['driver'];
    }
    if (!empty($_GET['class'])) {
        $filters['class'] = (string)$_GET['class'];
    }
    if (!empty($_GET['price_min'])) {
        $filters['price_min'] = (int)$_GET['price_min'];
    }
    if (!empty($_GET['price_max'])) {
        $filters['price_max'] = (int)$_GET['price_max'];
    }
    if (!empty($_GET['date_from'])) {
        $dateFrom = validate_iso_date($_GET['date_from']);
        if ($dateFrom) {
            $filters['date_from'] = $dateFrom;
        }
    }
    if (!empty($_GET['date_to'])) {
        $dateTo = validate_iso_date($_GET['date_to']);
        if ($dateTo) {
            $filters['date_to'] = $dateTo;
        }
    }

    // Perform search
    $results = search_photos($pdo, $query, $filters, $page, 20);

    set_cache_headers('short');

    render(__DIR__ . '/../../views/public/search.php', [
        'siteName' => $config['site']['name'] ?? 'Gallery',
        'query' => $query,
        'filters' => $filters,
        'results' => $results,
        'currencyCode' => $config['currency']['code'] ?? 'GBP',
    ]);
}

function public_search_api_controller(PDO $pdo, array $config): void {
    header('Content-Type: application/json');

    $clientIp = get_client_ip();
    if (!check_rate_limit($pdo, 'search_api', 'ip:' . $clientIp, 60, 30)) {
        http_response_code(429);
        echo json_encode(['error' => 'rate limit exceeded']);
        return;
    }

    $query = (string)($_GET['q'] ?? '');
    $page = validate_page((int)($_GET['page'] ?? 1));

    // Build filters
    $filters = [];
    if (!empty($_GET['event'])) {
        $filters['event_id'] = (int)$_GET['event'];
    }
    if (!empty($_GET['kart'])) {
        $filters['kart'] = (string)$_GET['kart'];
    }
    if (!empty($_GET['driver'])) {
        $filters['driver'] = (string)$_GET['driver'];
    }
    if (!empty($_GET['class'])) {
        $filters['class'] = (string)$_GET['class'];
    }

    // Perform search
    $results = search_photos($pdo, $query, $filters, $page, 20);

    set_cache_headers('short');

    // Return JSON
    echo json_encode([
        'success' => true,
        'query' => $query,
        'results' => $results,
    ]);
    exit;
}
