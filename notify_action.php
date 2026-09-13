<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$isPlatform = ($user['role'] ?? '') === 'platform';
if (!$isPlatform) {
    $user = require_desk_admin();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($isPlatform ? 'admin_signups.php' : 'dashboard.php');
}
csrf_check();

$action = post('action');
$key = trim(post('key'));
$back = $_SERVER['HTTP_REFERER'] ?? url($isPlatform ? 'admin_signups.php' : 'dashboard.php');
if (!is_string($back) || $back === '') {
    $back = url($isPlatform ? 'admin_signups.php' : 'dashboard.php');
}

if ($action === 'dismiss' && $key !== '') {
    notification_dismiss($key);
    redirect($back);
}

if ($action === 'done' && !$isPlatform) {
    $eventId = (int) post('event_id');
    if ($eventId > 0 && company_planner_enabled()) {
        planner_event_toggle_done($eventId);
        if ($key !== '') {
            notification_dismiss($key);
        }
        flash('Marked as done.');
    }
    redirect($back);
}

redirect($back);
