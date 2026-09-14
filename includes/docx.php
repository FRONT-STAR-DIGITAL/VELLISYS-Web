<?php
declare(strict_types=1);

function logo_file(?array $brand = null): ?string
{
    $brand = $brand ?? branding();
    $path = ltrim((string) ($brand['logo_path'] ?? 'assets/img/ofagros-logo.png'), '/');
    foreach ([$path, 'assets/img/ofagros-logo.png'] as $rel) {
        $full = ROOT_PATH . '/' . $rel;
        if ($rel !== '' && is_file($full) && preg_match('/\.(png|jpe?g|gif)$/i', $rel)) {
            return $full;
        }
    }
    return null;
}

function docx_xml(string $s): string
{
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function docx_color(string $hex): string
{
    $hex = strtoupper(ltrim(trim($hex), '#'));
    if (!preg_match('/^[0-9A-F]{6}$/', $hex)) {
        return '1E4EFF';
    }
    return $hex;
}

function docx_p(string $text, array $opts = []): string
{
    $size = (int) ($opts['size'] ?? 22);
    $bold = !empty($opts['bold']);
    $color = $opts['color'] ?? '111111';
    $align = $opts['align'] ?? 'left';
    $spaceAfter = (int) ($opts['after'] ?? 120);
    $italic = !empty($opts['italic']);
    $runs = '';
    $parts = preg_split("/\r\n|\n|\r/", $text) ?: [''];
    foreach ($parts as $i => $line) {
        if ($i > 0) {
            $runs .= '<w:br/>';
        }
        $runs .= '<w:t xml:space="preserve">' . docx_xml($line) . '</w:t>';
    }
    $rPr = '<w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:eastAsia="Calibri"/>'
        . '<w:sz w:val="' . $size . '"/><w:szCs w:val="' . $size . '"/>'
        . '<w:color w:val="' . docx_xml($color) . '"/>'
        . ($bold ? '<w:b/>' : '')
        . ($italic ? '<w:i/>' : '')
        . '</w:rPr>';
    return '<w:p><w:pPr><w:jc w:val="' . docx_xml($align) . '"/><w:spacing w:after="' . $spaceAfter . '"/></w:pPr>'
        . '<w:r>' . $rPr . $runs . '</w:r></w:p>';
}

function docx_logo_drawing(int $cx, int $cy): string
{
    return '<w:p><w:pPr><w:jc w:val="left"/><w:spacing w:after="80"/></w:pPr><w:r>'
        . '<w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0" '
        . 'xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" '
        . 'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
        . 'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/>'
        . '<wp:docPr id="1" name="Logo"/>'
        . '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
        . '<pic:pic><pic:nvPicPr><pic:cNvPr id="0" name="logo"/><pic:cNvPicPr/></pic:nvPicPr>'
        . '<pic:blipFill><a:blip r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
        . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm>'
        . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic>'
        . '</a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
}

function letter_docx_bytes(?array $doc = null): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('This server cannot build a Word file (ZipArchive is missing).');
    }
    $brand = branding();
    if ($doc) {
        $lh = decode_letterhead($doc['letterhead'] ?? '');
        if ($lh) {
            foreach ($lh as $k => $v) {
                if (is_string($v) && trim($v) !== '') {
                    $brand[$k] = $v;
                }
            }
        }
    }
    $color = docx_color((string) ($brand['brand_color'] ?? brand_color()));
    $name = (string) ($brand['name'] ?? 'Company');
    $date = $doc ? format_date($doc['date'] ?? today()) : format_date(today());
    $ref = $doc ? (string) ($doc['number'] ?? '') : '';
    $subject = $doc ? trim((string) ($doc['subject'] ?? '')) : '[Subject]';
    if ($subject === '') {
        $subject = '[Subject]';
    }
    $body = $doc ? trim((string) ($doc['body'] ?? '')) : '';
    if ($body === '') {
        $body = "Dear Sir / Madam,\n\n[Write the letter here.]\n\nYours faithfully,\n" . $name;
    }
    $toName = $doc ? (string) ($doc['party_name'] ?? '') : '[Recipient name]';
    $toAddr = $doc ? trim((string) ($doc['party_address'] ?? '')) : '[Recipient address]';
    $toContact = $doc ? trim(implode("\n", array_filter([
        (string) ($doc['party_phone'] ?? ''),
        (string) ($doc['party_email'] ?? ''),
    ]))) : '';
    $heading = $doc ? letter_heading($doc) : 'LETTER';

    $logoPath = logo_file($brand);
    $logoExt = 'png';
    $logoXml = '';
    $cx = 1371600;
    $cy = 548640;
    if ($logoPath) {
        $info = @getimagesize($logoPath);
        if (is_array($info) && ($info[0] ?? 0) > 0) {
            $w = (int) $info[0];
            $h = (int) $info[1];
            $cx = 1600200;
            $cy = (int) round($cx * ($h / max(1, $w)));
            $mime = (string) ($info['mime'] ?? 'image/png');
            $logoExt = str_contains($mime, 'jpeg') ? 'jpeg' : (str_contains($mime, 'gif') ? 'gif' : 'png');
        }
        $logoXml = docx_logo_drawing($cx, $cy);
    }

    $addrBits = array_filter([
        (string) ($brand['address'] ?? ''),
        (string) ($brand['city'] ?? ''),
        (string) ($brand['phone'] ?? ''),
        (string) ($brand['email'] ?? ''),
        (string) ($brand['website'] ?? ''),
        !empty($brand['tin']) ? 'TIN ' . $brand['tin'] : '',
    ]);

    $header = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . $logoXml
        . docx_p($name, ['size' => 32, 'bold' => true, 'color' => $color, 'after' => 40])
        . docx_p(implode("\n", $addrBits), ['size' => 18, 'color' => '444444', 'after' => 80])
        . '<w:p><w:pPr><w:spacing w:after="200"/></w:pPr><w:r><w:rPr><w:sz w:val="12"/></w:rPr>'
        . '<w:pict xmlns:v="urn:schemas-microsoft-com:vml"><v:rect style="width:468pt;height:2.5pt" fillcolor="#' . $color . '" stroked="f"/></w:pict>'
        . '</w:r></w:p></w:hdr>';

    $footLine = trim(implode(' · ', array_filter([(string) ($brand['phone'] ?? ''), (string) ($brand['email'] ?? ''), (string) ($brand['website'] ?? '')])));
    $footer = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="80"/></w:pPr><w:r><w:rPr>'
        . '<w:sz w:val="16"/><w:color w:val="666666"/></w:rPr><w:t>' . docx_xml($footLine) . '</w:t></w:r></w:p></w:ftr>';

    $bodyXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>'
        . docx_p($heading, ['size' => 28, 'bold' => true, 'color' => $color, 'after' => 200])
        . docx_p($date . ($ref !== '' ? '    Ref: ' . $ref : ''), ['size' => 22, 'after' => 240])
        . docx_p('To', ['size' => 18, 'bold' => true, 'color' => '555555', 'after' => 40])
        . docx_p($toName, ['size' => 22, 'bold' => true, 'after' => 40])
        . ($toAddr !== '' ? docx_p($toAddr, ['size' => 20, 'after' => 40]) : '')
        . ($toContact !== '' ? docx_p($toContact, ['size' => 20, 'after' => 200]) : docx_p('', ['after' => 200]))
        . docx_p('Subject: ' . $subject, ['size' => 22, 'bold' => true, 'after' => 280])
        . docx_p($body, ['size' => 22, 'after' => 200])
        . '<w:sectPr>'
        . '<w:headerReference w:type="default" r:id="rId1"/>'
        . '<w:footerReference w:type="default" r:id="rId2"/>'
        . '<w:pgSz w:w="11906" w:h="16838"/>'
        . '<w:pgMar w:top="2000" w:right="1440" w:bottom="1440" w:left="1440" w:header="720" w:footer="720"/>'
        . '</w:sectPr></w:body></w:document>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Default Extension="png" ContentType="image/png"/>'
        . '<Default Extension="jpeg" ContentType="image/jpeg"/>'
        . '<Default Extension="gif" ContentType="image/gif"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '<Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>'
        . '<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>'
        . '</Types>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '</Relationships>';

    $docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>'
        . '</Relationships>';

    $headRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . ($logoPath ? '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/logo.' . $logoExt . '"/>' : '')
        . '</Relationships>';

    $tmp = tempnam(sys_get_temp_dir(), 'docx');
    if ($tmp === false) {
        throw new RuntimeException('Could not write a Word file.');
    }
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmp);
        throw new RuntimeException('Could not write a Word file.');
    }
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('word/document.xml', $bodyXml);
    $zip->addFromString('word/_rels/document.xml.rels', $docRels);
    $zip->addFromString('word/header1.xml', $header);
    $zip->addFromString('word/footer1.xml', $footer);
    $zip->addFromString('word/_rels/header1.xml.rels', $headRels);
    if ($logoPath) {
        $zip->addFile($logoPath, 'word/media/logo.' . $logoExt);
    }
    $zip->close();
    $bytes = (string) file_get_contents($tmp);
    @unlink($tmp);
    if ($bytes === '') {
        throw new RuntimeException('The Word file was empty.');
    }
    return $bytes;
}

function letter_docx_filename(?array $doc = null): string
{
    $brand = branding();
    $base = $doc && !empty($doc['number'])
        ? (string) $doc['number']
        : preg_replace('/[^A-Za-z0-9]+/', '-', (string) ($brand['name'] ?? 'letterhead')) . '-letterhead';
    $base = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $base), '-');
    return ($base !== '' ? $base : 'letterhead') . '.docx';
}
