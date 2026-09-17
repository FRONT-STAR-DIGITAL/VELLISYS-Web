<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

$id = (int) ($_GET['id'] ?? post('id'));
$cid = current_company_id();
$party = $id ? db_one('SELECT * FROM parties WHERE id = ? AND company_id = ?', 'ii', [$id, $cid]) : null;

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
    $contact = post('contact_person') ?: null;
    $phone = post('phone') ?: null;
    $phone2 = post('phone2') ?: null;
    $email = post('email') ?: null;
    $address = post('address') ?: null;
    $city = post('city') ?: null;
    $country = post('country') ?: null;
    $notes = post('party_notes') ?: null;
    $status = party_normalize_status(post('status'));
    $entity = function_exists('normalize_party_entity') ? normalize_party_entity(post('to_entity') ?: post('entity') ?: '') : 'person';
    $profileJson = function_exists('posted_to_extras')
        ? json_encode(compact_party_extras(merge_party_profile($party ? party_profile($party) : [], posted_to_extras())), JSON_UNESCAPED_UNICODE)
        : null;
    if ($id && $party) {
        db_exec(
            'UPDATE parties SET name=?, kind=?, status=?, tin=?, contact_person=?, phone=?, phone2=?, email=?, address=?, city=?, country=?, notes=? WHERE id=? AND company_id=?',
            'ssssssssssssii',
            [$name, $kind, $status, $tin, $contact, $phone, $phone2, $email, $address, $city, $country, $notes, $id, $cid]
        );
        if (function_exists('persist_party_client_fields')) {
            persist_party_client_fields($id, ['entity' => $entity, 'profile' => $profileJson]);
        }
        flash('Client updated.');
        if (function_exists('record_company_activity')) {
            record_company_activity('client', 'Updated ' . $name, [
                'href' => 'client_view.php?id=' . $id,
                'ref_type' => 'party',
                'ref_id' => $id,
            ]);
        }
        redirect('client_view.php?id=' . $id);
    }
    $newId = db_exec(
        'INSERT INTO parties (company_id, name, kind, status, tin, contact_person, phone, phone2, email, address, city, country, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
        'issssssssssss',
        [$cid, $name, $kind, $status, $tin, $contact, $phone, $phone2, $email, $address, $city, $country, $notes]
    );
    if (function_exists('persist_party_client_fields')) {
        persist_party_client_fields((int) $newId, ['entity' => $entity, 'profile' => $profileJson]);
    }
    flash('Client added.');
    if (function_exists('record_company_activity')) {
        record_company_activity('client', 'Added ' . $name, [
            'href' => 'client_view.php?id=' . $newId,
            'ref_type' => 'party',
            'ref_id' => $newId,
        ]);
    }
    redirect('client_view.php?id=' . $newId);
}

layout_start($party ? 'Edit client' : 'New client', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon($party ? 'pencil' : 'plus') ?><?= $party ? 'Edit client' : 'New client' ?></h1>
    <p class="lede">People and firms you invoice, quote, or pay. Give the address and phones room so they print clearly on the sheet.</p>
    <?php if ($party && party_status($party) === 'deleted'): ?>
      <p class="lede">This client was removed from the list. Their documents stay on the books.</p>
    <?php endif; ?>
  </div>
</div>
<form class="card form-wide" method="post">
  <?= csrf_field() ?>
  <?php if ($id): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
  <div class="form-grid">
    <div>
      <label for="name">Name</label>
      <input id="name" name="name" required value="<?= h($party['name'] ?? '') ?>">
    </div>
    <div>
      <label for="kind">Kind</label>
      <select id="kind" name="kind">
        <?php foreach (['customer' => 'Customer', 'supplier' => 'Supplier', 'both' => 'Customer and supplier'] as $k => $label): ?>
          <option value="<?= h($k) ?>" <?= ($party['kind'] ?? 'customer') === $k ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="to_entity">Person, company or other</label>
      <select id="to_entity" name="to_entity" data-to-entity>
        <?php $ent = $party ? party_entity($party) : 'person'; ?>
        <option value="person" <?= $ent === 'person' ? 'selected' : '' ?>>Individual</option>
        <option value="organisation" <?= $ent === 'organisation' ? 'selected' : '' ?>>Company / organisation</option>
        <option value="other" <?= $ent === 'other' ? 'selected' : '' ?>>Other</option>
      </select>
    </div>
    <div>
      <label for="status">Status</label>
      <select id="status" name="status">
        <?php foreach (party_statuses() as $k => $label): ?>
          <option value="<?= h($k) ?>" <?= party_status($party ?? []) === $k ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="contact_person">Contact person</label>
      <input id="contact_person" name="contact_person" value="<?= h($party['contact_person'] ?? '') ?>">
    </div>
    <div>
      <label for="tin">TIN</label>
      <input id="tin" name="tin" value="<?= h($party['tin'] ?? '') ?>">
    </div>
    <div>
      <label for="email">Email</label>
      <input id="email" name="email" type="email" value="<?= h($party['email'] ?? '') ?>">
    </div>
    <div>
      <label for="phone">Phone</label>
      <input id="phone" name="phone" value="<?= h($party['phone'] ?? '') ?>">
    </div>
    <div>
      <label for="phone2">Second phone</label>
      <input id="phone2" name="phone2" value="<?= h($party['phone2'] ?? '') ?>">
    </div>
    <div>
      <label for="city">City</label>
      <input id="city" name="city" value="<?= h($party['city'] ?? '') ?>">
    </div>
    <div>
      <label for="country">Country</label>
      <input id="country" name="country" value="<?= h($party['country'] ?? '') ?>">
    </div>
  </div>
  <label for="address">Address</label>
  <textarea id="address" name="address" rows="5" placeholder="Street, building, P.O. Box…"><?= h($party['address'] ?? '') ?></textarea>
  <?php if (function_exists('render_client_edit_extras')) { render_client_edit_extras($party ?? []); } ?>
  <label for="party_notes">Notes</label>
  <textarea id="party_notes" name="party_notes" rows="4" placeholder="Delivery hours, gate codes, who to copy on email…"><?= h($party['notes'] ?? '') ?></textarea>
  <div class="actions" style="margin-top:16px">
    <button class="btn" type="submit"><?= icon('check') ?>Save client</button>
    <a class="btn ghost" href="<?= h($id ? url('client_view.php?id=' . $id) : url('clients.php')) ?>">Cancel</a>
    <?php if ($id && $party && party_status($party) !== 'deleted'): ?>
      <?php render_party_delete_button($id, true); ?>
    <?php endif; ?>
  </div>
</form>
<?php layout_end(); ?>
