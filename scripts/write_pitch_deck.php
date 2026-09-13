<?php
declare(strict_types=1);

require __DIR__ . '/../includes/pdf.php';
require __DIR__ . '/../includes/pitch_deck.php';

$bytes = vellisys_pitch_deck_bytes();
$name = 'Vellisys_Client_Pitch_Deck.pdf';
$targets = [
    '/opt/cursor/artifacts/' . $name,
    '/mnt/c/Users/DELL/OneDrive/Desktop/' . $name,
];

$written = [];
foreach ($targets as $path) {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        continue;
    }
    if (@file_put_contents($path, $bytes) !== false) {
        $written[] = $path . ' ' . strlen($bytes) . ' bytes';
    }
}
if (!$written) {
    fwrite(STDERR, "Could not write the PDF.\n");
    exit(1);
}
echo implode("\n", $written) . "\n";
