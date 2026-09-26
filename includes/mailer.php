<?php
declare(strict_types=1);

function vellisys_email_wrap(string $innerHtml, string $kicker = 'Vellisys'): string
{
    $navy = '#08143A';
    $blue = '#1E4EFF';
    $white = '#FFFFFF';
    $black = '#000000';
    $email = product_email();
    $phones = implode(' · ', product_phones());
    $maker = product_maker_name();
    $box = product_po_box();
    $site = product_maker_url();

    return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Vellisys</title></head>'
        . '<body style="margin:0;padding:0;background:' . $white . ';-webkit-text-size-adjust:100%;">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . h($kicker) . ' from Vellisys</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . $white . ';margin:0;padding:0;">'
        . '<tr><td align="center" style="padding:28px 12px;">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:' . $white . ';border:1px solid ' . $navy . ';">'
        . '<tr><td style="height:8px;line-height:8px;font-size:0;background:' . $blue . ';">&nbsp;</td></tr>'
        . '<tr><td align="center" style="padding:24px 32px 18px;background:' . $white . ';text-align:center;">'
        . '<img src="cid:vellisys-logo" alt="Vellisys" width="176" style="display:inline-block;margin:0 auto;border:0;outline:none;text-decoration:none;height:auto;max-width:176px;background:' . $white . ';">'
        . '</td></tr>'
        . '<tr><td align="center" style="background:' . $navy . ';padding:13px 32px;text-align:center;">'
        . '<p style="margin:0;font-family:Montserrat,Segoe UI,Arial,sans-serif;font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:' . $white . ';font-weight:700;text-align:center;">' . h($kicker) . '</p>'
        . '</td></tr>'
        . '<tr><td style="padding:28px 32px 16px;background:' . $white . ';color:' . $black . ';font-family:Montserrat,Segoe UI,Arial,sans-serif;font-size:15px;line-height:1.65;">'
        . $innerHtml
        . '</td></tr>'
        . '<tr><td style="background:' . $navy . ';padding:24px 32px;">'
        . '<p style="margin:0 0 6px;font-family:Montserrat,Segoe UI,Arial,sans-serif;font-size:16px;font-weight:700;color:' . $white . ';">Vellisys</p>'
        . '<p style="margin:0 0 12px;font-family:Montserrat,Segoe UI,Arial,sans-serif;font-size:13px;color:' . $white . ';">A product of ' . h($maker) . '</p>'
        . '<p style="margin:0;font-family:Montserrat,Segoe UI,Arial,sans-serif;font-size:13px;line-height:1.7;color:' . $white . ';">'
        . '<a href="mailto:' . h($email) . '" style="color:' . $white . ';text-decoration:none;">' . h($email) . '</a><br>'
        . h($phones) . '<br>' . h($box)
        . '</p>'
        . '</td></tr>'
        . '</table>'
        . '<p style="margin:18px 8px 0;font-family:Montserrat,Segoe UI,Arial,sans-serif;font-size:11px;color:' . $black . ';">Branded books for teams everywhere · <a href="' . h($site) . '" style="color:' . $blue . ';text-decoration:none;">' . h($maker) . '</a></p>'
        . '</td></tr></table></body></html>';
}

function email_html_preview(string $html): string
{
    $file = product_email_logo_file();
    if ($file !== '' && is_file($file)) {
        $html = str_replace('cid:vellisys-logo', 'data:image/png;base64,' . base64_encode((string) file_get_contents($file)), $html);
    }
    return $html;
}

function branded_company_wrap(array $brand, string $innerHtml, string $kicker = ''): string
{
    $name = (string) ($brand['name'] ?? 'Your company');
    $primary = parse_hex_color($brand['brand_color'] ?? '', '#82B440');
    $accent = parse_hex_color($brand['brand_accent'] ?? '', $primary);
    $deep = parse_hex_color($brand['brand_deep'] ?? '', '');
    if ($deep === '') {
        $deep = function_exists('hex_shade') ? hex_shade($primary, 0.52) : '#08143A';
    }
    $white = '#FFFFFF';
    $black = '#000000';
    $kicker = $kicker !== '' ? $kicker : $name;
    $path = (string) ($brand['logo_path'] ?? '');
    $fullLogo = $path !== '' ? ROOT_PATH . '/' . ltrim($path, '/') : '';
    $logoHtml = ($fullLogo !== '' && is_file($fullLogo))
        ? '<img src="cid:company-logo" alt="' . h($name) . '" width="160" style="display:inline-block;margin:0 auto;border:0;outline:none;max-height:56px;width:auto;background:' . $white . ';">'
        : '<p style="margin:0;font-size:20px;font-weight:700;color:' . $deep . ';text-align:center;">' . h($name) . '</p>';
    $phone = trim((string) ($brand['phone'] ?? ''));
    $email = trim((string) ($brand['email'] ?? ''));
    $address = trim((string) ($brand['address'] ?? ''));
    $city = trim((string) ($brand['city'] ?? ''));
    $website = trim((string) ($brand['website'] ?? ''));
    $tagline = trim((string) ($brand['tagline'] ?? ''));
    $lines = [h($name)];
    if ($tagline !== '') {
        $lines[] = h($tagline);
    }
    if ($email !== '') {
        $lines[] = '<a href="mailto:' . h($email) . '" style="color:' . $white . ';text-decoration:none;">' . h($email) . '</a>';
    }
    if ($phone !== '') {
        $lines[] = h($phone);
    }
    $place = trim($address . ($address !== '' && $city !== '' ? ', ' : '') . $city);
    if ($place !== '') {
        $lines[] = h($place);
    }
    if ($website !== '') {
        $href = preg_match('#^https?://#i', $website) ? $website : ('https://' . $website);
        $lines[] = '<a href="' . h($href) . '" style="color:' . $white . ';text-decoration:none;">' . h($website) . '</a>';
    }
    $foot = '<p style="margin:0;font-family:Montserrat,Segoe UI,Arial,sans-serif;font-size:13px;line-height:1.7;color:' . $white . ';">'
        . implode('<br>', $lines) . '</p>';

    return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($name) . '</title></head>'
        . '<body style="margin:0;padding:0;background:' . $white . ';-webkit-text-size-adjust:100%;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . $white . ';margin:0;padding:0;">'
        . '<tr><td align="center" style="padding:28px 12px;">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:' . $white . ';border:1px solid ' . $deep . ';">'
        . '<tr><td style="height:8px;line-height:8px;font-size:0;background:' . $primary . ';">&nbsp;</td></tr>'
        . '<tr><td style="height:4px;line-height:4px;font-size:0;background:' . $accent . ';">&nbsp;</td></tr>'
        . '<tr><td align="center" style="padding:24px 32px 18px;background:' . $white . ';text-align:center;">' . $logoHtml . '</td></tr>'
        . '<tr><td align="center" style="background:' . $deep . ';padding:13px 32px;text-align:center;">'
        . '<p style="margin:0;font-family:Montserrat,Segoe UI,Arial,sans-serif;font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:' . $white . ';font-weight:700;text-align:center;">' . h($kicker) . '</p>'
        . '</td></tr>'
        . '<tr><td style="padding:28px 32px 16px;background:' . $white . ';color:' . $black . ';font-family:Montserrat,Segoe UI,Arial,sans-serif;font-size:15px;line-height:1.65;">'
        . $innerHtml
        . '</td></tr>'
        . '<tr><td style="background:' . $deep . ';padding:24px 32px;">' . $foot . '</td></tr>'
        . '</table>'
        . '<p style="margin:14px 8px 0;font-family:Montserrat,Segoe UI,Arial,sans-serif;font-size:11px;color:#666666;text-align:center;">Sent from Vellisys system</p>'
        . '</td></tr></table></body></html>';
}

function mail_inlines_for_html(string $html, array $extra = []): array
{
    $known = [
        'vellisys-logo' => product_email_logo_file(),
    ];
    foreach ($extra as $cid => $path) {
        if (is_string($cid) && is_string($path) && $path !== '') {
            $known[$cid] = $path;
        }
    }
    $found = [];
    if (preg_match_all('/cid:([A-Za-z0-9._@-]+)/', $html, $m)) {
        foreach (array_unique($m[1]) as $cid) {
            $path = $known[$cid] ?? '';
            if ($path !== '' && is_file($path)) {
                $found[] = ['cid' => $cid, 'path' => $path, 'name' => basename($path)];
            }
        }
    }
    return $found;
}

function emails_same(string $a, string $b): bool
{
    return strtolower(trim($a)) === strtolower(trim($b));
}

function platform_copy_banner_html(string $fromEmail, string $clientEmail): string
{
    $navy = '#08143A';
    $white = '#FFFFFF';
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px">'
        . '<tr><td style="background:' . $navy . ';padding:14px 16px;">'
        . '<p style="margin:0 0 6px;font-family:Montserrat,Segoe UI,Arial,sans-serif;font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:' . $white . ';font-weight:700;">Copy for Vellisys</p>'
        . '<p style="margin:0;font-family:Montserrat,Segoe UI,Arial,sans-serif;font-size:13px;line-height:1.55;color:' . $white . ';">The client received this from ' . h($fromEmail)
        . '. Reply to write to <a href="mailto:' . h($clientEmail) . '" style="color:' . $white . ';font-weight:700;text-decoration:underline;">' . h($clientEmail) . '</a>.</p>'
        . '</td></tr></table>';
}

