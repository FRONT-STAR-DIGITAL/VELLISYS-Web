<?php
declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
foreach (['/includes/', '/config/', '/sql/'] as $blocked) {
    if (str_starts_with($uri, $blocked)) {
        http_response_code(403);
        exit('Forbidden');
    }
}
$file = __DIR__ . $uri;
if ($uri !== '/' && is_file($file) && !str_contains($uri, '..')) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $cached = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'woff2' => 'font/woff2',
        'woff' => 'font/woff',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
    ];
    if (isset($cached[$ext])) {
        header('Content-Type: ' . $cached[$ext]);
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile($file);
        return true;
    }
    return false;
}
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    return true;
}
http_response_code(404);
echo 'Not found';
