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

function absolute_url(string $path): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . url($path);
}
