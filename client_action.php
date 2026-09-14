<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

csrf_check();
$id = (int) post('id');
$action = post('action');
$view = post('next') === 'view';

try {
    if ($action === 'delete') {
        flash(delete_party($id));
        redirect('clients.php');
    }
    if ($action === 'status') {
        set_party_status($id, post('status'));
        $party = db_one('SELECT name, status FROM parties WHERE id = ? AND company_id = ?', 'ii', [$id, current_company_id()]);
        flash(($party['name'] ?? 'Client') . ' is now ' . strtolower(party_status_label($party ?? [])) . '.');
        redirect($view ? 'client_view.php?id=' . $id : 'clients.php');
    }
    throw new RuntimeException('That action is not available.');
} catch (Throwable $e) {
    flash($e->getMessage(), 'err');
    redirect($id > 0 ? 'client_view.php?id=' . $id : 'clients.php');
}
