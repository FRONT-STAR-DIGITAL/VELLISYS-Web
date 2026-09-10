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
        $type = $cached[$ext];
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            $fh = fopen($file, 'rb');
            $magic = $fh ? (string) fread($fh, 12) : '';
            if ($fh) {
                fclose($fh);
            }
            if (str_starts_with($magic, "\xFF\xD8\xFF")) {
                $type = 'image/jpeg';
            } elseif (str_starts_with($magic, "\x89PNG")) {
                $type = 'image/png';
            } elseif (str_starts_with($magic, 'GIF87a') || str_starts_with($magic, 'GIF89a')) {
                $type = 'image/gif';
            } elseif (str_starts_with($magic, 'RIFF') && str_contains($magic, 'WEBP')) {
                $type = 'image/webp';
            }
        }
        header('Content-Type: ' . $type);
        if (basename($file) === 'sw.js') {
            header('Cache-Control: no-cache');
        } else {
            header('Cache-Control: public, max-age=31536000, immutable');
        }
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
