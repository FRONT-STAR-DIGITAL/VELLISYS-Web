<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$role = (string) ($user['role'] ?? '');
$isPlatform = $role === 'platform';
$isSales = $role === 'sales_agent';
$defaultBack = $isPlatform
    ? 'admin_signups.php'
    : ($isSales ? (function_exists('sales_home') ? sales_home() : 'sales_home.php') : 'dashboard.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($defaultBack);
}
csrf_check();

$action = post('action');
$key = trim(post('key'));
$back = $_SERVER['HTTP_REFERER'] ?? url($defaultBack);
if (!is_string($back) || $back === '') {
    $back = url($defaultBack);
}

// Any portal login can dismiss a bell item they can see.
if ($action === 'dismiss' && $key !== '') {
    notification_dismiss($key);
    redirect($back);
}

// Planner Done is desk-only (not platform / sales).
if ($action === 'done' && !$isPlatform && !$isSales) {
    $eventId = (int) post('event_id');
    if ($eventId > 0 && function_exists('company_planner_enabled') && company_planner_enabled()) {
        planner_event_toggle_done($eventId);
        if ($key !== '') {
            notification_dismiss($key);
        }
        flash('Marked as done.');
    }
    redirect($back);
}

redirect($back);
