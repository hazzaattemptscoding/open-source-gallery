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
require_once __DIR__ . '/../../lib/settings.php';

function public_search_controller(PDO $pdo, array $config): void {
    if (!get_setting($pdo, 'search', 'enabled', true)) {
        http_response_code(404);
        render(__DIR__ . '/../../views/errors/404.php', []);
        return;
    }

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
    // ?driver= is deliberately not accepted: see app/lib/search.php.
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
    $perPage = (int) get_setting($pdo, 'search', 'results_per_page', 20);
    $minQueryLength = (int) get_setting($pdo, 'search', 'min_query_length', 2);
    $results = search_photos($pdo, $query, $filters, $page, $perPage, $minQueryLength);

    set_cache_headers('short');

    render(__DIR__ . '/../../views/public/search.php', [
        'siteName' => $config['site']['name'] ?? 'Gallery',
        'query' => $query,
        'filters' => $filters,
        'results' => $results,
        'currencyCode' => $config['currency'] ?? 'GBP',
    ]);
}

function public_search_api_controller(PDO $pdo, array $config): void {
    header('Content-Type: application/json');

    if (!get_setting($pdo, 'search', 'enabled', true)) {
        http_response_code(404);
        echo json_encode(['error' => 'search is disabled']);
        return;
    }

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
    if (!empty($_GET['class'])) {
        $filters['class'] = (string)$_GET['class'];
    }

    // Perform search
    $perPage = (int) get_setting($pdo, 'search', 'results_per_page', 20);
    $minQueryLength = (int) get_setting($pdo, 'search', 'min_query_length', 2);
    $results = search_photos($pdo, $query, $filters, $page, $perPage, $minQueryLength);

    set_cache_headers('short');

    // Return JSON
    echo json_encode([
        'success' => true,
        'query' => $query,
        'results' => $results,
    ]);
    exit;
}
