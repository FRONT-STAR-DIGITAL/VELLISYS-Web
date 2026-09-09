<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$receiveId = (int) ($_GET['receive'] ?? 0);
if ($receiveId && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $doc = load_document($receiveId);
    if (!$doc || $doc['kind'] !== 'invoice') {
        flash('Invoice not found.', 'err');
        redirect('documents.php?kind=invoice');
    }
    $balance = invoice_balance($doc);
    layout_start('Receipt for ' . $doc['number'], $user, ['kind' => 'receipt']);
    ?>
    <div class="page-head">
      <div>
        <h1>Take a receipt</h1>
        <p class="lede"><?= h($doc['party_name']) ?> still owes <?= h(ugx($balance)) ?> on <?= h($doc['number']) ?>.</p>
      </div>
    </div>
    <form class="card form" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="receive">
      <input type="hidden" name="id" value="<?= $receiveId ?>">
      <label for="amount">Amount (UGX)</label>
      <input id="amount" name="amount" inputmode="numeric" required value="<?= (int) $balance ?>">
      <label for="payment_method">Paid how</label>
      <select id="payment_method" name="payment_method">
        <?php foreach (payment_methods() as $k => $label): ?>
          <option value="<?= h($k) ?>"><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
      <label for="payment_ref">Reference</label>
      <input id="payment_ref" name="payment_ref">
      <div class="actions" style="margin-top:16px">
        <button class="btn" type="submit">Save receipt</button>
        <a class="btn ghost" href="<?= h(url('document_view.php?id=' . $receiveId)) ?>">Cancel</a>
      </div>
    </form>
    <?php
    layout_end();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}
csrf_check();
$id = (int) post('id');
$action = post('action');
$doc = load_document($id);
if (!$doc) {
    flash('Document not found.', 'err');
    redirect('dashboard.php');
}

try {
    if ($action === 'void') {
        void_document($id, post('reason') ?: 'Voided from desk');
        flash($doc['number'] . ' was voided.');
        redirect('document_view.php?id=' . $id);
    }
    if ($action === 'convert') {
        $newId = convert_quotation_to_invoice($id);
        flash('Invoice created from ' . $doc['number'] . '.');
        redirect('document_view.php?id=' . $newId);
    }
    if ($action === 'receive') {
        $amount = (int) preg_replace('/\D/', '', post('amount'));
        $newId = receive_on_invoice($id, $amount, post('payment_method') ?: 'bank-transfer', post('payment_ref'));
        flash('Receipt saved.');
        redirect('document_view.php?id=' . $newId);
    }
} catch (Throwable $e) {
    flash($e->getMessage(), 'err');
    redirect('document_view.php?id=' . $id);
}

flash('Nothing to do.', 'err');
redirect('document_view.php?id=' . $id);
