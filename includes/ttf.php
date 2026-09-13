<?php
declare(strict_types=1);

/**
 * Read a TrueType font enough to embed it as a WinAnsi PDF font.
 *
 * @return array{name:string,units:int,ascent:int,descent:int,cap:int,bbox:array{0:int,1:int,2:int,3:int},italic:float,stem:int,flags:int,widths:array<int,int>,file:string}
 */
function vellisys_ttf_for_pdf(string $path): array
{
    $raw = (string) file_get_contents($path);
    if ($raw === '' || strlen($raw) < 12) {
        throw new RuntimeException('Could not read font ' . $path);
    }
    $u16 = static function (string $s, int $o): int {
        $v = unpack('n', substr($s, $o, 2));
        return $v ? (int) $v[1] : 0;
    };
    $i16 = static function (string $s, int $o) use ($u16): int {
        $v = $u16($s, $o);
        return $v >= 0x8000 ? $v - 0x10000 : $v;
    };
    $u32 = static function (string $s, int $o): int {
        $v = unpack('N', substr($s, $o, 4));
        return $v ? (int) $v[1] : 0;
    };

    $numTables = $u16($raw, 4);
    $tables = [];
    for ($i = 0; $i < $numTables; $i++) {
        $off = 12 + $i * 16;
        $tag = substr($raw, $off, 4);
        $tables[$tag] = ['offset' => $u32($raw, $off + 8), 'length' => $u32($raw, $off + 12)];
    }
    $table = static function (string $tag) use ($tables, $raw): string {
        if (!isset($tables[$tag])) {
            throw new RuntimeException('Font missing table ' . $tag);
        }
        return substr($raw, $tables[$tag]['offset'], $tables[$tag]['length']);
    };

    $head = $table('head');
    $units = $u16($head, 18);
    $xMin = $i16($head, 36);
    $yMin = $i16($head, 38);
    $xMax = $i16($head, 40);
    $yMax = $i16($head, 42);

    $hhea = $table('hhea');
    $ascent = $i16($hhea, 4);
    $descent = $i16($hhea, 6);
    $numH = $u16($hhea, 34);

    $maxp = $table('maxp');
    $numGlyphs = $u16($maxp, 4);

    $hmtx = $table('hmtx');
    $advances = [];
    $last = 0;
    for ($g = 0; $g < $numGlyphs; $g++) {
        if ($g < $numH) {
            $last = $u16($hmtx, $g * 4);
        }
        $advances[$g] = $last;
    }

    $cmap = $table('cmap');
    $numEnc = $u16($cmap, 2);
    $cmapOff = 0;
    for ($i = 0; $i < $numEnc; $i++) {
        $plat = $u16($cmap, 4 + $i * 8);
        $enc = $u16($cmap, 6 + $i * 8);
        $o = $u32($cmap, 8 + $i * 8);
        $fmt = $u16($cmap, $o);
        if ($fmt === 4 && (($plat === 3 && ($enc === 1 || $enc === 10)) || $plat === 0)) {
            $cmapOff = $o;
            if ($plat === 3) {
                break;
            }
        }
    }
    if ($cmapOff === 0) {
        throw new RuntimeException('No Unicode cmap in ' . $path);
    }
    $segCount = $u16($cmap, $cmapOff + 6) / 2;
    $endCountOff = $cmapOff + 14;
    $startCountOff = $endCountOff + 2 + $segCount * 2;
    $idDeltaOff = $startCountOff + $segCount * 2;
    $idRangeOff = $idDeltaOff + $segCount * 2;
    $glyphFor = [];
    for ($s = 0; $s < $segCount; $s++) {
        $end = $u16($cmap, $endCountOff + $s * 2);
        $start = $u16($cmap, $startCountOff + $s * 2);
        $delta = $i16($cmap, $idDeltaOff + $s * 2);
        $range = $u16($cmap, $idRangeOff + $s * 2);
        for ($c = $start; $c <= $end; $c++) {
            if ($range === 0) {
                $gid = ($c + $delta) & 0xFFFF;
            } else {
                $ro = $idRangeOff + $s * 2 + $range + ($c - $start) * 2;
                $gid = $u16($cmap, $ro);
                if ($gid !== 0) {
                    $gid = ($gid + $delta) & 0xFFFF;
                }
            }
            $glyphFor[$c] = $gid;
        }
    }

    $os2 = $tables['OS/2'] ?? null;
    $cap = $ascent;
    $stem = 80;
    $italic = 0.0;
    if ($os2) {
        $os = $table('OS/2');
        if (strlen($os) >= 90) {
            $cap = $i16($os, 88) ?: $cap;
        }
        if (strlen($os) >= 70) {
            $weight = $u16($os, 4);
            $stem = $weight >= 700 ? 120 : 80;
        }
    }
    $post = $table('post');
    $italic = $i16($post, 4) + ($u16($post, 6) / 65536);

    $scale = 1000 / max($units, 1);
    $widths = [];
    for ($code = 32; $code <= 255; $code++) {
        $uni = $code;
        if ($code >= 128) {
            $map = [
                128 => 0x20AC, 130 => 0x201A, 131 => 0x0192, 132 => 0x201E, 133 => 0x2026,
                134 => 0x2020, 135 => 0x2021, 136 => 0x02C6, 137 => 0x2030, 138 => 0x0160,
                139 => 0x2039, 140 => 0x0152, 142 => 0x017D, 145 => 0x2018, 146 => 0x2019,
                147 => 0x201C, 148 => 0x201D, 149 => 0x2022, 150 => 0x2013, 151 => 0x2014,
                152 => 0x02DC, 153 => 0x2122, 154 => 0x0161, 155 => 0x203A, 156 => 0x0153,
                158 => 0x017E, 159 => 0x0178,
            ];
            $uni = $map[$code] ?? $code;
        }
        $gid = $glyphFor[$uni] ?? ($glyphFor[0x3F] ?? 0);
        $widths[$code] = (int) round(($advances[$gid] ?? 0) * $scale);
    }

    $flags = 32;
    if ($stem >= 110) {
        $flags |= 262144;
    }

    return [
        'name' => 'Montserrat',
        'units' => $units,
        'ascent' => (int) round($ascent * $scale),
        'descent' => (int) round($descent * $scale),
        'cap' => (int) round($cap * $scale),
        'bbox' => [
            (int) round($xMin * $scale),
            (int) round($yMin * $scale),
            (int) round($xMax * $scale),
            (int) round($yMax * $scale),
        ],
        'italic' => $italic,
        'stem' => $stem,
        'flags' => $flags,
        'widths' => $widths,
        'file' => $raw,
    ];
}

