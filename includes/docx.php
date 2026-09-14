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
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s) ?? $s;
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

function docx_rpr(array $opts = []): string
{
    $size = (int) ($opts['size'] ?? 22);
    $bold = !empty($opts['bold']);
    $italic = !empty($opts['italic']);
    $underline = !empty($opts['underline']);
    $color = $opts['color'] ?? '111111';
    return '<w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/>'
        . '<w:sz w:val="' . $size . '"/><w:szCs w:val="' . $size . '"/>'
        . '<w:color w:val="' . docx_xml($color) . '"/>'
        . ($bold ? '<w:b/><w:bCs/>' : '')
        . ($italic ? '<w:i/><w:iCs/>' : '')
        . ($underline ? '<w:u w:val="single"/>' : '')
        . '</w:rPr>';
}

function docx_text_run(string $text, array $opts = []): string
{
    $parts = preg_split("/\r\n|\n|\r/", $text);
    if ($parts === false) {
        $parts = [$text];
    }
    $xml = '';
    foreach ($parts as $i => $line) {
        if ($i > 0) {
            $xml .= '<w:r>' . docx_rpr($opts) . '<w:br/></w:r>';
        }
        $xml .= '<w:r>' . docx_rpr($opts) . '<w:t xml:space="preserve">' . docx_xml($line) . '</w:t></w:r>';
    }
    return $xml;
}

function docx_p_wrap(string $runs, array $opts = []): string
{
    $align = $opts['align'] ?? 'left';
    $spaceAfter = (int) ($opts['after'] ?? 120);
    if ($runs === '') {
        $runs = '<w:r>' . docx_rpr($opts) . '<w:t></w:t></w:r>';
    }
    return '<w:p><w:pPr><w:jc w:val="' . docx_xml($align) . '"/><w:spacing w:after="' . $spaceAfter . '"/></w:pPr>'
        . $runs . '</w:p>';
}

function docx_p(string $text, array $opts = []): string
{
    return docx_p_wrap(docx_text_run($text, $opts), $opts);
}

function docx_inline_image(int $cx, int $cy, string $rid, string $name, int $docPrId): string
{
    return '<w:p><w:pPr><w:jc w:val="left"/><w:spacing w:after="80"/></w:pPr><w:r>'
        . '<w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">'
        . '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/>'
        . '<wp:docPr id="' . $docPrId . '" name="' . docx_xml($name) . '"/>'
        . '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
        . '<pic:pic><pic:nvPicPr><pic:cNvPr id="' . $docPrId . '" name="' . docx_xml($name) . '"/><pic:cNvPicPr/></pic:nvPicPr>'
        . '<pic:blipFill><a:blip r:embed="' . docx_xml($rid) . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
        . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm>'
        . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic>'
        . '</a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
}

function docx_node_align(DOMNode $node): string
{
    if (!$node instanceof DOMElement) {
        return 'left';
    }
    $style = (string) $node->getAttribute('style');
    if (preg_match('/text-align\s*:\s*(left|center|right|justify)/i', $style, $m)) {
        return strtolower($m[1]);
    }
    $align = strtolower((string) $node->getAttribute('align'));
    return in_array($align, ['left', 'center', 'right', 'justify'], true) ? $align : 'left';
}

function docx_collect_runs(DOMNode $node, array $fmt): string
{
    $xml = '';
    foreach ($node->childNodes as $child) {
        if ($child->nodeType === XML_TEXT_NODE) {
            $xml .= docx_text_run((string) $child->textContent, $fmt);
            continue;
        }
        if ($child->nodeType !== XML_ELEMENT_NODE) {
            continue;
        }
        $tag = strtolower($child->nodeName);
        if ($tag === 'br') {
            $xml .= '<w:r>' . docx_rpr($fmt) . '<w:br/></w:r>';
            continue;
        }
        $next = $fmt;
        if (in_array($tag, ['b', 'strong'], true)) {
            $next['bold'] = true;
        }
        if (in_array($tag, ['i', 'em'], true)) {
            $next['italic'] = true;
        }
        if ($tag === 'u') {
            $next['underline'] = true;
        }
        $xml .= docx_collect_runs($child, $next);
    }
    return $xml;
}

