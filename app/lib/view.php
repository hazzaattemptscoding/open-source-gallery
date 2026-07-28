<?php
/**
 * Tiny templating helpers. No template engine — per CLAUDE.md, this project
 * has no build step and no framework, so views are plain PHP files that get
 * $vars extracted into scope and write HTML directly.
 */

declare(strict_types=1);

/** Escape for safe HTML output. Every piece of user- or DB-sourced text on a page must pass through this. */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/** @param array<string,mixed> $vars */
function render(string $viewPath, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require $viewPath;
}
