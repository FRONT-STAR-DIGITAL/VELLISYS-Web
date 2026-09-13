<?php
declare(strict_types=1);

require __DIR__ . '/../includes/pdf.php';
require __DIR__ . '/../includes/pitch_deck.php';

$bytes = vellisys_pitch_deck_bytes();
$targets = [
    '/home/ubuntu/Desktop/Vellisys_Client_Pitch_Deck.pdf',
    '/opt/cursor/artifacts/Vellisys_Client_Pitch_Deck.pdf',
];
foreach ($targets as $path) {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($path, $bytes);
    echo $path . ' ' . strlen($bytes) . " bytes\n";
}
