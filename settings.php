<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_desk_admin();

$brand = branding();
$error = '';
$cid = current_company_id();
$deskCompany = db_one('SELECT * FROM companies WHERE id = ?', 'i', [$cid]);
$members = db_all('SELECT id, name, job_title, email, role, access, features, status, branch_id, created_at FROM users WHERE company_id = ? ORDER BY role = \'admin\' DESC, id', 'i', [$cid]);
$seats = company_user_limit($deskCompany ?: null);
$used = company_seat_count($cid);
$editUserId = (int) ($_GET['edit'] ?? 0);
$editMember = $editUserId ? load_desk_user($cid, $editUserId) : null;

if (isset($_GET['backup'])) {
    company_backup_send((string) $_GET['backup']);
}

if (isset($_GET['import_template'])) {
    import_send_template((string) $_GET['import_template']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    if ($action === 'add_user') {
        $made = create_desk_user($cid, [
            'name' => post('user_name'),
            'email' => post('user_email'),
            'password' => post('user_password'),
            'job_title' => post('user_title'),
            'access' => post('user_access'),
            'features' => posted_user_features(post('user_access') === 'sales' ? 'sales' : 'books'),
            'branch_id' => post('user_branch'),
        ]);
        if (empty($made['ok'])) {
            $error = (string) ($made['error'] ?? 'Could not add that user.');
        } else {
            flash('Login created for ' . $made['email'] . '. Temporary password: ' . $made['password']);
            redirect('settings.php#people');
        }
    } elseif ($action === 'edit_user') {
        $uid = (int) post('user_id');
        $member = load_desk_user($cid, $uid);
        if (!$member) {
            $error = 'That user is not on this desk.';
        } else {
            $access = post('user_access') === 'sales' ? 'sales' : 'books';
            $saved = update_desk_user($cid, $uid, [
                'name' => post('user_name'),
                'email' => post('user_email'),
                'password' => post('user_password'),
                'job_title' => post('user_title'),
                'access' => $access,
                'features' => posted_user_features($access),
                'branch_id' => post('user_branch'),
            ]);
            if (empty($saved['ok'])) {
                $error = (string) ($saved['error'] ?? 'Could not save that user.');
            } else {
                flash('User updated: ' . $saved['email'] . '.');
                redirect('settings.php#people');
            }
        }
    } elseif ($action === 'suspend_user' || $action === 'restore_user') {
        $uid = (int) post('user_id');
        $saved = set_desk_user_suspended($cid, $uid, $action === 'suspend_user');
        if (empty($saved['ok'])) {
            $error = (string) ($saved['error'] ?? 'Could not change that login.');
        } else {
            flash($saved['status'] === 'suspended' ? ($saved['email'] . ' is suspended and cannot sign in.') : ($saved['email'] . ' can sign in again.'));
            redirect('settings.php#people');
        }
    } elseif ($action === 'delete_user') {
        $uid = (int) post('user_id');
        $saved = delete_desk_user($cid, $uid, (int) $user['id']);
        if (empty($saved['ok'])) {
            $error = (string) ($saved['error'] ?? 'Could not delete that user.');
        } else {
            flash('Deleted login ' . $saved['email'] . '.');
            redirect('settings.php#people');
        }
    } elseif ($action === 'save_user_access') {
        $uid = (int) post('user_id');
        $member = db_one('SELECT * FROM users WHERE id = ? AND company_id = ?', 'ii', [$uid, $cid]);
        $access = post('user_access') === 'sales' ? 'sales' : 'books';
        if (!$member) {
            $error = 'That user is not on this desk.';
        } elseif (($member['role'] ?? '') === 'admin') {
            $error = 'The company admin already has every desk page.';
        } else {
            $feat = posted_user_features($access);
            db_exec('UPDATE users SET access=?, features=? WHERE id=? AND company_id=?', 'ssii', [$access, json_encode($feat, JSON_UNESCAPED_UNICODE), $uid, $cid]);
            flash('Access updated for ' . $member['email'] . '.');
            redirect('settings.php#people');
        }
    } elseif ($action === 'reset_password') {
        $uid = (int) post('user_id');
        $member = db_one('SELECT * FROM users WHERE id = ? AND company_id = ?', 'ii', [$uid, $cid]);
        $newPass = post('new_password');
        if (!$member) {
            $error = 'That user is not on this desk.';
        } elseif (strlen($newPass) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            db_exec('UPDATE users SET password_hash = ? WHERE id = ?', 'si', [password_hash($newPass, PASSWORD_DEFAULT), $uid]);
            flash('Password updated for ' . $member['email'] . '.');
            redirect('settings.php#people');
        }
    } elseif ($action === 'assign_branch') {
        $uid = (int) post('user_id');
        if (company_branches_enabled() && assign_user_branch($uid, post('user_branch'))) {
            flash('Branch updated.');
            redirect('settings.php#people');
        } else {
            $error = 'Could not assign that branch.';
        }
    } elseif ($action === 'save_signature') {
        $wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || post('ajax') === '1';
        try {
            save_company_signature_png(post('signature_data', '', 900000));
            if ($wantsJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'url' => company_signature_url()], JSON_UNESCAPED_SLASHES);
                exit;
            }
            flash('Signature approved.');
            redirect('settings.php#appearance');
        } catch (Throwable $e) {
            if ($wantsJson) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
                exit;
            }
            $error = $e->getMessage();
        }
    } elseif ($action === 'upload_signature') {
        try {
            save_company_signature_upload($_FILES['signature_file'] ?? []);
            flash('Signature image saved.');
            redirect('settings.php#appearance');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'clear_signature') {
        $wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || post('ajax') === '1';
        clear_company_signature();
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true]);
            exit;
        }
        flash('Signature cleared. Draw it again to approve.');
        redirect('settings.php#appearance');
    } elseif ($action === '' || $action === 'save_brand') {
    $color = parse_hex_color(post('brand_color'), '#82B440');
    $accent = parse_hex_color(post('brand_accent'), '#C6A15B');
    $deep = hex_shade($color, 0.52);
    $logoPath = (string) ($brand['logo_path'] ?? '');
    $taken = branding_take_logo_upload($cid);
    if (empty($taken['ok'])) {
        $error = (string) ($taken['error'] ?? 'Could not save the logo file.');
    } elseif (!empty($taken['path'])) {
        $logoPath = $taken['path'];
    }
    if ($error === '') {
        db_exec(
            'UPDATE branding SET name=?, tagline=?, tin=?, vat_no=?, address=?, city=?, phone=?, email=?, website=?, bank_name=?, account_name=?, account_number=?, brand_color=?, brand_accent=?, brand_deep=?, logo_path=?, prefix=?, payment_note=?, invoice_comments=?, currency=?, fx_ugx_per_usd=?, letter_templates=?, doc_template=?, number_format=?, logo_bg=?, tax_name=?, tax_rate=? WHERE company_id=?',
            'ssssssssssssssssssssdsssisdi',
            [
                post('name'),
                post('tagline'),
                post('tin'),
                post('vat_no'),
                post('address'),
                post('city'),
                post('phone'),
                post('email'),
                post('website'),
                post('bank_name'),
                post('account_name'),
                post('account_number'),
                $color,
                $accent,
                $deep,
                $logoPath,
                strtoupper(post('prefix') ?: 'OFG'),
                post('payment_note'),
                post('invoice_comments'),
                posted_currency('currency', default_currency()),
                parse_fx_rate(post('fx_ugx_per_usd')),
                encode_letter_templates(isset($_POST['tpl']) && is_array($_POST['tpl']) ? $_POST['tpl'] : []),
                array_key_exists(post('doc_template'), doc_templates()) ? post('doc_template') : 'folio',
                sanitize_number_format(post('number_format')),
                (isset($_POST['logo_bg']) && (is_array($_POST['logo_bg']) ? in_array('1', $_POST['logo_bg'], true) : (string) $_POST['logo_bg'] === '1')) ? 1 : 0,
                sanitize_tax_name(post('tax_name')),
                parse_tax_rate_percent(post('tax_rate_percent'), company_tax_percent()),
                current_company_id(),
            ]
        );
        $tpl = array_key_exists(post('doc_template'), doc_templates()) ? post('doc_template') : 'folio';
        db_exec('UPDATE documents SET doc_template = ? WHERE company_id = ?', 'si', [$tpl, current_company_id()]);
        branding(true);
        unset($_SESSION['branding_welcome']);
        mark_branding_saved($cid, $user);
        flash('Settings saved. This design now prints on every document. USD converts at your ' . default_currency() . ' rate.');
        if (function_exists('record_company_activity')) {
            record_company_activity('settings', 'Letterhead and colours saved', [
                'detail' => 'Document layout ' . $tpl,
                'href' => 'settings.php',
            ]);
        }
        redirect('settings.php');
    }
    }
    if ($action === 'dismiss_welcome') {
        unset($_SESSION['branding_welcome']);
        redirect('settings.php');
    } elseif ($action === 'backup_now') {
        $path = company_backup_write(true);
        flash($path ? 'Backup saved. You can download it below.' : 'Could not write a backup.', $path ? 'ok' : 'err');
        redirect('settings.php#backup');
    } elseif ($action === 'restore_backup') {
        $file = $_FILES['backup'] ?? [];
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $error = 'Choose a backup file to restore.';
        } else {
            $payload = company_backup_read_file($file['tmp_name']);
            if (!$payload) {
                $error = 'That file is not a Vellisys backup.';
            } else {
                $res = company_backup_restore_payload($payload, $cid, ['branding' => true]);
                if (empty($res['ok'])) {
                    $error = (string) ($res['error'] ?? 'Could not restore.');
                } else {
                    flash('Desk restored from backup.');
                    redirect('settings.php#backup');
                }
            }
        }
    } elseif ($action === 'import_history') {
        $kind = post('import_kind');
        $file = $_FILES['import_file'] ?? [];
        if (!isset(import_kinds()[$kind])) {
            $error = 'Choose which template you are uploading: clients, documents, receipts, sales'
                . (company_stock_enabled() ? ' or stock.' : '.');
        } elseif (empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            $error = 'Choose the filled Excel or CSV file to upload.';
        } elseif ((int) ($file['size'] ?? 0) > 4 * 1024 * 1024) {
            $error = 'That file is larger than 4 MB. Split the sheet and upload again.';
        } else {
            $res = import_run($kind, (string) $file['tmp_name'], (string) ($file['name'] ?? 'upload.xlsx'));
            if (empty($res['ok'])) {
                $error = (string) ($res['error'] ?? 'Could not import that file.');
            } else {
                flash(import_flash_message($res));
                redirect('settings.php#import');
            }
        }
        if ($error !== '') {
            flash($error, 'err');
            redirect('settings.php#import');
        }
    }
}

