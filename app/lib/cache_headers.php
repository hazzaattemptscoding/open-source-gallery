<?php
declare(strict_types=1);

/**
 * Set cache headers for static and semi-static content.
 * Reduces redundant requests and improves performance on high-traffic galleries.
 */
function set_cache_headers(string $type = 'short', string $etag = ''): void {
    match($type) {
        'short' => header('Cache-Control: public, max-age=600'), // 10 minutes
        'medium' => header('Cache-Control: public, max-age=3600'), // 1 hour
        'long' => header('Cache-Control: public, max-age=86400'), // 1 day
        'no-cache' => header('Cache-Control: no-cache, no-store, must-revalidate'),
        default => header('Cache-Control: public, max-age=600'),
    };

    if ($etag) {
        header("ETag: \"{$etag}\"");
        // If client sent If-None-Match and it matches, return 304
        if (($ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null) && $ifNoneMatch === "\"{$etag}\"") {
            http_response_code(304);
            exit;
        }
    }

    header('Vary: Accept-Encoding, Cookie');
}

/**
 * Generate a simple ETag from content.
 * Use for gallery pages, search results, or other semi-static content.
 */
function generate_etag(string $content): string {
    return md5($content);
}
