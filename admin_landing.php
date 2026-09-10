<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $form = post('form');

    if ($form === 'trust') {
        $action = post('action');
        if ($action === 'add') {
            $name = mb_substr(post('name'), 0, 160);
            $sort = (int) post('sort');
            if ($name === '') {
                $error = 'Give the client a name.';
            } else {
                $up = save_uploaded_image('logo', 'uploads/trust', 'client');
                if (!$up['ok']) {
                    $error = $up['error'] ?? 'Could not save that logo.';
                } elseif ($up['path'] === null) {
                    $error = 'Upload a logo for this client.';
                } else {
                    if ($sort <= 0) {
                        $max = db_one('SELECT MAX(sort) AS s FROM trust_clients');
                        $sort = (int) ($max['s'] ?? 0) + 10;
                    }
                    db_exec('INSERT INTO trust_clients (name, logo_path, sort) VALUES (?,?,?)', 'ssi', [$name, $up['path'], $sort]);
                    flash('Added ' . $name . ' to Clients who trust us.');
                    redirect('admin_landing.php#trust-clients');
                }
            }
        } elseif ($action === 'save') {
            $id = (int) post('id');
            $row = $id ? db_one('SELECT * FROM trust_clients WHERE id = ?', 'i', [$id]) : null;
            if (!$row) {
                flash('That client was not found.', 'err');
                redirect('admin_landing.php#trust-clients');
            }
            $name = mb_substr(post('name'), 0, 160);
            $sort = (int) post('sort');
            if ($name === '') {
                $error = 'Give the client a name.';
            } else {
                $logoPath = (string) $row['logo_path'];
                $up = save_uploaded_image('logo', 'uploads/trust', 'client');
                if (!$up['ok']) {
                    $error = $up['error'] ?? 'Could not save that logo.';
                } else {
                    if ($up['path'] !== null) {
                        maybe_unlink_upload($logoPath);
                        $logoPath = $up['path'];
                    }
                    db_exec('UPDATE trust_clients SET name=?, logo_path=?, sort=? WHERE id=?', 'ssii', [$name, $logoPath, $sort, $id]);
                    flash('Saved ' . $name . '.');
                    redirect('admin_landing.php#trust-clients');
                }
            }
        } elseif ($action === 'delete') {
            $id = (int) post('id');
            $row = $id ? db_one('SELECT * FROM trust_clients WHERE id = ?', 'i', [$id]) : null;
            if ($row) {
                maybe_unlink_upload((string) $row['logo_path']);
                db_exec('DELETE FROM trust_clients WHERE id = ?', 'i', [$id]);
                flash('Removed ' . $row['name'] . ' from the marquee.');
            }
            redirect('admin_landing.php#trust-clients');
        }
    } else {
        $id = (int) post('id');
        $card = $id ? db_one('SELECT * FROM landing_cards WHERE id = ?', 'i', [$id]) : null;
        if (!$card) {
            flash('That card was not found.', 'err');
            redirect('admin_landing.php');
        }
        $title = post('title');
        $body = post('body');
        if ($title === '' || $body === '') {
            $error = 'Title and copy are required.';
        } else {
            $imagePath = (string) $card['image_path'];
            $up = save_uploaded_image('image', 'uploads/landing', (string) $card['slot'], 3_000_000);
            if (!$up['ok']) {
                $error = $up['error'] ?? 'Could not save that picture.';
            } else {
                if ($up['path'] !== null) {
                    maybe_unlink_upload($imagePath);
                    $imagePath = $up['path'];
                }
                db_exec('UPDATE landing_cards SET title=?, body=?, image_path=? WHERE id=?', 'sssi', [$title, $body, $imagePath, $id]);
                flash('Landing card saved.');
                redirect('admin_landing.php');
            }
        }
    }
}

$cards = landing_cards();
$trust = [];
try {
    $trust = db_all('SELECT * FROM trust_clients ORDER BY sort, id');
} catch (Throwable $e) {
    $trust = [];
}

