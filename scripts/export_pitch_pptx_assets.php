<?php
declare(strict_types=1);

require __DIR__ . '/../includes/pdf.php';
require __DIR__ . '/../includes/pitch_deck.php';

$dir = sys_get_temp_dir() . '/vellisys-pptx';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}

$root = dirname(__DIR__);
$mark = vellisys_pdf_load_png($root . '/assets/img/vellisys-mark.png', 640);
$logo = vellisys_pdf_load_png($root . '/assets/img/vellisys-wordmark.png', 900);
imagepng($mark, $dir . '/mark-alpha.png');
imagepng($logo, $dir . '/wordmark-alpha.png');

function vellisys_pptx_tile_bg(GdImage $mark, string $hex, float $opacity, int $w, int $h, int $tile, int $step): GdImage
{
    $flat = vellisys_pdf_flatten_on($mark, $hex, $opacity, 36);
    $bg = imagecreatetruecolor($w, $h);
    [$r, $g, $b] = vellisys_pdf_hex_rgb($hex);
    imagefill($bg, 0, 0, imagecolorallocate($bg, $r, $g, $b));
    $tw = imagesx($flat);
    $th = imagesy($flat);
    $row = 0;
    for ($y = -40; $y < $h + $tile; $y += (int) round($step * 0.84)) {
        $shift = ($row % 2) ? (int) round($step * 0.5) : 0;
        for ($x = -60 + $shift; $x < $w + $tile; $x += $step) {
            imagecopyresampled($bg, $flat, $x, $y, 0, 0, $tile, $tile, $tw, $th);
        }
        $row++;
    }
    imagedestroy($flat);
    return $bg;
}

$dark = vellisys_pptx_tile_bg($mark, '#08143A', 0.16, 1920, 1080, 168, 250);
imagepng($dark, $dir . '/bg-dark.png');
imagedestroy($dark);

$light = vellisys_pptx_tile_bg($mark, '#F7F8FC', 0.11, 1920, 1080, 180, 260);
imagepng($light, $dir . '/bg-light.png');
imagedestroy($light);

$wmDark = vellisys_pdf_flatten_on($mark, '#08143A', 1.0, 0);
imagepng($wmDark, $dir . '/mark-navy.png');
imagedestroy($wmDark);

imagedestroy($mark);
imagedestroy($logo);

echo $dir . "\n";
