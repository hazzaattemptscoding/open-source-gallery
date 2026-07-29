<?php
declare(strict_types=1);

/**
 * CLI helper functions for formatting, tables, and output.
 */

function table_header(array $columns): void {
    $widths = array_map(fn($col) => strlen($col) + 2, $columns);
    $header = implode('', array_map(fn($col, $width) => sprintf("%-{$width}s", $col), $columns, $widths));
    echo $header . "\n";
    echo str_repeat("─", strlen($header)) . "\n";
}

function table_row(array $values, array $widths): void {
    echo implode('', array_map(
        fn($val, $width) => sprintf("%-{$width}s", (string)$val),
        $values,
        $widths
    )) . "\n";
}

function format_bytes(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}

function format_duration(int $seconds): string {
    if ($seconds < 60) {
        return "${seconds}s";
    }
    if ($seconds < 3600) {
        return (int)($seconds / 60) . 'm';
    }
    return (int)($seconds / 3600) . 'h';
}

function progress_bar(int $current, int $total, int $width = 40): string {
    $percent = $total > 0 ? round(($current / $total) * 100) : 0;
    $filled = (int)(($percent / 100) * $width);
    $empty = $width - $filled;

    $bar = str_repeat('█', $filled) . str_repeat('░', $empty);
    return "[$bar] $percent%";
}

function success(string $msg): void {
    echo "✅ $msg\n";
}

function warning(string $msg): void {
    echo "⚠️  $msg\n";
}

function info(string $msg): void {
    echo "ℹ️  $msg\n";
}

function error(string $msg): void {
    echo "❌ Error: $msg\n";
    exit(1);
}

function section(string $title): void {
    $len = strlen($title) + 4;
    echo "\n";
    echo "╔" . str_repeat("═", $len) . "╗\n";
    echo "║  $title  ║\n";
    echo "╚" . str_repeat("═", $len) . "╝\n\n";
}

function subsection(string $title): void {
    echo "\n$title\n";
    echo str_repeat("─", strlen($title)) . "\n";
}