function html_with_platform_copy_banner(string $html, string $fromEmail, string $clientEmail): string
{
    $banner = platform_copy_banner_html($fromEmail, $clientEmail);
    if (preg_match('/<body\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
        $at = (int) $m[0][1] + strlen($m[0][0]);
        return substr($html, 0, $at) . $banner . substr($html, $at);
    }
    return $banner . $html;
}

function platform_alert_email(): string
{
    $raw = strtolower(trim(platform_setting('alert_email', '')));
    if ($raw === '' || !filter_var($raw, FILTER_VALIDATE_EMAIL)) {
        return '';
    }
    if (function_exists('emails_same') && emails_same($raw, product_email())) {
        return '';
    }
    return $raw;
}

function copy_outbound_to_platform(string $to, string $subject, string $html, string $text, string $fromEmail): void
{
    if (!empty($GLOBALS['folio_skip_platform_copy'])) {
        return;
    }
    $watch = product_email();
    if (!filter_var($watch, FILTER_VALIDATE_EMAIL) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    if (emails_same($to, $watch)) {
        return;
    }
    $from = $fromEmail !== '' ? $fromEmail : $watch;
    // Company mailboxes send their own branded letters. Copying them through
    // info@ (same From and To) is what Hostinger has been filing as Spam.
    if (!emails_same($from, $watch)) {
        return;
    }
    $copySubject = preg_match('/^copy\s*-/i', $subject) ? $subject : ('Copy - ' . $subject);
    $copyHtml = html_with_platform_copy_banner($html, $from, $to);
    $copyText = "Copy for Vellisys. The client received this from {$from}. Reply to write to {$to}.\n\n" . $text;
    $account = platform_mail_account();
    $fromName = (string) ($account['from_name'] ?? product_from_name());
    if (function_exists('mail_deliver_to_platform_inbox')) {
        try {
            mail_deliver_to_platform_inbox($watch, $fromName, $watch, $copySubject, $copyHtml, $copyText, $to, mail_inlines_for_html($copyHtml));
        } catch (Throwable $e) {
            error_log('Vellisys copy inbox: ' . $e->getMessage());
        }
    }
    $alert = platform_alert_email();
    if ($alert !== '') {
        $GLOBALS['folio_skip_platform_copy'] = true;
        send_platform_email($alert, $copySubject, $copyHtml, $copyText, 0, $to);
        unset($GLOBALS['folio_skip_platform_copy']);
    }
}

function deliver_mail(array $account, string $to, string $subject, string $html, string $text, string $replyTo = '', array $inlines = []): array
{
    $merged = [];
    foreach (array_merge($inlines, mail_inlines_for_html($html)) as $img) {
        $cid = (string) ($img['cid'] ?? '');
        if ($cid !== '') {
            $merged[$cid] = $img;
        }
    }
    $result = smtp_send($account, $to, $subject, $html, $text, $replyTo, array_values($merged));
    $result['from'] = (string) ($account['from_email'] ?? ($result['from'] ?? ''));
    if (empty($result['error'])) {
        $result['error'] = $result['ok'] ? '' : 'The mailbox did not accept this message.';
    }
    if (!empty($result['ok'])) {
        try {
            copy_outbound_to_platform($to, $subject, $html, $text, (string) $result['from']);
        } catch (Throwable $e) {
            // The client letter already left. A missed copy must not undo that.
        }
    }
    return $result;
}

function log_email(?int $documentId, int $userId, string $to, string $subject, string $body, bool $ok, string $error = '', string $fromEmail = ''): void
{
    $status = $ok ? 'sent' : 'queued';
    $err = $ok ? '' : $error;
    if ($documentId === null) {
        db_exec(
            'INSERT INTO emails (document_id, user_id, to_email, from_email, subject, body, status, error) VALUES (NULL,?,?,?,?,?,?,?)',
            'issssss',
            [$userId, $to, $fromEmail, $subject, $body, $status, $err]
        );
        return;
    }
    db_exec(
        'INSERT INTO emails (document_id, user_id, to_email, from_email, subject, body, status, error) VALUES (?,?,?,?,?,?,?,?)',
        'iissssss',
        [$documentId, $userId, $to, $fromEmail, $subject, $body, $status, $err]
    );
}

function send_platform_email(string $to, string $subject, string $html, string $text, int $userId = 0, string $replyTo = ''): array
{
    $account = platform_mail_account();
    $from = (string) ($account['from_email'] ?? product_email());
    $fromName = (string) ($account['from_name'] ?? product_from_name());
    $reply = $replyTo !== '' ? $replyTo : $from;
    $inlines = mail_inlines_for_html($html);
    if (emails_same($to, $from) || emails_same($to, product_email())) {
        $inbox = false;
        if (function_exists('mail_deliver_to_platform_inbox')) {
            try {
                $inbox = mail_deliver_to_platform_inbox($from, $fromName, product_email(), $subject, $html, $text, $reply, $inlines);
            } catch (Throwable $e) {
                error_log('Vellisys platform inbox: ' . $e->getMessage());
            }
        }
        $alert = platform_alert_email();
        $result = ['ok' => $inbox, 'from' => $from, 'error' => $inbox ? '' : 'Could not place the letter in the info@ Inbox.'];
        if ($alert !== '') {
            $prev = $GLOBALS['folio_skip_platform_copy'] ?? null;
            $GLOBALS['folio_skip_platform_copy'] = true;
            $extra = deliver_mail($account, $alert, $subject, $html, $text, $reply, $inlines);
            if ($prev === null) {
                unset($GLOBALS['folio_skip_platform_copy']);
            } else {
                $GLOBALS['folio_skip_platform_copy'] = $prev;
            }
            if (!empty($extra['ok'])) {
                $result = $extra;
                $result['alert'] = $alert;
            }
        } elseif (!$inbox) {
            $result = deliver_mail($account, $to, $subject, $html, $text, $reply, $inlines);
        }
        log_email(null, $userId, $to, $subject, $text, !empty($result['ok']), (string) ($result['error'] ?? ''), $from);
        return $result;
    }
    $result = deliver_mail($account, $to, $subject, $html, $text, $reply, $inlines);
    log_email(null, $userId, $to, $subject, $text, $result['ok'], (string) ($result['error'] ?? ''), $from);
    return $result;
}

function company_mailbox_missing_error(): string
{
    return 'Vellisys has not assigned a sending mailbox for this company yet. Ask the platform admin to add the Hostinger address on the company page.';
}

function desk_mail_query(string $type, int $docId, int $partyId): string
{
    $q = [];
    if ($type !== '' && $type !== 'custom') {
        $q[] = 'type=' . rawurlencode($type);
    }
    if ($docId > 0) {
        $q[] = 'id=' . $docId;
    }
    if ($partyId > 0) {
        $q[] = 'party=' . $partyId;
    }
    return $q ? '?' . implode('&', $q) : '';
}

function retry_queued_platform_mail(): array
{
    $from = product_email();
    $rows = db_all(
        "SELECT * FROM emails WHERE status = 'queued' AND document_id IS NULL AND (from_email = '' OR from_email = ?) ORDER BY id",
        's',
        [$from]
    );
    $ok = 0;
    $fail = 0;
    foreach ($rows as $row) {
        $to = (string) $row['to_email'];
        $subject = (string) $row['subject'];
        $text = (string) $row['body'];
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $fail++;
            continue;
        }
        $html = vellisys_email_wrap('<p style="margin:0 0 14px">' . nl2br(h($text)) . '</p>');
        if (emails_same($to, $from)) {
            $result = send_platform_email($to, $subject, $html, $text, 0, product_email());
        } else {
            $result = deliver_mail(platform_mail_account(), $to, $subject, $html, $text, product_email());
        }
        if (!empty($result['ok'])) {
            db_exec("UPDATE emails SET status = 'sent', error = '' WHERE id = ?", 'i', [(int) $row['id']]);
            $ok++;
        } else {
            db_exec(
                'UPDATE emails SET error = ? WHERE id = ?',
                'si',
                [(string) ($result['error'] ?? 'The mailbox did not accept this message.'), (int) $row['id']]
            );
            $fail++;
        }
    }
    return ['ok' => $ok, 'fail' => $fail, 'total' => count($rows)];
}

function notify_platform(string $subject, string $html, string $text, string $replyTo = '', int $userId = 0): array
{
    $inner = $html;
    if (!str_contains($html, 'font-family:Montserrat')) {
        $inner = vellisys_email_wrap($html);
    }
    return send_platform_email(product_email(), $subject, $inner, $text, $userId, $replyTo);
}

function company_logo_inlines(array $brand): array
{
    $logoPath = (string) ($brand['logo_path'] ?? '');
    $logoFull = $logoPath !== '' ? ROOT_PATH . '/' . ltrim($logoPath, '/') : '';
    if ($logoFull !== '' && is_file($logoFull)) {
        return [['cid' => 'company-logo', 'path' => $logoFull, 'name' => basename($logoFull)]];
    }
    return [];
}

function send_document_email(array $user, array $doc, string $to, string $subject, string $message): array
{
    $meta = kind_meta((string) ($doc['kind'] ?? ''));
    $kicker = trim($meta['singular'] . ' ' . (string) ($doc['number'] ?? ''));
    return send_company_email($user, $to, $subject, $message, $doc, $kicker);
}

function send_company_email(array $user, string $to, string $subject, string $message, ?array $doc = null, string $kicker = ''): array
{
    $brand = branding();
    $cid = (int) ($doc['company_id'] ?? current_company_id());
    $company = $cid ? db_one('SELECT * FROM companies WHERE id = ?', 'i', [$cid]) : null;
    $account = $company ? company_mail_account($company) : null;
    $docId = $doc ? (int) ($doc['id'] ?? 0) : 0;
    if (!$account) {
        $err = company_mailbox_missing_error();
        log_email($docId > 0 ? $docId : null, (int) $user['id'], $to, $subject, $message, false, $err, '');
        return ['ok' => false, 'from' => '', 'error' => $err];
    }
    $from = (string) $account['from_email'];
    $link = $doc ? document_share_url($doc) : '';
    $safe = sanitize_rich_html($message);
    $inner = '<div style="margin:0 0 14px;color:#000000">' . ($safe !== '' ? $safe : '<p style="margin:0">' . nl2br(h($message)) . '</p>') . '</div>';
    if ($doc) {
        $meta = kind_meta((string) ($doc['kind'] ?? ''));
        $inner .= '<p style="margin:0 0 14px;color:#000000">' . h($meta['singular']) . ' <strong>' . h((string) ($doc['number'] ?? '')) . '</strong></p>'
            . '<p style="margin:0 0 14px"><a href="' . h($link) . '" style="color:#1E4EFF">Open the branded sheet</a></p>';
    }
    $inner .= '<p style="margin:0;color:#000000">' . h($user['name'] ?: (string) $brand['name']) . '<br>' . h((string) $brand['name']) . '</p>';
    $html = branded_company_wrap($brand, $inner, $kicker);
    $text = html_to_plain($message);
    if ($link !== '') {
        $text .= "\n\n" . $link;
    }
    $text .= "\n\nSent from Vellisys system";
    $prev = $GLOBALS['folio_skip_platform_copy'] ?? null;
    $GLOBALS['folio_skip_platform_copy'] = true;
    $result = deliver_mail($account, $to, $subject, $html, $text, $from, company_logo_inlines($brand));
    if ($prev === null) {
        unset($GLOBALS['folio_skip_platform_copy']);
    } else {
        $GLOBALS['folio_skip_platform_copy'] = $prev;
    }
    log_email($docId > 0 ? $docId : null, (int) $user['id'], $to, $subject, $message, $result['ok'], (string) ($result['error'] ?? ''), $from);
    $result['from'] = $from;
    if (function_exists('record_company_activity') && $cid > 0) {
        $label = $doc ? (kind_meta((string) ($doc['kind'] ?? ''))['singular'] . ' ' . (string) ($doc['number'] ?? '')) : $subject;
        record_company_activity('email', 'Emailed ' . $label, [
            'detail' => 'To ' . $to . (!empty($result['ok']) ? '' : ' · queued'),
            'href' => $docId > 0 ? 'document_view.php?id=' . $docId : 'desk_mail.php',
            'ref_type' => $docId > 0 ? 'document' : 'email',
            'ref_id' => $docId,
            'company_id' => $cid,
            'user_id' => (int) $user['id'],
            'branch_id' => $doc['branch_id'] ?? ($user['branch_id'] ?? null),
        ]);
    }
    return $result;
}

function notify_admin_question(array $q): void
{
    $name = (string) ($q['name'] ?? '');
    $email = (string) ($q['email'] ?? '');
    $phone = (string) ($q['phone'] ?? '');
    $topic = (string) ($q['topic'] ?? '');
    $topicLabel = function_exists('ask_contact_topic_label') ? ask_contact_topic_label($topic) : $topic;
    $message = (string) ($q['message'] ?? '');
    $html = vellisys_email_wrap(
        '<p style="margin:0 0 14px">A visitor asked a question on the Vellisys site.</p>'
        . '<p style="margin:0 0 8px"><strong>Name:</strong> ' . h($name) . '</p>'
        . '<p style="margin:0 0 8px"><strong>Email:</strong> ' . h($email) . '</p>'
        . '<p style="margin:0 0 8px"><strong>Phone:</strong> ' . h($phone !== '' ? $phone : 'Not given') . '</p>'
        . ($topicLabel !== '' ? '<p style="margin:0 0 8px"><strong>Reason:</strong> ' . h($topicLabel) . '</p>' : '')
        . '<p style="margin:16px 0;padding:14px;background:#FFFFFF;border:1px solid #08143A;color:#000000">' . nl2br(h($message)) . '</p>'
        . '<p style="margin:0"><a href="' . h(absolute_url('admin_question.php?id=' . (int) ($q['id'] ?? 0))) . '" style="color:#1E4EFF">Open the question</a></p>'
    );
    $text = "A visitor asked a question on the Vellisys site.\n\nName: {$name}\nEmail: {$email}\nPhone: " . ($phone !== '' ? $phone : '(none)')
        . ($topicLabel !== '' ? "\nReason: {$topicLabel}" : '')
        . "\n\n{$message}\n\nOpen: " . absolute_url('admin_questions.php');
    notify_platform('Vellisys question from ' . ($name !== '' ? $name : 'a visitor'), $html, $text, $email);
    if (function_exists('push_notify_item')) {
        $qid = (int) ($q['id'] ?? 0);
        push_notify_item([
            'title' => $name !== '' ? $name : 'New question',
            'meta' => clip_text($message, 80),
            'href' => url('admin_question.php?id=' . $qid),
            'key' => $qid > 0 ? 'question:' . $qid : 'question-new',
        ], 'platform');
    }
    notify_visitor_question($q);
}

function notify_visitor_question(array $q): void
{
    $to = strtolower(trim((string) ($q['email'] ?? '')));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $name = trim((string) ($q['name'] ?? ''));
    $who = $name !== '' ? $name : 'there';
    $message = trim((string) ($q['message'] ?? ''));
    $phones = implode(' or ', product_phones());
    $subject = 'Thank you - we have your question';
    $html = vellisys_email_wrap(
        '<p style="margin:0 0 16px">Dear ' . h($who) . ',</p>'
        . '<p style="margin:0 0 14px">Thank you for writing to us. We have your note, and a person on the Vellisys team will reply to this email.</p>'
        . ($message !== ''
            ? '<p style="margin:0 0 8px"><strong>What you asked</strong></p><p style="margin:0 0 18px;padding:14px;background:#FFFDF8;border:1px solid #08143A;color:#000000">' . nl2br(h($message)) . '</p>'
            : '')
        . '<p style="margin:0 0 14px">If you need us today, call ' . h($phones) . '. We are glad you reached out.</p>'
        . '<p style="margin:0">Warm regards,<br><strong>Vellisys</strong></p>',
        'Thank you'
    );
    $text = "Dear {$who},\n\nThank you for writing to us. We have your note, and a person on the Vellisys team will reply to this email.\n\n"
        . ($message !== '' ? "What you asked:\n{$message}\n\n" : '')
        . "If you need us today, call {$phones}. We are glad you reached out.\n\nWarm regards,\nVellisys\n" . product_email();
    send_platform_email($to, $subject, $html, $text, 0, product_email());
}

function notify_password_reset_request(string $email): array
{
    $email = strtolower(trim($email));
    $found = null;
    try {
        $found = db_one(
            'SELECT u.id, u.name, u.email, u.role, u.company_id, c.name AS company_name, c.status AS company_status
             FROM users u
             LEFT JOIN companies c ON c.id = u.company_id
             WHERE u.email = ?',
            's',
            [$email]
        );
    } catch (Throwable $e) {
        $found = null;
    }

    $uid = $found ? (int) $found['id'] : 0;
    $cid = $found ? (int) ($found['company_id'] ?? 0) : 0;
    $role = $found ? (string) ($found['role'] ?? '') : '';
    $who = $found ? (string) ($found['name'] ?? '') : '';
    $company = $found ? trim((string) ($found['company_name'] ?? '')) : '';
    $status = $found ? trim((string) ($found['company_status'] ?? '')) : '';
    if ($found && $role === 'platform') {
        $company = 'Vellisys platform';
    }
    $deskLink = $cid > 0 ? absolute_url('admin_company.php?id=' . $cid) : absolute_url('admin_companies.php');
    $matchLine = $found
        ? h($who !== '' ? $who : $email) . ' · ' . h($role !== '' ? $role : 'desk')
        : 'No matching desk login';
    $companyLine = $company !== ''
        ? h($company) . ($status !== '' ? ' · ' . h($status) : '')
        : ($found ? 'No company on this login' : 'Unknown');

    $html = vellisys_email_wrap(
        '<p style="margin:0 0 14px">A client asked Vellisys to reset a desk password. Please help them today.</p>'
        . '<p style="margin:0 0 8px"><strong>Personal email:</strong> ' . h($email) . '</p>'
        . '<p style="margin:0 0 8px"><strong>Desk user:</strong> ' . $matchLine . '</p>'
        . '<p style="margin:0 0 8px"><strong>Company:</strong> ' . $companyLine . '</p>'
        . '<p style="margin:16px 0 0">Reset the password on the company page (People), then send it to this mailbox. Do not post the new password in a public place.</p>'
        . '<p style="margin:16px 0 0"><a href="' . h($deskLink) . '" style="color:#1E4EFF">Open the company</a></p>',
        'Password reset'
    );
    $text = "A client asked Vellisys to reset a desk password.\n\n"
        . "Personal email: {$email}\n"
        . 'Desk user: ' . ($found ? (($who !== '' ? $who : $email) . ' (' . $role . ')') : 'no matching desk login') . "\n"
        . 'Company: ' . strip_tags($companyLine) . "\n\n"
        . "Reset the password on the desk, then send it to this mailbox.\n"
        . $deskLink;

    if (function_exists('platform_alert_add')) {
        platform_alert_add(
            'password_reset',
            'Password reset · ' . $email,
            ($found ? (($who !== '' ? $who : $email) . ($company !== '' ? ' · ' . $company : '')) : 'No matching login'),
            $deskLink,
            $email,
            'urgent'
        );
    }

    return notify_platform('Password reset requested - ' . $email, $html, $text, $email, $uid);
}

function notify_admin_order(array $order, string $event): void
{
    $plan = function_exists('pricing_package') ? pricing_package((string) ($order['plan'] ?? '')) : null;
    $planName = $plan['name'] ?? (string) ($order['plan'] ?? 'desk');
    $title = match ($event) {
        'draft' => 'Incomplete checkout',
        'pending' => 'Checkout started (Pesapal)',
        'paid' => 'Desk payment received',
        'failed' => 'Desk payment failed',
        'cancelled' => 'Desk payment cancelled',
        default => 'Website checkout',
    };
    $amount = h((string) ($order['currency'] ?? '')) . ' ' . h((string) ($order['amount'] ?? ''));
    $place = order_place_label($order);
    $html = vellisys_email_wrap(
        '<p style="margin:0 0 14px">' . h($title) . ' for <strong>' . h((string) ($order['company'] ?? 'a company')) . '</strong>.</p>'
        . '<p style="margin:0 0 8px"><strong>Package:</strong> ' . h($planName) . '</p>'
        . '<p style="margin:0 0 8px"><strong>Amount:</strong> ' . $amount . ' (UGX ' . h((string) ($order['amount_ugx'] ?? '')) . ')</p>'
        . '<p style="margin:0 0 8px"><strong>Contact:</strong> ' . h((string) ($order['name'] ?? '')) . '</p>'
        . '<p style="margin:0 0 8px"><strong>Email:</strong> ' . h((string) ($order['email'] ?? '')) . '</p>'
        . '<p style="margin:0 0 8px"><strong>Phone:</strong> ' . h((string) ($order['phone'] ?? '')) . '</p>'
        . ($place !== '' ? '<p style="margin:0 0 8px"><strong>Place:</strong> ' . h($place) . '</p>' : '')
        . '<p style="margin:0 0 8px"><strong>Status:</strong> ' . h((string) ($order['status'] ?? $event)) . '</p>'
        . ((string) ($order['last_error'] ?? '') !== ''
            ? '<p style="margin:0 0 14px"><strong>Error:</strong> ' . h((string) $order['last_error']) . '</p>'
            : '')
        . '<p style="margin:0"><a href="' . h(absolute_url('admin_signups.php')) . '" style="color:#1E4EFF">Open sign-ups</a></p>'
    );
    $text = $title . ': ' . ($order['company'] ?? '') . ' / ' . $planName . ' / ' . ($order['email'] ?? '') . ' / ' . $amount;
    notify_platform('Vellisys ' . $title . ': ' . ($order['company'] ?? 'a company'), $html, $text, (string) ($order['email'] ?? ''));
    if ($event === 'paid') {
        notify_visitor_order_paid($order, $planName, $amount);
    } elseif (in_array($event, ['pending', 'failed', 'cancelled'], true)) {
        notify_visitor_payment_open($order, $planName);
    }
}

function order_place_label(array $order): string
{
    $city = trim((string) ($order['city'] ?? ''));
    $country = trim((string) ($order['country'] ?? ''));
    return trim($city . ($city !== '' && $country !== '' ? ', ' : '') . $country);
}

function order_invoice_number(array $order): string
{
    $ref = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($order['merchant_ref'] ?? '')) ?? '');
    if ($ref === '') {
        $ref = strtoupper(substr((string) ($order['public_id'] ?? 'INV'), 0, 8));
    }
    return 'VEL-INV-' . $ref;
}

