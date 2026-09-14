<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$kind = (string) ($_GET['k'] ?? 'logo');
if (!in_array($kind, ['logo', 'signature'], true)) {
    $kind = 'logo';
}
$cid = (int) ($_GET['c'] ?? 0);
if ($cid < 1) {
    $cid = current_company_id();
}
if ($cid < 1) {
    http_response_code(404);
    exit;
}

$brand = branding_for($cid);
$col = $kind === 'signature' ? 'signature_path' : 'logo_path';
$rel = ltrim((string) ($brand[$col] ?? ''), '/');
$full = $rel !== '' ? ROOT_PATH . '/' . $rel : '';
$mime = $rel !== '' ? branding_asset_mime($rel) : 'image/png';
$bin = ($full !== '' && is_file($full)) ? (string) file_get_contents($full) : '';
if ($bin === '') {
    $asset = branding_asset_row($cid, $kind);
    if ($asset && is_string($asset['bin'] ?? null) && $asset['bin'] !== '') {
        $bin = $asset['bin'];
        $mime = (string) ($asset['mime'] ?: $mime);
    }
}
if ($bin === '') {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=86400');
header('Content-Length: ' . (string) strlen($bin));
echo $bin;
