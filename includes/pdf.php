<?php
declare(strict_types=1);

function vellisys_pdf_latin(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = strtr($text, [
        '—' => '-', '–' => '-', '−' => '-',
        '’' => "'", '‘' => "'", '“' => '"', '”' => '"',
    ]);
    $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
    return $converted !== false ? $converted : $text;
}

function vellisys_pdf_escape(string $text): string
{
    $text = vellisys_pdf_latin($text);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function vellisys_pdf_wrap(string $text, int $width): array
{
    $text = trim(preg_replace('/[ \t]+/', ' ', $text) ?? $text);
    if ($text === '') {
        return [];
    }
    $lines = [];
    foreach (explode("\n", $text) as $para) {
        $para = trim($para);
        if ($para === '') {
            $lines[] = '';
            continue;
        }
        while (strlen($para) > $width) {
            $chunk = substr($para, 0, $width);
            $sp = strrpos($chunk, ' ');
            if ($sp !== false && $sp > 8) {
                $lines[] = substr($para, 0, $sp);
                $para = ltrim(substr($para, $sp));
            } else {
                $lines[] = $chunk;
                $para = substr($para, $width);
            }
        }
        if ($para !== '') {
            $lines[] = $para;
        }
    }
    return $lines;
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
    $put = static function (float $x, float &$yy, string $s, float $size, string $font = 'F1') use ($text): void {
        if ($s === '') {
            return;
        }
        $text($x, $yy, $s, $size, $font);
        $yy -= $size + 4;
    };

    $rgb($blue);
    $fill($m, $h - 32, $w - $m * 2, 14);
    $add("1 1 1 rg\n");
    $text($m + 8, $h - 27, strtoupper($kind), 12, 'F2');
    $y = $h - 52;
    $rgb($navy);
    $put($m, $y, $name, 12, 'F2');
    $rgb('#333333');
    foreach (array_filter([
        (string) ($brand['tagline'] ?? ''),
        (string) ($brand['address'] ?? ''),
        trim(implode(', ', array_filter([(string) ($brand['city'] ?? ''), (string) ($brand['phone'] ?? ''), (string) ($brand['email'] ?? '')]))),
        !empty($brand['tin']) ? 'TIN ' . $brand['tin'] : '',
        (string) ($brand['website'] ?? ''),
    ]) as $line) {
        foreach (vellisys_pdf_wrap($line, 90) as $wrap) {
            $put($m, $y, $wrap, 12);
        }
    }
    $y -= 6;
    $rgb($blue);
    $put($m, $y, (string) $doc['number'], 12, 'F2');
    $rgb('#000000');
    $put($m, $y, 'Date ' . format_date((string) ($doc['date'] ?? '')), 12);
    if (!empty($doc['due_date'])) {
        $put($m, $y, 'Due ' . format_date((string) $doc['due_date']), 12);
    }
    $y -= 4;
    $rgb($navy);
    $put($m, $y, 'To', 12, 'F2');
    $rgb('#000000');
    if (function_exists('document_party_to_lines')) {
        foreach (document_party_to_lines($doc) as $line) {
            $label = trim((string) ($line['label'] ?? ''));
            $text = trim((string) ($line['value'] ?? ''));
            $shown = $label !== '' ? ($label . ': ' . $text) : $text;
            foreach (vellisys_pdf_wrap($shown, 90) as $wrap) {
                $put($m, $y, $wrap, 12);
            }
        }
    } else {
        $put($m, $y, (string) ($doc['party_name'] ?? ''), 12, 'F2');
        foreach (array_filter([
            !empty($doc['party_contact']) ? 'Attn: ' . $doc['party_contact'] : '',
            (string) ($doc['party_address'] ?? ''),
            party_place_line($doc),
            trim(implode(' · ', array_filter([(string) ($doc['party_phone'] ?? ''), (string) ($doc['party_phone2'] ?? '')]))),
            (string) ($doc['party_email'] ?? ''),
            !empty($doc['party_tin']) ? 'TIN: ' . $doc['party_tin'] : '',
        ]) as $line) {
            foreach (vellisys_pdf_wrap((string) $line, 90) as $wrap) {
                $put($m, $y, $wrap, 12);
            }
        }
    }
    $y -= 8;

    $isLetter = in_array($doc['kind'] ?? '', ['letter', 'custom'], true) && (trim((string) ($doc['body'] ?? '')) !== '' || trim((string) ($doc['subject'] ?? '')) !== '');
    if ($isLetter) {
        if (trim((string) ($doc['subject'] ?? '')) !== '') {
            $rgb($navy);
            $put($m, $y, 'Subject: ' . (string) $doc['subject'], 12, 'F2');
            $y -= 2;
        }
        $rgb('#000000');
        foreach (vellisys_pdf_wrap(html_to_plain((string) ($doc['body'] ?? '')), 88) as $wrap) {
            $ensure(16);
            $put($m, $y, $wrap, 12);
        }
    } else {
        $cols = [28, 120, 190, 40, 70, 70];
        $headers = ['#', 'Item', 'Description', 'Qty', 'Rate', 'Amount'];
        $rgb($navy);
        $fill($m, $y - 4, $w - $m * 2, 16);
        $add("1 1 1 rg\n");
        $x = $m + 4;
        foreach ($headers as $i => $label) {
            $text($x, $y, $label, 12, 'F2');
            $x += $cols[$i];
        }
        $y -= 20;
        $n = 0;
        foreach ($doc['items'] ?? [] as $item) {
            $n++;
            $descLines = vellisys_pdf_wrap(line_item_description($item) ?: '-', 36) ?: ['-'];
            $need = 16 + (count($descLines) - 1) * 11;
            $ensure($need);
            $rgb('#000000');
            $text($m + 4, $y, (string) $n, 12);
            $text($m + 4 + $cols[0], $y, mb_substr(line_item_name($item) ?: '-', 0, 22), 12, 'F2');
            $text($m + 4 + $cols[0] + $cols[1], $y, $descLines[0], 12);
            $text($m + 4 + $cols[0] + $cols[1] + $cols[2], $y, rtrim(rtrim(number_format((float) ($item['qty'] ?? 0), 2, '.', ''), '0'), '.') ?: '0', 12);
            $text($m + 4 + $cols[0] + $cols[1] + $cols[2] + $cols[3], $y, money((float) ($item['rate'] ?? 0), $ccy), 12);
            $text($m + 4 + $cols[0] + $cols[1] + $cols[2] + $cols[3] + $cols[4], $y, money(line_amount($item), $ccy), 12);
            $y -= 14;
            for ($di = 1; $di < count($descLines); $di++) {
                $ensure(14);
                $text($m + 4 + $cols[0] + $cols[1], $y, $descLines[$di], 12);
                $y -= 13;
            }
            $add("0.85 0.87 0.9 RG\n");
            $stroke($m, $y + 8, $w - $m, $y + 8);
            $y -= 4;
        }
        $y -= 8;
        $ensure(80);
        $rgb($navy);
        $text($w - $m - 200, $y, 'Net', 12);
        $text($w - $m - 90, $y, money((float) ($totals['net'] ?? 0), $ccy), 12, 'F2');
        $y -= 14;
        if ((float) ($totals['vat'] ?? 0) > 0) {
            $text($w - $m - 200, $y, tax_rate_label((float) ($doc['vat_rate'] ?? 0), $brand), 12);
            $text($w - $m - 90, $y, money((float) $totals['vat'], $ccy), 12, 'F2');
            $y -= 14;
        }
        $text($w - $m - 200, $y, 'Total', 12);
        $text($w - $m - 90, $y, money((float) ($totals['total'] ?? 0), $ccy), 12);
        $y -= 16;
        if (($doc['kind'] ?? '') === 'receipt') {
            $s = $doc['settlement'] ?? [];
            $text($w - $m - 200, $y, 'Received', 12, 'F2');
            $text($w - $m - 90, $y, money((float) ($s['received'] ?? $doc['paid'] ?? 0), $ccy), 12, 'F2');
            $y -= 14;
            $dueAmt = function_exists('document_due_amount') ? document_due_amount($doc) : (float) ($s['invoice_balance'] ?? $s['balance'] ?? $doc['balance'] ?? 0);
            $text($w - $m - 200, $y, 'Due', 12);
            $text($w - $m - 90, $y, money($dueAmt, $ccy), 12, 'F2');
            $y -= 16;
        } elseif (isset($doc['paid']) && (float) $doc['paid'] > 0) {
            $text($w - $m - 200, $y, 'Paid', 12);
            $text($w - $m - 90, $y, money((float) $doc['paid'], $ccy), 12, 'F2');
            $y -= 14;
            $text($w - $m - 200, $y, 'Balance', 12);
            $text($w - $m - 90, $y, money((float) ($doc['balance'] ?? 0), $ccy), 12, 'F2');
            $y -= 16;
        }
    }

    $y -= 10;
    $notes = trim((string) ($doc['notes'] ?? ''));
    $pay = trim((string) ($brand['payment_note'] ?? ''));
    $method = trim((string) ($doc['payment_method'] ?? ''));
    $ref = trim((string) ($doc['payment_ref'] ?? ''));
    $extra = array_filter([
        $method !== '' ? 'Paid how: ' . ($method) : '',
        $ref !== '' ? 'Reference: ' . $ref : '',
        $notes,
        $pay,
    ]);
    if ($extra) {
        $rgb('#000000');
        foreach ($extra as $block) {
            foreach (vellisys_pdf_wrap((string) $block, 90) as $wrap) {
                $ensure(14);
                $put($m, $y, $wrap, 12);
            }
            $y -= 4;
        }
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