function order_pay_url(array $order): string
{
    $plan = trim((string) ($order['plan'] ?? ''));
    $public = trim((string) ($order['public_id'] ?? ''));
    $q = 'checkout.php?plan=' . rawurlencode($plan);
    if ($public !== '') {
        $q .= '&o=' . rawurlencode($public);
    }
    if (function_exists('order_hosted_pay_url') && order_hosted_pay_url($order) !== '') {
        $q .= '&pay=1';
    }
    return absolute_url($q);
}

function order_amount_label(array $order): string
{
    $ccy = strtoupper(trim((string) ($order['currency'] ?? 'UGX'))) ?: 'UGX';
    return money((float) ($order['amount'] ?? 0), $ccy);
}

function order_term_label(): string
{
    if (function_exists('pricing_section')) {
        $label = trim((string) (pricing_section()['term_label'] ?? ''));
        if ($label !== '') {
            return $label;
        }
    }
    return 'per year';
}

function payment_awaits_copy(array $order, string $planName): array
{
    $who = trim((string) ($order['name'] ?? '')) ?: 'there';
    $company = trim((string) ($order['company'] ?? 'your company'));
    $amount = order_amount_label($order);
    $inv = order_invoice_number($order);
    $pay = order_pay_url($order);
    $phones = implode(' or ', product_phones());
    $term = order_term_label();
    $subject = 'Your Vellisys payment awaits - ' . $company;
    $html = vellisys_email_wrap(
        '<p style="margin:0 0 16px">Dear ' . h($who) . ',</p>'
        . '<p style="margin:0 0 14px">Thank you for choosing the <strong>' . h($planName) . '</strong> desk for <strong>' . h($company) . '</strong>. Your payment awaits.</p>'
        . '<p style="margin:0 0 14px">The amount due is <strong>' . h($amount) . '</strong> for the ' . h($term) . '. Unpaid invoice <strong>' . h($inv) . '</strong> follows in a separate email.</p>'
        . '<p style="margin:0 0 18px">Complete payment on the Vellisys checkout page to confirm the desk. If the form closed, open <a href="' . h($pay) . '" style="color:#1E4EFF">your checkout</a> again - you stay on our site. After payment you set your own admin email and password.</p>'
        . '<p style="margin:0 0 14px">If you need us, write to <a href="mailto:' . h(product_email()) . '" style="color:#1E4EFF">' . h(product_email()) . '</a> or call ' . h($phones) . '.</p>'
        . '<p style="margin:0">Kind regards,<br><strong>Vellisys</strong></p>',
        'Payment awaits'
    );
    $text = "Dear {$who},\n\n"
        . "Thank you for choosing the {$planName} desk for {$company}. Your payment awaits.\n\n"
        . "The amount due is {$amount} for the {$term}. Unpaid invoice {$inv} follows in a separate email.\n\n"
        . "Complete payment on the Vellisys checkout page to confirm the desk. If the form closed, open {$pay} again - you stay on our site. After payment you set your own admin email and password.\n\n"
        . 'If you need us, write to ' . product_email() . " or call {$phones}.\n\nKind regards,\nVellisys";
    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

function unpaid_invoice_copy(array $order, string $planName): array
{
    $who = trim((string) ($order['name'] ?? '')) ?: 'there';
    $company = trim((string) ($order['company'] ?? 'your company'));
    $email = trim((string) ($order['email'] ?? ''));
    $phone = trim((string) ($order['phone'] ?? ''));
    $place = order_place_label($order);
    $amount = order_amount_label($order);
    $ugx = money((float) ($order['amount_ugx'] ?? 0), 'UGX');
    $ccy = strtoupper(trim((string) ($order['currency'] ?? 'UGX'))) ?: 'UGX';
    $inv = order_invoice_number($order);
    $pay = order_pay_url($order);
    $term = order_term_label();
    $issued = format_date(desk_now()->format('Y-m-d')) ?: desk_now()->format('d/m/Y');
    $plan = function_exists('pricing_package') ? pricing_package((string) ($order['plan'] ?? '')) : null;
    $seats = max(1, (int) ($plan['seats'] ?? 1));
    $seatWord = pricing_staff_label($seats);
    $line = 'Vellisys ' . $planName . ' desk (' . $seatWord . '), ' . $term;
    $billTo = $company;
    if ($who !== '' && strcasecmp($who, 'there') !== 0) {
        $billTo = $who . "\n" . $company;
    }
    $phones = implode(' or ', product_phones());
    $subject = 'Unpaid invoice ' . $inv . ' - ' . $company;
    $border = '1px solid #08143A';
    $row = static function (string $label, string $value, bool $last = false) use ($border): string {
        return '<tr>'
            . '<td style="padding:10px 14px;border-bottom:' . ($last ? '0' : $border) . ';font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#08143A;width:38%;">' . h($label) . '</td>'
            . '<td style="padding:10px 14px;border-bottom:' . ($last ? '0' : $border) . ';font-size:15px;color:#000000;font-weight:600;">' . nl2br(h($value)) . '</td>'
            . '</tr>';
    };
    $html = vellisys_email_wrap(
        '<p style="margin:0 0 16px;color:#000000">Dear ' . h($who) . ',</p>'
        . '<p style="margin:0 0 18px;color:#000000">This is your unpaid Vellisys invoice. Balance due on receipt.</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #08143A;border-collapse:collapse;margin:0 0 20px;">'
        . '<tr><td colspan="2" style="background:#08143A;padding:12px 14px;">'
        . '<p style="margin:0;font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:#FFFFFF;font-weight:700;">Unpaid invoice</p>'
        . '<p style="margin:4px 0 0;font-size:13px;color:#FFFFFF;">' . h($inv) . '</p>'
        . '</td></tr>'
        . $row('Bill to', $billTo)
        . ($email !== '' ? $row('Email', $email) : '')
        . ($phone !== '' ? $row('Phone', $phone) : '')
        . ($place !== '' ? $row('Place', $place) : '')
        . $row('Issued', $issued)
        . $row('Due', 'On receipt')
        . $row('Status', 'UNPAID', true)
        . '</table>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #08143A;border-collapse:collapse;margin:0 0 20px;">'
        . '<tr><td style="background:#08143A;padding:10px 14px;font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:#FFFFFF;font-weight:700;">Description</td>'
        . '<td style="background:#08143A;padding:10px 14px;font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:#FFFFFF;font-weight:700;text-align:right;width:34%;">Amount</td></tr>'
        . '<tr><td style="padding:12px 14px;border-bottom:' . $border . ';color:#000000;">' . h($line) . '</td>'
        . '<td style="padding:12px 14px;border-bottom:' . $border . ';color:#000000;text-align:right;font-weight:700;">' . h($amount) . '</td></tr>'
        . '<tr><td style="padding:12px 14px;color:#08143A;font-weight:700;">Balance due</td>'
        . '<td style="padding:12px 14px;color:#08143A;text-align:right;font-weight:700;">' . h($amount) . '</td></tr>'
        . ($ccy !== 'UGX' ? '<tr><td style="padding:0 14px 12px;color:#000000;font-size:13px;">Charged equivalent</td><td style="padding:0 14px 12px;color:#000000;text-align:right;font-size:13px;">' . h($ugx) . '</td></tr>' : '')
        . '</table>'
        . '<p style="margin:0 0 16px"><a href="' . h($pay) . '" style="display:inline-block;background:#1E4EFF;color:#FFFFFF;text-decoration:none;padding:12px 18px;font-weight:700;">Pay this invoice</a></p>'
        . '<p style="margin:0 0 14px;color:#000000">If payment did not finish, use the button above. After payment you set your admin email and password, then sign in.</p>'
        . '<p style="margin:0 0 14px;color:#000000">Questions: <a href="mailto:' . h(product_email()) . '" style="color:#1E4EFF">' . h(product_email()) . '</a> · ' . h($phones) . '</p>'
        . '<p style="margin:0;color:#000000">Kind regards,<br><strong>Vellisys</strong></p>',
        'Unpaid invoice'
    );
    $text = "Dear {$who},\n\n"
        . "UNPAID INVOICE {$inv}\n"
        . "Bill to: {$company}\n"
        . ($email !== '' ? "Email: {$email}\n" : '')
        . ($phone !== '' ? "Phone: {$phone}\n" : '')
        . ($place !== '' ? "Place: {$place}\n" : '')
        . "Issued: {$issued}\nDue: On receipt\nStatus: UNPAID\n\n"
        . "{$line}\nAmount due: {$amount}"
        . ($ccy !== 'UGX' ? " ({$ugx})" : '') . "\n\n"
        . "Pay: {$pay}\n\n"
        . 'Questions: ' . product_email() . " · {$phones}\n\nKind regards,\nVellisys";
    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

function notify_visitor_payment_open(array $order, string $planName): void
{
    $to = strtolower(trim((string) ($order['email'] ?? '')));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $id = (int) ($order['id'] ?? 0);
    if ($id > 0 && function_exists('db_has_column') && db_has_column(db(), 'website_orders', 'notified_pending')) {
        $row = db_one('SELECT notified_pending FROM website_orders WHERE id = ?', 'i', [$id]);
        if ($row && (int) ($row['notified_pending'] ?? 0) !== 0) {
            return;
        }
    }
    $awaits = payment_awaits_copy($order, $planName);
    send_platform_email($to, $awaits['subject'], $awaits['html'], $awaits['text'], 0, product_email());
    $invoice = unpaid_invoice_copy($order, $planName);
    send_platform_email($to, $invoice['subject'], $invoice['html'], $invoice['text'], 0, product_email());
    if ($id > 0 && function_exists('db_has_column') && db_has_column(db(), 'website_orders', 'notified_pending')) {
        db_exec('UPDATE website_orders SET notified_pending=1 WHERE id=?', 'i', [$id]);
    }
}

function notify_visitor_order_paid(array $order, string $planName, string $amountHtml): void
{
    $to = strtolower(trim((string) ($order['email'] ?? '')));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $who = trim((string) ($order['name'] ?? '')) ?: 'there';
    $company = trim((string) ($order['company'] ?? 'your company'));
    $setup = function_exists('onboard_url') ? onboard_url($order) : absolute_url('login.php');
    $cid = (int) ($order['company_id'] ?? 0);
    $term = '';
    if ($cid > 0) {
        $co = db_one('SELECT * FROM companies WHERE id = ?', 'i', [$cid]);
        $term = $co ? company_term_label($co) : '';
        if ($co) {
            company_mark_onboard_step($cid, 'receipt_email');
        }
    }
    $subject = 'Thank you for your payment - set up your Vellisys desk';
    $html = vellisys_email_wrap(
        '<p style="margin:0 0 14px">Dear ' . h($who) . ',</p>'
        . '<p style="margin:0 0 14px">Thank you. We received payment for the <strong>' . h($planName) . '</strong> desk for <strong>' . h($company) . '</strong> (' . $amountHtml . '). Welcome to Vellisys.</p>'
        . ($term !== '' ? '<p style="margin:0 0 14px"><strong>Paid term:</strong> ' . h($term) . '</p>' : '')
        . '<p style="margin:0 0 14px">Your desk is waiting. Open the link below, choose the admin name, sign-in email and password you will use, then sign in with those details.</p>'
        . '<p style="margin:0 0 18px"><a href="' . h($setup) . '" style="display:inline-block;background:#1E4EFF;color:#FFFFFF;text-decoration:none;padding:10px 16px;font-weight:700;">Set up your desk</a></p>'
        . '<p style="margin:0 0 14px">If the button does not open, use ' . h($setup) . '</p>'
        . '<p style="margin:0">Kind regards,<br><strong>Vellisys</strong></p>',
        'Thank you'
    );
    $text = "Dear {$who},\n\nThank you. We received payment for the {$planName} desk for {$company} ({$amountHtml})."
        . ($term !== '' ? " Paid term: {$term}." : '')
        . "\n\nSet your admin email and password: {$setup}\n\nKind regards,\nVellisys";
    send_platform_email($to, $subject, $html, $text, 0, product_email());
}

function notify_self_onboard_done(array $company, array $made, array $order): void
{
    $to = (string) ($made['email'] ?? '');
    $login = absolute_url('login.php?email=' . rawurlencode($to));
    $who = trim((string) ($order['name'] ?? '')) ?: 'the team';
    $name = (string) ($company['name'] ?? 'your company');
    if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $html = vellisys_email_wrap(
            '<p style="margin:0 0 14px">Dear ' . h($who) . ',</p>'
            . '<p style="margin:0 0 14px">Your Vellisys desk for <strong>' . h($name) . '</strong> is ready. Sign in with the email and password you just chose.</p>'
            . '<p style="margin:0 0 18px"><a href="' . h($login) . '" style="color:#1E4EFF">Open sign in</a></p>'
            . '<p style="margin:0">After you sign in, complete company branding on Settings. Call ' . h(implode(' or ', product_phones())) . ' if you need an agent.</p>'
        );
        $text = "Your Vellisys desk for {$name} is ready. Sign in at {$login} with the email and password you chose.";
        send_platform_email($to, 'Your Vellisys desk login is ready', $html, $text, 0, product_email());
    }
    notify_platform(
        'Credentials set: ' . $name,
        '<p style="margin:0">The paid client <strong>' . h($name) . '</strong> set an admin login (' . h($to) . ').</p>'
            . '<p style="margin:8px 0 0"><a href="' . h(absolute_url('admin_company.php?id=' . (int) ($company['id'] ?? 0))) . '" style="color:#1E4EFF">Open the company</a></p>',
        'Credentials set for ' . $name . ' / ' . $to,
        $to
    );
}

function notify_client_first_login(array $company, array $user): void
{
    $name = (string) ($company['name'] ?? 'a company');
    $email = (string) ($user['email'] ?? '');
    notify_platform(
        'First sign-in: ' . $name,
        '<p style="margin:0"><strong>' . h($name) . '</strong> signed in for the first time as ' . h($email) . '.</p>'
            . '<p style="margin:8px 0 0"><a href="' . h(absolute_url('admin_company.php?id=' . (int) ($company['id'] ?? 0))) . '" style="color:#1E4EFF">Open the company</a></p>',
        'First sign-in for ' . $name . ' / ' . $email,
        $email,
        (int) ($user['id'] ?? 0)
    );
}

function send_client_first_login_email(array $company, array $user): array
{
    $to = strtolower(trim((string) ($user['email'] ?? '')));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'No email on the desk login.'];
    }
    $who = trim((string) ($user['name'] ?? '')) ?: 'the team';
    $name = (string) ($company['name'] ?? 'your company');
    $tutorials = absolute_url('tutorials.php');
    $login = absolute_url('login.php');
    $phones = implode(' or ', product_phones());
    $html = vellisys_email_wrap(
        '<p style="margin:0 0 16px">Dear ' . h($who) . ',</p>'
        . '<p style="margin:0 0 14px">You have successfully signed in to the Vellisys desk for <strong>' . h($name) . '</strong>. We wish you a perfect journey with the portal.</p>'
        . '<p style="margin:0 0 14px">Open <a href="' . h($tutorials) . '" style="color:#1E4EFF">Tutorials</a> for screenshots of every tab, or write to <a href="mailto:' . h(product_email()) . '" style="color:#1E4EFF">' . h(product_email()) . '</a> if you need an agent.</p>'
        . '<p style="margin:0 0 14px">Sign in again any time at <a href="' . h($login) . '" style="color:#1E4EFF">' . h($login) . '</a>.</p>'
        . '<p style="margin:0">Kind regards,<br><strong>Vellisys</strong></p>'
    );
    $text = "Dear {$who},\n\nYou have successfully signed in to the Vellisys desk for {$name}. We wish you a perfect journey with the portal.\n\nTutorials: {$tutorials}\nSign in: {$login}\n\nIf you need an agent, write to " . product_email() . " or call {$phones}.\n\nKind regards,\nVellisys";
    return send_platform_email($to, 'Welcome to your Vellisys desk', $html, $text, (int) ($user['id'] ?? 0), product_email());
}

