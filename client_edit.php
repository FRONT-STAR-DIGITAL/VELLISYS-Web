<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$id = (int) ($_GET['id'] ?? post('id'));
$party = $id ? db_one('SELECT * FROM parties WHERE id = ?', 'i', [$id]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = post('name');
    if ($name === '') {
        flash('Name is required.', 'err');
        redirect('client_edit.php' . ($id ? '?id=' . $id : ''));
    }
    $kind = post('kind') ?: 'customer';
    if (!in_array($kind, ['customer', 'supplier', 'both'], true)) {
        $kind = 'customer';
    }
    $tin = post('tin') ?: null;
    $phone = post('phone') ?: null;
    $email = post('email') ?: null;
    $address = post('address') ?: null;
    if ($id && $party) {
        db_exec(
            'UPDATE parties SET name=?, kind=?, tin=?, phone=?, email=?, address=? WHERE id=?',
            'ssssssi',
            [$name, $kind, $tin, $phone, $email, $address, $id]
        );
        flash('Client updated.');
        redirect('client_view.php?id=' . $id);
    }
    $newId = db_exec(
        'INSERT INTO parties (name, kind, tin, phone, email, address) VALUES (?,?,?,?,?,?)',
        'ssssss',
        [$name, $kind, $tin, $phone, $email, $address]
    );
    flash('Client added.');
    redirect('client_view.php?id=' . $newId);
}

layout_start($party ? 'Edit client' : 'New client', $user);
?>
<div class="page-head">
  <div>
    <h1><?= $party ? 'Edit client' : 'New client' ?></h1>
    <p class="lede">People and firms you invoice, quote, or pay.</p>
  </div>
</div>
<form class="card form" method="post">
  <?= csrf_field() ?>
  <?php if ($id): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
  <label for="name">Name</label>
  <input id="name" name="name" required value="<?= h($party['name'] ?? '') ?>">
  <label for="kind">Kind</label>
  <select id="kind" name="kind">
    <?php foreach (['customer' => 'Customer', 'supplier' => 'Supplier', 'both' => 'Customer and supplier'] as $k => $label): ?>
      <option value="<?= h($k) ?>" <?= ($party['kind'] ?? 'customer') === $k ? 'selected' : '' ?>><?= h($label) ?></option>
    <?php endforeach; ?>
  </select>
  <label for="tin">TIN</label>
  <input id="tin" name="tin" value="<?= h($party['tin'] ?? '') ?>">
  <label for="email">Email</label>
  <input id="email" name="email" type="email" value="<?= h($party['email'] ?? '') ?>">
  <label for="phone">Phone</label>
  <input id="phone" name="phone" value="<?= h($party['phone'] ?? '') ?>">
  <label for="address">Address</label>
  <input id="address" name="address" value="<?= h($party['address'] ?? '') ?>">
  <div class="actions" style="margin-top:16px">
    <button class="btn" type="submit">Save client</button>
    <a class="btn ghost" href="<?= h($id ? url('client_view.php?id=' . $id) : url('clients.php')) ?>">Cancel</a>
  </div>
</form>
<?php layout_end(); ?>
