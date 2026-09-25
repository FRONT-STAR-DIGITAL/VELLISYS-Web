<?php
declare(strict_types=1);

function ask_contact_topics(): array
{
    return [
        'pricing' => 'Pricing and packages',
        'onboarding' => 'Onboarding help',
        'demo' => 'Book or ask about a demo',
        'technical' => 'Technical / desk issue',
        'billing' => 'Billing or renewal',
        'partnership' => 'Partnership or reseller',
        'other' => 'Other',
    ];
}

function ask_contact_topic_label(string $key): string
{
    return ask_contact_topics()[$key] ?? $key;
}

function desk_feedback_save(array $fields, int $companyId, int $userId): array
{
    $message = trim((string) ($fields['message'] ?? ''));
    if (mb_strlen($message) < 10) {
        return ['ok' => false, 'error' => 'Write a short note so we know how to help.'];
    }
    $id = db_exec(
        'INSERT INTO desk_feedback (company_id, user_id, message, status) VALUES (?,?,?,?)',
        'iiss',
        [$companyId, $userId, mb_substr($message, 0, 4000), 'new']
    );
    return ['ok' => true, 'id' => (int) $id];
}

function desk_feedback_for_company(int $companyId, int $limit = 40): array
{
    return db_all(
        'SELECT f.*, u.name AS user_name, u.email AS user_email
         FROM desk_feedback f
         LEFT JOIN users u ON u.id = f.user_id
         WHERE f.company_id = ?
         ORDER BY f.id DESC LIMIT ' . (int) $limit,
        'i',
        [$companyId]
    );
}

function desk_feedback_open(int $limit = 80): array
{
    return db_all(
        'SELECT f.*, u.name AS user_name, u.email AS user_email, c.name AS company_name
         FROM desk_feedback f
         LEFT JOIN users u ON u.id = f.user_id
         LEFT JOIN companies c ON c.id = f.company_id
         WHERE f.status IN (\'new\',\'read\')
         ORDER BY FIELD(f.status,\'new\',\'read\'), f.id DESC
         LIMIT ' . (int) $limit
    );
}

function desk_feedback_done(int $limit = 40): array
{
    return db_all(
        'SELECT f.*, u.name AS user_name, u.email AS user_email, c.name AS company_name
         FROM desk_feedback f
         LEFT JOIN users u ON u.id = f.user_id
         LEFT JOIN companies c ON c.id = f.company_id
         WHERE f.status = \'replied\'
         ORDER BY f.replied_at DESC, f.id DESC
         LIMIT ' . (int) $limit
    );
}

function desk_feedback_one(int $id): ?array
{
    return db_one(
        'SELECT f.*, u.name AS user_name, u.email AS user_email, c.name AS company_name
         FROM desk_feedback f
         LEFT JOIN users u ON u.id = f.user_id
         LEFT JOIN companies c ON c.id = f.company_id
         WHERE f.id = ?',
        'i',
        [$id]
    );
}

function desk_feedback_new_count(): int
{
    try {
        return (int) (db_one("SELECT COUNT(*) c FROM desk_feedback WHERE status = 'new'")['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function desk_feedback_reply(int $id, string $reply, int $adminId): array
{
    $row = desk_feedback_one($id);
    if (!$row) {
        return ['ok' => false, 'error' => 'Feedback not found.'];
    }
    $reply = trim($reply);
    if (mb_strlen($reply) < 2) {
        return ['ok' => false, 'error' => 'Write a reply.'];
    }
    db_exec(
        "UPDATE desk_feedback SET status='replied', reply_body=?, replied_by=?, replied_at=NOW(), updated_at=NOW() WHERE id=?",
        'sii',
        [mb_substr($reply, 0, 4000), $adminId, $id]
    );
    return ['ok' => true, 'row' => desk_feedback_one($id)];
}

function notify_admin_desk_feedback(array $row): void
{
    $id = (int) ($row['id'] ?? 0);
    $company = (string) ($row['company_name'] ?? 'Company');
    $who = (string) ($row['user_name'] ?? 'Desk user');
    $email = (string) ($row['user_email'] ?? '');
    $message = (string) ($row['message'] ?? '');
    $href = absolute_url('admin_feedback.php?id=' . $id);
    $html = vellisys_email_wrap(
        '<p style="margin:0 0 14px">A company desk asked for assistance.</p>'
        . '<p style="margin:0 0 8px"><strong>Company:</strong> ' . h($company) . '</p>'
        . '<p style="margin:0 0 8px"><strong>From:</strong> ' . h($who) . ($email !== '' ? ' · ' . h($email) : '') . '</p>'
        . '<p style="margin:0 0 8px"><strong>Message</strong></p>'
        . '<p style="margin:0 0 16px;padding:14px;background:#FFFDF8;border:1px solid #08143A">' . nl2br(h($message)) . '</p>'
        . '<p style="margin:0"><a href="' . h($href) . '" style="color:#1E4EFF">Open and reply</a></p>',
        'Desk feedback'
    );
    $text = "Desk feedback from {$company}\nFrom: {$who} {$email}\n\n{$message}\n\nOpen: {$href}";
    if (function_exists('platform_alert_add')) {
        platform_alert_add(
            'desk_feedback',
            'Desk help · ' . $company,
            clip_text($message, 80),
            url('admin_feedback.php?id=' . $id),
            $email,
            'urgent'
        );
    }
    notify_platform('Desk feedback - ' . $company, $html, $text, $email);
}

function notify_desk_feedback_reply(array $row): void
{
    $to = strtolower(trim((string) ($row['user_email'] ?? '')));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $who = (string) ($row['user_name'] ?? 'there');
    $company = (string) ($row['company_name'] ?? 'your desk');
    $reply = (string) ($row['reply_body'] ?? '');
    $original = (string) ($row['message'] ?? '');
    $html = vellisys_email_wrap(
        '<p style="margin:0 0 14px">Hello ' . h($who) . ',</p>'
        . '<p style="margin:0 0 14px">Vellisys replied to your help request for ' . h($company) . '.</p>'
        . '<p style="margin:0 0 8px"><strong>Your note</strong></p>'
        . '<p style="margin:0 0 14px;padding:12px;background:#f5f7fc;border:1px solid #d8dde8">' . nl2br(h($original)) . '</p>'
        . '<p style="margin:0 0 8px"><strong>Our reply</strong></p>'
        . '<p style="margin:0 0 16px;padding:12px;background:#FFFDF8;border:1px solid #08143A">' . nl2br(h($reply)) . '</p>'
        . '<p style="margin:0">You can also open <a href="' . h(absolute_url('feedback.php')) . '" style="color:#1E4EFF">Feedback</a> on your desk.</p>',
        'Help reply'
    );
    $text = "Hello {$who},\n\nVellisys replied to your help request.\n\nYour note:\n{$original}\n\nOur reply:\n{$reply}\n\nOpen: " . absolute_url('feedback.php');
    send_platform_email($to, 'Vellisys replied to your help request', $html, $text, (int) ($row['user_id'] ?? 0));
}
