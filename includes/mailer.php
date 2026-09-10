<?php
declare(strict_types=1);

function send_document_email(array $user, array $doc, string $to, string $subject, string $message): array
{
    $brand = branding();
    $fromName = $user['name'] ?: $brand['name'];
    $fromEmail = $user['email'] ?: ($brand['email'] ?: 'noreply@localhost');
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . sprintf('%s <%s>', $fromName, $fromEmail),
        'Reply-To: ' . $fromEmail,
        'X-Mailer: Vellisys',
    ];
    $html = '<p>' . nl2br(h($message)) . '</p>'
        . '<p>' . h(kind_meta($doc['kind'])['singular']) . ' <strong>' . h($doc['number']) . '</strong></p>'
        . '<p><a href="' . h(document_share_url($doc)) . '">Open the branded sheet</a></p>'
        . '<p>' . h($fromName) . '<br>' . h($brand['name']) . '</p>';

    $ok = @mail($to, $subject, $html, implode("\r\n", $headers));
    db_exec(
        'INSERT INTO emails (document_id, user_id, to_email, subject, body, status, error) VALUES (?,?,?,?,?,?,?)',
        'iisssss',
        [
            (int) $doc['id'],
            (int) $user['id'],
            $to,
            $subject,
            $message,
            $ok ? 'sent' : 'queued',
            $ok ? '' : 'mail() was not accepted. On XAMPP configure Mercury or SMTP, or use a live host.',
        ]
    );
    return ['ok' => $ok, 'from' => $fromEmail];
}

function notify_admin_question(array $q): void
{
    $to = product_email();
    $subject = 'Vellisys question from ' . ($q['name'] ?? 'a visitor');
    $lines = [
        'A visitor asked a question on the Vellisys site.',
        '',
        'Name: ' . ($q['name'] ?? ''),
        'Email: ' . ($q['email'] ?? ''),
        'Phone: ' . (($q['phone'] ?? '') !== '' ? $q['phone'] : '(none)'),
        '',
        $q['message'] ?? '',
        '',
        'Open the inbox: ' . absolute_url('admin_questions.php'),
    ];
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/plain; charset=UTF-8',
        'From: Vellisys <' . $to . '>',
        'Reply-To: ' . ($q['email'] ?? $to),
        'X-Mailer: Vellisys',
    ];
    @mail($to, $subject, implode("\n", $lines), implode("\r\n", $headers));
}

function send_platform_email(string $to, string $subject, string $html, string $text, int $userId = 0): array
{
    $from = product_email();
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: Vellisys <' . $from . '>',
        'Reply-To: ' . $from,
        'X-Mailer: Vellisys',
    ];
    $ok = @mail($to, $subject, $html, implode("\r\n", $headers));
    db_exec(
        'INSERT INTO emails (document_id, user_id, to_email, subject, body, status, error) VALUES (NULL,?,?,?,?,?,?)',
        'isssss',
        [
            $userId,
            $to,
            $subject,
            $text,
            $ok ? 'sent' : 'queued',
            $ok ? '' : 'mail() was not accepted. On XAMPP configure Mercury or SMTP, or use a live host.',
        ]
    );
    return ['ok' => $ok, 'from' => $from];
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

    $html = '<div style="font-family:Montserrat,Segoe UI,sans-serif;color:#10182c;line-height:1.55;font-size:15px">'
        . '<p style="margin:0 0 16px;font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#1E4EFF;font-weight:700">Vellisys</p>'
        . '<p style="margin:0 0 18px">Dear ' . h($who) . ',</p>'
        . '<p style="margin:0 0 14px">' . h($lead) . ' The current arrangement is <strong>' . h($term) . '</strong>.</p>'
        . '<p style="margin:0 0 14px">' . h($ask) . '</p>'
        . '<p style="margin:0 0 18px">Reply to this email or call <strong>' . h($phones) . '</strong>.</p>'
        . '<p style="margin:0 0 4px">Kind regards,</p>'
        . '<p style="margin:0"><strong>Vellisys</strong><br>A product of ' . h(product_maker_name()) . '<br>'
        . '<a href="mailto:' . h(product_email()) . '" style="color:#1E4EFF">' . h(product_email()) . '</a></p>'
        . '</div>';

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
    return ['ok' => $sent['ok'], 'contact' => $contact, 'copy' => $copy, 'status' => $status];
}

function absolute_url(string $path): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . url($path);
}
