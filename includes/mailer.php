<?php
declare(strict_types=1);

function vellisys_email_wrap(string $innerHtml, string $kicker = 'Vellisys'): string
{
    $logo = absolute_url('assets/img/our-logo.png');
    return '<div style="font-family:Montserrat,Segoe UI,sans-serif;color:#10182c;line-height:1.55;font-size:15px;max-width:640px;margin:0 auto">'
        . '<p style="margin:0 0 18px"><img src="' . h($logo) . '" alt="Vellisys" style="height:36px;width:auto;display:block"></p>'
        . '<p style="margin:0 0 16px;font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#1E4EFF;font-weight:700">' . h($kicker) . '</p>'
        . $innerHtml
        . '<p style="margin:28px 0 0;padding-top:16px;border-top:1px solid #e4e7f0;font-size:13px;color:#5c6780">'
        . '<strong style="color:#08143A">Vellisys</strong><br>A product of ' . h(product_maker_name()) . '<br>'
        . '<a href="mailto:' . h(product_email()) . '" style="color:#1E4EFF">' . h(product_email()) . '</a>'
        . '<br>' . h(implode(' · ', product_phones()))
        . '</p></div>';
}

function branded_company_wrap(array $brand, string $innerHtml): string
{
    $name = (string) ($brand['name'] ?? 'Your company');
    $color = parse_hex_color($brand['brand_color'] ?? '', '#1E4EFF');
    $logoSrc = '';
    $path = (string) ($brand['logo_path'] ?? '');
    if ($path !== '' && is_file(ROOT_PATH . '/' . ltrim($path, '/'))) {
        $logoSrc = absolute_url($path);
    }
    $header = $logoSrc !== ''
        ? '<p style="margin:0 0 18px"><img src="' . h($logoSrc) . '" alt="' . h($name) . '" style="max-height:52px;width:auto;display:block"></p>'
        : '<p style="margin:0 0 16px;font-size:18px;font-weight:700;color:' . h($color) . '">' . h($name) . '</p>';
    $phone = trim((string) ($brand['phone'] ?? ''));
    $email = trim((string) ($brand['email'] ?? ''));
    $foot = '<p style="margin:28px 0 0;padding-top:16px;border-top:1px solid #e4e7f0;font-size:13px;color:#5c6780">'
        . '<strong style="color:#10182c">' . h($name) . '</strong>';
    if ($email !== '') {
        $foot .= '<br><a href="mailto:' . h($email) . '" style="color:' . h($color) . '">' . h($email) . '</a>';
    }
    if ($phone !== '') {
        $foot .= '<br>' . h($phone);
    }
    $foot .= '</p>';
    return '<div style="font-family:Montserrat,Segoe UI,sans-serif;color:#10182c;line-height:1.55;font-size:15px;max-width:640px;margin:0 auto">'
        . $header
        . $innerHtml
        . $foot
        . '</div>';
}

function deliver_mail(array $account, string $to, string $subject, string $html, string $text, string $replyTo = ''): array
{
    $result = smtp_send($account, $to, $subject, $html, $text, $replyTo);
    $result['from'] = $account['from_email'] ?? ($result['from'] ?? '');
    if (empty($result['error'])) {
        $result['error'] = $result['ok'] ? '' : 'The mailbox did not accept this message.';
    }
    return $result;
}

function log_email(?int $documentId, int $userId, string $to, string $subject, string $body, bool $ok, string $error = ''): void
{
    if ($documentId === null) {
        db_exec(
            'INSERT INTO emails (document_id, user_id, to_email, subject, body, status, error) VALUES (NULL,?,?,?,?,?,?)',
            'isssss',
            [$userId, $to, $subject, $body, $ok ? 'sent' : 'queued', $ok ? '' : $error]
        );
        return;
    }
    db_exec(
        'INSERT INTO emails (document_id, user_id, to_email, subject, body, status, error) VALUES (?,?,?,?,?,?,?)',
        'iisssss',
        [$documentId, $userId, $to, $subject, $body, $ok ? 'sent' : 'queued', $ok ? '' : $error]
    );
}

