<?php
declare(strict_types=1);

function vellisys_pdf_escape(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function document_pdf_bytes(array $brand, array $doc): string
{
    $w = 595.28;
    $h = 841.89;
    $m = 42.0;
    $objects = [];
    $pages = [];
    $content = '';
    $y = 0.0;

    $add = static function (string $s) use (&$content): void {
        $content .= $s;
    };
    $newPage = static function () use (&$content, &$pages, &$y, $h, $m): void {
        if ($content !== '') {
            $pages[] = $content;
        }
        $content = '';
        $y = $h - $m;
    };
    $ensure = static function (float $need) use (&$newPage, &$y, $m): void {
        if ($y - $need < $m + 28) {
            $newPage();
        }
    };
    $text = static function (float $x, float $yy, string $s, float $size, string $font = 'F1') use (&$add): void {
        $add(sprintf("BT /%s %.2f Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n", $font, $size, $x, $yy, vellisys_pdf_escape($s)));
    };
    $rgb = static function (string $hex) use (&$add): void {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            $hex = '08143A';
        }
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;
        $add(sprintf("%.3f %.3f %.3f rg\n", $r, $g, $b));
    };
    $fill = static function (float $x, float $yy, float $ww, float $hh) use (&$add): void {
        $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $x, $yy, $ww, $hh));
    };
    $stroke = static function (float $x1, float $y1, float $x2, float $y2) use (&$add): void {
        $add(sprintf("%.2f %.2f m %.2f %.2f l S\n", $x1, $y1, $x2, $y2));
    };

    $newPage();
    $navy = (string) ($brand['brand_deep'] ?? '#08143A');
    $blue = (string) ($brand['brand_color'] ?? '#1E4EFF');
    $name = (string) ($brand['name'] ?? 'Company');
    $kind = kind_meta((string) $doc['kind'])['singular'] ?? 'Document';
    $ccy = doc_currency($doc);
    $totals = $doc['totals'] ?? document_totals($doc);

    $rgb($navy);
    $fill($m, $h - 36, $w - $m * 2, 18);
    $add("1 1 1 rg\n");
    $text($m + 8, $h - 31, 'VELLISYS', 9, 'F2');
    $y = $h - 58;
    $rgb($navy);
    $text($m, $y, $name, 16, 'F2');
    $y -= 16;
    $line = trim(implode(' · ', array_filter([
        (string) ($brand['city'] ?? ''),
        (string) ($brand['phone'] ?? ''),
        (string) ($brand['email'] ?? ''),
    ])));
    if ($line !== '') {
        $text($m, $y, $line, 9);
        $y -= 14;
    }
    $rgb($blue);
    $text($m, $y, strtoupper($kind), 11, 'F2');
    $y -= 16;
    $rgb('#000000');
    $text($m, $y, (string) $doc['number'], 13, 'F2');
    $y -= 14;
    $text($m, $y, 'Date ' . format_date((string) ($doc['date'] ?? '')), 10);
    $y -= 18;
    $text($m, $y, 'In account with ' . (string) ($doc['party_name'] ?? ''), 11, 'F2');
    $y -= 22;

    $cols = [28, 150, 200, 40, 50, 70];
    $headers = ['#', 'Item', 'Description', 'Qty', 'Rate', 'Amount'];
    $rgb($navy);
    $fill($m, $y - 4, $w - $m * 2, 16);
    $add("1 1 1 rg\n");
    $x = $m + 4;
    foreach ($headers as $i => $label) {
        $text($x, $y, $label, 8, 'F2');
        $x += $cols[$i];
    }
    $y -= 20;
    $n = 0;
    foreach ($doc['items'] ?? [] as $item) {
        $n++;
        $ensure(28);
        $rgb('#000000');
        $row = [
            (string) $n,
            mb_substr(line_item_name($item) ?: '-', 0, 28),
            mb_substr(line_item_description($item) ?: '-', 0, 36),
            rtrim(rtrim(number_format((float) ($item['qty'] ?? 0), 2, '.', ''), '0'), '.') ?: '0',
            money((float) ($item['rate'] ?? 0), $ccy),
            money(line_amount($item), $ccy),
        ];
        $x = $m + 4;
        foreach ($row as $i => $cell) {
            $text($x, $y, $cell, 8);
            $x += $cols[$i];
        }
        $y -= 16;
        $add("0.85 0.87 0.9 RG\n");
        $stroke($m, $y + 10, $w - $m, $y + 10);
    }
    $y -= 10;
    $ensure(70);
    $rgb($navy);
    $text($w - $m - 200, $y, 'Net', 10);
    $text($w - $m - 90, $y, money((float) ($totals['net'] ?? 0), $ccy), 10, 'F2');
    $y -= 14;
    if ((float) ($totals['vat'] ?? 0) > 0) {
        $text($w - $m - 200, $y, 'VAT', 10);
        $text($w - $m - 90, $y, money((float) $totals['vat'], $ccy), 10, 'F2');
        $y -= 14;
    }
    $text($w - $m - 200, $y, 'Total', 12, 'F2');
    $text($w - $m - 90, $y, money((float) ($totals['total'] ?? 0), $ccy), 12, 'F2');
    $y -= 28;
    $note = trim((string) ($brand['payment_note'] ?? ''));
    if ($note !== '') {
        $ensure(24);
        $rgb('#000000');
        $text($m, $y, mb_substr($note, 0, 90), 9);
    }

    $pages[] = $content;
    $nPages = count($pages);
    $objs = [];
    $objs[] = "<< /Type /Catalog /Pages 2 0 R >>";
    $kids = [];
    $objN = 3;
    $contentIds = [];
    for ($i = 0; $i < $nPages; $i++) {
        $contentIds[] = $objN + 1;
        $kids[] = $objN . ' 0 R';
        $objN += 2;
    }
    $objs[] = '<< /Type /Pages /Count ' . $nPages . ' /Kids [' . implode(' ', $kids) . '] >>';
    foreach ($pages as $i => $body) {
        $stream = $body;
        $objs[] = sprintf(
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Contents %d 0 R /Resources << /Font << /F1  %d 0 R /F2 %d 0 R >> >> >>",
            $w,
            $h,
            $contentIds[$i],
            $objN,
            $objN + 1
        );
        $objs[] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
    }
    $objs[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
    $objs[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

    $out = "%PDF-1.4\n";
    $xref = [0];
    foreach ($objs as $i => $body) {
        $xref[] = strlen($out);
        $out .= ($i + 1) . " 0 obj\n" . $body . "\nendobj\n";
    }
    $start = strlen($out);
    $out .= "xref\n0 " . (count($objs) + 1) . "\n";
    $out .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objs); $i++) {
        $out .= sprintf("%010d 00000 n \n", $xref[$i]);
    }
    $out .= "trailer << /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n" . $start . "\n%%EOF";
    return $out;
}
