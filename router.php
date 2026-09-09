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
if ($uri !== '/' && is_file($file)) {
    return false;
}
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    return true;
}
http_response_code(404);
echo 'Not found';