function docx_block_from_node(DOMNode $node, array $base): string
{
    if ($node->nodeType === XML_TEXT_NODE) {
        $t = trim((string) $node->textContent);
        return $t === '' ? '' : docx_p($t, $base);
    }
    if ($node->nodeType !== XML_ELEMENT_NODE) {
        return '';
    }
    $tag = strtolower($node->nodeName);
    if ($tag === 'br') {
        return docx_p('', array_merge($base, ['after' => 0]));
    }
    if ($tag === 'ul' || $tag === 'ol') {
        $xml = '';
        $i = 0;
        foreach ($node->childNodes as $li) {
            if (strtolower($li->nodeName) !== 'li') {
                continue;
            }
            $i++;
            $prefix = $tag === 'ol' ? ($i . '. ') : "• ";
            $opts = array_merge($base, ['after' => 60]);
            $xml .= docx_p_wrap(docx_text_run($prefix, $opts) . docx_collect_runs($li, $opts), $opts);
        }
        return $xml;
    }
    $opts = $base;
    $opts['align'] = docx_node_align($node);
    if (in_array($tag, ['p', 'div', 'span'], true) || $tag === 'body') {
        return docx_p_wrap(docx_collect_runs($node, $opts), $opts);
    }
    return docx_p_wrap(docx_collect_runs($node, $opts), $opts);
}