function send_login_credentials_email(array $company, array $member, string $password, int $userId = 0): array
{
    $to = strtolower(trim((string) ($member['email'] ?? '')));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'No desk email to send credentials to.', 'from' => product_email()];
    }
    if ($password === '') {
        return ['ok' => false, 'error' => 'A temporary password is required.', 'from' => product_email()];
    }
    $who = trim((string) ($member['name'] ?? '')) ?: 'the team';
    $name = (string) ($company['name'] ?? 'your company');
    $login = absolute_url('login.php?email=' . rawurlencode($to));
    $phones = implode(' or ', product_phones());
    $html = vellisys_email_wrap(
        '<p style="margin:0 0 16px">Dear ' . h($who) . ',</p>'
        . '<p style="margin:0 0 14px">Your Vellisys desk for <strong>' . h($name) . '</strong> is ready. Sign in with the details below.</p>'
        . '<p style="margin:0 0 8px">Sign in: <a href="' . h($login) . '" style="color:#1E4EFF">' . h(absolute_url('login.php')) . '</a></p>'
        . '<p style="margin:0 0 8px">Email: <strong>' . h($to) . '</strong></p>'
        . '<p style="margin:0 0 14px">Temporary password: <strong>' . h($password) . '</strong></p>'
        . '<p style="margin:0 0 14px">Please change this password after you sign in (Settings → Account). Do not share it.</p>'
        . '<p style="margin:0">If you need help, write to <a href="mailto:' . h(product_email()) . '" style="color:#1E4EFF">' . h(product_email()) . '</a> or call ' . h($phones) . '.</p>'
    );
    $text = "Dear {$who},\n\nYour Vellisys desk for {$name} is ready.\n\nSign in: " . absolute_url('login.php') . "\nEmail: {$to}\nTemporary password: {$password}\n\nPlease change this password after you sign in (Settings, then Account). Do not share it.\n\nIf you need help, write to " . product_email() . " or call {$phones}.\n\nKind regards,\nVellisys";
    $result = send_platform_email($to, 'Your Vellisys login credentials', $html, $text, $userId, product_email());
    $cid = (int) ($company['id'] ?? 0);
    if ($cid > 0) {
        company_mark_onboard_step($cid, 'credentials_set');
        company_mark_onboard_step($cid, 'welcome_email');
        company_mark_onboard_step($cid, 'desk_login');
    }
    notify_platform(
        'Login credentials: ' . $name,
        '<p style="margin:0">Login credentials for <strong>' . h($name) . '</strong> were sent to ' . h($to) . ' from ' . h(product_email()) . '.</p>'
            . '<p style="margin:8px 0 0"><a href="' . h(absolute_url('admin_company.php?id=' . $cid)) . '" style="color:#1E4EFF">Open the company</a></p>',
        'Login credentials sent to ' . $to . ' for ' . $name . '.',
        $to,
        $userId
    );
    return $result;
}

