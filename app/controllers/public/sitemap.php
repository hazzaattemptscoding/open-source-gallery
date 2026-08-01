<?php
/**
 * XML Sitemap for search engine crawling.
 * Lists all public pages: home, events, search.
 */

declare(strict_types=1);

function public_sitemap_controller(PDO $pdo, array $config): void {
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=86400');

    // 'url' is not a real config.php key (see bootstrap-config.php); base_url
    // is. Every URL in this sitemap was built on the 'https://example.com'
    // fallback for every self-hosted install until this was corrected --
    // every submitted sitemap pointed search engines at the wrong domain.
    $baseUrl = rtrim($config['site']['base_url'] ?? 'https://example.com', '/');

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    // Home page
    $xml .= '  <url>' . "\n";
    $xml .= '    <loc>' . htmlspecialchars($baseUrl) . '/</loc>' . "\n";
    $xml .= '    <changefreq>weekly</changefreq>' . "\n";
    $xml .= '    <priority>1.0</priority>' . "\n";
    $xml .= '  </url>' . "\n";

    // Search page
    $xml .= '  <url>' . "\n";
    $xml .= '    <loc>' . htmlspecialchars($baseUrl) . '/search</loc>' . "\n";
    $xml .= '    <changefreq>weekly</changefreq>' . "\n";
    $xml .= '    <priority>0.8</priority>' . "\n";
    $xml .= '  </url>' . "\n";

    // All published events
    try {
        $stmt = $pdo->prepare('
            SELECT id, slug, updated_at
            FROM events
            WHERE is_published = 1
            ORDER BY created_at DESC
        ');
        $stmt->execute();

        while ($event = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $lastmod = date('Y-m-d', strtotime($event['updated_at'] ?? 'now'));

            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($baseUrl) . '/e/' . htmlspecialchars($event['slug']) . '</loc>' . "\n";
            $xml .= '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
            $xml .= '    <changefreq>weekly</changefreq>' . "\n";
            $xml .= '    <priority>0.7</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }
    } catch (Exception $e) {
        error_log('Sitemap event fetch failed: ' . $e->getMessage());
    }

    $xml .= '</urlset>' . "\n";

    echo $xml;
    exit;
}
?>
