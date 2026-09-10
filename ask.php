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

if (form_is_spam('ask')) {
    ask_done(true);
}

$name = mb_substr(post('ask_name', '', 80), 0, 80);
$email = strtolower(mb_substr(post('ask_email', '', 190), 0, 190));
$phone = mb_substr(post('ask_phone', '', 40), 0, 40);
$message = mb_substr(post('ask_message', '', 2000), 0, 2000);

$_SESSION['ask_draft'] = [
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'message' => $message,
];

if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    ask_done(false, 'Your name and a valid email are required.');
}
if (mb_strlen($message) < 20) {
    ask_done(false, 'Write a little more so we know how to help - at least a sentence.');
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
    'INSERT INTO questions (name, email, phone, message, ip_hash, status) VALUES (?,?,?,?,?,?)',
    'ssssss',
    [$name, $email, $phone, $message, $ipHash, 'new']
);
form_rate_hit('ask');

unset($_SESSION['ask_form_at'], $_SESSION['ask_draft']);

$question = [
    'id' => $id,
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'message' => $message,
];
folio_redirect_then('?asked=1#ask', static function () use ($question): void {
    notify_admin_question($question);
});