function docx_from_html(string $html, array $opts = []): string
{
    $html = function_exists('sanitize_rich_html') ? sanitize_rich_html($html) : trim($html);
    if ($html === '') {
        return '';
    }
    if (!function_exists('looks_like_html') || !looks_like_html($html)) {
        return docx_p(function_exists('html_to_plain') ? html_to_plain($html) : $html, $opts);
    }
    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$body) {
        return docx_p(html_to_plain($html), $opts);
    }
    $xml = '';
    foreach ($body->childNodes as $child) {
        $xml .= docx_block_from_node($child, $opts);
    }
    return $xml !== '' ? $xml : docx_p(html_to_plain($html), $opts);
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
        $out['body'] = function_exists('sanitize_rich_html') ? sanitize_rich_html($bodyIn) : $bodyIn;
    }
    if (array_key_exists('add_signature', $_REQUEST)) {
        $out['add_signature'] = (string) $_REQUEST['add_signature'] === '1' ? 1 : 0;
    }
    $dateIn = trim((string) ($_REQUEST['date'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateIn)) {
        $out['date'] = $dateIn;
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
    $color = docx_color((string) ($brand['brand_color'] ?? brand_color()));
    $name = (string) ($brand['name'] ?? 'Company');
    $tagline = trim((string) ($brand['tagline'] ?? ''));
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
    if ($logoPath && is_readable($logoPath)) {
        $info = @getimagesize($logoPath);
        if (is_array($info) && ($info[0] ?? 0) > 0) {
            $w = (int) $info[0];
            $h = (int) $info[1];
            $cx = 1600200;
            $cy = (int) round($cx * ($h / max(1, $w)));
            $mime = (string) ($info['mime'] ?? 'image/png');
            $logoExt = str_contains($mime, 'jpeg') ? 'jpeg' : (str_contains($mime, 'gif') ? 'gif' : 'png');
        }
        $logoXml = docx_inline_image($cx, $cy, 'rId6', 'Logo', 1);
    } else {
        $logoPath = null;
    }

    $addrBits = array_filter([
        (string) ($brand['address'] ?? ''),
        (string) ($brand['city'] ?? ''),
        (string) ($brand['phone'] ?? ''),
        (string) ($brand['email'] ?? ''),
        (string) ($brand['website'] ?? ''),
        !empty($brand['tin']) ? 'TIN ' . $brand['tin'] : '',
    ]);

    $nsDoc = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
        . 'xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" '
        . 'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
        . 'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"';

    $muted = ['size' => 20, 'color' => '6B7280', 'after' => 20];
    $bodySize = ['size' => 22, 'after' => 80];
    $letterhead = $logoXml
        . docx_p($name, ['size' => 24, 'bold' => true, 'color' => $color, 'after' => 40])
        . ($tagline !== '' ? docx_p($tagline, array_merge($muted, ['after' => 40])) : '')
        . ($addrBits ? docx_p(implode("\n", $addrBits), array_merge($muted, ['after' => 80])) : '')
        . '<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="18" w:space="1" w:color="' . $color . '"/></w:pBdr>'
        . '<w:spacing w:before="80" w:after="280"/></w:pPr></w:p>';

    $signPath = ($stampSign && company_signature_path($brand) !== '') ? (ROOT_PATH . '/' . company_signature_path($brand)) : '';
    $signXml = '';
    $signCx = 2286000;
    $signCy = 914400;
    if ($signPath && is_file($signPath) && is_readable($signPath)) {
        $info = @getimagesize($signPath);
        if (is_array($info) && ($info[0] ?? 0) > 0) {
            $signCy = (int) round($signCx * ((int) $info[1] / max(1, (int) $info[0])));
        }
        $signXml = docx_inline_image($signCx, $signCy, 'rId4', 'Signature', 2);
    } else {
        $signPath = '';
    }

    $dateLine = $date . ($ref !== '' ? '    Ref: ' . $ref : '');
    $toXml = docx_p('To', ['size' => 22, 'bold' => true, 'after' => 40])
        . ($toName !== '' ? docx_p($toName, array_merge($bodySize, ['after' => 40])) : docx_p('', ['after' => 40]))
        . ($toAddr !== '' ? docx_p($toAddr, array_merge($bodySize, ['after' => 40])) : '')
        . ($toContact !== '' ? docx_p($toContact, array_merge($bodySize, ['after' => 200])) : docx_p('', ['after' => 200]));
    $subjectXml = $subject === ''
        ? docx_p_wrap(docx_text_run('Subject: ', ['size' => 28, 'bold' => true]), ['after' => 240])
        : docx_p_wrap(
            docx_text_run('Subject: ', ['size' => 28, 'bold' => true])
            . docx_text_run($subject, ['size' => 28, 'bold' => true]),
            ['size' => 28, 'after' => 240]
        );
    $bodyXmlInner = $body !== '' ? docx_from_html($body, $bodySize) : docx_p('', ['after' => 400]) . docx_p('', ['after' => 400]);

    $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document ' . $nsDoc . '><w:body>'
        . $letterhead
        . docx_p($dateLine, array_merge($bodySize, ['after' => 240]))
        . $toXml
        . $subjectXml
        . $bodyXmlInner
        . $signXml
        . '<w:sectPr>'
        . '<w:pgSz w:w="11906" w:h="16838"/>'
        . '<w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="0" w:footer="0"/>'
        . '</w:sectPr></w:body></w:document>';

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/>'
        . '<w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr></w:rPrDefault>'
        . '<w:pPrDefault><w:pPr><w:spacing w:after="120"/></w:pPr></w:pPrDefault></w:docDefaults>'
        . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/>'
        . '<w:qFormat/><w:pPr/><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr></w:style>'
        . '</w:styles>';

    $settings = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:compat><w:compatSetting w:name="compatibilityMode" w:uri="http://schemas.microsoft.com/office/word" w:val="15"/></w:compat>'
        . '</w:settings>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Default Extension="png" ContentType="image/png"/>'
        . '<Default Extension="jpeg" ContentType="image/jpeg"/>'
        . '<Default Extension="gif" ContentType="image/gif"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
        . '<Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>'
        . '</Types>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '</Relationships>';

    $docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '<Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>'
        . ($logoPath ? '<Relationship Id="rId6" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/logo.' . $logoExt . '"/>' : '')
        . ($signXml !== '' ? '<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/sign.png"/>' : '')
        . '</Relationships>';

    $tmp = sys_get_temp_dir() . '/vellisys-' . bin2hex(random_bytes(8)) . '.docx';
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not write a Word file.');
    }
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('word/document.xml', $document);
    $zip->addFromString('word/styles.xml', $styles);
    $zip->addFromString('word/settings.xml', $settings);
    $zip->addFromString('word/_rels/document.xml.rels', $docRels);
    if ($logoPath) {
        $bytesLogo = (string) file_get_contents($logoPath);
        if ($bytesLogo !== '') {
            $zip->addFromString('word/media/logo.' . $logoExt, $bytesLogo);
        }
    }
    if ($signXml !== '' && $signPath !== '') {
        $bytesSign = (string) file_get_contents($signPath);
        if ($bytesSign !== '') {
            $zip->addFromString('word/media/sign.png', $bytesSign);
        }
    }
    $zip->close();
    $bytes = (string) file_get_contents($tmp);
    @unlink($tmp);
    if ($bytes === '' || strncmp($bytes, "PK", 2) !== 0) {
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
