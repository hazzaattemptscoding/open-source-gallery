<?php
/**
 * SEO helpers for meta tags and structured data.
 */

declare(strict_types=1);

/**
 * Generate meta tags for a page.
 *
 * @param array $config Site config (from bootstrap)
 * @param string $title Page title
 * @param string $description Page meta description
 * @param string $url Full page URL (for canonical)
 * @param string $imageUrl Image URL for Open Graph (optional)
 * @return string HTML meta tags as string
 */
function generate_meta_tags(
    array $config,
    string $title,
    string $description,
    string $url,
    string $imageUrl = ''
): string {
    $siteName = htmlspecialchars($config['site']['name'] ?? 'Gallery');
    $title = htmlspecialchars($title);
    $description = htmlspecialchars(substr($description, 0, 160));
    $url = htmlspecialchars($url);
    $imageUrl = htmlspecialchars($imageUrl ?: ($config['site']['default_og_image'] ?? ''));

    $tags = [
        "<title>{$title} — {$siteName}</title>",
        "<meta name=\"description\" content=\"{$description}\">",
        "<meta name=\"theme-color\" content=\"#000000\">",
        "<link rel=\"canonical\" href=\"{$url}\">",
        "<meta property=\"og:title\" content=\"{$title}\">",
        "<meta property=\"og:description\" content=\"{$description}\">",
        "<meta property=\"og:url\" content=\"{$url}\">",
        "<meta property=\"og:site_name\" content=\"{$siteName}\">",
        "<meta property=\"og:type\" content=\"website\">",
    ];

    if ($imageUrl) {
        $tags[] = "<meta property=\"og:image\" content=\"{$imageUrl}\">";
        $tags[] = "<meta property=\"og:image:alt\" content=\"{$title}\">";
    }

    // Twitter Card
    $tags[] = "<meta name=\"twitter:card\" content=\"summary_large_image\">";
    $tags[] = "<meta name=\"twitter:title\" content=\"{$title}\">";
    $tags[] = "<meta name=\"twitter:description\" content=\"{$description}\">";

    if ($imageUrl) {
        $tags[] = "<meta name=\"twitter:image\" content=\"{$imageUrl}\">";
    }

    return implode("\n", $tags);
}

/**
 * Generate JSON-LD structured data for an event.
 *
 * @param array $config Site config
 * @param array $event Event data (id, title, event_date, venue, description, etc.)
 * @param string $imageUrl Event cover image URL
 * @return string JSON-LD script tag
 */
function generate_event_schema(
    array $config,
    array $event,
    string $imageUrl = ''
): string {
    $baseUrl = rtrim(site_base_url($config) ?? 'https://example.com', '/');
    $siteName = $config['site']['name'] ?? 'Gallery';

    $eventDate = $event['event_date'] ?? '';
    $startDate = $eventDate ? date('c', strtotime($eventDate)) : '';

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $event['title'] ?? '',
        'description' => substr($event['description'] ?? '', 0, 160),
        'startDate' => $startDate,
        'url' => $baseUrl . '/e/' . ($event['slug'] ?? ''),
        'organizer' => [
            '@type' => 'Organization',
            'name' => $siteName,
        ],
    ];

    if ($event['venue'] ?? '') {
        $schema['location'] = [
            '@type' => 'Place',
            'name' => $event['venue'],
        ];
    }

    if ($imageUrl) {
        $schema['image'] = $imageUrl;
    }

    return '<script type="application/ld+json">' . json_encode($schema) . '</script>';
}

/**
 * Generate JSON-LD structured data for Organization (usually on home page).
 *
 * @param array $config Site config
 * @return string JSON-LD script tag
 */
function generate_organization_schema(array $config): string {
    $baseUrl = rtrim(site_base_url($config) ?? 'https://example.com', '/');
    $siteName = $config['site']['name'] ?? 'Gallery';

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        'name' => $siteName,
        'url' => $baseUrl,
        'email' => $config['contact']['email'] ?? '',
    ];

    return '<script type="application/ld+json">' . json_encode($schema) . '</script>';
}

/**
 * Generate descriptive alt text for a photo.
 *
 * Driver name is never included. Alt text ships in the public HTML and is
 * read by search-engine crawlers, so putting a driver's name here would
 * publish it more widely than any on-page label. Kart number, class, and
 * event carry the descriptive weight instead.
 *
 * @param array $photo Photo data (id, title, description, kart, class, event_name, session_name)
 * @return string Alt text (max 125 chars for accessibility)
 */
function generate_photo_alt_text(array $photo): string {
    $parts = [];

    if ($photo['title'] ?? '') {
        $parts[] = $photo['title'];
    }

    if ($photo['kart'] ?? '') {
        $parts[] = 'kart ' . $photo['kart'];
    }

    if ($photo['class'] ?? '') {
        $parts[] = 'class ' . $photo['class'];
    }

    if ($photo['event_name'] ?? '') {
        $parts[] = 'at ' . $photo['event_name'];
    }

    if ($photo['session_name'] ?? '') {
        $parts[] = $photo['session_name'];
    }

    $alt = implode(', ', $parts);
    return substr($alt, 0, 125) ?: 'Gallery photo';
}

/**
 * Generate alt text from gallery photo tags (kart_tags, class_tags).
 * Used in grid views where photos are linked via tags.
 *
 * Driver names are deliberately excluded for the same reason as
 * generate_photo_alt_text(): alt text is public, crawlable markup.
 * fetch_gallery_media() no longer selects driver_tags at all.
 *
 * @param array $photo Photo data from fetch_gallery_media (kart_tags, class_tags)
 * @return string Alt text (max 125 chars)
 */
function generate_gallery_photo_alt_text(array $photo): string {
    $parts = [];

    if ($photo['kart_tags'] ?? '') {
        $karts = array_map('trim', explode(',', $photo['kart_tags']));
        $karts = array_filter($karts);
        if (!empty($karts)) {
            $parts[] = 'kart ' . implode(', ', $karts);
        }
    }

    if ($photo['class_tags'] ?? '') {
        $classes = array_map('trim', explode(',', $photo['class_tags']));
        $classes = array_filter($classes);
        if (!empty($classes)) {
            $parts[] = 'class ' . implode(', ', $classes);
        }
    }

    $alt = implode(', ', $parts);
    return substr($alt, 0, 125) ?: 'Gallery photo';
}
?>
