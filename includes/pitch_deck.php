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

require_once __DIR__ . '/ttf.php';

/**
 * Landscape A4 client pitch deck (navy / Vellisys blue, Montserrat).
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
    $logoDarkIm = vellisys_pdf_flatten_on($logo, '#08143A', 1.0, 4);
    $logoLightIm = vellisys_pdf_flatten_on($logo, '#FFFFFF', 1.0, 4);
    [$patDarkJpeg, $pdw, $pdh] = vellisys_pdf_jpeg($patDarkIm, 80);
    [$patLightJpeg, $plw, $plh] = vellisys_pdf_jpeg($patLightIm, 80);
    [$logoDarkJpeg, $ldw, $ldh] = vellisys_pdf_jpeg($logoDarkIm, 90);
    [$logoLightJpeg, $llw, $llh] = vellisys_pdf_jpeg($logoLightIm, 90);
    imagedestroy($mark);
    imagedestroy($logo);
    imagedestroy($patDarkIm);
    imagedestroy($patLightIm);
    imagedestroy($logoDarkIm);
    imagedestroy($logoLightIm);

    $images = [
        'PDk' => [$patDarkJpeg, $pdw, $pdh],
        'PLt' => [$patLightJpeg, $plw, $plh],
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

    $circleFill = static function (float $cx, float $cy, float $r) use (&$add): void {
        $k = 0.55228475 * $r;
        $add(sprintf("%.2f %.2f m\n", $cx + $r, $cy));
        $add(sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $cx + $r, $cy + $k, $cx + $k, $cy + $r, $cx, $cy + $r));
        $add(sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $cx - $k, $cy + $r, $cx - $r, $cy + $k, $cx - $r, $cy));
        $add(sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $cx - $r, $cy - $k, $cx - $k, $cy - $r, $cx, $cy - $r));
        $add(sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $cx + $k, $cy - $r, $cx + $r, $cy - $k, $cx + $r, $cy));
        $add("f\n");
    };

    $icon = static function (float $x, float $y, string $kind, float $size = 22.0) use (&$add, $fillRgb, $circleFill, $blue): void {
        $r = $size / 2;
        $cx = $x + $r;
        $cy = $y + $r;
        $fillRgb($blue);
        $circleFill($cx, $cy, $r);
        $fillRgb('#FFFFFF');
        $s = $size / 22.0;
        if ($kind === 'file') {
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $cx - 5 * $s, $cy - 6.5 * $s, 9 * $s, 12 * $s));
            $fillRgb($blue);
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $cx + 0.5 * $s, $cy + 1.5 * $s, 3.5 * $s, 4 * $s));
        } elseif ($kind === 'people') {
            $circleFill($cx - 2.2 * $s, $cy + 3.2 * $s, 2.5 * $s);
            $circleFill($cx + 3.4 * $s, $cy + 2.4 * $s, 1.9 * $s);
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $cx - 6.2 * $s, $cy - 6.2 * $s, 7.4 * $s, 5.2 * $s));
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $cx + 1.4 * $s, $cy - 5.4 * $s, 5.4 * $s, 4.2 * $s));
        } elseif ($kind === 'mail') {
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $cx - 6.2 * $s, $cy - 4.2 * $s, 12.4 * $s, 8.4 * $s));
            $add("1 1 1 RG 1.2 w\n");
            $add(sprintf("%.2f %.2f m %.2f %.2f l %.2f %.2f l S\n", $cx - 6.2 * $s, $cy + 4.2 * $s, $cx, $cy - 0.6 * $s, $cx + 6.2 * $s, $cy + 4.2 * $s));
        } elseif ($kind === 'tax') {
            $circleFill($cx - 3.2 * $s, $cy + 3.2 * $s, 2.1 * $s);
            $circleFill($cx + 3.2 * $s, $cy - 3.2 * $s, 2.1 * $s);
            $add("1 1 1 RG 1.6 w 1 J\n");
            $add(sprintf("%.2f %.2f m %.2f %.2f l S\n", $cx + 4.2 * $s, $cy + 4.6 * $s, $cx - 4.2 * $s, $cy - 4.6 * $s));
        } elseif ($kind === 'money') {
            $add("1 1 1 RG 1.5 w\n");
            $rr = 6.2 * $s;
            $k = 0.55228475 * $rr;
            $add(sprintf("%.2f %.2f m\n", $cx + $rr, $cy));
            $add(sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $cx + $rr, $cy + $k, $cx + $k, $cy + $rr, $cx, $cy + $rr));
            $add(sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $cx - $k, $cy + $rr, $cx - $rr, $cy + $k, $cx - $rr, $cy));
            $add(sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $cx - $rr, $cy - $k, $cx - $k, $cy - $rr, $cx, $cy - $rr));
            $add(sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $cx + $k, $cy - $rr, $cx + $rr, $cy - $k, $cx + $rr, $cy));
            $add("S\n");
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $cx - 1.1 * $s, $cy - 4.4 * $s, 2.2 * $s, 8.8 * $s));
        } elseif ($kind === 'chart') {
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $cx - 6 * $s, $cy - 6 * $s, 3.2 * $s, 7 * $s));
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $cx - 1.6 * $s, $cy - 6 * $s, 3.2 * $s, 10.5 * $s));
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $cx + 2.8 * $s, $cy - 6 * $s, 3.2 * $s, 5.2 * $s));
        } elseif ($kind === 'calendar') {
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $cx - 6.2 * $s, $cy - 5.5 * $s, 12.4 * $s, 11 * $s));
            $fillRgb($blue);
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $cx - 6.2 * $s, $cy + 2.6 * $s, 12.4 * $s, 2.9 * $s));
            $fillRgb('#FFFFFF');
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $cx - 3.6 * $s, $cy - 3.2 * $s, 2.2 * $s, 2.2 * $s));
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $cx + 1.4 * $s, $cy - 3.2 * $s, 2.2 * $s, 2.2 * $s));
        } elseif ($kind === 'send') {
            $add(sprintf("%.2f %.2f m %.2f %.2f l %.2f %.2f l %.2f %.2f l f\n", $cx - 6.5 * $s, $cy - 4.5 * $s, $cx + 6.5 * $s, $cy, $cx - 6.5 * $s, $cy + 4.5 * $s, $cx - 3.2 * $s, $cy));
        } elseif ($kind === 'truck') {
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $cx - 6.5 * $s, $cy - 2.2 * $s, 9.2 * $s, 6.4 * $s));
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $cx + 2.4 * $s, $cy - 2.2 * $s, 4.2 * $s, 4.2 * $s));
            $fillRgb($blue);
            $circleFill($cx - 3.4 * $s, $cy - 4.4 * $s, 1.7 * $s);
            $circleFill($cx + 3.6 * $s, $cy - 4.4 * $s, 1.7 * $s);
        } elseif ($kind === 'check') {
            $add("1 1 1 RG 2.1 w 1 J 1 j\n");
            $add(sprintf("%.2f %.2f m %.2f %.2f l %.2f %.2f l S\n", $cx - 5.2 * $s, $cy, $cx - 1.4 * $s, $cy - 4.2 * $s, $cx + 5.6 * $s, $cy + 4.4 * $s));
        } elseif ($kind === 'globe') {
            $add("1 1 1 RG 1.35 w\n");
            $rr = 6.4 * $s;
            $k = 0.55228475 * $rr;
            $add(sprintf("%.2f %.2f m\n", $cx + $rr, $cy));
            $add(sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $cx + $rr, $cy + $k, $cx + $k, $cy + $rr, $cx, $cy + $rr));
            $add(sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $cx - $k, $cy + $rr, $cx - $rr, $cy + $k, $cx - $rr, $cy));
            $add(sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $cx - $rr, $cy - $k, $cx - $k, $cy - $rr, $cx, $cy - $rr));
            $add(sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $cx + $k, $cy - $rr, $cx + $rr, $cy - $k, $cx + $rr, $cy));
            $add("S\n");
            $add(sprintf("%.2f %.2f m %.2f %.2f l S\n", $cx, $cy - $rr, $cx, $cy + $rr));
            $add(sprintf("%.2f %.2f m %.2f %.2f l S\n", $cx - $rr, $cy, $cx + $rr, $cy));
        } elseif ($kind === 'pack') {
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $cx - 6 * $s, $cy - 5.5 * $s, 12 * $s, 11 * $s));
            $fillRgb($blue);
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $cx - 1 * $s, $cy - 5.5 * $s, 2 * $s, 11 * $s));
        } else {
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $cx - 4 * $s, $cy - 4 * $s, 8 * $s, 8 * $s));
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
            $lh = 38.0;
            $lw = $lh * ($ldw / max($ldh, 1));
            $drawImg('LDk', 56, $h - 62, $lw, $lh);
        } else {
            $fillRgb('#FFFFFF');
            $rect(36, 28, $w - 72, $h - 56);
            $content .= "q\n36.00 28.00 " . sprintf('%.2f %.2f', $w - 72, $h - 56) . " re W n\n";
            $tileV('PLt', 64, 92);
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
    $text(56, 492, 'FOR YOUR COMPANY', 11, 'F2');
    $fillRgb('#FFFFFF');
    $text(56, 462, 'Business Made Effortless', 18, 'F2');
    $text(56, 412, 'You already know what it costs', 24, 'F2');
    $text(56, 384, 'when a quote goes missing.', 24, 'F2');
    $fillRgb('#C9D2EE');
    $para(56, 'Vellisys is the desk you open in the morning: quotations, invoices, receipts, expenses, letters and reports in your logo, your colours, your currency and your tax. One place. One year. We stay with you until it feels like your office, because it is.', 13, '#C9D2EE', 76, 17);
    $y = 210;
    $fillRgb($gold);
    $text(56, $y, 'www.vellisys.com  ·  info@vellisys.com', 12, 'F2');
    $y -= 20;
    $fillRgb('#FFFFFF');
    $text(56, $y, '+256 779 971 024  ·  +256 756 524 451', 12);
    $y -= 20;
    $text(56, $y, 'Front Star Digital  ·  Written for the company you are sitting with', 10);
    $fillRgb('#FFFFFF');
    $text(56, 96, 'Read this through. By the last page the next step should feel obvious.', 11, 'F2');

    // --- 2 Pain ---
    $newPage();
    $fillRgb($navy);
    $text(56, 530, 'You have already paid for this.', 22, 'F2');
    $fillRgb($muted);
    $text(56, 508, 'Not in cash. In time, in awkward follow-ups, in money you were not sure had landed.', 12);
    $cols = [
        [
            ['file', 'The quote was in Word. The invoice in a spreadsheet. The receipt in a pad. When a client calls, three people tell three stories.'],
            ['globe', 'The paper that leaves the office does not look like you. Wrong colour. Missing logo. Tax guessed. They notice.'],
            ['money', 'Someone rewrites the invoice when a part payment comes in. Debtors become a feeling, not a list.'],
            ['mail', 'Bills go out from a personal Gmail. The client wonders if it is real. You wonder if it arrived.'],
        ],
        [
            ['people', 'You do not need a six-month project. You need a desk that already works the way you sell.'],
            ['file', 'A spreadsheet does not remind anyone. It does not turn a yes into an invoice. It waits until Friday panic.'],
            ['tax', 'The rate lives in someone\'s head. Eighteen here, sixteen there, until a sheet is wrong and you find out late.'],
            ['globe', 'The next country, the next currency, should be a setting. Not a new way of working.'],
        ],
    ];
    $x0 = 56;
    foreach ($cols as $ci => $list) {
        $x = $x0 + $ci * 380;
        $yy = 468;
        foreach ($list as $item) {
            [$kind, $copy] = $item;
            $fillRgb($paper);
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $x, $yy - 78, 360, 88));
            $fillRgb($blue);
            $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $x, $yy - 78, 6, 88));
            $icon($x + 16, $yy - 36, $kind, 22);
            $fillRgb($navy);
            $ty = $yy - 18;
            foreach ($wrap($copy, 46) as $line) {
                $text($x + 44, $ty, $line, 10);
                $ty -= 13;
            }
            $yy -= 100;
        }
    }

    // --- 3 What it is ---
    $newPage();
    $fillRgb($navy);
    $text(56, 530, 'This is the desk.', 22, 'F2');
    $y = 500;
    $para(56, 'Vellisys is simply the place your company keeps the books and the paper. You raise a quote, they say yes, you press Invoice. Money comes in, you write a receipt. Someone is late, you send a reminder that still looks like you. We onboard you. We give you a sending mailbox. You get back to selling.', 12, $ink, 108, 16);
    $y -= 8;
    $bits = [
        ['file', 'Your paper', 'Your logo. Two colours. One design for every sheet. Print it, send a link, WhatsApp it, or email it from the company mailbox. It looks like it left your office because it did.'],
        ['money', 'Your money', 'Bill in the currency you actually use. Put a USD sheet out when you need to. Set the rate once. The month still adds up.'],
        ['tax', 'Your tax', 'Call it VAT, GST, SST, IVA. Set the percent. New lines follow that rate. Old sheets keep what you already issued. No arguing with a default from somewhere else.'],
        ['people', 'Your people', 'One, two or three logins. You stay the admin. Give someone Books or Sales so they can work without seeing everything. That is enough for most companies.'],
    ];
    $i = 0;
    foreach ($bits as $bit) {
        [$kind, $title, $body] = $bit;
        $bx = 56 + ($i % 2) * 380;
        $by = $i < 2 ? 300 : 140;
        $fillRgb($paper);
        $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $bx, $by, 360, 130));
        $icon($bx + 16, $by + 94, $kind, 22);
        $fillRgb($blue);
        $text($bx + 46, $by + 102, strtoupper($title), 11, 'F2');
        $fillRgb($ink);
        $ty = $by + 82;
        foreach ($wrap($body, 48) as $line) {
            $text($bx + 16, $ty, $line, 10);
            $ty -= 13;
        }
        $i++;
    }

    // --- 4 How the desk works ---
    $newPage();
    $fillRgb($navy);
    $text(56, 530, 'What Monday feels like', 22, 'F2');
    $fillRgb($muted);
    $text(56, 508, 'After the desk is open, these stop being problems. They become the morning.', 12);
    $why = [
        ['file', 'One trail', 'Quote, invoice, receipt and letter wear the same face. Your client never has to wonder who sent it.'],
        ['check', 'Money that matches', 'A part payment is a receipt, not a rewrite. Debtors drop on their own. You can trust the number in the meeting.'],
        ['tax', 'Tax you chose', 'You name it. You set the percent. New work follows. Yesterday\'s sheets stay honest.'],
        ['money', 'Your currency', 'UGX, KES, EUR, USD, or whatever you type. Reports come home to the currency on the books.'],
        ['mail', 'Mail that belongs', 'It leaves from the mailbox we assign you. Logo on a white band. Not a personal inbox. Not a maybe.'],
        ['people', 'The right hands', 'Up to three people. Admin, Books or Sales. The person who quotes is not wandering through Settings.'],
        ['globe', 'Another country', 'Change the currency and the tax in Settings. You do not wait on anyone to "add your market".'],
        ['pack', 'One year, done', 'Quill, Ledger or Crest. Pay once for the year. Mobile money, card, or we invoice you. Then we open the desk.'],
    ];
    foreach ($why as $i => $row) {
        [$kind, $title, $body] = $row;
        $bx = 48 + ($i % 4) * 190;
        $by = $i < 4 ? 268 : 88;
        $fillRgb($paper);
        $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $bx, $by, 180, 168));
        $icon($bx + 16, $by + 130, $kind, 26);
        $fillRgb($navy);
        $text($bx + 16, $by + 112, $title, 11, 'F2');
        $fillRgb($ink);
        $ty = $by + 92;
        foreach ($wrap($body, 24) as $line) {
            $text($bx + 16, $ty, $line, 9);
            $ty -= 12;
        }
    }

    // --- 5 Features books ---
    $newPage();
    $fillRgb($navy);
    $text(56, 530, 'What you can do before lunch', 22, 'F2');
    $fillRgb($muted);
    $text(56, 508, 'This is the work that used to live in five folders and one person\'s laptop.', 12);
    $feats = [
        ['file', 'Write a quotation that still looks like you when they open it on their phone.'],
        ['file', 'When they say yes, turn it into an invoice without typing the lines again.'],
        ['check', 'Take a full payment or a part. The receipt says RECEIVED and DUE. The balance is true.'],
        ['money', 'Log what you spent. Fuel, rent, a supplier. Reports tot it without a Friday scramble.'],
        ['truck', 'Send a delivery note with quantities, not prices. The invoice stays the bill.'],
        ['money', 'Record a refund or a return so profit is not a guess.'],
        ['mail', 'See who still owes you. Send a reminder that looks like the rest of your paper.'],
        ['people', 'See who you still need to pay. Write to them from the same desk.'],
        ['file', 'Send a headed letter or a custom form on the same stationery as the books.'],
        ['chart', 'Ask the month a question: collected, outstanding, tax due, who converted.'],
        ['chart', 'On Crest, read profit with the other income and costs beside the invoices.'],
        ['check', 'Fix a saved sheet. Void one that is dead. Number it the way your office numbers things.'],
    ];
    foreach ($feats as $i => $f) {
        [$kind, $copy] = $f;
        $x = ($i % 2 === 0) ? 56 : 430;
        $rowY = 470 - (int) floor($i / 2) * 58;
        $fillRgb($paper);
        $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $x, $rowY - 38, 350, 50));
        $icon($x + 12, $rowY - 28, $kind, 20);
        $fillRgb($ink);
        $ty = $rowY - 8;
        foreach ($wrap($copy, 44) as $line) {
            $text($x + 40, $ty, $line, 9);
            $ty -= 12;
        }
    }

    // --- 6 Features desk ---
    $newPage();
    $fillRgb($navy);
    $text(56, 530, 'The rest of the office, in the same place', 20, 'F2');
    $desk = [
        ['file', 'Look', 'You pick two colours and a logo. Twelve papers to choose from. Change it once and every sheet reprints. Clients stop asking if you "have a letterhead".'],
        ['people', 'People', 'Three seats at most. You keep Settings and Reports. Give Sales to the person who quotes. They cannot wander. You sleep.'],
        ['mail', 'Mail', 'We assign the company mailbox. Quotes, invoices, letters and reminders leave from there. Personal Gmail stays personal.'],
        ['calendar', 'Planner', 'On Ledger and Crest: notes, a budget, a calendar, a bell when money should be logged. The desk remembers so you do not have to.'],
        ['send', 'Share', 'Print. Save a PDF. WhatsApp the link. Email the sheet. They see paper. They never see your books. On a phone it is still the A4 page, just smaller.'],
        ['pack', 'Pay', 'Pay in the same tab: mobile money, card, bank. Or ask us to invoice you. Or book a morning and we walk you through it. You are not left with a login and a shrug.'],
    ];
    $yy = 455;
    foreach ($desk as $row) {
        [$kind, $title, $body] = $row;
        $icon(56, $yy - 4, $kind, 18);
        $fillRgb($blue);
        $text(82, $yy, strtoupper($title), 10, 'F2');
        $fillRgb($ink);
        $ty = $yy - 16;
        foreach ($wrap($body, 104) as $line) {
            $text(82, $ty, $line, 10);
            $ty -= 13;
        }
        $yy = $ty - 10;
    }

    // --- 7 Packages ---
    $newPage();
    $fillRgb($navy);
    $text(56, 530, 'One year. One decision.', 22, 'F2');
    $fillRgb($muted);
    $text(56, 508, 'Prices in Uganda shillings. Change currency on the site. The crossed-out figure is what it was. Most companies take Ledger and get on with work.', 11);
    $pkgs = [
        ['QUILL', 'If it is just you', 'UGX 150,000', 'was 200,000', '1 seat', [
            'You, the company admin',
            'Quotes, invoices and receipts that look like you',
            'Clients, debtors, email and WhatsApp',
            'Print and PDF, no hunt',
            'Reports for the person who signs in',
        ]],
        ['LEDGER', 'Where most of us land', 'UGX 200,000', 'was 280,000', '2 seats', [
            'You, plus one (Books or Sales)',
            'Everything in Quill',
            'Expenses, creditors, delivery notes',
            'Planner: notes, budget, calendar, tasks',
            'Letters from the company mailbox',
            'Branches up to your logins; several people per shop',
        ]],
        ['CREST', 'The full house', 'UGX 250,000', 'was 350,000', '3 seats', [
            'You, plus two',
            'Everything in Ledger',
            'Custom documents and every layout',
            'Profit, refunds, return notes',
            'Branches up to your logins; several people per shop',
            'We onboard you first, properly',
        ]],
    ];
    foreach ($pkgs as $i => $p) {
        $x = 48 + $i * 258;
        $fillRgb($i === 1 ? $navy : $paper);
        $add(sprintf("%.2f %.2f %.2f %.2f re f\n", $x, 78, 246, 410));
        $titleC = $i === 1 ? '#FFFFFF' : $navy;
        $bodyC = $i === 1 ? '#D5DCF0' : $ink;
        $icon($x + 16, 456, 'pack', 20);
        $fillRgb($i === 1 ? $gold : $blue);
        $text($x + 42, 460, $p[1], 9, 'F2');
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
    $text(56, 530, 'A week that does not fight you', 22, 'F2');
    $y = 490;
    $ease = [
        'Monday. You write the quote in your colours and send the link. They accept. You press Invoice. The lines are already there.',
        'Money lands. You write a receipt against that invoice. If they paid part, you do not rewrite the bill. Debtors quietly gets smaller.',
        'Goods go out. The driver takes a delivery note. Quantities, not prices. The invoice is still the invoice.',
        'You spend. You log the supplier and the tax. Friday\'s report is not a reconstruction. It is a button.',
        'Someone is late. You open Debtors and send a reminder that looks like you. They take it seriously because it looks serious.',
        'You hire a second person. You give them Sales. They can quote. They cannot open the whole ledger. You stay in charge.',
        'You take a job in another currency. You set the tax and the rate in Settings. The desk does not need a committee.',
        'The year turns. One invoice from us. The desk you already know. That is the whole relationship.',
    ];
    foreach ($ease as $item) {
        $icon(56, $y - 3, 'check', 16);
        $fillRgb($ink);
        foreach ($wrap($item, 104) as $line) {
            $text(80, $y, $line, 11);
            $y -= 14;
        }
        $y -= 8;
    }

    // --- 9 Onboarding ---
    $newPage();
    $fillRgb($navy);
    $text(56, 530, 'If you are still with us, you already know.', 20, 'F2');
    $y = 498;
    $letter = [
        'Welcome. We are glad you read this far.',
        'You are not buying another password. You are opening a desk that prints like your office and keeps the books where you can find them.',
        'Here is how we start. It is short on purpose.',
        '1. Tell us how many people will sign in. We will say Quill, Ledger or Crest. Most companies take Ledger. If you need Planner and profit, take Crest.',
        '2. Pay on the site (mobile money or card), or we invoice you, or you book a morning and we sit together. There is no password until the desk is opened on purpose. That is how we keep it clean.',
        '3. We create the company, assign the mailbox, and write to you from info@vellisys.com with a short tutorial. You are not dumped at a blank screen.',
        '4. You sign in. Settings is first: logo, colours, TIN, tax name and rate, currency, bank. Save once. Every sheet from then on is you.',
        '5. Add the people. Add the first clients. Raise the first quotation. Convert it when they say yes. That first yes is the moment it clicks.',
        'We stay for the branding, the first documents, and the mailbox. If something is unclear, write or call. You should never have to invent a workaround in a spreadsheet to make this look true.',
        'The cost of waiting is another week of the same leak. The cost of starting is one year, at the rate on the last-but-one page.',
        'We would like to open the desk this week.',
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
    $text(56, 500, 'Pay. We open the desk.', 14, 'F2');
    $fillRgb($gold);
    $text(56, 472, 'Business Made Effortless', 16, 'F2');
    $fillRgb('#FFFFFF');
    $text(56, 430, 'This week is better than next month.', 22, 'F2');
    $y = 392;
    $para(56, 'Go to www.vellisys.com and take Ledger if you are unsure. Most people are. Tell us the company name, how many will sign in, the currency you bill in, and the tax you charge. We match the package and we open the books. If you would rather talk first, call. If you are ready, pay. Either way, the leak stops.', 13, '#C9D2EE', 88, 17);
    $y = 268;
    $fillRgb($gold);
    $text(56, $y, 'www.vellisys.com', 16, 'F2');
    $y -= 22;
    $fillRgb('#FFFFFF');
    $text(56, $y, 'info@vellisys.com', 16, 'F2');
    $y -= 22;
    $text(56, $y, '+256 779 971 024', 16, 'F2');
    $y -= 20;
    $text(56, $y, '+256 756 524 451', 16, 'F2');
    $y -= 32;
    $fillRgb('#C9D2EE');
    $text(56, $y, 'Front Star Digital  ·  Vellisys', 11);
    $y -= 16;
    $text(56, $y, 'Yearly prices in UGX as published on the site. Pay, and we treat it as the start of the work, not the end of a pitch.', 9);
    $fillRgb('#FFFFFF');
    $text(56, 96, 'Thank you for reading. Come. Let us make the paper look like you.', 12, 'F2');

    $pages[] = $content;
    $nPages = count($pages);
    $imgNames = array_keys($images);
    $font1 = 3 + 2 * $nPages;
    $font1Desc = $font1 + 1;
    $font1File = $font1 + 2;
    $font2 = $font1 + 3;
    $font2Desc = $font1 + 4;
    $font2File = $font1 + 5;
    $imgIds = [];
    $imgObj = $font2File + 1;
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
    $ttfReg = vellisys_ttf_for_pdf($root . '/assets/fonts/Montserrat-Regular.ttf');
    $ttfBold = vellisys_ttf_for_pdf($root . '/assets/fonts/Montserrat-Bold.ttf');
    [$f1, $d1, $file1] = vellisys_pdf_ttf_objects($ttfReg, 'Montserrat-Regular', $font1Desc, $font1File);
    [$f2, $d2, $file2] = vellisys_pdf_ttf_objects($ttfBold, 'Montserrat-Bold', $font2Desc, $font2File);
    $objs[] = $f1;
    $objs[] = $d1;
    $objs[] = $file1;
    $objs[] = $f2;
    $objs[] = $d2;
    $objs[] = $file2;
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
