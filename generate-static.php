#!/usr/bin/env php
<?php
/**
 * Static HTML Generator
 *
 * Exports your gallery as pure HTML + CSS files. No database needed to view.
 *
 * Usage: php generate-static.php [output-directory]
 *
 * Creates standalone HTML files in output directory that can be:
 * - Uploaded to any static hosting (GitHub Pages, Netlify, S3)
 * - Opened locally in a browser
 * - Served by any web server without PHP/database
 * - Archived for long-term preservation
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

require __DIR__ . '/app/bootstrap.php';

$outputDir = $argv[1] ?? __DIR__ . '/public_static';

echo "PowerMedia Gallery - Static HTML Generator\n";
echo "==========================================\n\n";

// Create output directory
if (!is_dir($outputDir)) {
    if (!@mkdir($outputDir, 0755, true)) {
        die("ERROR: Cannot create output directory: $outputDir\n");
    }
}

$assetsDir = "$outputDir/assets";
$mediaDir = "$outputDir/media";

if (!@mkdir("$assetsDir/css", 0755, true)) {
    die("ERROR: Cannot create assets directory\n");
}
if (!@mkdir("$mediaDir/d", 0755, true)) {
    die("ERROR: Cannot create media directory\n");
}

echo "Output directory: $outputDir\n";
echo "Assets directory: $assetsDir\n";
echo "Media directory: $mediaDir\n\n";

// Copy CSS and static assets
echo "Copying assets...\n";
copy_dir(__DIR__ . '/public/assets', $assetsDir);
echo "✓ CSS and JavaScript copied\n\n";

// Copy media derivatives
echo "Copying photo derivatives...\n";
copy_dir(__DIR__ . '/public/media/d', "$mediaDir/d");
echo "✓ Photo derivatives copied\n\n";

// Generate pages
echo "Generating HTML...\n";

// Home page
echo "  Generating home.html...\n";
$homeHtml = generate_home_page($pdo, $config);
file_put_contents("$outputDir/index.html", $homeHtml);

// Event pages
$events = $pdo->query("SELECT id, slug, title FROM events WHERE published = 1 ORDER BY date DESC")->fetchAll();
foreach ($events as $event) {
    echo "  Generating events/{$event['slug']}.html...\n";
    $eventHtml = generate_event_page($pdo, $config, $event['id']);
    @mkdir("$outputDir/events", 0755, true);
    file_put_contents("$outputDir/events/{$event['slug']}.html", $eventHtml);
}

// Search results (limited set)
echo "  Generating search results (sample)...\n";
$searchHtml = generate_search_page($pdo, $config);
file_put_contents("$outputDir/search.html", $searchHtml);

// 404 page
echo "  Generating 404.html...\n";
$notFoundHtml = generate_404_page();
file_put_contents("$outputDir/404.html", $notFoundHtml);

echo "\n✓ Static gallery generated successfully!\n";
echo "\nTo view locally:\n";
echo "  cd $outputDir\n";
echo "  python3 -m http.server 8000\n";
echo "  Then open http://localhost:8000\n";
echo "\nTo deploy:\n";
echo "  - Upload all files to your web server\n";
echo "  - Or push to GitHub and enable GitHub Pages\n";
echo "  - Or upload to Netlify/Vercel (drag and drop folder)\n";

// ============================================================================
// Generator Functions
// ============================================================================

function generate_home_page(PDO $pdo, array $config): string {
    $events = $pdo->query("SELECT * FROM events WHERE published = 1 ORDER BY date DESC LIMIT 12")->fetchAll();

    $html = "<!DOCTYPE html>\n<html>\n<head>\n";
    $html .= "  <meta charset=\"UTF-8\">\n";
    $html .= "  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
    $html .= "  <title>" . htmlspecialchars($config['site']['name'] ?? 'Gallery') . "</title>\n";
    $html .= "  <link rel=\"stylesheet\" href=\"assets/css/style.css\">\n";
    $html .= "</head>\n<body>\n";
    $html .= "  <header><h1>" . htmlspecialchars($config['site']['name'] ?? 'Gallery') . "</h1></header>\n";
    $html .= "  <main>\n";

    foreach ($events as $event) {
        $html .= "    <article class=\"event-card\">\n";
        $html .= "      <h2><a href=\"events/" . htmlspecialchars($event['slug']) . ".html\">" . htmlspecialchars($event['title']) . "</a></h2>\n";
        if ($event['description']) {
            $html .= "      <p>" . htmlspecialchars($event['description']) . "</p>\n";
        }
        if ($event['date']) {
            $html .= "      <p class=\"date\">" . htmlspecialchars($event['date']) . "</p>\n";
        }
        $html .= "    </article>\n";
    }

    $html .= "  </main>\n";
    $html .= "  <footer><p>Generated " . date('Y-m-d H:i:s') . "</p></footer>\n";
    $html .= "</body>\n</html>";

    return $html;
}

function generate_event_page(PDO $pdo, array $config, int $eventId): string {
    $event = $pdo->prepare("SELECT * FROM events WHERE id = ?")->execute([$eventId])->fetch();
    $photos = $pdo->prepare("SELECT * FROM photos WHERE event_id = ? AND status = 'ready' ORDER BY created_at")->execute([$eventId])->fetchAll();

    $html = "<!DOCTYPE html>\n<html>\n<head>\n";
    $html .= "  <meta charset=\"UTF-8\">\n";
    $html .= "  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
    $html .= "  <title>" . htmlspecialchars($event['title']) . "</title>\n";
    $html .= "  <link rel=\"stylesheet\" href=\"../assets/css/style.css\">\n";
    $html .= "</head>\n<body>\n";
    $html .= "  <header>\n";
    $html .= "    <h1>" . htmlspecialchars($event['title']) . "</h1>\n";
    $html .= "    <p><a href=\"../index.html\">← Back to gallery</a></p>\n";
    $html .= "  </header>\n";
    $html .= "  <main class=\"gallery\">\n";

    foreach ($photos as $photo) {
        $html .= "    <figure class=\"photo-item\">\n";
        $html .= "      <img src=\"../media/d/" . htmlspecialchars($photo['public_token']) . "-800.jpg\" ";
        if ($photo['width'] && $photo['height']) {
            $html .= "width=\"" . (int)$photo['width'] . "\" height=\"" . (int)$photo['height'] . "\" ";
        }
        $html .= "alt=\"" . htmlspecialchars($photo['original_filename'] ?? 'Photo') . "\">\n";
        $html .= "    </figure>\n";
    }

    $html .= "  </main>\n";
    $html .= "  <footer><p>Generated " . date('Y-m-d H:i:s') . "</p></footer>\n";
    $html .= "</body>\n</html>";

    return $html;
}

function generate_search_page(PDO $pdo, array $config): string {
    $photos = $pdo->query("SELECT * FROM photos WHERE status = 'ready' ORDER BY created_at DESC LIMIT 100")->fetchAll();

    $html = "<!DOCTYPE html>\n<html>\n<head>\n";
    $html .= "  <meta charset=\"UTF-8\">\n";
    $html .= "  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
    $html .= "  <title>All Photos</title>\n";
    $html .= "  <link rel=\"stylesheet\" href=\"assets/css/style.css\">\n";
    $html .= "</head>\n<body>\n";
    $html .= "  <header>\n";
    $html .= "    <h1>All Photos</h1>\n";
    $html .= "    <p><a href=\"index.html\">← Back to gallery</a></p>\n";
    $html .= "  </header>\n";
    $html .= "  <main class=\"gallery\">\n";

    foreach ($photos as $photo) {
        $html .= "    <figure class=\"photo-item\">\n";
        $html .= "      <img src=\"media/d/" . htmlspecialchars($photo['public_token']) . "-800.jpg\" ";
        if ($photo['width'] && $photo['height']) {
            $html .= "width=\"" . (int)$photo['width'] . "\" height=\"" . (int)$photo['height'] . "\" ";
        }
        $html .= "alt=\"" . htmlspecialchars($photo['original_filename'] ?? 'Photo') . "\">\n";
        $html .= "    </figure>\n";
    }

    $html .= "  </main>\n";
    $html .= "  <footer><p>Generated " . date('Y-m-d H:i:s') . "</p></footer>\n";
    $html .= "</body>\n</html>";

    return $html;
}

function generate_404_page(): string {
    return <<<'HTML'
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Page Not Found</title>
  <style>
    body { font-family: sans-serif; text-align: center; padding: 100px 20px; }
    h1 { font-size: 48px; margin: 0; }
    p { color: #666; }
    a { color: #000; text-decoration: underline; }
  </style>
</head>
<body>
  <h1>404</h1>
  <p>Page not found.</p>
  <p><a href="index.html">← Back to gallery</a></p>
</body>
</html>
HTML;
}

function copy_dir(string $src, string $dst): void {
    if (!is_dir($src)) {
        return;
    }

    @mkdir($dst, 0755, true);

    $files = scandir($src);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;

        $srcPath = "$src/$file";
        $dstPath = "$dst/$file";

        if (is_dir($srcPath)) {
            copy_dir($srcPath, $dstPath);
        } else {
            @copy($srcPath, $dstPath);
        }
    }
}