function notify_branding_saved(array $company, array $user): void
{
    $name = (string) ($company['name'] ?? 'a company');
    $email = (string) ($user['email'] ?? '');
    notify_platform(
        'Branding saved: ' . $name,
        '<p style="margin:0"><strong>' . h($name) . '</strong> saved company branding on Settings (' . h($email) . ').</p>'
            . '<p style="margin:8px 0 0"><a href="' . h(absolute_url('admin_company.php?id=' . (int) ($company['id'] ?? 0))) . '" style="color:#1E4EFF">Open the company</a></p>',
        'Branding saved for ' . $name . ' / ' . $email,
        $email,
        (int) ($user['id'] ?? 0)
    );
}

function notify_admin_signup(array $signup): void
{
    try {
        $source = (string) ($signup['source'] ?? 'register');
        $isQuote = $source === 'quote';
        $isDemo = $source === 'demo';
        $note = trim((string) ($signup['note'] ?? ''));
        $intro = $isQuote
            ? 'A company asked for a Vellisys quote.'
            : ($isDemo ? 'A company booked a Vellisys demo.' : 'A company registered for a Vellisys desk (manual onboarding).');
        $html = vellisys_email_wrap(
            '<p style="margin:0 0 14px">' . $intro . '</p>'
            . '<p style="margin:0 0 8px"><strong>Kind:</strong> ' . h(signup_source_label($signup['source'] ?? null)) . '</p>'
            . '<p style="margin:0 0 8px"><strong>Contact:</strong> ' . h($signup['name'] ?? '') . '</p>'
            . '<p style="margin:0 0 8px"><strong>Company:</strong> ' . h($signup['company'] ?? '') . '</p>'
            . '<p style="margin:0 0 8px"><strong>Email:</strong> ' . h($signup['email'] ?? '') . '</p>'
            . '<p style="margin:0 0 14px"><strong>Phone:</strong> ' . h($signup['phone'] ?? '') . '</p>'
            . ($note !== ''
                ? '<p style="margin:0 0 8px"><strong>Note</strong></p><p style="margin:0 0 14px;padding:14px;background:#FFFDF8;border:1px solid #08143A;color:#000000">' . nl2br(h($note)) . '</p>'
                : '')
            . '<p style="margin:0"><a href="' . h(absolute_url('admin_signups.php')) . '" style="color:#1E4EFF">Open sign-ups</a></p>'
        );
        $kind = $isQuote ? 'quote request' : ($isDemo ? 'demo request' : 'registration');
        $text = 'New ' . $kind . ': ' . ($signup['company'] ?? '') . ' / ' . ($signup['name'] ?? '') . ' / ' . ($signup['email'] ?? '') . ' / ' . ($signup['phone'] ?? '');
        notify_platform('Vellisys ' . $kind . ': ' . ($signup['company'] ?? 'a company'), $html, $text, (string) ($signup['email'] ?? ''));
        if (function_exists('push_notify_item')) {
            $sid = (int) ($signup['id'] ?? 0);
            push_notify_item([
                'title' => trim((string) ($signup['company'] ?: $signup['name'])) ?: 'New sign-up',
                'meta' => 'Sign-up · ' . (string) ($signup['email'] ?? ''),
                'href' => url('admin_signups.php'),
                'key' => $sid > 0 ? 'signup:' . $sid : 'signup-new',
            ], 'platform');
        }
        notify_visitor_signup($signup);
    } catch (Throwable $e) {
        error_log('Vellisys signup mail: ' . $e->getMessage());
    }
}

