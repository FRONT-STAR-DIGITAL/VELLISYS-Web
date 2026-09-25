<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();
$companyId = current_company_id();
if ($companyId < 1) {
    flash('Open a company desk first.', 'err');
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $result = desk_feedback_save($_POST, $companyId, (int) $user['id']);
    if (!$result['ok']) {
        flash($result['error'] ?? 'Could not send that note.', 'err');
        redirect('feedback.php');
    }
    $row = desk_feedback_one((int) $result['id']);
    if ($row) {
        notify_admin_desk_feedback($row);
    }
    flash('Sent. Vellisys will reply here and by email.');
    redirect('feedback.php');
}

$threads = desk_feedback_for_company($companyId, 50);

layout_start('Feedback', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('help') ?>Feedback</h1>
    <p class="lede">Need assistance on the desk? Send a short note. Super admin at Vellisys receives it and answers back here.</p>
  </div>
</div>

<div class="desk-grid stock-split">
  <div class="card">
    <div class="card-head"><h2><?= icon('send', 16) ?>Quick help</h2></div>
    <form class="pad-form" method="post">
      <?= csrf_field() ?>
      <label for="fb_message">What do you need?
        <textarea id="fb_message" name="message" required minlength="10" maxlength="4000" rows="6" placeholder="Example: We cannot add a second branch user, or the receipt PDF looks wrong on mobile."></textarea>
      </label>
      <p class="hint">At least a short sentence. Include what you were doing and what went wrong.</p>
      <button class="btn" type="submit"><?= icon('send', 16) ?>Send to Vellisys</button>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><h2><?= icon('letter', 16) ?>Your notes</h2></div>
    <?php if (!$threads): ?>
      <p class="empty">No feedback yet. Send a note when you need help.</p>
    <?php else: ?>
      <div class="work-list">
        <?php foreach ($threads as $t):
            $status = (string) ($t['status'] ?? 'new');
            $pill = match ($status) {
                'new', 'read' => ' warn',
                'replied' => '',
                default => '',
            };
            $label = match ($status) {
                'new' => 'Sent',
                'read' => 'Seen',
                'replied' => 'Replied',
                default => $status,
            };
            ?>
          <article class="work-row" style="display:block;cursor:default">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
              <div>
                <strong><?= h(clip_text((string) $t['message'], 90)) ?></strong>
                <span><?= h(substr((string) $t['created_at'], 0, 16)) ?> · <?= h((string) ($t['user_name'] ?? 'You')) ?></span>
              </div>
              <span class="pill<?= $pill ?>"><?= h($label) ?></span>
            </div>
            <p style="margin:10px 0 0;white-space:pre-wrap"><?= h((string) $t['message']) ?></p>
            <?php if ($status === 'replied' && trim((string) ($t['reply_body'] ?? '')) !== ''): ?>
              <div style="margin-top:12px;padding:12px;border:1px solid var(--line, #d8dde8);background:var(--panel-soft, #f7f8fc);border-radius:10px">
                <strong style="display:block;margin-bottom:6px">Vellisys reply</strong>
                <p style="margin:0;white-space:pre-wrap"><?= h((string) $t['reply_body']) ?></p>
                <?php if (!empty($t['replied_at'])): ?>
                  <p class="hint" style="margin:8px 0 0"><?= h(substr((string) $t['replied_at'], 0, 16)) ?></p>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php layout_end(); ?>