function vellisys_pdf_ttf_objects(array $font, string $baseFont, int $descId, int $fileId): array
{
    $widths = [];
    for ($i = 32; $i <= 255; $i++) {
        $widths[] = (string) ($font['widths'][$i] ?? 500);
    }
    $compressed = gzcompress($font['file'], 9);
    if ($compressed === false) {
        $compressed = $font['file'];
        $filter = '';
    } else {
        $filter = '/Filter /FlateDecode ';
    }
    $fontObj = sprintf(
        '<< /Type /Font /Subtype /TrueType /BaseFont /%s /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 255 /Widths [%s] /FontDescriptor %d 0 R >>',
        $baseFont,
        implode(' ', $widths),
        $descId
    );
    $descObj = sprintf(
        '<< /Type /FontDescriptor /FontName /%s /Flags %d /FontBBox [%d %d %d %d] /ItalicAngle %.2f /Ascent %d /Descent %d /CapHeight %d /StemV %d /FontFile2 %d 0 R >>',
        $baseFont,
        $font['flags'],
        $font['bbox'][0],
        $font['bbox'][1],
        $font['bbox'][2],
        $font['bbox'][3],
        $font['italic'],
        $font['ascent'],
        $font['descent'],
        $font['cap'],
        $font['stem'],
        $fileId
    );
    $fileObj = sprintf(
        "<< %s/Length %d /Length1 %d >>\nstream\n%s\nendstream",
        $filter,
        strlen($compressed),
        strlen($font['file']),
        $compressed
    );
    return [$fontObj, $descObj, $fileObj];
}