function notify_visitor_signup(array $signup): void
{
    $to = strtolower(trim((string) ($signup['email'] ?? '')));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $who = trim((string) ($signup['name'] ?? ''));
    if ($who === '') {
        $who = 'there';
    }
    $company = trim((string) ($signup['company'] ?? 'your company'));
    $phones = implode(' or ', product_phones());
    $source = (string) ($signup['source'] ?? 'register');
    $isQuote = $source === 'quote';
    $isDemo = $source === 'demo';
    $subject = $isQuote
        ? 'We have your Vellisys quote request'
        : ($isDemo ? 'We have your Vellisys demo request' : 'We have your Vellisys registration');
    $intro = $isQuote
        ? 'Thank you for requesting a quote for <strong>' . h($company) . '</strong>.'
        : ($isDemo
            ? 'Thank you for booking a demo of Vellisys for <strong>' . h($company) . '</strong>.'
            : 'Thank you for registering <strong>' . h($company) . '</strong> for a Vellisys desk.');
    $next = $isQuote
        ? 'We have your request. A Vellisys admin will send a quote and call you to onboard the company. There is no password yet - you receive one when the desk is opened.'
        : ($isDemo
            ? 'We have your request. A Vellisys admin will write or call to set a time for the walkthrough. There is no password yet - you receive one when a desk is opened.'
            : 'We have your registration. A Vellisys admin will call you to onboard the company. There is no password yet - you receive one when the desk is opened.');
    $html = vellisys_email_wrap(
        '<p style="margin:0 0 16px">Dear ' . h($who) . ',</p>'
        . '<p style="margin:0 0 14px">' . $intro . '</p>'
        . '<p style="margin:0 0 14px">' . $next . '</p>'
        . '<p style="margin:0 0 14px">If you need us sooner, write to <a href="mailto:' . h(product_email()) . '" style="color:#1E4EFF">' . h(product_email()) . '</a> or call ' . h($phones) . '.</p>'
        . '<p style="margin:0">Kind regards,<br><strong>Vellisys</strong></p>',
        $isQuote ? 'Quote request received' : ($isDemo ? 'Demo request received' : 'Registration received')
    );
    $textIntro = $isQuote
        ? "Thank you for requesting a quote for {$company}."
        : ($isDemo
            ? "Thank you for booking a demo of Vellisys for {$company}."
            : "Thank you for registering {$company} for a Vellisys desk.");
    $text = "Dear {$who},\n\n{$textIntro}\n\n"
        . strip_tags($next) . "\n\n"
        . 'If you need us sooner, write to ' . product_email() . " or call {$phones}.\n\nKind regards,\nVellisys\n" . product_email();
    send_platform_email($to, $subject, $html, $text, 0, product_email());
}

