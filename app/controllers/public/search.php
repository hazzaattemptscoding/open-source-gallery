<?php
/**
 * Public search controller.
 * Handles full-text search and advanced filtering of photos.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/view.php';
require_once __DIR__ . '/../../lib/search.php';
require_once __DIR__ . '/../../lib/cache_headers.php';

function public_search_controller(PDO $pdo, array $config): void {
    $query = $_GET['q'] ?? '';
    $page = (int)($_GET['page'] ?? 1);

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
        $filters['date_from'] = (string)$_GET['date_from'];
    }
    if (!empty($_GET['date_to'])) {
        $filters['date_to'] = (string)$_GET['date_to'];
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

    $query = $_GET['q'] ?? '';
    $page = (int)($_GET['page'] ?? 1);

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

    // Return JSON
    echo json_encode([
        'success' => true,
        'query' => $query,
        'results' => $results,
    ]);
    exit;
}
