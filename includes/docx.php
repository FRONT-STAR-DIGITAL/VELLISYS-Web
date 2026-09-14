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

function docx_inline_image(int $cx, int $cy, string $rid, string $name): string
{
    return '<w:p><w:pPr><w:jc w:val="left"/><w:spacing w:after="80"/></w:pPr><w:r>'
        . '<w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0" '
        . 'xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" '
        . 'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
        . 'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/>'
        . '<wp:docPr id="' . h($rid) . '" name="' . docx_xml($name) . '"/>'
        . '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
        . '<pic:pic><pic:nvPicPr><pic:cNvPr id="0" name="' . docx_xml($name) . '"/><pic:cNvPicPr/></pic:nvPicPr>'
        . '<pic:blipFill><a:blip r:embed="' . docx_xml($rid) . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
        . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm>'
        . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic>'
        . '</a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
}

function docx_logo_drawing(int $cx, int $cy): string
{
    return docx_inline_image($cx, $cy, 'rId1', 'Logo');
}

function letter_docx_apply_request(?array $doc): array
{
    $tplKey = trim((string) ($_REQUEST['template'] ?? ''));
    $subjectIn = trim((string) ($_REQUEST['subject'] ?? ''));
    $bodyPosted = array_key_exists('body', $_REQUEST);
    $bodyIn = (string) ($_REQUEST['body'] ?? '');
    $partyId = (int) ($_REQUEST['party_id'] ?? 0);
    $out = $doc ?: [
        'kind' => 'letter',
        'number' => '',
        'date' => today(),
        'subject' => '',
        'body' => '',
        'letter_template' => null,
        'add_signature' => 0,
        'party_name' => '',
        'party_address' => '',
        'party_phone' => '',
        'party_email' => '',
    ];
    if ($tplKey !== '') {
        $out['letter_template'] = ($tplKey === 'none') ? null : $tplKey;
        $templates = letter_templates();
        if ($tplKey === 'none' && $subjectIn === '' && !$bodyPosted) {
            $out['subject'] = '';
            $out['body'] = '';
        } elseif ($tplKey !== 'none' && isset($templates[$tplKey]) && $subjectIn === '' && !$bodyPosted) {
            $out['subject'] = $templates[$tplKey]['subject'];
            $out['body'] = $templates[$tplKey]['body'];
        }
    }
    if ($subjectIn !== '' || (array_key_exists('subject', $_REQUEST) && $tplKey === 'none')) {
        $out['subject'] = $subjectIn;
    }
    if ($bodyPosted) {
        $out['body'] = $bodyIn;
    }
    if (array_key_exists('add_signature', $_REQUEST)) {
        $out['add_signature'] = (string) $_REQUEST['add_signature'] === '1' ? 1 : 0;
    }
    if ($partyId > 0) {
        $party = db_one('SELECT * FROM parties WHERE id = ? AND company_id = ?', 'ii', [$partyId, current_company_id()]);
        if ($party) {
            $out['party_name'] = (string) $party['name'];
            $out['party_address'] = (string) ($party['address'] ?? '');
            $out['party_phone'] = (string) ($party['phone'] ?? '');
            $out['party_email'] = (string) ($party['email'] ?? '');
        }
    }
    return $out;
}

function letter_docx_bytes(?array $doc = null): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('This server cannot build a Word file (ZipArchive is missing).');
    }
    $doc = letter_docx_apply_request($doc);
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
    $subject = $doc ? trim((string) ($doc['subject'] ?? '')) : '';
    $body = $doc ? trim((string) ($doc['body'] ?? '')) : '';
    $toName = $doc ? trim((string) ($doc['party_name'] ?? '')) : '';
    $toAddr = $doc ? trim((string) ($doc['party_address'] ?? '')) : '';
    $toContact = $doc ? trim(implode("\n", array_filter([
        (string) ($doc['party_phone'] ?? ''),
        (string) ($doc['party_email'] ?? ''),
    ]))) : '';
    $stampSign = $doc && !empty($doc['add_signature']);

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

    $signPath = ($stampSign && company_signature_path() !== '') ? (ROOT_PATH . '/' . company_signature_path()) : '';
    $signXml = '';
    $signCx = 2286000;
    $signCy = 914400;
    if ($signPath && is_file($signPath)) {
        $info = @getimagesize($signPath);
        if (is_array($info) && ($info[0] ?? 0) > 0) {
            $signCy = (int) round($signCx * ((int) $info[1] / max(1, (int) $info[0])));
        }
        $signXml = docx_inline_image($signCx, $signCy, 'rId3', 'Signature');
    }

    $toXml = docx_p('To', ['size' => 16, 'bold' => true, 'color' => '666666', 'after' => 40])
        . ($toName !== '' ? docx_p($toName, ['size' => 22, 'bold' => true, 'after' => 40]) : '')
        . ($toAddr !== '' ? docx_p($toAddr, ['size' => 20, 'after' => 40]) : '')
        . ($toContact !== '' ? docx_p($toContact, ['size' => 20, 'after' => 200]) : docx_p('', ['after' => 200]));
    $subjectXml = $subject === ''
        ? ''
        : docx_p('Subject', ['size' => 16, 'bold' => true, 'color' => '555555', 'after' => 20])
            . docx_p($subject, ['size' => 28, 'bold' => true, 'color' => $color, 'after' => 240]);
    $bodyXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>'
        . docx_p($date . ($ref !== '' ? '    Ref: ' . $ref : ''), ['size' => 22, 'after' => 240])
        . $toXml
        . $subjectXml
        . docx_p($body, ['size' => 22, 'after' => 200])
        . $signXml
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
        . ($signXml !== '' ? '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/sign.png"/>' : '')
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
    if ($signXml !== '' && $signPath) {
        $zip->addFile($signPath, 'word/media/sign.png');
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