function send_platform_email(string $to, string $subject, string $html, string $text, int $userId = 0, string $replyTo = ''): array
{
    $account = platform_mail_account();
    $result = deliver_mail($account, $to, $subject, $html, $text, $replyTo !== '' ? $replyTo : (string) $account['from_email']);
    log_email(null, $userId, $to, $subject, $text, $result['ok'], (string) ($result['error'] ?? ''));
    return $result;
}

function notify_platform(string $subject, string $html, string $text, string $replyTo = '', int $userId = 0): array
{
    $inner = $html;
    if (!str_contains($html, 'font-family:Montserrat')) {
        $inner = vellisys_email_wrap($html);
    }
    return send_platform_email(product_email(), $subject, $inner, $text, $userId, $replyTo);
}

function send_document_email(array $user, array $doc, string $to, string $subject, string $message): array
{
    $brand = branding();
    $cid = (int) ($doc['company_id'] ?? current_company_id());
    $company = $cid ? db_one('SELECT * FROM companies WHERE id = ?', 'i', [$cid]) : null;
    $account = $company ? company_mail_account($company) : null;
    if (!$account) {
        $err = 'Vellisys has not assigned a sending mailbox for this company yet. Ask the platform admin to add the Hostinger address on the company page.';
        log_email((int) $doc['id'], (int) $user['id'], $to, $subject, $message, false, $err);
        return ['ok' => false, 'from' => '', 'error' => $err];
    }
    $meta = kind_meta($doc['kind']);
    $inner = '<p style="margin:0 0 14px">' . nl2br(h($message)) . '</p>'
        . '<p style="margin:0 0 14px">' . h($meta['singular']) . ' <strong>' . h($doc['number']) . '</strong></p>'
        . '<p style="margin:0 0 14px"><a href="' . h(document_share_url($doc)) . '" style="color:' . h(parse_hex_color($brand['brand_color'] ?? '', '#1E4EFF')) . '">Open the branded sheet</a></p>'
        . '<p style="margin:0">' . h($user['name'] ?: $brand['name']) . '<br>' . h($brand['name']) . '</p>';
    $html = branded_company_wrap($brand, $inner);
    $text = $message . "\n\n" . $meta['singular'] . ' ' . $doc['number'] . "\n" . document_share_url($doc);
    $reply = $account['from_email'];
    $result = deliver_mail($account, $to, $subject, $html, $text, $reply);
    log_email((int) $doc['id'], (int) $user['id'], $to, $subject, $message, $result['ok'], (string) ($result['error'] ?? ''));
    notify_platform(
        'Sent: ' . $meta['singular'] . ' ' . $doc['number'] . ' from ' . $brand['name'],
        '<p style="margin:0 0 12px"><strong>' . h($brand['name']) . '</strong> sent ' . h(strtolower($meta['singular'])) . ' <strong>' . h($doc['number']) . '</strong> to ' . h($to) . ' from ' . h($account['from_email']) . '.</p>'
            . '<p style="margin:0">Status: ' . ($result['ok'] ? 'sent' : 'queued') . '.</p>',
        $brand['name'] . ' sent ' . $meta['singular'] . ' ' . $doc['number'] . ' to ' . $to . '.',
        $account['from_email'],
        (int) $user['id']
    );
    $result['from'] = $account['from_email'];
    return $result;
}

