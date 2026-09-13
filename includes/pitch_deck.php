<?php
declare(strict_types=1);

function vellisys_pdf_hex_rgb(string $hex): array
{
    $hex = ltrim($hex, '#');
    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
}

function vellisys_pdf_load_png(string $path, int $maxEdge): GdImage
{
    $src = imagecreatefrompng($path);
    if ($src === false) {
        throw new RuntimeException('Could not read ' . $path);
    }
    $sw = imagesx($src);
    $sh = imagesy($src);
    $scale = min($maxEdge / max($sw, 1), $maxEdge / max($sh, 1), 1.0);
    $dw = max(1, (int) round($sw * $scale));
    $dh = max(1, (int) round($sh * $scale));
    $out = imagecreatetruecolor($dw, $dh);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    $clear = imagecolorallocatealpha($out, 0, 0, 0, 127);
    imagefill($out, 0, 0, $clear);
    imagealphablending($out, true);
    imagecopyresampled($out, $src, 0, 0, 0, 0, $dw, $dh, $sw, $sh);
    imagedestroy($src);
    for ($y = 0; $y < $dh; $y++) {
        for ($x = 0; $x < $dw; $x++) {
            $c = imagecolorat($out, $x, $y);
            $a = ($c >> 24) & 0x7F;
            $r = ($c >> 16) & 0xFF;
            $g = ($c >> 8) & 0xFF;
            $b = $c & 0xFF;
            if ($a >= 120 || ($r < 26 && $g < 26 && $b < 30)) {
                imagesetpixel($out, $x, $y, $clear);
            }
        }
    }
    return $out;
}

function vellisys_pdf_flatten_on(GdImage $src, string $bgHex, float $opacity, ?int $pad = null): GdImage
{
    [$br, $bg, $bb] = vellisys_pdf_hex_rgb($bgHex);
    $sw = imagesx($src);
    $sh = imagesy($src);
    $pad = $pad ?? 0;
    $dw = $sw + $pad * 2;
    $dh = $sh + $pad * 2;
    $dst = imagecreatetruecolor($dw, $dh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, $br, $bg, $bb));
    for ($y = 0; $y < $sh; $y++) {
        for ($x = 0; $x < $sw; $x++) {
            $c = imagecolorat($src, $x, $y);
            $a = ($c >> 24) & 0x7F;
            if ($a >= 120) {
                continue;
            }
            $o = $opacity * (1 - $a / 127);
            if ($o <= 0.002) {
                continue;
            }
            $r = (int) round($br + ((($c >> 16) & 0xFF) - $br) * $o);
            $g = (int) round($bg + ((($c >> 8) & 0xFF) - $bg) * $o);
            $b = (int) round($bb + (($c & 0xFF) - $bb) * $o);
            imagesetpixel($dst, $x + $pad, $y + $pad, imagecolorallocate($dst, $r, $g, $b));
        }
    }
    return $dst;
}

function vellisys_pdf_jpeg(GdImage $im, int $quality = 84): array
{
    ob_start();
    imagejpeg($im, null, $quality);
    $bytes = (string) ob_get_clean();
    return [$bytes, imagesx($im), imagesy($im)];
}

function vellisys_pdf_image_object(string $jpeg, int $w, int $h): string
{
    return '<< /Type /XObject /Subtype /Image /Width ' . $w
        . ' /Height ' . $h
        . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '
        . strlen($jpeg) . " >>\nstream\n" . $jpeg . "\nendstream";
}

/**
 * Landscape A4 client pitch deck (navy / Vellisys blue, Helvetica).
 */
