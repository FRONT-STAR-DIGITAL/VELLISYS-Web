<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_sales_agent();

$leadId = (int) ($_GET['lead'] ?? post('lead_id'));
if ($leadId < 1) {
    flash('Open an interested lead to start a test desk.');
    redirect('sales_leads.php?status=interested');
}

$lead = sales_lead($leadId);
if (!$lead || (int) ($lead['agent_id'] ?? 0) !== (int) $user['id'] || !empty($lead['deleted_at'])) {
    flash('Lead not found.', 'err');
    redirect('sales_leads.php');
}

if (!empty($lead['company_id'])) {
    $existing = db_one('SELECT id, testing_mode FROM companies WHERE id = ?', 'i', [(int) $lead['company_id']]);
    if ($existing && !empty($existing['testing_mode'])) {
        flash('This lead already has a testing desk.');
        redirect('sales_lead_edit.php?id=' . $leadId . '#lead-testing');
    }
}

if ((string) ($lead['status'] ?? '') !== 'interested') {
    flash('Mark the lead as Interested before opening a test desk.', 'err');
    redirect('sales_lead_edit.php?id=' . $leadId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $made = sales_start_testing_from_lead($leadId, (int) $user['id'], post('desk_email'));
    if (empty($made['ok'])) {
        flash((string) ($made['error'] ?? 'Could not create the testing desk.'), 'err');
        redirect('sales_lead_edit.php?id=' . $leadId . '#lead-testing');
    }
    $_SESSION['testing_creds'] = [
        'company_id' => (int) $made['company_id'],
        'name' => (string) $made['name'],
        'email' => (string) $made['email'],
        'password' => (string) $made['password'],
        'expires_at' => (string) $made['expires_at'],
    ];
    flash($made['name'] . ' test desk is ready. Login is on the lead page.');
    redirect('sales_lead_edit.php?id=' . $leadId . '#lead-testing');
}

redirect('sales_lead_edit.php?id=' . $leadId . '#lead-testing');