$b = branding();
layout_start('Settings', $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('settings') ?>Settings</h1>
    <p class="lede">Letterhead, colours, and your currency. Quotations, invoices, receipts, headed letters, debtor reminders, notes to creditors and custom mail leave from the company mailbox Vellisys assigned - you cannot change it here.</p>
  </div>
</div>

<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

<div class="settings-layout">
  <aside class="settings-toc">
    <a href="#account"><?= icon('lock', 16) ?>Account</a>
    <a href="#notifications"><?= icon('bell', 16) ?>Notifications</a>
    <a href="#people"><?= icon('user', 16) ?>People</a>
    <a href="#appearance"><?= icon('palette', 16) ?>Appearance</a>
    <a href="#company"><?= icon('building', 16) ?>Company</a>
    <a href="#tax"><?= icon('hash', 16) ?>Tax</a>
    <a href="#bank"><?= icon('bank', 16) ?>Bank</a>
    <a href="#documents"><?= icon('invoice', 16) ?>Documents</a>
    <a href="#templates"><?= icon('palette', 16) ?>Templates</a>
    <a href="#import"><?= icon('upload', 16) ?>Bring in books</a>
    <a href="#backup"><?= icon('download', 16) ?>Backup</a>
  </aside>

  <div class="settings-stack">
    <section class="card settings-card" id="account">
      <h2><?= icon('lock') ?>Signed-in account</h2>
      <p class="lede">This is who is using the desk. Change your own password below. Outgoing mail leaves from the company mailbox Vellisys assigned. Only a Vellisys admin can change that mailbox.</p>
      <div class="account-chip">
        <?= icon('user', 22) ?>
        <div>
          <strong><?= h($user['name']) ?></strong>
          <span><?= h($user['email']) ?><?= !empty($user['job_title']) ? ' · ' . h((string) $user['job_title']) : '' ?></span>
        </div>
      </div>
      <div class="settings-account-actions">
        <a class="btn sm" href="<?= h(url('account.php')) ?>"><?= icon('lock', 14) ?>Change my password</a>
      </div>
      <?php
        $deskCompany = db_one('SELECT * FROM companies WHERE id = ?', 'i', [current_company_id()]);
        $sendAcct = $deskCompany ? company_mail_account($deskCompany) : null;
      ?>
      <div class="account-chip" style="margin-top:12px">
        <?= icon('send', 22) ?>
        <div>
          <strong>Sending mailbox</strong>
          <?php if ($sendAcct): ?>
            <span><?= h($sendAcct['from_name']) ?> · <?= h($sendAcct['from_email']) ?> (locked)</span>
          <?php else: ?>
            <span>Not assigned yet. Ask Vellisys to add the company Hostinger address. You can still print and share a link.</span>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <?php render_push_settings_card(); ?>

    <section class="card settings-card" id="people">
      <h2><?= icon('user') ?>People</h2>
      <p class="lede">This desk has <?= (int) $used ?> of <?= (int) $seats ?> login<?= $seats === 1 ? '' : 's' ?>. Vellisys sets the number. <?= h(company_plan_label()) ?> allows up to <?= (int) plan_user_limit_max($deskCompany ?: null) ?> users. Only the company admin sees profit, net profit, reports, settings, branches and activities. Assign Desk or Sales, then tick the pages that user may open. You can edit, suspend or delete a login.</p>
      <?php if ($editMember): ?>
        <?php
          $eAccess = ((string) ($editMember['access'] ?? 'books')) === 'sales' ? 'sales' : 'books';
          $eFeat = parse_user_features($editMember['features'] ?? '', $eAccess);
          $eIsAdmin = ($editMember['role'] ?? '') === 'admin';
        ?>
        <form method="post" class="people-add" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="edit_user">
          <input type="hidden" name="user_id" value="<?= (int) $editMember['id'] ?>">
          <h3>Edit <?= h($editMember['name']) ?></h3>
          <div class="form-grid">
            <div>
              <label for="edit_user_name">Name</label>
              <input id="edit_user_name" name="user_name" required value="<?= h(post('user_name') !== '' ? post('user_name') : (string) $editMember['name']) ?>">
            </div>
            <div>
              <label for="edit_user_title">Title</label>
              <input id="edit_user_title" name="user_title" value="<?= h(post('user_title') !== '' ? post('user_title') : (string) ($editMember['job_title'] ?? '')) ?>">
            </div>
            <div>
              <label for="edit_user_email">Email</label>
              <input id="edit_user_email" name="user_email" type="email" required value="<?= h(post('user_email') !== '' ? post('user_email') : (string) $editMember['email']) ?>">
            </div>
            <?php if (!$eIsAdmin): ?>
            <div>
              <label for="edit_user_access">Access</label>
              <select id="edit_user_access" name="user_access" data-access-select>
                <?php foreach (desk_staff_access_levels() as $key => $info): ?>
                  <option value="<?= h($key) ?>" <?= $eAccess === $key ? 'selected' : '' ?>><?= h($info['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <div>
              <label for="edit_user_password">New password (optional)</label>
              <input id="edit_user_password" name="user_password" type="password" minlength="8" autocomplete="new-password">
            </div>
            <?php if (company_branches_enabled()): ?>
            <div>
              <label for="edit_user_branch">Branch</label>
              <select id="edit_user_branch" name="user_branch">
                <?php render_branch_options((int) ($editMember['branch_id'] ?? 0)); ?>
              </select>
            </div>
            <?php endif; ?>
          </div>
          <?php if ($eIsAdmin): ?>
            <p class="hint">The company admin keeps every desk page. Leave the password blank to keep the current one.</p>
          <?php else: ?>
            <p class="hint">Leave the password blank to keep the current one. Tick editing, deleting and backdating documents if this person should change issued sheets.</p>
            <?php render_desk_feature_checks($eFeat); ?>
          <?php endif; ?>
          <div class="actions" style="margin-top:12px">
            <button class="btn sm" type="submit"><?= icon('check', 14) ?>Save user</button>
            <a class="btn ghost sm" href="<?= h(url('settings.php#people')) ?>">Cancel</a>
          </div>
        </form>
      <?php endif; ?>
      <?php if (!$members): ?>
        <p class="empty">No logins yet.</p>
      <?php else: ?>
        <div class="table-scroll">
        <table class="grid">
          <thead>
            <tr>
              <th>Name</th>
              <th>Title</th>
              <th>Email</th>
              <th>Access</th>
              <th>Status</th>
              <?php if (company_branches_enabled()): ?><th>Branch</th><?php endif; ?>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($members as $m): ?>
              <tr>
                <td><?= h($m['name']) ?></td>
                <td><?= h((string) ($m['job_title'] ?? '')) ?></td>
                <td class="mono"><?= h($m['email']) ?></td>
                <td><?= h(desk_access_label((string) $m['role'], (string) ($m['access'] ?? 'books'))) ?></td>
                <td><?= (($m['status'] ?? 'live') === 'suspended') ? 'Suspended' : 'Live' ?></td>
                <?php if (company_branches_enabled()): ?>
                  <td>
                    <form method="post" class="people-reset">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="assign_branch">
                      <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                      <select name="user_branch" aria-label="Branch for <?= h($m['name']) ?>">
                        <?php render_branch_options((int) ($m['branch_id'] ?? 0)); ?>
                      </select>
                      <button class="btn ghost sm" type="submit">Save</button>
                    </form>
                  </td>
                <?php endif; ?>
                <td class="row-actions">
                  <div class="actions people-user-actions">
                    <a class="btn ghost sm" href="<?= h(url('settings.php?edit=' . (int) $m['id'] . '#people')) ?>">Edit</a>
                    <?php if (($m['status'] ?? 'live') === 'suspended'): ?>
                      <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="restore_user">
                        <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                        <button class="btn ghost sm" type="submit">Restore</button>
                      </form>
                    <?php else: ?>
                      <form method="post" onsubmit="return confirm('Suspend this login? They will not be able to sign in.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="suspend_user">
                        <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                        <button class="btn ghost sm" type="submit">Suspend</button>
                      </form>
                    <?php endif; ?>
                    <form method="post" onsubmit="return confirm('Delete this login? This cannot be undone.');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_user">
                      <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                      <button class="btn danger sm" type="submit">Delete</button>
                    </form>
                  </div>
                  <form method="post" class="people-reset">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                    <input name="new_password" type="password" minlength="8" required placeholder="New password" autocomplete="new-password" aria-label="New password for <?= h($m['name']) ?>">
                    <button class="btn ghost sm" type="submit">Reset</button>
                  </form>
                </td>
              </tr>
              <?php if (($m['role'] ?? '') !== 'admin'): ?>
              <?php
                $mAccess = ((string) ($m['access'] ?? 'books')) === 'sales' ? 'sales' : 'books';
                $mFeat = parse_user_features($m['features'] ?? '', $mAccess);
              ?>
              <tr class="people-access-row">
                <td colspan="<?= company_branches_enabled() ? 7 : 6 ?>">
                  <form method="post" class="people-access">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_user_access">
                    <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                    <div class="people-access-head">
                      <label>
                        <span>Access</span>
                        <select name="user_access" data-access-select>
                          <?php foreach (desk_staff_access_levels() as $key => $info): ?>
                            <option value="<?= h($key) ?>" <?= $mAccess === $key ? 'selected' : '' ?>><?= h($info['label']) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </label>
                      <button class="btn sm" type="submit">Save access</button>
                    </div>
                    <?php render_desk_feature_checks($mFeat); ?>
                  </form>
                </td>
              </tr>
              <?php endif; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      <?php endif; ?>
      <?php if (!$editMember && $used < $seats): ?>
        <form method="post" class="people-add" autocomplete="off" data-access-features="new">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_user">
          <h3>Add a user</h3>
          <div class="form-grid">
            <div>
              <label for="user_name">Name</label>
              <input id="user_name" name="user_name" required value="<?= h(post('user_name')) ?>">
            </div>
            <div>
              <label for="user_title">Title</label>
              <input id="user_title" name="user_title" value="<?= h(post('user_title')) ?>" placeholder="Accountant">
            </div>
            <div>
              <label for="user_email">Email</label>
              <input id="user_email" name="user_email" type="email" required value="<?= h(post('user_email')) ?>">
            </div>
            <div>
              <label for="user_access">Access</label>
              <select id="user_access" name="user_access" data-access-select>
                <?php foreach (desk_staff_access_levels() as $key => $info): ?>
                  <option value="<?= h($key) ?>" <?= post('user_access') === $key ? 'selected' : '' ?>><?= h($info['label']) ?></option>
                <?php endforeach; ?>
              </select>
              <p class="hint"><?= h(desk_staff_access_levels()['books']['hint']) ?> <?= h(desk_staff_access_levels()['sales']['hint']) ?></p>
            </div>
            <div>
              <label for="user_password">Temporary password</label>
              <input id="user_password" name="user_password" value="<?= h(post('user_password') !== '' ? post('user_password') : default_desk_password()) ?>" minlength="8">
            </div>
            <?php if (company_branches_enabled()): ?>
            <div>
              <label for="user_branch">Branch</label>
              <select id="user_branch" name="user_branch">
                <?php render_branch_options((int) post('user_branch')); ?>
              </select>
            </div>
            <?php endif; ?>
          </div>
          <p class="hint">Tick the pages this login may open. Profit, reports, settings, branches and activities stay with the admin. Tick editing, deleting and backdating documents if this person should change issued sheets.</p>
          <?php render_desk_feature_checks(posted_user_features(post('user_access') === 'sales' ? 'sales' : 'books')); ?>
          <div class="actions" style="margin-top:12px">
            <button class="btn sm" type="submit"><?= icon('plus', 14) ?>Create login</button>
          </div>
        </form>
      <?php endif; ?>
      <?php if (!$editMember && $used >= $seats): ?>
        <p class="hint">All <?= (int) $seats ?> seats are in use. Delete a login to free a seat, or ask Vellisys to raise the limit.</p>
      <?php endif; ?>
    </section>

    <form method="post" enctype="multipart/form-data" data-brand-form>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_brand">
    <section class="card settings-card" id="appearance">
      <h2><?= icon('palette') ?>Appearance</h2>
      <p class="lede">Logo and two brand colours. Primary paints the desk and the strong bars on documents. Accent marks rails, rules and highlights. Type on those colours is black or white, whichever reads clearly.</p>
      <div class="form-grid">
        <div>
          <label for="brand_color">Primary</label>
          <div class="color-row" data-color-pair data-color-role="primary">
            <input id="brand_color" name="brand_color" type="color" value="<?= h(parse_hex_color($b['brand_color'] ?? '', '#82B440')) ?>" data-color-picker>
            <input id="brand_color_hex" name="brand_color_hex" type="text" maxlength="7" value="<?= h(parse_hex_color($b['brand_color'] ?? '', '#82B440')) ?>" data-color-hex aria-label="Primary hex">
          </div>
        </div>
        <div>
          <label for="brand_accent">Accent</label>
          <div class="color-row" data-color-pair data-color-role="accent">
            <input id="brand_accent" name="brand_accent" type="color" value="<?= h(parse_hex_color($b['brand_accent'] ?? '', '#C6A15B')) ?>" data-color-picker>
            <input id="brand_accent_hex" type="text" maxlength="7" value="<?= h(parse_hex_color($b['brand_accent'] ?? '', '#C6A15B')) ?>" data-color-hex aria-label="Accent hex">
          </div>
        </div>
        <div>
          <label for="logo">Logo</label>
          <input id="logo" name="logo" type="file" accept="image/*,.svg">
          <?php if (!empty($b['logo_path'])): ?>
            <div class="logo-preview"><img src="<?= h(logo_url()) ?>" alt=""></div>
          <?php endif; ?>
        </div>
      </div>
      <div class="sig-block" data-signature-pad data-sig-url="<?= h(url('settings.php')) ?>">
        <h3>Signature</h3>
        <p class="hint">Write with a finger or mouse, or upload a small signature image (PNG, JPG, GIF or WebP, under 400 KB). Cancel clears the pad only. Retake lets you draw again without dropping the stored mark until you approve the new one. Remove deletes the saved signature.</p>
        <?php $sigUrl = company_signature_url($b); ?>
        <div class="sig-preview" data-sig-preview <?= $sigUrl === '' ? 'hidden' : '' ?>>
          <?php if ($sigUrl !== ''): ?>
            <img src="<?= h($sigUrl) ?>" alt="Approved signature">
          <?php endif; ?>
          <span>Approved</span>
        </div>
        <canvas class="sig-canvas" width="560" height="180" data-sig-canvas <?= $sigUrl !== '' ? 'hidden' : '' ?>></canvas>
        <div class="sig-actions">
          <button class="btn ghost sm" type="button" data-sig-cancel>Cancel</button>
          <button class="btn ghost sm" type="button" data-sig-retake>Retake</button>
          <button class="btn ghost sm" type="button" data-sig-remove>Remove</button>
          <button class="btn sm" type="button" data-sig-approve>Approve signature</button>
        </div>
        <p class="hint" data-sig-status></p>
      </div>
      <div class="sig-upload">
        <label for="signature_file">Upload a signature image</label>
        <input id="signature_file" name="signature_file" type="file" accept="image/png,image/jpeg,image/gif,image/webp">
        <p class="hint">A small scan or photo of the sign-off. PNG, JPG, GIF or WebP, under 400 KB.</p>
        <button class="btn ghost sm" type="submit" name="action" value="upload_signature"><?= icon('check', 14) ?>Save image</button>
      </div>
      <div class="palette-swatches" aria-hidden="true">
        <span style="background:var(--brand)"></span>
        <span style="background:var(--brand-2)"></span>
      </div>
      <div class="preview-nav" data-color-preview>
        <span>Navigation preview</span>
        <strong><?= h($b['name']) ?></strong>
      </div>
    </section>

    <section class="card settings-card" id="company">
      <h2><?= icon('building') ?>Company</h2>
      <p class="lede">Printed on every Head office sheet under the logo. Named branches (Business and Pro) keep their own address.</p>
      <div class="form-grid">
        <div>
          <label for="name">Company name</label>
          <input id="name" name="name" required value="<?= h($b['name']) ?>">
        </div>
        <div>
          <label for="tagline">Tagline</label>
          <input id="tagline" name="tagline" value="<?= h($b['tagline']) ?>">
        </div>
        <div>
          <label for="phone">Phone</label>
          <input id="phone" name="phone" value="<?= h($b['phone']) ?>">
        </div>
        <div>
          <label for="email">Email</label>
          <input id="email" name="email" type="email" value="<?= h($b['email']) ?>">
        </div>
        <div>
          <label for="website">Website</label>
          <input id="website" name="website" value="<?= h($b['website']) ?>">
        </div>
        <div>
          <label for="city">City</label>
          <input id="city" name="city" value="<?= h($b['city'] ?? '') ?>">
        </div>
        <div style="grid-column:1 / -1">
          <label for="address">Address</label>
          <input id="address" name="address" value="<?= h($b['address']) ?>">
        </div>
      </div>
    </section>

    <section class="card settings-card" id="tax">
      <h2><?= icon('hash') ?>Tax</h2>
      <p class="lede">TIN and the tax number print on stationery. Name the tax your country uses and the percent charged on taxed lines. Sheets already issued keep the rate they were saved with.</p>
      <div class="form-grid">
        <div>
          <label for="tin">TIN</label>
          <input id="tin" name="tin" value="<?= h($b['tin']) ?>">
        </div>
        <div>
          <label for="vat_no">Tax / VAT number</label>
          <input id="vat_no" name="vat_no" value="<?= h($b['vat_no'] ?? '') ?>">
        </div>
        <div>
          <label for="tax_name">Tax name</label>
          <input id="tax_name" name="tax_name" maxlength="40" value="<?= h(company_tax_name($b)) ?>" placeholder="VAT">
          <p class="hint">VAT, GST, SST, IVA, or whatever your books call it.</p>
        </div>
        <div>
          <label for="tax_rate_percent">Tax rate (%)</label>
          <input id="tax_rate_percent" name="tax_rate_percent" inputmode="decimal" value="<?= h(rtrim(rtrim(number_format(company_tax_percent($b), 4, '.', ''), '0'), '.')) ?>">
          <p class="hint">Enter a percent, for example 18 for 18%. New documents use this on lines marked Y.</p>
        </div>
        <div>
          <label for="currency-pick">Currency</label>
          <?php currency_field('currency', 'currency', (string) ($b['currency'] ?? default_currency()), ['data-fx-home-input' => true]); ?>
          <p class="hint">Pick a currency, or type any three-letter primary code (UGX, KES, EUR, MWK…).</p>
        </div>
        <div>
          <label for="fx_ugx_per_usd">1 USD equals</label>
          <div class="fx-row">
            <input id="fx_ugx_per_usd" name="fx_ugx_per_usd" inputmode="decimal" value="<?= h(rtrim(rtrim(number_format(fx_home_per_usd(), 4, '.', ''), '0'), '.')) ?>">
            <span data-fx-home-label><?= h(default_currency()) ?></span>
          </div>
          <p class="hint">Used when a document is in USD and your books are in <?= h(default_currency()) ?>, and on printed equivalents.</p>
        </div>
        <div>
          <label for="prefix">Document prefix</label>
          <input id="prefix" name="prefix" maxlength="12" value="<?= h($b['prefix']) ?>">
        </div>
        <div style="grid-column:1 / -1">
          <label for="number_format">Document number format</label>
          <input id="number_format" name="number_format" maxlength="80" value="<?= h((string) ($b['number_format'] ?? default_number_format())) ?>">
          <p class="hint">Use <code>{prefix}</code>, <code>{kind}</code> (INV, QTN, RCT…), <code>{yyyy}</code> or <code>{yy}</code>, and <code>{seq:4}</code>. Example now: <strong><?= h(format_document_number('invoice', 1, $b)) ?></strong></p>
        </div>
      </div>
    </section>

    <section class="card settings-card" id="bank">
      <h2><?= icon('bank') ?>Bank</h2>
      <p class="lede">Shown as the payment note on invoices unless you write something else below.</p>
      <div class="form-grid">
        <div>
          <label for="bank_name">Bank</label>
          <input id="bank_name" name="bank_name" value="<?= h($b['bank_name'] ?? '') ?>">
        </div>
        <div>
          <label for="account_name">Account name</label>
          <input id="account_name" name="account_name" value="<?= h($b['account_name'] ?? '') ?>">
        </div>
        <div>
          <label for="account_number">Account number</label>
          <input id="account_number" name="account_number" value="<?= h($b['account_number'] ?? '') ?>">
        </div>
      </div>
    </section>

    <section class="card settings-card" id="documents">
      <h2><?= icon('invoice') ?>Document copy</h2>
      <label for="payment_note">Payment note on invoices</label>
      <textarea id="payment_note" name="payment_note" rows="2"><?= h($b['payment_note'] ?? '') ?></textarea>
      <label for="invoice_comments">Default invoice comments</label>
      <textarea id="invoice_comments" name="invoice_comments" rows="4"><?= h($b['invoice_comments'] ?? '') ?></textarea>
    </section>

    <section class="card settings-card" id="templates">
      <h2><?= icon('palette') ?>Document designs</h2>
      <p class="lede">Fifteen layouts on white paper with black type and your brand colours. The preview is the printed sheet. This design prints on invoices, quotations, receipts and letters. On small screens A4 pages are scaled to fit. Page frame and Inset border put a rule around the paper. Thermal roll is an 80mm receipt for a kitchen or shop printer. Logo watermark and Bond watermark print the company mark faintly. Changing the design here reprints the whole books.</p>
      <?php $letterTpls = letter_templates(true); ?>
      <label class="check">
        <input type="hidden" name="logo_bg" value="0">
        <input type="checkbox" name="logo_bg" value="1" <?= !empty($b['logo_bg']) ? 'checked' : '' ?>>
        Put the company logo in the background of documents
      </label>
      <p class="hint">A faint watermark of your logo on invoices, receipts, quotations, letters and the rest - not only the Logo watermark and Bond watermark layouts.</p>
      <div class="design-grid">
        <?php
        $currentDesign = doc_template_key(['doc_template' => $b['doc_template'] ?? 'folio']);
        foreach (doc_templates() as $key => $info):
            ?>
          <label class="design-card<?= $currentDesign === $key ? ' is-selected' : '' ?>">
            <div class="design-previews" aria-hidden="true">
              <div class="design-preview">
                <div class="design-mini mini-<?= h($key) ?>"></div>
              </div>
            </div>
            <span class="design-pick">
              <input type="radio" name="doc_template" value="<?= h($key) ?>" <?= $currentDesign === $key ? 'checked' : '' ?>>
              <strong><?= h($info['name']) ?></strong>
            </span>
            <span class="design-blurb"><?= h($info['blurb']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <h2 style="margin-top:28px"><?= icon('letter') ?>Letter starting texts</h2>
      <p class="lede">The letter itself stays fully editable when you write it. These are starting texts only. Put <code>{company}</code> where the company name should appear. Open one tab at a time - Save still stores every letter and email template. Download a blank Word letterhead from Letters when you need to finish a note in Microsoft Word.</p>
      <div class="tpl-tabs" data-tpl-tabs>
        <div class="tpl-tab-bar" data-tpl-tab-bar>
          <?php foreach ($letterTpls as $key => $tpl): ?>
            <button type="button" class="tpl-tab" data-tpl-tab="<?= h($key) ?>"><?= h($tpl['title']) ?></button>
          <?php endforeach; ?>
        </div>
        <div data-tpl-list>
          <?php foreach ($letterTpls as $key => $tpl): ?>
            <div class="tpl-edit" data-tpl-card data-tpl-panel="<?= h($key) ?>">
              <div class="form-grid">
                <div>
                  <label>Title on the desk</label>
                  <input name="tpl[<?= h($key) ?>][title]" value="<?= h($tpl['title']) ?>" required>
                </div>
                <div>
                  <label>Heading on the page</label>
                  <input name="tpl[<?= h($key) ?>][heading]" value="<?= h($tpl['heading']) ?>">
                </div>
                <div style="grid-column:1 / -1">
                  <label>Subject</label>
                  <input name="tpl[<?= h($key) ?>][subject]" value="<?= h($tpl['subject']) ?>">
                </div>
              </div>
              <label>Body</label>
              <textarea name="tpl[<?= h($key) ?>][body]" rows="7"><?= h($tpl['body']) ?></textarea>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <p class="hint" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px">
        <button class="btn ghost sm" type="button" data-add-template><?= icon('plus', 14) ?>Add template</button>
        Clear a custom title and save to remove it.
      </p>
      <div class="actions sticky-save">
        <button class="btn" type="submit"><?= icon('check') ?>Save settings</button>
      </div>
    </section>
    </form>
    <section class="card settings-card" id="import">
      <h2><?= icon('upload') ?>Bring in books</h2>
      <p class="lede">After onboarding, drop in clients, old invoices, receipts and sales from a spreadsheet. Vellisys <strong>adds</strong> them to this desk. It does not replace what you have already issued, and it is not a backup restore.</p>
      <ol class="import-steps">
        <li>Download the matching Excel template. Keep the header row. Sample rows show the shape - delete them before you upload, or leave them if they are real.</li>
        <li>Copy from your old books, a notebook, or another system. Dates as <span class="mono">YYYY-MM-DD</span> (for example 2025-06-15). On documents and sales, the same <strong>Group</strong> number means one sheet with several lines.</li>
        <li>Upload one file at a time. Start with <strong>Clients</strong>, then <strong>Documents</strong> or <strong>Sales</strong>, then <strong>Receipts</strong> so payments can sit against invoices you just brought in.</li>
        <li>Open Clients, Invoices and Receipts to check. If a document number is already on this desk, that row is skipped. Blank numbers get the next Vellisys number.</li>
      </ol>
      <div class="import-packs">
        <?php foreach (import_kinds() as $ikey => $ipack): ?>
          <article class="import-pack">
            <h3><?= icon($ipack['icon'], 16) ?><?= h($ipack['title']) ?></h3>
            <p><?= h($ipack['lead']) ?></p>
            <a class="btn ghost sm" href="<?= h(url('settings.php?import_template=' . urlencode($ikey))) ?>"><?= icon('download', 14) ?>Download <?= class_exists('ZipArchive') ? 'Excel' : 'CSV' ?></a>
          </article>
        <?php endforeach; ?>
      </div>
      <form method="post" enctype="multipart/form-data" class="import-upload">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="import_history">
        <div class="form-grid">
          <div>
            <label for="import_kind">This file is</label>
            <select id="import_kind" name="import_kind" required>
              <?php foreach (import_kinds() as $ikey => $ipack): ?>
                <option value="<?= h($ikey) ?>"><?= h($ipack['title']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="import_file">Filled file</label>
            <input id="import_file" name="import_file" type="file" accept=".xlsx,.csv,.txt,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required>
          </div>
        </div>
        <p class="hint">Excel (.xlsx) or CSV. Maximum 4 MB and about 2,500 rows. Matching client names are reused, not duplicated. Stock opening quantities only apply to new products.</p>
        <div class="actions" style="margin-top:12px">
          <button class="btn" type="submit"><?= icon('upload') ?>Upload and add to this desk</button>
        </div>
      </form>
    </section>
    <section class="card settings-card" id="backup">
      <h2><?= icon('download') ?>Backup</h2>
      <p class="lede">A copy of this desk is saved each day. Download it, or upload a copy to restore products, sales, purchases and documents. Logins are not replaced.</p>
      <?php $backs = company_backup_list(); ?>
      <div class="actions" style="margin-bottom:12px">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="backup_now">
          <button class="btn ghost" type="submit"><?= icon('download', 16) ?>Save backup now</button>
        </form>
      </div>
      <?php if (!$backs): ?>
        <p class="empty">No backups yet. Open the desk or tap Save backup now.</p>
      <?php else: ?>
        <div class="table-scroll">
          <table class="grid">
            <thead><tr><th>File</th><th>When</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($backs as $bfile): ?>
                <tr>
                  <td class="mono"><?= h($bfile['file']) ?></td>
                  <td><?= h(date('j M Y H:i', $bfile['mtime'])) ?></td>
                  <td><a class="btn ghost sm" href="<?= h(url('settings.php?backup=' . urlencode($bfile['file']))) ?>">Download</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
      <form method="post" enctype="multipart/form-data" style="margin-top:16px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="restore_backup">
        <label for="backup">Restore from a file</label>
        <input id="backup" name="backup" type="file" accept=".gz,.json,application/gzip" required>
        <p class="hint">This replaces products, parties and documents on this desk with the file. It cannot be undone except by another backup.</p>
        <div class="actions" style="margin-top:12px">
          <button class="btn" type="submit"><?= icon('check') ?>Restore backup</button>
        </div>
      </form>
    </section>
  </div>
</div>
<template id="tpl-proto">
  <div class="tpl-edit" data-tpl-card data-tpl-panel="__KEY__">
    <div class="form-grid">
      <div>
        <label>Title on the desk</label>
        <input name="tpl[__KEY__][title]" placeholder="Thank you">
      </div>
      <div>
        <label>Heading on the page</label>
        <input name="tpl[__KEY__][heading]" placeholder="THANK YOU">
      </div>
      <div style="grid-column:1 / -1">
        <label>Subject</label>
        <input name="tpl[__KEY__][subject]">
      </div>
    </div>
    <label>Body</label>
    <textarea name="tpl[__KEY__][body]" rows="7">Dear Sir / Madam,

Yours faithfully,
Accounts
{company}</textarea>
  </div>
</template>
<?php
$featDefaults = json_encode([
    'books' => desk_feature_defaults('books'),
    'sales' => desk_feature_defaults('sales'),
], JSON_UNESCAPED_UNICODE);
layout_end('<script>window.vellisysFeatureDefaults=' . $featDefaults . ';</script><script src="' . h(asset('js/people-access.js')) . '"></script>');
?>