function welcome_desk_copy(array $company, array $member, string $password = ''): array
{
    $who = $member['name'] ?: 'the team';
    $login = absolute_url('login.php');
    $tutorials = absolute_url('tutorials.php');
    $subject = 'Welcome to Vellisys - your desk for ' . $company['name'];
    $passLine = $password !== ''
        ? 'Your temporary password is ' . $password . '. Sign in, then keep this mailbox for the team.'
        : 'Use the password your Vellisys admin shared when they opened the desk.';
    $text = "Dear {$who},\n\n"
        . "The Vellisys desk for {$company['name']} is ready.\n\n"
        . "Sign in: {$login}\n"
        . "Email: {$member['email']}\n"
        . $passLine . "\n\n"
        . "A short tour:\n"
        . "1. Add a client on Clients.\n"
        . "2. Send a quotation, then convert it to an invoice when they say yes.\n"
        . "3. Record receipts so Debtors stays honest.\n"
        . "4. Capture expenses and pay suppliers from Creditors.\n"
        . "5. Print or email a branded sheet in one click - mail leaves from the company mailbox Vellisys assigned.\n"
        . "6. Open Tutorials on the desk for screenshots of every tab.\n\n"
        . "Tutorials: {$tutorials}\n\n"
        . "Kind regards,\nVellisys\nA product of " . product_maker_name() . "\n" . product_email();
    $html = vellisys_email_wrap(
        '<p style="margin:0 0 16px">Dear ' . h($who) . ',</p>'
        . '<p style="margin:0 0 14px">The Vellisys desk for <strong>' . h($company['name']) . '</strong> is ready.</p>'
        . '<p style="margin:0 0 8px">Sign in at <a href="' . h($login) . '" style="color:#1E4EFF">' . h($login) . '</a></p>'
        . '<p style="margin:0 0 8px">Email: <strong>' . h($member['email']) . '</strong></p>'
        . '<p style="margin:0 0 18px">' . h($passLine) . '</p>'
        . '<p style="margin:0 0 8px"><strong>A short tour</strong></p>'
        . '<ol style="margin:0 0 18px;padding-left:18px">'
        . '<li>Add a client on Clients.</li>'
        . '<li>Send a quotation, then convert it to an invoice when they say yes.</li>'
        . '<li>Record receipts so Debtors stays honest.</li>'
        . '<li>Capture expenses and pay suppliers from Creditors.</li>'
        . '<li>Print or email a branded sheet in one click. Mail leaves from the company mailbox Vellisys assigned, with your logo.</li>'
        . '<li>Open <a href="' . h($tutorials) . '" style="color:#1E4EFF">Tutorials</a> on the desk for screenshots of every tab.</li>'
        . '</ol>'
        . '<p style="margin:0">Reply to this email or call ' . h(implode(' or ', product_phones())) . ' if you want a walk-through.</p>'
    );
    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

function send_welcome_email(array $company, array $member, string $password = '', int $userId = 0): array
{
    $to = (string) ($member['email'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'No desk email to welcome.', 'from' => product_email()];
    }
    $copy = welcome_desk_copy($company, $member, $password);
    $result = send_platform_email($to, $copy['subject'], $copy['html'], $copy['text'], $userId);
    notify_platform(
        'Onboarded: ' . ($company['name'] ?? 'a company'),
        '<p style="margin:0 0 12px"><strong>' . h($company['name'] ?? '') . '</strong> has a desk. Welcome mail went to ' . h($to) . '.</p>'
            . '<p style="margin:0"><a href="' . h(absolute_url('admin_company.php?id=' . (int) ($company['id'] ?? 0))) . '" style="color:#1E4EFF">Open the company</a></p>',
        'Onboarded ' . ($company['name'] ?? '') . '. Welcome sent to ' . $to . '.',
        $to,
        $userId
    );
    $cid = (int) ($company['id'] ?? 0);
    if ($cid > 0) {
        company_mark_onboard_step($cid, 'welcome_email');
        company_mark_onboard_step($cid, 'desk_login');
    }
    return $result;
}

function send_live_email(array $company, array $member, int $userId = 0): array
{
    $to = (string) ($member['email'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'from' => product_email()];
    }
    $login = absolute_url('login.php');
    $subject = 'Your Vellisys desk for ' . $company['name'] . ' is live';
    $html = vellisys_email_wrap(
        '<p style="margin:0 0 14px">Dear ' . h($member['name'] ?: 'the team') . ',</p>'
        . '<p style="margin:0 0 14px">The desk for <strong>' . h($company['name']) . '</strong> is live. Sign in at <a href="' . h($login) . '" style="color:#1E4EFF">' . h($login) . '</a> and open Tutorials if you want a refresher.</p>'
        . '<p style="margin:0">We are on ' . h(implode(' or ', product_phones())) . '.</p>'
    );
    $text = 'The desk for ' . $company['name'] . " is live.\nSign in: {$login}\n";
    $result = send_platform_email($to, $subject, $html, $text, $userId);
    notify_platform(
        'Live: ' . $company['name'],
        '<p style="margin:0">' . h($company['name']) . ' is marked live. The team was told at ' . h($to) . '.</p>',
        $company['name'] . ' is live.',
        $to,
        $userId
    );
    $cid = (int) ($company['id'] ?? 0);
    if ($cid > 0) {
        company_mark_onboard_step($cid, 'desk_live');
    }
    return $result;
}

function custom_vellisys_message(string $to, string $name, string $subject, string $message, int $userId = 0): array
{
    $html = vellisys_email_wrap(
        '<p style="margin:0 0 16px">Dear ' . h($name !== '' ? $name : 'the team') . ',</p>'
        . '<div style="margin:0 0 18px">' . nl2br(h($message)) . '</div>'
        . '<p style="margin:0">Kind regards,<br><strong>Vellisys</strong></p>'
    );
    $text = "Dear " . ($name !== '' ? $name : 'the team') . ",\n\n" . $message . "\n\nKind regards,\nVellisys\n" . product_email();
    return send_platform_email($to, $subject, $html, $text, $userId);
}

function renewal_notice_copy(array $company, array $contact): array
{
    $name = $company['name'] ?? 'your company';
    $who = $contact['name'] ?: 'the team';
    $expires = company_expires_on($company);
    $when = $expires ? format_date($expires) : 'the renewal date';
    $days = company_days_left($expires);
    $phones = implode(' or ', product_phones());
    $expired = $days !== null && $days < 0;
    $term = company_term_label($company);

    if ($expired) {
        $subject = 'Action needed: Vellisys desk for ' . $name . ' has lapsed';
        $lead = 'The paid term for the Vellisys desk used by ' . $name . ' ended on ' . $when . '.';
        $ask = 'To restore the desk and keep quotations, invoices and receipts available to the team, please confirm renewal with us.';
    } else {
        $left = $days === null ? 'soon' : ($days === 0 ? 'today' : ($days === 1 ? 'in 1 day' : 'in ' . $days . ' days'));
        $subject = 'Reminder: Vellisys desk for ' . $name . ' renews on ' . $when;
        $lead = 'The paid term for the Vellisys desk used by ' . $name . ' ends on ' . $when . ' (' . $left . ').';
        $ask = 'To keep the desk live without interruption, please confirm renewal with us before that date.';
    }

    $text = "Dear " . $who . ",\n\n"
        . $lead . " The current arrangement is " . $term . ".\n\n"
        . $ask . "\n\n"
        . "Reply to this email or call " . $phones . ".\n\n"
        . "Kind regards,\nVellisys\nA product of " . product_maker_name() . "\n" . product_email();

    $html = vellisys_email_wrap(
        '<p style="margin:0 0 18px">Dear ' . h($who) . ',</p>'
        . '<p style="margin:0 0 14px">' . h($lead) . ' The current arrangement is <strong>' . h($term) . '</strong>.</p>'
        . '<p style="margin:0 0 14px">' . h($ask) . '</p>'
        . '<p style="margin:0">Reply to this email or call <strong>' . h($phones) . '</strong>.</p>'
    );

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

function payment_receipt_ref(array $company): string
{
    $id = (int) ($company['id'] ?? 0);
    $from = preg_replace('/\D+/', '', (string) ($company['paid_from'] ?? '')) ?: date('Ymd');
    return 'VEL-PAY-' . $id . '-' . substr($from, 0, 8);
}

function payment_receipt_waiting_credentials(array $company, array $members = []): bool
{
    if (($company['status'] ?? '') !== 'live') {
        return true;
    }
    foreach ($members as $m) {
        if (filter_var((string) ($m['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            return false;
        }
    }
    return true;
}

function payment_receipt_period_label(array $company): string
{
    $term = company_term_label($company);
    $started = format_date((string) ($company['paid_from'] ?? ''));
    $expires = format_date((string) ($company['expires_at'] ?? ''));
    $bits = [];
    if ($term !== '' && $term !== '-') {
        $bits[] = $term;
    }
    if ($started !== '' && $expires !== '') {
        $bits[] = $started . ' – ' . $expires;
    } elseif ($expires !== '') {
        $bits[] = 'until ' . $expires;
    } elseif ($started !== '') {
        $bits[] = 'from ' . $started;
    }
    return $bits !== [] ? implode(' · ', $bits) : '-';
}

function payment_receipt_share_secret(): string
{
    static $secret = null;
    if ($secret !== null) {
        return $secret;
    }
    $cfg = @include ROOT_PATH . '/config/database.php';
    $secret = hash('sha256', 'vellisys-pay-receipt|' . (is_array($cfg) ? (($cfg['name'] ?? '') . '|' . ($cfg['user'] ?? '')) : 'local'));
    return $secret;
}

function payment_receipt_share_token(array $company): string
{
    $id = (int) ($company['id'] ?? 0);
    $from = (string) ($company['paid_from'] ?? '');
    $paid = number_format(company_fee_paid($company), 2, '.', '');
    return hash_hmac('sha256', $id . ':' . $from . ':' . $paid, payment_receipt_share_secret());
}

function payment_receipt_share_url(array $company): string
{
    $id = (int) ($company['id'] ?? 0);
    return absolute_url('payment_receipt.php?id=' . $id . '&t=' . payment_receipt_share_token($company));
}

function payment_receipt_share_message(array $company, float $thisPayment = 0.0): string
{
    $name = (string) ($company['name'] ?? 'your company');
    $currency = company_fee_currency($company);
    $paid = company_fee_paid($company);
    $balance = company_fee_balance($company);
    $period = payment_receipt_period_label($company);
    $ref = payment_receipt_ref($company);
    $lines = [
        'Vellisys payment receipt ' . $ref,
        'Company: ' . $name,
        'Period: ' . $period,
    ];
    if ($thisPayment > 0.009) {
        $lines[] = 'This payment: ' . money($thisPayment, $currency);
    }
    $lines[] = 'Amount paid: ' . money($paid, $currency);
    $lines[] = 'Balance remaining: ' . money($balance, $currency);
    $lines[] = 'Open the receipt: ' . payment_receipt_share_url($company);
    return implode("\n", $lines);
}

function payment_receipt_whatsapp_url(array $company, string $phone = '', float $thisPayment = 0.0): string
{
    // Ignore $phone: always open WhatsApp without a recipient so the admin picks the contact.
    $text = payment_receipt_share_message($company, $thisPayment);
    return 'https://wa.me/?text=' . rawurlencode($text);
}

function payment_receipt_preview_url(array $company, float $thisPayment = 0.0): string
{
    $url = payment_receipt_share_url($company) . '&og=1';
    if ($thisPayment > 0.009) {
        $url .= '&paid=' . rawurlencode((string) $thisPayment);
    }
    return $url;
}

function payment_receipt_preview_cache_path(array $company, float $thisPayment = 0.0): string
{
    $dir = ROOT_PATH . '/uploads/share-previews';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $token = substr(payment_receipt_share_token($company), 0, 16);
    $suffix = $thisPayment > 0.009 ? ('-' . preg_replace('/\D+/', '', (string) round($thisPayment * 100))) : '';
    return $dir . '/pay-' . (int) ($company['id'] ?? 0) . '-' . $token . $suffix . '.jpg';
}

function payment_receipt_og_description(array $company, float $thisPayment = 0.0): string
{
    $currency = company_fee_currency($company);
    $bits = [
        payment_receipt_ref($company),
        (string) ($company['name'] ?? ''),
        payment_receipt_period_label($company),
    ];
    if ($thisPayment > 0.009) {
        $bits[] = 'Paid now ' . money($thisPayment, $currency);
    }
    $bits[] = 'Paid ' . money(company_fee_paid($company), $currency);
    $bits[] = 'Balance ' . money(company_fee_balance($company), $currency);
    return implode(' · ', array_filter($bits, static fn ($b) => trim((string) $b) !== '' && trim((string) $b) !== '-'));
}

/** Echo Open Graph tags so WhatsApp previews the receipt table, not the product mark. */
function payment_receipt_og_meta(array $company, float $thisPayment = 0.0): void
{
    $title = 'Payment receipt · ' . (string) ($company['name'] ?? product_name());
    $desc = payment_receipt_og_description($company, $thisPayment);
    $url = payment_receipt_share_url($company);
    if ($thisPayment > 0.009) {
        $url .= (str_contains($url, '?') ? '&' : '?') . 'paid=' . rawurlencode((string) $thisPayment);
    }
    $img = payment_receipt_preview_url($company, $thisPayment);
    echo '<meta name="description" content="' . h($desc) . '">' . "\n";
    echo '<meta property="og:site_name" content="' . h(product_name()) . '">' . "\n";
    echo '<meta property="og:title" content="' . h($title) . '">' . "\n";
    echo '<meta property="og:description" content="' . h($desc) . '">' . "\n";
    echo '<meta property="og:type" content="article">' . "\n";
    echo '<meta property="og:url" content="' . h($url) . '">' . "\n";
    echo '<meta property="og:image" content="' . h($img) . '">' . "\n";
    echo '<meta property="og:image:type" content="image/jpeg">' . "\n";
    echo '<meta property="og:image:width" content="1200">' . "\n";
    echo '<meta property="og:image:height" content="630">' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . h($title) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . h($desc) . '">' . "\n";
    echo '<meta name="twitter:image" content="' . h($img) . '">' . "\n";
}

function payment_receipt_preview_font(string $weight = 'regular'): string
{
    $bold = ROOT_PATH . '/assets/fonts/Montserrat-Bold.ttf';
    $reg = ROOT_PATH . '/assets/fonts/Montserrat-Regular.ttf';
    if ($weight === 'bold' && is_file($bold)) {
        return $bold;
    }
    if (is_file($reg)) {
        return $reg;
    }
    if (is_file('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf')) {
        return '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
    }
    return '';
}

function payment_receipt_preview_draw_text($im, float $size, int $x, int $y, string $text, $color, string $weight = 'regular'): void
{
    $font = payment_receipt_preview_font($weight);
    if ($font !== '' && function_exists('imagettftext')) {
        imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
        return;
    }
    imagestring($im, 5, $x, max(0, $y - 14), substr($text, 0, 70), $color);
}

/** Build a 1200×630 JPEG of the receipt table for WhatsApp / Open Graph. */
function payment_receipt_preview_card_bytes(array $company, float $thisPayment = 0.0): string
{
    if (!function_exists('imagecreatetruecolor')) {
        return '';
    }
    $w = 1200;
    $h = 630;
    $im = imagecreatetruecolor($w, $h);
    if ($im === false) {
        return '';
    }
    $white = imagecolorallocate($im, 255, 255, 255);
    $ink = imagecolorallocate($im, 8, 20, 58);
    $muted = imagecolorallocate($im, 91, 100, 120);
    $brand = imagecolorallocate($im, 30, 78, 255);
    $line = imagecolorallocate($im, 215, 220, 232);
    $stripe = imagecolorallocate($im, 246, 248, 252);
    $deep = imagecolorallocate($im, 8, 20, 58);
    imagefilledrectangle($im, 0, 0, $w, $h, $white);
    imagefilledrectangle($im, 0, 0, $w, 14, $brand);
    imagefilledrectangle($im, 0, $h - 14, $w, $h, $deep);

    $iconPath = function_exists('product_favicon_file') ? product_favicon_file() : '';
    if ($iconPath !== '' && is_file($iconPath)) {
        $icon = @imagecreatefrompng($iconPath);
        if ($icon !== false) {
            imagecopyresampled($im, $icon, 56, 40, 0, 0, 56, 56, imagesx($icon), imagesy($icon));
            imagedestroy($icon);
        }
    }

    $name = (string) ($company['name'] ?? 'Company');
    $ref = payment_receipt_ref($company);
    $currency = company_fee_currency($company);
    payment_receipt_preview_draw_text($im, 22, 132, 62, product_name(), $ink, 'bold');
    payment_receipt_preview_draw_text($im, 13, 132, 90, 'PAYMENT RECEIPT', $muted, 'bold');
    payment_receipt_preview_draw_text($im, 12, 56, 130, $ref, $brand, 'bold');
    payment_receipt_preview_draw_text($im, 28, 56, 172, mb_substr($name, 0, 48), $ink, 'bold');

    $rows = [
        ['Period', payment_receipt_period_label($company)],
    ];
    if ($thisPayment > 0.009) {
        $rows[] = ['This payment', money($thisPayment, $currency)];
    }
    $rows[] = ['Amount paid', money(company_fee_paid($company), $currency)];
    $rows[] = ['Fee for this term', money(company_fee_amount($company), $currency)];
    $rows[] = ['Balance remaining', money(company_fee_balance($company), $currency)];
    $rows[] = ['Account expires', format_date((string) ($company['expires_at'] ?? '')) ?: '-'];

    $tableX = 56;
    $tableY = 200;
    $tableW = $w - 112;
    $rowH = 52;
    $tableH = count($rows) * $rowH;
    imagerectangle($im, $tableX, $tableY, $tableX + $tableW, $tableY + $tableH, $ink);
    foreach ($rows as $i => $row) {
        $y0 = $tableY + ($i * $rowH);
        if ($i % 2 === 0) {
            imagefilledrectangle($im, $tableX + 1, $y0 + 1, $tableX + $tableW - 1, $y0 + $rowH - 1, $stripe);
        }
        if ($i > 0) {
            imageline($im, $tableX, $y0, $tableX + $tableW, $y0, $line);
        }
        imageline($im, $tableX + (int) ($tableW * 0.38), $y0, $tableX + (int) ($tableW * 0.38), $y0 + $rowH, $line);
        payment_receipt_preview_draw_text($im, 14, $tableX + 22, $y0 + 33, strtoupper($row[0]), $muted, 'bold');
        payment_receipt_preview_draw_text($im, 18, $tableX + (int) ($tableW * 0.38) + 22, $y0 + 34, mb_substr($row[1], 0, 42), $ink, 'bold');
    }

    ob_start();
    imagejpeg($im, null, 88);
    imagedestroy($im);
    return (string) ob_get_clean();
}

function payment_receipt_ensure_preview(array $company, float $thisPayment = 0.0): string
{
    $path = payment_receipt_preview_cache_path($company, $thisPayment);
    $paid = number_format(company_fee_paid($company), 2, '.', '');
    $stamp = $paid . '|' . (string) ($company['paid_from'] ?? '') . '|' . (string) ($company['expires_at'] ?? '') . '|' . (string) $thisPayment;
    if (is_file($path) && filesize($path) > 400) {
        $meta = @file_get_contents($path . '.meta');
        if ($meta !== false && hash_equals(trim($meta), hash('sha256', $stamp))) {
            return $path;
        }
    }
    $bytes = payment_receipt_preview_card_bytes($company, $thisPayment);
    if ($bytes === '') {
        return '';
    }
    @file_put_contents($path, $bytes);
    @file_put_contents($path . '.meta', hash('sha256', $stamp));
    return (is_file($path) && filesize($path) > 400) ? $path : '';
}

function payment_receipt_send_preview(array $company, float $thisPayment = 0.0): void
{
    $path = payment_receipt_ensure_preview($company, $thisPayment);
    if ($path === '' || !is_file($path)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: public, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

function company_notice_phone(int $companyId, array $brand = [], array $members = []): string
{
    $phone = trim((string) ($brand['phone'] ?? ''));
    if (phone_digits($phone) !== '') {
        return $phone;
    }
    foreach ($members as $m) {
        $p = trim((string) ($m['phone'] ?? ''));
        if (phone_digits($p) !== '') {
            return $p;
        }
    }
    if ($companyId > 0 && $members === []) {
        try {
            $rows = db_all('SELECT phone FROM users WHERE company_id = ? AND phone IS NOT NULL AND phone <> \'\' ORDER BY id LIMIT 5', 'i', [$companyId]);
            foreach ($rows as $r) {
                $p = trim((string) ($r['phone'] ?? ''));
                if (phone_digits($p) !== '') {
                    return $p;
                }
            }
        } catch (Throwable $e) {
        }
    }
    return '';
}

function payment_receipt_copy(array $company, array $contact, array $members = [], float $thisPayment = 0.0): array
{
    $who = trim((string) ($contact['name'] ?? ''));
    if ($who === '' || strcasecmp($who, 'the team') === 0) {
        $who = 'the team';
    }
    $name = (string) ($company['name'] ?? 'your company');
    $currency = company_fee_currency($company);
    $fee = company_fee_amount($company);
    $paid = company_fee_paid($company);
    $balance = company_fee_balance($company);
    $amountShown = $paid > 0 ? $paid : $fee;
    $started = format_date((string) ($company['paid_from'] ?? ''));
    $expires = format_date((string) ($company['expires_at'] ?? ''));
    $term = company_term_label($company);
    $period = payment_receipt_period_label($company);
    $ref = payment_receipt_ref($company);
    $phones = implode(' or ', product_phones());
    $wait = payment_receipt_waiting_credentials($company, $members);
    $login = absolute_url('login.php');
    $moneyPaid = money($amountShown, $currency);
    $moneyFee = money($fee, $currency);
    $moneyBalance = money($balance, $currency);
    $moneyThis = $thisPayment > 0.009 ? money($thisPayment, $currency) : '';

    if ($wait) {
        $nextTitle = 'Please wait for onboarding credentials';
        $nextBody = 'Thank you. Please wait while we finish onboarding. Sign-in credentials will be sent to this mailbox in a separate letter. Do not try to sign in until you receive that letter.';
        $nextText = "Please wait for onboarding credentials. We will send the desk login to this mailbox in a separate letter. Do not try to sign in until you receive it.";
    } else {
        $nextTitle = 'Your desk is ready';
        $nextBody = 'Thank you. Your Vellisys desk is live. Sign in at ' . $login . ' with the mailbox we issued for the team.';
        $nextText = 'Your desk is live. Sign in at ' . $login . ' with the mailbox we issued for the team.';
    }

    $subject = 'Thank you for your payment - Vellisys receipt';
    $text = "Dear {$who},\n\n"
        . "Thank you for trusting Vellisys with the books for {$name}. We have received your payment.\n\n"
        . "PAYMENT RECEIPT {$ref}\n"
        . "Company: {$name}\n"
        . "Period: {$period}\n"
        . ($moneyThis !== '' ? "This payment: {$moneyThis}\n" : '')
        . "Amount paid: {$moneyPaid}\n"
        . "Fee for this term: {$moneyFee}\n"
        . "Balance remaining: {$moneyBalance}\n"
        . "Paid term: {$term}\n"
        . "Term started: {$started}\n"
        . "Account expires: {$expires}\n"
        . "Currency: {$currency}\n\n"
        . "This letter is our thanks for that payment. Your desk is where quotations, invoices and receipts will leave in your branding.\n\n"
        . $nextText . "\n\n"
        . "Welcome to Vellisys. If you need us, write to " . product_email() . " or call {$phones}.\n\n"
        . "Kind regards,\nVellisys\nA product of " . product_maker_name() . "\n" . product_email();

    $row = static function (string $label, string $value, bool $last = false): string {
        $border = $last ? '0' : '1px solid #08143A';
        return '<tr>'
            . '<td style="padding:10px 14px;border-bottom:' . $border . ';font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#08143A;width:42%;">' . h($label) . '</td>'
            . '<td style="padding:10px 14px;border-bottom:' . $border . ';font-size:15px;color:#000000;font-weight:600;">' . h($value) . '</td>'
            . '</tr>';
    };

    $rowsHtml = $row('Company', $name)
        . $row('Period', $period)
        . ($moneyThis !== '' ? $row('This payment', $moneyThis) : '')
        . $row('Amount paid', $moneyPaid)
        . $row('Fee for this term', $moneyFee)
        . $row('Balance remaining', $moneyBalance)
        . $row('Paid term', $term)
        . $row('Term started', $started !== '' ? $started : '-')
        . $row('Account expires', $expires !== '' ? $expires : '-')
        . $row('Currency', $currency, true);

    $html = vellisys_email_wrap(
        '<p style="margin:0 0 16px;color:#000000">Dear ' . h($who) . ',</p>'
        . '<p style="margin:0 0 18px;color:#000000">Thank you for trusting Vellisys with the books for <strong>' . h($name) . '</strong>. We have received your payment.</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #08143A;border-collapse:collapse;margin:0 0 20px;">'
        . '<tr><td colspan="2" style="background:#08143A;padding:12px 14px;">'
        . '<p style="margin:0;font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:#FFFFFF;font-weight:700;">Payment receipt</p>'
        . '<p style="margin:4px 0 0;font-size:13px;color:#FFFFFF;">' . h($ref) . '</p>'
        . '</td></tr>'
        . $rowsHtml
        . '</table>'
        . '<p style="margin:0 0 18px;color:#000000">This letter is our thanks for that payment. Your desk is where quotations, invoices and receipts will leave in your branding.</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #1E4EFF;margin:0 0 18px;">'
        . '<tr><td style="padding:14px 16px;background:#FFFFFF;">'
        . '<p style="margin:0 0 6px;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#1E4EFF;font-weight:700;">Welcome</p>'
        . '<p style="margin:0 0 8px;font-weight:700;color:#08143A;">' . h($nextTitle) . '</p>'
        . '<p style="margin:0;color:#000000;">' . h($nextBody) . '</p>'
        . '</td></tr></table>'
        . '<p style="margin:0 0 14px;color:#000000">Welcome to Vellisys. If you need us, write to <a href="mailto:' . h(product_email()) . '" style="color:#1E4EFF">' . h(product_email()) . '</a> or call ' . h($phones) . '.</p>'
        . '<p style="margin:0;color:#000000">Kind regards,<br><strong>Vellisys</strong></p>',
        'Thank you'
    );

    return [
        'subject' => $subject,
        'html' => $html,
        'text' => $text,
        'to_name' => $who,
        'period' => $period,
        'amount_paid' => $amountShown,
        'balance' => $balance,
        'fee' => $fee,
        'this_payment' => $thisPayment,
        'currency' => $currency,
        'ref' => $ref,
    ];
}

function send_payment_receipt(array $company, array $user): array
{
    $id = (int) ($company['id'] ?? 0);
    if (!company_expires_on($company)) {
        return ['ok' => false, 'error' => 'Save a paid term with a start date before sending a payment receipt.', 'contact' => ['email' => '', 'name' => '']];
    }
    $brand = $id ? (branding_for($id) ?: []) : [];
    $members = $id ? db_all('SELECT id, name, email FROM users WHERE company_id = ? ORDER BY id', 'i', [$id]) : [];
    $contact = company_notice_email($id, $brand, $members);
    if ($contact['email'] === '' || !filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'No email on file for this company. Add a public email on stationery or a desk login.', 'contact' => $contact];
    }
    $copy = payment_receipt_copy($company, $contact, $members);
    $sent = send_platform_email($contact['email'], $copy['subject'], $copy['html'], $copy['text'], (int) ($user['id'] ?? 0));
    $status = !empty($sent['ok']) ? 'sent' : 'queued';
    notify_platform(
        'Payment receipt: ' . ($company['name'] ?? ''),
        '<p style="margin:0">A payment receipt for <strong>' . h($company['name'] ?? '') . '</strong> was ' . h($status) . ' to ' . h($contact['email']) . ' from ' . h(product_email()) . '.</p>'
            . '<p style="margin:8px 0 0">Amount ' . h(money(company_fee_paid($company) > 0 ? company_fee_paid($company) : company_fee_amount($company), company_fee_currency($company)))
            . ' · Term ' . h(format_date((string) ($company['paid_from'] ?? ''))) . ' to ' . h(format_date((string) ($company['expires_at'] ?? ''))) . '.</p>',
        'Payment receipt for ' . ($company['name'] ?? '') . ' to ' . $contact['email'] . ' (' . $status . ').',
        $contact['email'],
        (int) ($user['id'] ?? 0)
    );
    if ($id > 0) {
        company_mark_onboard_step($id, 'receipt_email');
        company_mark_onboard_step($id, 'paid_term');
    }
    return ['ok' => !empty($sent['ok']), 'contact' => $contact, 'copy' => $copy, 'status' => $status, 'error' => (string) ($sent['error'] ?? '')];
}

function send_renewal_notice(array $company, array $user): array
{
    $id = (int) ($company['id'] ?? 0);
    $brand = $id ? (branding_for($id) ?: []) : [];
    $members = $id ? db_all('SELECT id, name, email FROM users WHERE company_id = ? ORDER BY id', 'i', [$id]) : [];
    $contact = company_notice_email($id, $brand, $members);
    if ($contact['email'] === '' || !filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'No email on file for this company. Add a public email on stationery or a desk login.', 'contact' => $contact];
    }
    $copy = renewal_notice_copy($company, $contact);
    $sent = send_platform_email($contact['email'], $copy['subject'], $copy['html'], $copy['text'], (int) ($user['id'] ?? 0));
    $status = $sent['ok'] ? 'sent' : 'queued';
    db_exec(
        'INSERT INTO renewal_notices (company_id, to_email, subject, body, status) VALUES (?,?,?,?,?)',
        'issss',
        [$id, $contact['email'], $copy['subject'], $copy['text'], $status]
    );
    db_exec('UPDATE companies SET renewal_notice_sent_at = NOW() WHERE id = ?', 'i', [$id]);
    notify_platform(
        'Renewal notice: ' . ($company['name'] ?? ''),
        '<p style="margin:0">A renewal letter for <strong>' . h($company['name'] ?? '') . '</strong> was ' . h($status) . ' to ' . h($contact['email']) . '.</p>',
        'Renewal notice for ' . ($company['name'] ?? '') . ' to ' . $contact['email'] . ' (' . $status . ').',
        $contact['email'],
        (int) ($user['id'] ?? 0)
    );
    return ['ok' => $sent['ok'], 'contact' => $contact, 'copy' => $copy, 'status' => $status, 'error' => $sent['error'] ?? ''];
}

function absolute_url(string $path): string
{
    $https = function_exists('folio_request_is_https') ? folio_request_is_https() : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    if ($host === '') {
        $host = 'localhost';
    }
    if (function_exists('folio_is_live_host') && folio_is_live_host()) {
        $https = true;
        $bare = function_exists('folio_http_host') ? folio_http_host() : strtolower($host);
        if ($bare === 'vellisys.com') {
            $host = 'www.vellisys.com';
        }
    }
    $rel = url($path);
    // Guard against BASE_URL="." producing http://host./path
    $rel = preg_replace('#^\./#', '/', $rel) ?? $rel;
    if ($rel === '' || $rel[0] !== '/') {
        $rel = '/' . ltrim($rel, '/');
    }
    return ($https ? 'https' : 'http') . '://' . $host . $rel;
}
