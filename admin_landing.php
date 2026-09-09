<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
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
        if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            $ext = strtolower(pathinfo((string) $_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
                $error = 'Use PNG, JPG, GIF or WebP.';
            } elseif (($_FILES['image']['size'] ?? 0) > 3_000_000) {
                $error = 'Keep the picture under 3 MB.';
            } else {
                $dir = ROOT_PATH . '/uploads/landing';
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
                $fname = $card['slot'] . '-' . date('YmdHis') . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $dir . '/' . $fname)) {
                    $imagePath = 'uploads/landing/' . $fname;
                } else {
                    $error = 'Could not save that picture.';
                }
            }
        }
        if ($error === '') {
            db_exec('UPDATE landing_cards SET title=?, body=?, image_path=? WHERE id=?', 'sssi', [$title, $body, $imagePath, $id]);
            flash('Landing card saved.');
            redirect('admin_landing.php');
        }
    }
}

$cards = landing_cards();
layout_admin_start('Landing', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('image') ?>Landing page</h1>
    <p class="lede">Change the pictures and the words on the public site. The favicon stays the V mark. The header uses the Vellisys logo on its own.</p>
  </div>
  <a class="btn ghost" href="<?= h(url()) ?>" target="_blank" rel="noopener">View site</a>
</div>

<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

<div class="landing-admin">
  <?php foreach ($cards as $c): ?>
    <form class="card" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
      <img class="landing-admin-pic" src="<?= h(landing_card_image_url($c)) ?>" alt="">
      <p class="hint" style="margin:8px 0 0"><?= h($c['section']) ?> · <?= h($c['slot']) ?></p>
      <label for="title-<?= (int) $c['id'] ?>">Title</label>
      <input id="title-<?= (int) $c['id'] ?>" name="title" required value="<?= h($c['title']) ?>">
      <label for="body-<?= (int) $c['id'] ?>">Copy</label>
      <textarea id="body-<?= (int) $c['id'] ?>" name="body" rows="4" required><?= h($c['body']) ?></textarea>
      <label for="image-<?= (int) $c['id'] ?>">Replace picture</label>
      <input id="image-<?= (int) $c['id'] ?>" name="image" type="file" accept="image/png,image/jpeg,image/gif,image/webp">
      <div class="actions" style="margin-top:12px">
        <button class="btn" type="submit"><?= icon('check') ?>Save card</button>
      </div>
    </form>
  <?php endforeach; ?>
</div>
<?php layout_end(); ?>