function vellisys_pitch_deck_bytes(): string
{
    $w = 841.89;
    $h = 595.28;
    $navy = '#08143A';
    $blue = '#1E4EFF';
    $gold = '#C6A15B';
    $ink = '#141712';
    $muted = '#5A6172';
    $paper = '#F4F6FB';
    $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__);
    $markPath = $root . '/assets/img/vellisys-mark.png';
    $logoPath = $root . '/assets/img/vellisys-wordmark.png';

    $mark = vellisys_pdf_load_png($markPath, 420);
    $logo = vellisys_pdf_load_png($logoPath, 720);
    $patDarkIm = vellisys_pdf_flatten_on($mark, '#0A1744', 0.16, 48);
    $patLightIm = vellisys_pdf_flatten_on($mark, '#F7F8FC', 0.11, 48);
    $wmDarkIm = vellisys_pdf_flatten_on($mark, '#0A1744', 0.22, 8);
    $wmLightIm = vellisys_pdf_flatten_on($mark, '#FFFFFF', 0.14, 8);
    $logoDarkIm = vellisys_pdf_flatten_on($logo, '#08143A', 1.0, 4);
    $logoLightIm = vellisys_pdf_flatten_on($logo, '#FFFFFF', 1.0, 4);
    [$patDarkJpeg, $pdw, $pdh] = vellisys_pdf_jpeg($patDarkIm, 80);
    [$patLightJpeg, $plw, $plh] = vellisys_pdf_jpeg($patLightIm, 80);
    [$wmDarkJpeg, $wdw, $wdh] = vellisys_pdf_jpeg($wmDarkIm, 86);
    [$wmLightJpeg, $wlw, $wlh] = vellisys_pdf_jpeg($wmLightIm, 86);
    [$logoDarkJpeg, $ldw, $ldh] = vellisys_pdf_jpeg($logoDarkIm, 90);
    [$logoLightJpeg, $llw, $llh] = vellisys_pdf_jpeg($logoLightIm, 90);
    imagedestroy($mark);
    imagedestroy($logo);
    imagedestroy($patDarkIm);
    imagedestroy($patLightIm);
    imagedestroy($wmDarkIm);
    imagedestroy($wmLightIm);
    imagedestroy($logoDarkIm);
    imagedestroy($logoLightIm);

    $images = [
        'PDk' => [$patDarkJpeg, $pdw, $pdh],
        'PLt' => [$patLightJpeg, $plw, $plh],
        'WDk' => [$wmDarkJpeg, $wdw, $wdh],
        'WLt' => [$wmLightJpeg, $wlw, $wlh],
        'LDk' => [$logoDarkJpeg, $ldw, $ldh],
        'LLt' => [$logoLightJpeg, $llw, $llh],
    ];

    $pages = [];
    $content = '';
    $y = 0.0;

    $hex = static function (string $hex): array {
        $hex = ltrim($hex, '#');
        return [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
    };
    $add = static function (string $s) use (&$content): void {
        $content .= $s;
    };
    $fillRgb = static function (string $c) use (&$add, $hex): void {
        [$r, $g, $b] = $hex($c);
        $add(sprintf("%.3f %.3f %.3f rg\n", $r, $g, $b));
    };
    $rect = static function (float $x, float $yy, float $rw, float $rh) use (&$add): void {
        $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $x, $yy, $rw, $rh));
    };
    $text = static function (float $x, float $yy, string $s, float $size, string $font = 'F1') use (&$add): void {
        $add(sprintf("BT /%s %.2f Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n", $font, $size, $x, $yy, vellisys_pdf_escape($s)));
    };
    $drawImg = static function (string $name, float $x, float $yy, float $dw, float $dh) use (&$add): void {
        $add(sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /%s Do Q\n", $dw, $dh, $x, $yy, $name));
    };
    $tileV = static function (string $name, float $size, float $step) use (&$drawImg, $w, $h): void {
        $row = 0;
        for ($py = -24.0; $py < $h + $size; $py += $step * 0.84) {
            $shift = ($row % 2) ? $step * 0.5 : 0.0;
            for ($px = -40.0 + $shift; $px < $w + $size; $px += $step) {
                $drawImg($name, $px, $py, $size, $size);
            }
            $row++;
        }
    };

    $newPage = static function (string $mode = 'light') use (
        &$content,
        &$pages,
        &$y,
        $h,
        $w,
        $fillRgb,
        $rect,
        $navy,
        $blue,
        $tileV,
        $drawImg,
        $ldw,
        $ldh,
        $llw,
        $llh
    ): void {
        if ($content !== '') {
            $pages[] = $content;
        }
        $content = '';
        $fillRgb($navy);
        $rect(0, 0, $w, $h);
        $tileV('PDk', 58, 86);
        $fillRgb($blue);
        $rect(0, $h - 8, $w, 8);
        $rect(0, 0, $w, 8);
        if ($mode === 'dark') {
            $wm = 340.0;
            $drawImg('WDk', ($w - $wm) / 2, ($h - $wm) / 2 - 8, $wm, $wm);
            $lh = 38.0;
            $lw = $lh * ($ldw / max($ldh, 1));
            $drawImg('LDk', 56, $h - 62, $lw, $lh);
        } else {
            $fillRgb('#FFFFFF');
            $rect(36, 28, $w - 72, $h - 56);
            $content .= "q\n36.00 28.00 " . sprintf('%.2f %.2f', $w - 72, $h - 56) . " re W n\n";
            $tileV('PLt', 64, 92);
            $wm = 300.0;
            $drawImg('WLt', ($w - $wm) / 2, ($h - $wm) / 2 - 6, $wm, $wm);
            $content .= "Q\n";
            $lh = 26.0;
            $lw = $lh * ($llw / max($llh, 1));
            $drawImg('LLt', $w - 48 - $lw, $h - 62, $lw, $lh);
        }
        $y = $h - 48;
    };

    $wrap = static function (string $text, int $width) {
        return vellisys_pdf_wrap($text, $width);
    };

    $para = static function (float $x, string $body, float $size, string $color, int $width, float $leading) use (&$y, &$text, &$fillRgb, $wrap): void {
        $fillRgb($color);
        foreach ($wrap($body, $width) as $line) {
            $text($x, $y, $line, $size);
            $y -= $leading;
        }
    };

    // --- 1 Cover ---
    $newPage('dark');
    $fillRgb($blue);
    $rect(0, 70, 18, $h - 78);
    $fillRgb($gold);
    $rect(18, 70, 6, $h - 78);
    $fillRgb($gold);
    $text(56, 478, 'CLIENT PITCH DECK', 11, 'F2');
    $fillRgb('#FFFFFF');
    $text(56, 430, 'Branded books for companies', 28, 'F2');
    $text(56, 396, 'that cannot lose the paper.', 28, 'F2');
    $fillRgb('#C9D2EE');
    $para(56, 'Quotations, invoices, receipts, expenses, delivery notes, headed letters and reports in your logo, colours, currency and tax. One desk. Yearly packages. Built for East Africa and used anywhere a company still sends paper that must look like it left their office.', 12, '#C9D2EE', 78, 16);
    $y = 210;
    $fillRgb($gold);
    $text(56, $y, 'www.vellisys.com  ·  info@vellisys.com', 12, 'F2');
    $y -= 20;
    $fillRgb('#FFFFFF');
    $text(56, $y, '+256 779 971 024  ·  +256 756 524 451', 12);
    $y -= 20;
    $text(56, $y, 'Front Star Digital  ·  Confidential to the company you are visiting', 10);
    $fillRgb('#FFFFFF');
    $text(56, 96, 'Send this pack before a demo. It is the whole system, the packages, and how we start together.', 10);

    // --- 2 Pain ---
    $newPage();
    $fillRgb($navy);
    $text(56, 530, 'The books are leaking.', 22, 'F2');
    $fillRgb($muted);
    $text(56, 508, 'Common pains Vellisys is built to close', 12);
    $cols = [
        [
            'Quotes live in Word. Invoices in Excel. Receipts in a pad. Nobody can show the same story twice.',
            'The sheet that leaves the office does not look like the company. Logo missing, colours wrong, tax guessed.',
            'Part payments are rewritten on the invoice. Debtors become a rumour.',
            'Staff email from personal Gmail. Clients never know if the bill is real.',
        ],
        [
            'QuickBooks and Xero assume a monthly SaaS seat, US or UK payroll, and a bookkeeper who already lives in that product.',
            'Spreadsheets do not age, do not remind, and do not convert a quote to an invoice.',
            'Tax is hardcoded in the owner\'s head: 18% here, 16% there, until a sheet is wrong.',
            'Onboarding a new country means a new tool, a new login culture, and another invoice from Silicon Valley.',
        ],
    ];
    $x0 = 56;
    foreach ($cols as $ci => $list) {
        $x = $x0 + $ci * 380;
        $yy = 468;
        foreach ($list as $item) {
            $fillRgb($paper);
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $x, $yy - 78, 360, 88));
            $fillRgb($blue);
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $x, $yy - 78, 6, 88));
            $fillRgb($navy);
            $ty = $yy - 18;
            foreach ($wrap($item, 52) as $line) {
                $text($x + 18, $ty, $line, 10);
                $ty -= 13;
            }
            $yy -= 100;
        }
    }

    // --- 3 What it is ---
    $newPage();
    $fillRgb($navy);
    $text(56, 530, 'What Vellisys is', 22, 'F2');
    $y = 500;
    $para(56, 'Vellisys is branded books software. A company desk: the people who raise quotes, issue invoices, take receipts, log expenses, chase debtors and write headed letters. Super admin at Vellisys onboard the company, assign a sending mailbox, and keep the paid term honest.', 12, $ink, 110, 16);
    $y -= 8;
    $bits = [
        'Your paper' => 'Logo, two brand colours, one document design for the whole desk. Print, PDF, WhatsApp link or email from the company mailbox.',
        'Your money' => 'Any three-letter currency. USD on a sheet if you need it. You set the rate. Reports add it back to home currency.',
        'Your tax' => 'Name it VAT, GST, SST, IVA. Set the percent. Taxed lines use that rate. Old sheets keep the rate they were saved with.',
        'Your seats' => 'One, two or three logins. Company admin plus Books or Sales access. Not an unlimited cloud that bills per extra user forever.',
    ];
    $i = 0;
    foreach ($bits as $title => $body) {
        $bx = 56 + ($i % 2) * 380;
        $by = $i < 2 ? 300 : 140;
        $fillRgb($paper);
        $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $bx, $by, 360, 130));
        $fillRgb($blue);
        $text($bx + 16, $by + 102, strtoupper($title), 11, 'F2');
        $fillRgb($ink);
        $ty = $by + 82;
        foreach ($wrap($body, 50) as $line) {
            $text($bx + 16, $ty, $line, 10);
            $ty -= 13;
        }
        $i++;
    }

    // --- 4 Compare ---
    $newPage();
    $fillRgb($navy);
    $text(56, 530, 'Beside QuickBooks, Xero and Sage', 22, 'F2');
    $fillRgb($muted);
    $text(56, 508, 'Different job. Different price. Different paper.', 12);
    $headers = ['', 'Vellisys', 'QuickBooks', 'Xero', 'Sage'];
    $rows = [
        ['What it is', 'Branded books desk', 'Full SME accounting', 'Cloud ledgers + apps', 'Accounting suites'],
        ['Paper that leaves', 'Your logo, colours, tax', 'Generic / templates', 'Generic / add-ons', 'Forms, not stationery'],
        ['Billing', 'Per year, 3 packages', 'Monthly SaaS seats', 'Monthly + payroll', 'Licence + partners'],
        ['East Africa pay', 'Pesapal, mobile money', 'Cards, region limits', 'Cards, region limits', 'Partner billing'],
        ['Tax', 'You name it and set %', 'Tax codes / setups', 'Tax rates / GST', 'Tax engines'],
        ['Onboarding', 'Vellisys walks you in', 'Self-serve + partners', 'Self-serve + advisors', 'Implementers'],
        ['Mailbox', 'Company Hostinger/Titan', 'Your own SMTP / none', 'Your own SMTP', 'Desktop / add-ins'],
        ['Best when', 'You sell on paper', 'You need US payroll', 'You have an accountant', 'You already run Sage'],
    ];
    $colW = [118, 132, 132, 132, 132];
    $yy = 478;
    $fillRgb($navy);
    $add(sprintf("%.2f %.2f %.2f %.2f re f\n", 48, $yy - 8, 746, 22));
    $fillRgb('#FFFFFF');
    $cx = 56;
    foreach ($headers as $i => $lab) {
        $text($cx, $yy, $lab, 9, 'F2');
        $cx += $colW[$i];
    }
    $yy -= 28;
    foreach ($rows as $ri => $row) {
        if ($ri % 2 === 0) {
            $fillRgb($paper);
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", 48, $yy - 8, 746, 22));
        }
        $cx = 56;
        foreach ($row as $ci => $cell) {
            $fillRgb($ci === 1 ? $blue : $ink);
            $text($cx, $yy, $cell, 8, $ci <= 1 ? 'F2' : 'F1');
            $cx += $colW[$ci];
        }
        $yy -= 22;
    }
    $fillRgb($muted);
    $text(56, 70, 'We do not replace a Big Four audit pack. We replace the lost quote, the unbranded invoice, and the spreadsheet that only one person understands.', 9);

    // --- 5 Features books ---
    $newPage();
    $fillRgb($navy);
    $text(56, 530, 'The books', 22, 'F2');
    $fillRgb($muted);
    $text(56, 508, 'Everything that used to live in five folders', 12);
    $feats = [
        'Quotations that convert to invoices without retyping lines, tax or currency.',
        'Invoices with due dates, part receipts, and balances that stay visible until they are cleared.',
        'Receipts that print RECEIVED and DUE, in the same stationery as the invoice.',
        'Expenses against suppliers, with categories that feed reports.',
        'Delivery notes: quantities out, no prices. Return notes when goods come back.',
        'Refunds in and out, linked when you can, so Profit & Loss stays honest.',
        'Debtors list with branded reminders from the company mailbox.',
        'Creditors list with a note or a letter when you need to write to a supplier.',
        'Headed letters and custom documents on the same paper as the books.',
        'Reports: income, collections, outstanding, tax due, aging, quote conversion, CSV export.',
        'Profit & Loss on Crest: other income and costs beside invoices and expenses.',
        'Edit a saved sheet. Void when it is dead. Number formats you control.',
    ];
    foreach ($feats as $i => $f) {
        $x = ($i % 2 === 0) ? 56 : 430;
        $rowY = 470 - (int) floor($i / 2) * 58;
        $fillRgb($paper);
        $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $x, $rowY - 38, 350, 50));
        $fillRgb($blue);
        $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $x, $rowY - 38, 5, 50));
        $fillRgb($ink);
        $ty = $rowY - 8;
        foreach ($wrap($f, 50) as $line) {
            $text($x + 16, $ty, $line, 9);
            $ty -= 12;
        }
    }

    // --- 6 Features desk ---
    $newPage();
    $fillRgb($navy);
    $text(56, 530, 'The desk around the books', 22, 'F2');
    $desk = [
        ['Brand', 'Primary and accent colours. Logo with optional white plate. Twelve layouts: Folio bar, Colour ledger, Corner bill, Accent bill, Twin copy, Accent stripe, Estate panel, Harbour block, watermarks and more. One choice reprints every sheet.'],
        ['People', 'Up to three seats. Company admin opens Settings, Reports and the people list. Extra seats are Books or Sales so a salesperson cannot open the whole ledger.'],
        ['Mail', 'Vellisys assigns a Hostinger or Titan mailbox. Quotes, invoices, receipts, letters and debtor reminders leave from that address with the logo on a white band. Personal Gmail stays out of the books.'],
        ['Planner', 'On Ledger and Crest: notes, budget targets, calendar, notifications. Receive on the bell when money should be logged.'],
        ['Share', 'Print the A4 sheet. Save PDF. WhatsApp the link. Email the sheet. The client sees paper, not the desk. On a phone the page is still A4, scaled to fit.'],
        ['Pay us', 'Pesapal in the same tab: mobile money, cards, bank. Or register without paying. Or book a demo. Super admin still walks the company in.'],
    ];
    $yy = 455;
    foreach ($desk as $row) {
        $fillRgb($blue);
        $text(56, $yy, strtoupper($row[0]), 10, 'F2');
        $fillRgb($ink);
        $ty = $yy - 16;
        foreach ($wrap($row[1], 108) as $line) {
            $text(56, $ty, $line, 10);
            $ty -= 13;
        }
        $yy = $ty - 10;
    }

    // --- 7 Packages ---
    $newPage();
    $fillRgb($navy);
    $text(56, 530, 'Packages, billed per year', 22, 'F2');
    $fillRgb($muted);
    $text(56, 508, 'List prices in Uganda shillings. Change currency on www.vellisys.com. Discount shown as was / now.', 11);
    $pkgs = [
        ['QUILL', 'Starting', 'UGX 150,000', 'was 200,000', '1 seat', [
            'Company admin login',
            'Branded quotations, invoices, receipts',
            'Clients, debtors, email and WhatsApp share',
            'Print and PDF',
            'Reports for the person who signs in',
        ]],
        ['LEDGER', 'Most companies', 'UGX 200,000', 'was 280,000', '2 seats', [
            'Admin plus one login (Books or Sales)',
            'Everything in Quill',
            'Expenses, creditors, delivery notes',
            'Planner notes, budget, calendar',
            'Headed letters from the company mailbox',
        ]],
        ['CREST', 'Full house', 'UGX 250,000', 'was 350,000', '3 seats', [
            'Admin plus two logins',
            'Everything in Ledger',
            'Custom documents and every layout',
            'Profit & Loss, refunds, return notes',
            'Priority onboarding from Vellisys',
        ]],
    ];
    foreach ($pkgs as $i => $p) {
        $x = 48 + $i * 258;
        $fillRgb($i === 1 ? $navy : $paper);
        $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $x, 78, 246, 410));
        $titleC = $i === 1 ? '#FFFFFF' : $navy;
        $bodyC = $i === 1 ? '#D5DCF0' : $ink;
        $fillRgb($i === 1 ? $gold : $blue);
        $text($x + 16, 460, $p[1], 9, 'F2');
        $fillRgb($titleC);
        $text($x + 16, 438, $p[0], 18, 'F2');
        $fillRgb($i === 1 ? $gold : $blue);
        $text($x + 16, 410, $p[2], 13, 'F2');
        $fillRgb($bodyC);
        $text($x + 16, 392, $p[3] . '  ·  ' . $p[4] . '  ·  per year', 8);
        $ty = 368;
        foreach ($p[5] as $pt) {
            $fillRgb($i === 1 ? $gold : $blue);
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $x + 16, $ty + 1, 5, 5));
            $fillRgb($bodyC);
            foreach ($wrap($pt, 32) as $line) {
                $text($x + 28, $ty, $line, 9);
                $ty -= 13;
            }
            $ty -= 6;
        }
    }

    // --- 8 Ease ---
    $newPage();
    $fillRgb($navy);
    $text(56, 530, 'How Vellisys eases the work', 22, 'F2');
    $y = 490;
    $ease = [
        'Monday: raise a quote in the company colours. Share the link. When they accept, press Invoice. Lines, tax and currency copy.',
        'When money lands: record a receipt against the invoice. Part payment is a receipt, not a rewritten bill. Debtors drop by themselves.',
        'When goods leave: a delivery note with quantities. The invoice stays the bill. The driver carries paper that matches the office.',
        'When you spend: log the supplier and the tax. Reports tot income, expenses and tax due for the period you pick.',
        'When someone is late: open Debtors, send a reminder from the company mailbox. It looks like the rest of your stationery.',
        'When you hire a second person: give them Sales so they can quote without opening Settings or the full reports.',
        'When you open in another country: set currency, tax name and tax percent. You do not wait for a global vendor to add your rate.',
        'When the year turns: one invoice from Vellisys, not a surprise monthly SaaS that grew with seats you forgot.',
    ];
    foreach ($ease as $item) {
        $fillRgb($blue);
        $add(sprintf("%.2f %.2f %.2f %.2f re f\n", 56, $y + 1, 6, 6));
        $fillRgb($ink);
        foreach ($wrap($item, 108) as $line) {
            $text(72, $y, $line, 11);
            $y -= 14;
        }
        $y -= 8;
    }

    // --- 9 Onboarding ---
    $newPage();
    $fillRgb($navy);
    $text(56, 530, 'A note as you start', 22, 'F2');
    $y = 498;
    $letter = [
        'Welcome to Vellisys.',
        'You are not buying another login to a foreign accounts cloud. You are opening a desk that prints like your office and keeps the books in one place.',
        'This is how we start, together:',
        '1. You pick Quill, Ledger or Crest, or you ask us to recommend one from how many people will sign in and whether you need Planner and Profit & Loss.',
        '2. You pay on Pesapal, or we invoice you, or you register and we call. There is no password until the desk is opened on purpose.',
        '3. We create the company, assign the sending mailbox, and send a welcome from info@vellisys.com with a short tutorial.',
        '4. You sign in. Settings opens first: logo, colours, TIN, tax name and rate, currency, bank, document prefix. Save once. Every sheet follows.',
        '5. Add the people who will work the desk. Add the first clients. Raise the first quotation. Convert it when they say yes.',
        'We stay on the line for branding, the first documents, and the mailbox. If something is unclear, write to info@vellisys.com or call the numbers on the last page. You should never have to invent a workaround in Excel to make Vellisys look true.',
        'We are glad you are here.',
        'The Vellisys desk  ·  Front Star Digital',
    ];
    foreach ($letter as $p) {
        $fillRgb($ink);
        $size = str_starts_with($p, 'Welcome') || str_starts_with($p, 'The Vellisys') ? 12 : 10;
        $font = str_starts_with($p, 'Welcome') || str_starts_with($p, 'The Vellisys') ? 'F2' : 'F1';
        foreach ($wrap($p, 108) as $line) {
            $text(56, $y, $line, $size, $font);
            $y -= $size + 4;
        }
        $y -= 6;
    }

    // --- 10 Close ---
    $newPage('dark');
    $fillRgb($blue);
    $rect(0, 70, 18, $h - 78);
    $fillRgb($gold);
    $rect(18, 70, 6, $h - 78);
    $fillRgb('#FFFFFF');
    $text(56, 500, 'Next step', 14, 'F2');
    $text(56, 460, 'Open the desk with us.', 26, 'F2');
    $y = 420;
    $para(56, 'Pay at www.vellisys.com, register without paying, or book a demo. Tell us the company name, how many people will sign in, the currency you bill in, and the tax you charge. We will match a package and open the books.', 12, '#C9D2EE', 90, 16);
    $y = 300;
    $fillRgb($gold);
    $text(56, $y, 'www.vellisys.com', 14, 'F2');
    $y -= 22;
    $fillRgb('#FFFFFF');
    $text(56, $y, 'info@vellisys.com', 14, 'F2');
    $y -= 22;
    $text(56, $y, '+256 779 971 024', 14, 'F2');
    $y -= 20;
    $text(56, $y, '+256 756 524 451', 14, 'F2');
    $y -= 36;
    $fillRgb('#C9D2EE');
    $text(56, $y, 'Front Star Digital  ·  Vellisys branded books', 11);
    $y -= 18;
    $text(56, $y, 'This deck is for the company named in your email or meeting. Figures are yearly list prices in UGX as published on the site.', 9);
    $fillRgb('#FFFFFF');
    $text(56, 96, 'Thank you for reading. We will make the paper look like you.', 11, 'F2');

    $pages[] = $content;
    $nPages = count($pages);
    $imgNames = array_keys($images);
    $font1 = 3 + 2 * $nPages;
    $font2 = $font1 + 1;
    $imgIds = [];
    $imgObj = $font2 + 1;
    foreach ($imgNames as $name) {
        $imgIds[$name] = $imgObj;
        $imgObj++;
    }
    $xObj = [];
    foreach ($imgIds as $name => $id) {
        $xObj[] = '/' . $name . ' ' . $id . ' 0 R';
    }
    $xObjStr = implode(' ', $xObj);

    $objs = [];
    $objs[] = '<< /Type /Catalog /Pages 2 0 R >>';
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
        $objs[] = sprintf(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Contents %d 0 R /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >> /XObject << %s >> >> >>',
            $w,
            $h,
            $contentIds[$i],
            $font1,
            $font2,
            $xObjStr
        );
        $objs[] = '<< /Length ' . strlen($body) . " >>\nstream\n" . $body . 'endstream';
    }
    $objs[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    $objs[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
    foreach ($imgNames as $name) {
        [$jpeg, $iw, $ih] = $images[$name];
        $objs[] = vellisys_pdf_image_object($jpeg, $iw, $ih);
    }

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
    $out .= 'trailer << /Size ' . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n" . $start . "\n%%EOF";
    return $out;
}