layout_admin_start('Landing', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('image') ?>Landing page</h1>
    <p class="lede">Change the pictures, the words, and the <strong>Clients who trust us</strong> logos on the public site. The favicon stays the V mark. The header uses the Vellisys logo on its own.</p>
  </div>
  <a class="btn ghost" href="<?= h(url()) ?>" target="_blank" rel="noopener">View site</a>
</div>

<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

<h2 class="landing-admin-h">Page cards</h2>
<div class="landing-admin">
  <?php foreach ($cards as $c): ?>
    <form class="card" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="form" value="card">
      <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
      <img class="landing-admin-pic" src="<?= h(landing_card_image_url($c)) ?>" alt="">
      <p class="hint" style="margin:8px 0 0"><?= h($c['section']) ?> · <?= h($c['slot']) ?></p>
      <label for="title-<?= (int) $c['id'] ?>">Title</label>
      <input id="title-<?= (int) $c['id'] ?>" name="title" required value="<?= h($c['title']) ?>">
      <label for="body-<?= (int) $c['id'] ?>">Copy</label>
      <textarea id="body-<?= (int) $c['id'] ?>" name="body" rows="4" required><?= h($c['body']) ?></textarea>
      <label for="image-<?= (int) $c['id'] ?>">Replace picture</label>
      <input id="image-<?= (int) $c['id'] ?>" name="image" type="file" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml">
      <div class="actions" style="margin-top:12px">
        <button class="btn" type="submit"><?= icon('check') ?>Save card</button>
      </div>
    </form>
  <?php endforeach; ?>
</div>

<h2 class="landing-admin-h" id="trust-clients"><?= icon('clients', 20) ?>Clients who trust us</h2>
<p class="lede" style="margin-top:-8px">These names and logos scroll on the public home page. Add a company, upload its mark, and set the order (lower numbers come first).</p>

<form class="card trust-admin-add" method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="form" value="trust">
  <input type="hidden" name="action" value="add">
  <h3 style="margin:0 0 4px">Add a client</h3>
  <label for="trust-new-name">Name</label>
  <input id="trust-new-name" name="name" required maxlength="160" placeholder="Company name">
  <label for="trust-new-logo">Logo</label>
  <input id="trust-new-logo" name="logo" type="file" required accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml">
  <label for="trust-new-sort">Order <span class="hint">(optional)</span></label>
  <input id="trust-new-sort" name="sort" type="number" min="0" step="1" placeholder="Auto">
  <div class="actions" style="margin-top:12px">
    <button class="btn" type="submit"><?= icon('plus') ?>Add to marquee</button>
  </div>
</form>

<?php if (!$trust): ?>
  <p class="empty">No clients on the marquee yet. Add one above.</p>
<?php else: ?>
  <div class="trust-admin">
    <?php foreach ($trust as $t): ?>
      <form class="card" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="trust">
        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
        <div class="trust-admin-preview">
          <img src="<?= h(trust_client_logo_url($t)) ?>" alt="">
        </div>
        <label for="trust-name-<?= (int) $t['id'] ?>">Name</label>
        <input id="trust-name-<?= (int) $t['id'] ?>" name="name" required maxlength="160" value="<?= h($t['name']) ?>">
        <label for="trust-sort-<?= (int) $t['id'] ?>">Order</label>
        <input id="trust-sort-<?= (int) $t['id'] ?>" name="sort" type="number" required min="0" step="1" value="<?= (int) $t['sort'] ?>">
        <label for="trust-logo-<?= (int) $t['id'] ?>">Replace logo</label>
        <input id="trust-logo-<?= (int) $t['id'] ?>" name="logo" type="file" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml">
        <div class="actions" style="margin-top:12px">
          <button class="btn" type="submit" name="action" value="save"><?= icon('check') ?>Save</button>
          <button class="btn ghost" type="submit" name="action" value="delete" onclick="return confirm('Remove this client from the marquee?');"><?= icon('trash') ?>Remove</button>
        </div>
      </form>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php layout_end(); ?>
