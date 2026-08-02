<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Admin') ?><?= ($siteName ?? false) ? ' — ' . e($siteName) : '' ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<?php if ($includeAdminStyles ?? false): ?>
<link rel="stylesheet" href="/assets/css/admin-refined.css">
<?php endif; ?>
<?= isset($extraStyles) ? $extraStyles : '' ?>
<link rel="stylesheet" href="/api/styles.css">
</head>
<body>