function notify_admin_question(array $q): void
{
    $name = (string) ($q['name'] ?? '');
    $email = (string) ($q['email'] ?? '');
    $phone = (string) ($q['phone'] ?? '');
    $message = (string) ($q['message'] ?? '');
    $html = vellisys_email_wrap(
        '<p style="margin:0 0 14px">A visitor asked a question on the Vellisys site.</p>'
        . '<p style="margin:0 0 8px"><strong>Name:</strong> ' . h($name) . '</p>'
        . '<p style="margin:0 0 8px"><strong>Email:</strong> ' . h($email) . '</p>'
        . '<p style="margin:0 0 8px"><strong>Phone:</strong> ' . h($phone !== '' ? $phone : 'Not given') . '</p>'
        . '<p style="margin:16px 0;padding:14px;background:#f5f7fc;border-radius:8px">' . nl2br(h($message)) . '</p>'
        . '<p style="margin:0"><a href="' . h(absolute_url('admin_question.php?id=' . (int) ($q['id'] ?? 0))) . '" style="color:#1E4EFF">Open the question</a></p>'
    );
    $text = "A visitor asked a question on the Vellisys site.\n\nName: {$name}\nEmail: {$email}\nPhone: " . ($phone !== '' ? $phone : '(none)') . "\n\n{$message}\n\nOpen: " . absolute_url('admin_questions.php');
    notify_platform('Vellisys question from ' . ($name !== '' ? $name : 'a visitor'), $html, $text, $email);
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
    $subject = 'We have your question - Vellisys';
    $html = vellisys_email_wrap(
        '<p style="margin:0 0 16px">Dear ' . h($who) . ',</p>'
        . '<p style="margin:0 0 14px">Thank you for writing to Vellisys. We have your question and a member of the team will reply to this email.</p>'
        . ($message !== ''
            ? '<p style="margin:0 0 8px"><strong>Your note</strong></p><p style="margin:0 0 18px;padding:14px;background:#f5f7fc;border-radius:8px">' . nl2br(h($message)) . '</p>'
            : '')
        . '<p style="margin:0 0 14px">If it is urgent, call ' . h($phones) . '.</p>'
        . '<p style="margin:0">Kind regards,<br><strong>Vellisys</strong></p>',
        'We have your question'
    );
    $text = "Dear {$who},\n\nThank you for writing to Vellisys. We have your question and a member of the team will reply to this email.\n\n"
        . ($message !== '' ? "Your note:\n{$message}\n\n" : '')
        . "If it is urgent, call {$phones}.\n\nKind regards,\nVellisys\n" . product_email();
    send_platform_email($to, $subject, $html, $text, 0, product_email());
}

function notify_admin_signup(array $signup): void
{
    $html = vellisys_email_wrap(
        '<p style="margin:0 0 14px">A company asked for a Vellisys desk.</p>'
        . '<p style="margin:0 0 8px"><strong>Contact:</strong> ' . h($signup['name'] ?? '') . '</p>'
        . '<p style="margin:0 0 8px"><strong>Company:</strong> ' . h($signup['company'] ?? '') . '</p>'
        . '<p style="margin:0 0 8px"><strong>Email:</strong> ' . h($signup['email'] ?? '') . '</p>'
        . '<p style="margin:0 0 14px"><strong>Phone:</strong> ' . h($signup['phone'] ?? '') . '</p>'
        . '<p style="margin:0"><a href="' . h(absolute_url('admin_signups.php')) . '" style="color:#1E4EFF">Open sign-ups</a></p>'
    );
    $text = 'New sign-up: ' . ($signup['company'] ?? '') . ' / ' . ($signup['name'] ?? '') . ' / ' . ($signup['email'] ?? '') . ' / ' . ($signup['phone'] ?? '');
    notify_platform('Vellisys sign-up: ' . ($signup['company'] ?? 'a company'), $html, $text, (string) ($signup['email'] ?? ''));
    notify_visitor_signup($signup);
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
    $subject = 'We have your Vellisys registration';
    $html = vellisys_email_wrap(
        '<p style="margin:0 0 16px">Dear ' . h($who) . ',</p>'
        . '<p style="margin:0 0 14px">Thank you for registering <strong>' . h($company) . '</strong> for a Vellisys desk.</p>'
        . '<p style="margin:0 0 14px">We have your request. A Vellisys admin will call you to onboard the company. There is no password yet - you receive one when the desk is opened.</p>'
        . '<p style="margin:0 0 14px">If you need us sooner, write to <a href="mailto:' . h(product_email()) . '" style="color:#1E4EFF">' . h(product_email()) . '</a> or call ' . h($phones) . '.</p>'
        . '<p style="margin:0">Kind regards,<br><strong>Vellisys</strong></p>',
        'Registration received'
    );
    $text = "Dear {$who},\n\nThank you for registering {$company} for a Vellisys desk.\n\n"
        . "We have your request. A Vellisys admin will call you to onboard the company. There is no password yet - you receive one when the desk is opened.\n\n"
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
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . url($path);
}
