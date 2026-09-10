<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$scope = BASE_URL === '' ? '/' : BASE_URL . '/';
$start = url('login.php');
$icon192 = url('assets/img/pwa-192.png');
$icon512 = url('assets/img/pwa-512.png');
$iconMask = url('assets/img/pwa-maskable-512.png');

$manifest = [
    'id' => $scope . 'app',
    'name' => product_name(),
    'short_name' => product_name(),
    'description' => 'Sign in to your branded books desk. Quotations, invoices, receipts and reports in one place.',
    'lang' => 'en',
    'dir' => 'ltr',
    'start_url' => $start,
    'scope' => $scope,
    'display' => 'standalone',
    'display_override' => ['standalone', 'minimal-ui'],
    'orientation' => 'any',
    'background_color' => '#08143A',
    'theme_color' => '#08143A',
    'categories' => ['business', 'finance', 'productivity'],
    'prefer_related_applications' => false,
    'icons' => [
        [
            'src' => $icon192,
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src' => $icon512,
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src' => $iconMask,
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'maskable',
        ],
    ],
];

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache');
echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
