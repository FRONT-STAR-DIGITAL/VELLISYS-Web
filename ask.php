<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

function ask_done(bool $ok, string $message = ''): never
{
    unset($_SESSION['ask_form_at']);
    if ($ok) {
        unset($_SESSION['ask_draft']);
        redirect('?asked=1#ask');
    }
    if ($message !== '') {
        flash($message, 'err');
    }
    redirect('#ask');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('#ask');
}

csrf_check();

if (form_is_spam('ask', 3)) {
    ask_done(true);
}

$name = post_plain('ask_name', 80);
$email = strtolower(post_plain('ask_email', 190));
$phone = post_plain('ask_phone', 40);
$topic = post_plain('ask_topic', 60);
$message = post_plain('ask_message', 2000, true);
$topics = ask_contact_topics();

$_SESSION['ask_draft'] = [
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'topic' => $topic,
    'message' => $message,
];

if (public_form_looks_like_spam(['name' => $name, 'email' => $email, 'message' => $message, 'phone' => $phone])) {
    ask_done(true);
}
if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    ask_done(false, 'Your name and a valid email are required.');
}
if ($phone !== '' && !public_phone_ok($phone)) {
    ask_done(false, 'Please enter a working phone number, or leave it blank.');
}
if ($topic === '' || !isset($topics[$topic])) {
    ask_done(false, 'Choose why you are contacting us.');
}
if ($message !== '' && mb_strlen($message) < 3) {
    ask_done(false, 'Tell us a little more, or leave that field blank.');
}
if ($message === '') {
    $message = 'Contact reason: ' . ask_contact_topic_label($topic);
}

$ipHash = visitor_ip_hash();
$recent = db_one(
    'SELECT COUNT(*) AS c FROM questions WHERE ip_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
    's',
    [$ipHash]
);
if (form_rate_blocked('ask', 3) || (int) ($recent['c'] ?? 0) >= 3) {
    ask_done(false, 'Please wait a bit before sending another question.');
}

$id = db_exec(
    'INSERT INTO questions (name, email, phone, topic, message, ip_hash, status) VALUES (?,?,?,?,?,?,?)',
    'sssssss',
    [$name, $email, $phone, $topic, $message, $ipHash, 'new']
);
form_rate_hit('ask');

unset($_SESSION['ask_form_at'], $_SESSION['ask_draft']);

$question = [
    'id' => $id,
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'topic' => $topic,
    'message' => $message,
];
folio_redirect_then('?asked=1#ask', static function () use ($question): void {
    notify_admin_question($question);
});
