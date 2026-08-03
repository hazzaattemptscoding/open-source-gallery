<?php
/**
 * Serves the free share image for a photo.
 *
 * Only reached when the cached file does not exist: .htaccess passes through to
 * index.php exclusively for paths that are not real files, so once the card has
 * been generated Apache serves it directly and this never runs again. Same
 * arrangement as the derivative fallback in placeholder.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/share_image.php';
require_once __DIR__ . '/../../lib/rate_limit.php';

/**
 * GET /media/share/{public_token}.jpg
 *
 * Generates the card on first request, caches it, and streams it back.
 */
function public_share_image_controller(PDO $pdo, array $config, string $publicToken): void
{
    $path = share_image_path($publicToken);

    // Race: another request may have generated it between Apache deciding the
    // file was missing and this running.
    if (is_file($path)) {
        share_image_stream($path);
        return;
    }

    $meta = fetch_share_image_meta($pdo, $publicToken);
    if ($meta === null) {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo 'Not found';
        return;
    }

    /*
     * Rate limited per IP because generation is the one expensive thing an
     * unauthenticated visitor can trigger here: each miss decodes a 1600px
     * JPEG and writes a new file. Without a limit, walking public tokens would
     * be a cheap way to make the server do a lot of GD work and fill the disk.
     * Generous enough that nobody sharing their own photos will notice.
     */
    $maxGenerations = adjust_rate_limit_for_dev($config, 60);
    if (!check_rate_limit($pdo, 'share_image', get_client_ip(), 3600, $maxGenerations)) {
        http_response_code(429);
        header('Content-Type: text/plain');
        echo 'Too many requests';
        return;
    }

    $siteName = (string) ($config['site']['name'] ?? 'Gallery');
    $domain = (string) parse_url(site_base_url($config) ?? '', PHP_URL_HOST);

    $generated = generate_share_image($publicToken, [
        'site_name' => $siteName,
        'domain' => $domain,
        'number' => $meta['number'],
        'class' => $meta['class'],
    ]);

    if (!$generated || !is_file($path)) {
        // The photo exists but its derivative has not been built yet, which is
        // normal in the window between upload and the cron run. 404 rather than
        // a broken image, and no caching, so a retry after cron works.
        http_response_code(404);
        header('Cache-Control: no-store');
        header('Content-Type: text/plain');
        echo 'Share image not available yet';
        return;
    }

    share_image_stream($path);
}

/** Stream a generated card with caching headers. */
function share_image_stream(string $path): void
{
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . (string) filesize($path));
    // Immutable in practice: the card is regenerated only if the file is
    // deleted, and the URL is keyed on the photo's public token.
    header('Cache-Control: public, max-age=604800');
    readfile($path);
}
