<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_platform();

$id = (int) ($_GET['id'] ?? post('id'));
$company = $id ? db_one('SELECT * FROM companies WHERE id = ?', 'i', [$id]) : null;
if (!$company) {
    flash('Company not found.', 'err');
    redirect('admin_companies.php');
}
$brand = branding_for($id);
$members = db_all('SELECT id, name, job_title, email, role, access, features, status, created_at FROM users WHERE company_id = ? ORDER BY role = \'admin\' DESC, id', 'i', [$id]);
$error = '';
$editUserId = (int) ($_GET['edit_user'] ?? 0);
$editMember = $editUserId ? load_desk_user($id, $editUserId) : null;

if (isset($_GET['backup'])) {
    company_backup_send((string) $_GET['backup'], $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    if ($action === 'profile') {
        $status = post('status') ?: 'onboarding';
        if (!in_array($status, ['onboarding', 'live', 'suspended'], true)) {
            $status = 'onboarding';
        }
        $name = post('name') ?: $company['name'];
        $kindsPosted = $_POST['enabled_kinds'] ?? [];
        $plan = normalize_company_plan(post('plan') ?: (string) ($company['plan'] ?? 'sme'));
        $limit = clamp_user_limit((int) post('user_limit'), $plan);
        if (!is_array($kindsPosted) || $kindsPosted === []) {
            $error = 'Select at least one document type this company will use.';
        } elseif ($limit < company_seat_count($id)) {
            $error = 'This desk already has ' . company_seat_count($id) . ' logins. Raise the seat count or remove a user first.';
        } else {
            $plannerOn = planner_resolve_enabled($plan, !empty($_POST['planner_enabled']), $company);
            $pnlOn = pnl_resolve_enabled($plan, !empty($_POST['pnl_enabled']), $company);
            try {
                db_exec('UPDATE companies SET name=?, status=?, plan=?, notes=?, enabled_kinds=?, custom_doc=?, nature_of_business=?, client_audience=?, client_fields=?, line_columns=?, user_limit=?, planner_enabled=?, pnl_enabled=?, stock_enabled=? WHERE id=?', 'ssssssssssiiiii', [$name, $status, $plan, post('notes') ?: null, posted_enabled_kinds(), posted_custom_doc(), sanitize_nature_of_business(post('nature_of_business')), posted_client_fields()['audience'], posted_client_fields_json(), posted_document_line_columns(), $limit, $plannerOn, $pnlOn, !empty($_POST['stock_enabled']) ? 1 : 0, $id]);
                db_exec('UPDATE branding SET name=? WHERE company_id=?', 'si', [$name, $id]);
            } catch (Throwable $e) {
                $error = 'Could not save the company profile. Check the form and try again.';
            }
            if ($error === '') {
                if ($status === 'live') {
                    company_mark_onboard_step($id, 'desk_live');
                }
                flash('Company profile saved.');
                redirect('admin_company.php?id=' . $id);
            }
        }
    }
    if ($action === 'location') {
        $loc = posted_admin_location();
        try {
            db_exec(
                'UPDATE companies SET loc_office=?, loc_street=?, loc_city=?, loc_region=?, loc_country=? WHERE id=?',
                'sssssi',
                [$loc['office'], $loc['street'], $loc['city'], $loc['region'], $loc['country'], $id]
            );
            flash('Location saved for ' . $company['name'] . '. Desks do not see this.');
            redirect('admin_company.php?id=' . $id);
        } catch (Throwable $e) {
            $error = 'Could not save the location.';
        }
    }
    if ($action === 'branding') {
        $color = parse_hex_color(post('brand_color'), '#82B440');
        $accent = parse_hex_color(post('brand_accent'), '#C6A15B');
        $deep = hex_shade($color, 0.52);
        $logoPath = (string) ($brand['logo_path'] ?? '');
        $taken = branding_take_logo_upload($id);
        if (empty($taken['ok'])) {
            $error = (string) ($taken['error'] ?? 'Could not save the logo file.');
        } elseif (!empty($taken['path'])) {
            $logoPath = $taken['path'];
        }
        if ($error === '') {
            try {
                db_exec(
                    'UPDATE branding SET tagline=?, tin=?, vat_no=?, address=?, city=?, phone=?, email=?, website=?, bank_name=?, account_name=?, account_number=?, brand_color=?, brand_accent=?, brand_deep=?, logo_path=?, prefix=?, payment_note=?, invoice_comments=?, receipt_comments=?, currency=?, fx_ugx_per_usd=? WHERE company_id=?',
                    'ssssssssssssssssssssdi',
                    [
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
                        strtoupper(post('prefix') ?: prefix_from_name($company['name'])),
                        post('payment_note'),
                        post('invoice_comments'),
                        post('receipt_comments'),
                        posted_currency('currency', 'UGX'),
                        parse_fx_rate(post('fx_ugx_per_usd')),
                        $id,
                    ]
                );
                flash('Stationery saved for ' . $company['name'] . '.');
                redirect('admin_company.php?id=' . $id);
            } catch (Throwable $e) {
                $error = 'Could not save stationery. Check the fields and try again.';
            }
        }
    }
    if ($action === 'add_user') {
        $made = create_desk_user($id, [
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
            company_mark_onboard_step($id, 'desk_login');
            flash('Desk login created for ' . $made['email'] . ($made['role'] === 'admin' ? ' as company admin.' : '.'));
            redirect('admin_company.php?id=' . $id);
        }
    }
    if ($action === 'edit_user') {
        $uid = (int) post('user_id');
        $access = post('user_access') === 'sales' ? 'sales' : 'books';
        $saved = update_desk_user($id, $uid, [
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
            flash('Desk login updated for ' . $saved['email'] . '.');
            redirect('admin_company.php?id=' . $id);
        }
    }
    if ($action === 'reset_user_password') {
        $uid = (int) post('user_id');
        $saved = reset_desk_user_password($id, $uid, post('new_password'));
        if (empty($saved['ok'])) {
            $error = (string) ($saved['error'] ?? 'Could not reset that password.');
        } else {
            flash('Password reset for ' . $saved['email'] . ': ' . $saved['password']);
            redirect('admin_company.php?id=' . $id);
        }
    }
    if ($action === 'suspend_user' || $action === 'restore_user') {
        $uid = (int) post('user_id');
        $saved = set_desk_user_suspended($id, $uid, $action === 'suspend_user');
        if (empty($saved['ok'])) {
            $error = (string) ($saved['error'] ?? 'Could not change that login.');
        } else {
            flash($saved['status'] === 'suspended' ? ($saved['email'] . ' is suspended.') : ($saved['email'] . ' can sign in again.'));
            redirect('admin_company.php?id=' . $id);
        }
    }
    if ($action === 'delete_user') {
        $uid = (int) post('user_id');
        $saved = delete_desk_user($id, $uid);
        if (empty($saved['ok'])) {
            $error = (string) ($saved['error'] ?? 'Could not delete that user.');
        } else {
            flash('Deleted desk login ' . $saved['email'] . '.');
            redirect('admin_company.php?id=' . $id);
        }
    }
    if ($action === 'go_live') {
        db_exec("UPDATE companies SET status='live' WHERE id=?", 'i', [$id]);
        company_mark_onboard_step($id, 'desk_live');
        $member = $members[0] ?? null;
        if ($member) {
            $live = send_live_email(array_merge($company, ['status' => 'live']), $member, (int) $user['id']);
            flash($company['name'] . ' is live. ' . ($live['ok'] ? 'The team was emailed from ' . product_email() . '.' : 'Welcome mail was queued from ' . product_email() . '.'));
        } else {
            flash($company['name'] . ' is live.');
        }
        redirect('admin_company.php?id=' . $id);
    }
    if ($action === 'mailbox' || $action === 'mailbox_test') {
        $provider = post('mail_provider');
        if (!isset(mail_provider_presets()[$provider])) {
            $provider = 'hostinger';
        }
        $preset = mail_provider_presets()[$provider];
        $email = strtolower(post('mail_email'));
        $fromName = post('mail_from_name') ?: $company['name'];
        $host = post('smtp_host') ?: $preset['smtp_host'];
        $port = (int) post('smtp_port') ?: (int) $preset['smtp_port'];
        $secure = post('smtp_secure');
        if (!in_array($secure, ['ssl', 'tls', 'none'], true)) {
            $secure = $preset['smtp_secure'];
        }
        $popHost = post('pop_host') ?: $preset['pop_host'];
        $popPort = (int) post('pop_port') ?: (int) $preset['pop_port'];
        $imapHost = post('imap_host') ?: $preset['imap_host'];
        $imapPort = (int) post('imap_port') ?: (int) $preset['imap_port'];
        $passPlain = post('mail_password');
        $stored = (string) ($company['mail_password'] ?? '');
        if ($passPlain !== '') {
            $stored = mail_encrypt_secret($passPlain);
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'The sending mailbox must be a valid email.';
        } else {
            db_exec(
                'UPDATE companies SET mail_provider=?, mail_email=?, mail_password=?, mail_from_name=?, smtp_host=?, smtp_port=?, smtp_secure=?, pop_host=?, pop_port=?, imap_host=?, imap_port=? WHERE id=?',
                'sssssissisii',
                [$provider, $email, $stored, $fromName, $host, $port, $secure, $popHost, $popPort, $imapHost, $imapPort, $id]
            );
            $company = db_one('SELECT * FROM companies WHERE id = ?', 'i', [$id]);
            if ($email !== '') {
                company_mark_onboard_step($id, 'mailbox');
            }
            if ($action === 'mailbox_test') {
                $acct = company_mail_account($company);
                if (!$acct) {
                    $error = 'Save a mailbox email and password before testing.';
                } else {
                    $coBrand = branding_for($id);
                    $from = (string) $acct['from_email'];
                    $html = branded_company_wrap($coBrand, '<p style="margin:0 0 14px;color:#000000">This is a test from <strong>' . h($company['name']) . '</strong>.</p><p style="margin:0 0 14px;color:#000000">Reminders, custom letters, quotations, invoices and receipts leave from <strong>' . h($from) . '</strong> in these colours, with this logo and these contact details.</p><p style="margin:0;color:#000000">If you can read this, the company mailbox is sending.</p>', 'Mailbox test');
                    $text = 'Mailbox test from ' . $company['name'] . ' (' . $from . ").\n\nSent from Vellisys system";
                    $inlines = company_logo_inlines($coBrand);
                    $targets = [$from];
                    $alert = platform_alert_email();
                    if ($alert !== '' && !emails_same($alert, $from)) {
                        $targets[] = $alert;
                    }
                    $okTo = [];
                    $err = '';
                    $prevCopy = $GLOBALS['folio_skip_platform_copy'] ?? null;
                    $GLOBALS['folio_skip_platform_copy'] = true;
                    foreach ($targets as $toAddr) {
                        $test = deliver_mail($acct, $toAddr, 'Mailbox test: ' . $company['name'], $html, $text, $from, $inlines);
                        log_email(null, (int) $user['id'], $toAddr, 'Mailbox test: ' . $company['name'], $text, !empty($test['ok']), (string) ($test['error'] ?? ''), $from);
                        if (!empty($test['ok'])) {
                            $okTo[] = $toAddr;
                        } else {
                            $err = (string) ($test['error'] ?? 'SMTP did not accept the message.');
                        }
                    }
                    if ($prevCopy === null) {
                        unset($GLOBALS['folio_skip_platform_copy']);
                    } else {
                        $GLOBALS['folio_skip_platform_copy'] = $prevCopy;
                    }
                    if ($okTo) {
                        flash('Company test sent from ' . $from . ' (their logo and colours) to ' . implode(' and ', $okTo) . '. Open that inbox - not info@vellisys.com.');
                    } else {
                        flash('Company test was not sent from ' . $from . ': ' . ($err !== '' ? $err : 'SMTP did not accept the message.') . ' Check the Gmail App Password (16 characters) and that the provider is Gmail.', 'err');
                    }
                    redirect('admin_company.php?id=' . $id);
                }
            } else {
                flash('Sending mailbox saved for ' . $company['name'] . '. The company desk cannot edit it.');
                redirect('admin_company.php?id=' . $id);
            }
        }
    }
    if ($action === 'term') {
        $term = parse_paid_term(post('paid_term'));
        $unit = normalize_paid_unit(post('paid_unit'));
        $term = clamp_paid_term($term, $unit);
        $from = post('paid_from');
        $sendReceipt = post('send_receipt') !== '';
        if ($term <= 0) {
            if ($sendReceipt) {
                $error = 'Set the paid term, start date and amount before sending a payment receipt.';
            } else {
            db_exec(
                'UPDATE companies SET paid_term=0, paid_unit=?, paid_from=NULL, expires_at=NULL, renewal_notice_sent_at=NULL WHERE id=?',
                'si',
                [$unit, $id]
            );
            flash('Paid term cleared for ' . $company['name'] . '.');
            redirect('admin_company.php?id=' . $id);
            }
        } else {
        if ($from === '' || !DateTime::createFromFormat('Y-m-d', $from)) {
            $from = date('Y-m-d');
        }
        $expires = compute_expiry_date($from, $term, $unit);
        $feeAmount = money_parse(post('fee_amount'));
        $paidRaw = str_replace([',', ' '], '', post('fee_paid'));
        $feePaid = $paidRaw === '' ? $feeAmount : money_parse($paidRaw);
        $feeCurrency = posted_currency('fee_currency', 'USD');
        if (!$expires) {
            $error = 'Could not calculate the expiry date. Check the start date and term.';
        } else {
            db_exec(
                'UPDATE companies SET paid_term=?, paid_unit=?, paid_from=?, expires_at=?, renewal_notice_sent_at=NULL, fee_amount=?, fee_paid=?, fee_currency=? WHERE id=?',
                'dsssddsi',
                [$term, $unit, $from, $expires, $feeAmount, $feePaid, $feeCurrency, $id]
            );
            $fresh = db_one('SELECT * FROM companies WHERE id = ?', 'i', [$id]);
            company_mark_onboard_step($id, 'paid_term');
            $delta = $feePaid - company_fee_paid($company);
            if ($delta > 0.009) {
                record_platform_fee($id, $delta, $feeCurrency, 'term', 'Paid term for ' . $company['name']);
            }
            $note = $company['name'] . ' is paid for ' . format_paid_term_number($term) . ' ' . $unit . ', until ' . format_date($expires) . '.';
            if ($sendReceipt) {
                $sent = send_payment_receipt($fresh ?: $company, $user);
                if (!empty($sent['ok'])) {
                    flash($note . ' Payment receipt sent to ' . ($sent['contact']['email'] ?? '') . ' from ' . product_email() . '.');
                } elseif (($sent['status'] ?? '') === 'queued') {
                    flash($note . ' Receipt queued for ' . ($sent['contact']['email'] ?? '') . '. ' . ($sent['error'] ?? ''), 'err');
                } else {
                    flash($note . ' ' . ($sent['error'] ?? 'Could not send the payment receipt.'), 'err');
                }
            } else {
                flash($note);
            }
            redirect('admin_company.php?id=' . $id);
        }
        }
    }
    if ($action === 'onboard_steps') {
        $posted = $_POST['onboard'] ?? [];
        $keys = [];
        if (is_array($posted)) {
            foreach (array_keys(onboard_step_defs()) as $key) {
                if (!empty($posted[$key])) {
                    $keys[] = $key;
                }
            }
        }
        company_save_onboard_steps($id, $keys, company_onboard_map($company));
        flash('Onboarding steps saved for ' . $company['name'] . '.');
        redirect('admin_company.php?id=' . $id);
    }
    if ($action === 'send_receipt_only') {
        $sent = send_payment_receipt($company, $user);
        if (!empty($sent['ok'])) {
            flash('Payment receipt sent to ' . ($sent['contact']['email'] ?? '') . ' from ' . product_email() . '.');
        } elseif (($sent['status'] ?? '') === 'queued') {
            flash('Payment receipt queued for ' . ($sent['contact']['email'] ?? '') . '. ' . ($sent['error'] ?? ''), 'err');
        } else {
            flash($sent['error'] ?? 'Could not send the payment receipt.', 'err');
        }
        redirect('admin_company.php?id=' . $id);
    }
    if ($action === 'send_login_credentials') {
        $target = null;
        foreach ($members as $m) {
            if (($m['role'] ?? '') === 'admin') {
                $target = $m;
                break;
            }
        }
        $target = $target ?: ($members[0] ?? null);
        if (!$target) {
            $error = 'Create a desk login before sending credentials.';
        } else {
            $reset = reset_desk_user_password($id, (int) $target['id']);
            if (empty($reset['ok'])) {
                $error = (string) ($reset['error'] ?? 'Could not prepare a temporary password.');
            } else {
                $sent = send_login_credentials_email($company, array_merge($target, ['email' => $reset['email']]), (string) $reset['password'], (int) $user['id']);
                if (!empty($sent['ok'])) {
                    flash('Login credentials sent from ' . product_email() . ' to ' . $reset['email'] . '. The temporary password is in that email. Ask them to change it after they sign in.');
                } elseif (($sent['status'] ?? '') === 'queued' || !empty($sent['error'])) {
                    flash('Credentials queued for ' . $reset['email'] . '. ' . ($sent['error'] ?? 'Mail is waiting to send.'), 'err');
                } else {
                    flash($sent['error'] ?? 'Could not send login credentials.', 'err');
                }
                redirect('admin_company.php?id=' . $id);
            }
        }
    }
    if ($action === 'reset_training') {
        $confirm = trim(post('reset_confirm'));
        $scopes = $_POST['reset_scope'] ?? [];
        if (!is_array($scopes)) {
            $scopes = [];
        }
        if (post('reset_all') !== '') {
            $scopes = array_keys(company_reset_scopes());
        }
        if (strcasecmp($confirm, (string) $company['name']) !== 0) {
            $error = 'Type the company name exactly to confirm the reset.';
        } else {
            $done = company_reset_training_data($id, $scopes);
            if (empty($done['ok'])) {
                $error = (string) ($done['error'] ?? 'Could not reset that desk.');
            } else {
                flash($company['name'] . ' is ready for official use. Cleared: ' . implode(', ', $done['cleared'] ?? []) . '. Logins, branding, mailbox and paid term were kept.');
                redirect('admin_company.php?id=' . $id);
            }
        }
    }
    if ($action === 'restore_backup') {
        $file = $_FILES['backup'] ?? [];
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $error = 'Choose a Vellisys backup file to restore.';
        } else {
            $payload = company_backup_read_file($file['tmp_name']);
            if (!$payload) {
                $error = 'That file is not a Vellisys backup.';
            } else {
                $res = company_backup_restore_payload($payload, $id, ['branding' => post('restore_branding') !== '']);
                if (empty($res['ok'])) {
                    $error = (string) ($res['error'] ?? 'Could not restore that backup.');
                } else {
                    flash('Backup restored onto ' . $company['name'] . '. The desk is no longer empty.');
                    redirect('admin_company.php?id=' . $id);
                }
            }
        }
    }
    if ($action === 'delete_company') {
        $confirm = trim(post('delete_confirm'));
        if (strcasecmp($confirm, (string) $company['name']) !== 0) {
            $error = 'Type the company name exactly to delete it.';
        } else {
            $gone = platform_delete_company($id);
            if (empty($gone['ok'])) {
                $error = (string) ($gone['error'] ?? 'Could not delete that company.');
            } else {
                flash('Deleted ' . $gone['name'] . '. The desk and its logins are gone.');
                redirect('admin_companies.php');
            }
        }
    }
}

$company = db_one('SELECT * FROM companies WHERE id = ?', 'i', [$id]);
$brand = branding_for($id);
$members = db_all('SELECT id, name, job_title, email, role, access, created_at, last_seen_at, last_login_at FROM users WHERE company_id = ? ORDER BY role = \'admin\' DESC, id', 'i', [$id]);
$onboardDefs = onboard_step_defs();
$onboardProgress = company_onboard_progress($company);
$receiptContact = company_notice_email($id, $brand ?: [], $members);
$receiptPreview = company_expires_on($company) ? payment_receipt_copy($company, $receiptContact, $members) : null;
$useFrom = desk_now()->modify('-30 days')->format('Y-m-d');
$useTo = desk_now()->format('Y-m-d');
$deskUse = platform_usage_counts($id, $useFrom, $useTo);
$onlineNow = 0;
$lastSeen = null;
$lastLogin = null;
foreach ($members as $m) {
    if (user_is_online($m['last_seen_at'] ?? null)) {
        $onlineNow++;
    }
    $seen = (string) ($m['last_seen_at'] ?? '');
    $login = (string) ($m['last_login_at'] ?? '');
    if ($seen !== '' && ($lastSeen === null || strcmp($seen, $lastSeen) > 0)) {
        $lastSeen = $seen;
    }
    if ($login !== '' && ($lastLogin === null || strcmp($login, $lastLogin) > 0)) {
        $lastLogin = $login;
    }
}
$namedBranches = 0;
try {
    $br = db_one('SELECT COUNT(*) AS c FROM branches WHERE company_id = ?', 'i', [$id]);
    $namedBranches = (int) ($br['c'] ?? 0);
} catch (Throwable $e) {
}
$deskHealth = platform_desk_health(['last_seen_at' => $lastSeen, 'last_login_at' => $lastLogin]);
$resetScopes = company_reset_scopes();
$deskBackups = company_backup_list($id);
$deskEmails = [];
$toList = [];
foreach ($members as $m) {
    $em = strtolower(trim((string) ($m['email'] ?? '')));
    if ($em !== '') {
        $toList[] = $em;
    }
}
$brandMail = strtolower(trim((string) ($brand['email'] ?? '')));
if ($brandMail !== '') {
    $toList[] = $brandMail;
}
$toList = array_values(array_unique($toList));
if ($toList) {
    $in = implode(',', array_fill(0, count($toList), '?'));
    $types = 's' . str_repeat('s', count($toList));
    $deskEmails = db_all(
        'SELECT * FROM emails WHERE from_email = ? AND to_email IN (' . $in . ') ORDER BY id DESC LIMIT 12',
        $types,
        array_merge([product_email()], $toList)
    );
}

layout_admin_start($company['name'], $user);
?>
<div class="page-head">
  <div>
    <h1><?= icon('building') ?><?= h($company['name']) ?></h1>
    <p class="lede"><?= h(ucfirst((string) $company['status'])) ?> · <?= h($brand['currency'] ?? 'UGX') ?> · <?= count($members) ?> / <?= (int) company_user_limit($company) ?> user<?= count($members) === 1 ? '' : 's' ?> · <?= h(company_term_label($company)) ?><?php if (company_expires_on($company)): ?> · <?= h(company_remaining_phrase($company)) ?> · <?= h(company_expiry_date_label($company)) ?><?php endif; ?><?php $nob = trim((string) ($company['nature_of_business'] ?? '')); if ($nob !== ''): ?> · <?= h($nob) ?><?php endif; ?></p>
  </div>
  <div class="actions">
    <a class="btn" href="<?= h(url('admin_desk.php?id=' . $id)) ?>"><?= icon('desk') ?>Open desk</a>
    <?php if ($company['status'] !== 'live'): ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="action" value="go_live">
        <button class="btn ghost" type="submit"><?= icon('check') ?>Mark live</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($error): ?><p class="flash flash-err" style="margin:0 0 16px"><?= icon('alert', 16) ?><?= h($error) ?></p><?php endif; ?>

<div class="stats">
  <div class="card stat">
    <?= icon('clock', 20) ?>
    <span>Desk</span>
    <strong><?= h($deskHealth['label']) ?></strong>
    <em class="admin-health is-<?= h($deskHealth['key']) ?>"><?= h(format_when($lastSeen)) ?></em>
  </div>
  <div class="card stat">
    <?= icon('user', 20) ?>
    <span>On this desk now</span>
    <strong><?= $onlineNow ?></strong>
  </div>
  <div class="card stat">
    <?= icon('clients', 20) ?>
    <span>People onboard</span>
    <strong><?= count($members) ?> / <?= (int) company_user_limit($company) ?></strong>
  </div>
  <div class="card stat">
    <?= icon('pin', 20) ?>
    <span>Named branches</span>
    <strong><?= $namedBranches ?></strong>
  </div>
</div>
<div class="stats">
  <div class="card stat">
    <?= icon('file', 20) ?>
    <span>Sheets, last 30 days</span>
    <strong><?= (int) $deskUse['documents'] ?></strong>
  </div>
  <div class="card stat">
    <?= icon('clock', 20) ?>
    <span>Desk events, last 30 days</span>
    <strong><?= (int) $deskUse['activities'] ?></strong>
  </div>
  <div class="card stat">
    <?= icon('reports', 20) ?>
    <span>Use score</span>
    <strong><?= (int) $deskUse['score'] ?></strong>
  </div>
  <div class="card stat">
    <?= icon('user', 20) ?>
    <span>Last sign-in</span>
    <strong><?= h(format_when($lastLogin)) ?></strong>
  </div>
</div>
<p class="hint" style="margin:-8px 0 20px">This snapshot is people and how busy the desk is. What they invoiced their own clients lives on their books, not here. Paid term for Vellisys is further down.</p>

<?php
$locCountry = company_loc($company, 'country');
$locRegion = company_loc($company, 'region');
$locKnownCountry = in_array($locCountry, platform_countries(), true);
$locUg = $locCountry === 'Uganda' || ($locCountry === '' && in_array($locRegion, uganda_regions(), true));
$locUgRegion = in_array($locRegion, uganda_regions(), true) ? $locRegion : '';
?>
<form class="card form-wide" method="post" style="margin-bottom:20px" data-admin-location>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <input type="hidden" name="action" value="location">
  <div class="card-head"><h2><?= icon('pin', 16) ?>Location</h2></div>
  <p class="hint" style="padding:0 22px;margin:0 0 8px">Vellisys only. Used for country and region reports. This is not the printed address on their stationery, and the company desk cannot see or edit it.</p>
  <div class="form-grid" style="padding:0 22px">
    <div>
      <label for="loc_office">Office no</label>
      <input id="loc_office" name="loc_office" value="<?= h(company_loc($company, 'office')) ?>" placeholder="Plot 12, Suite 3">
    </div>
    <div>
      <label for="loc_street">Street name</label>
      <input id="loc_street" name="loc_street" value="<?= h(company_loc($company, 'street')) ?>" placeholder="Kampala Road">
    </div>
    <div>
      <label for="loc_city">City / district</label>
      <input id="loc_city" name="loc_city" list="loc-districts" value="<?= h(company_loc($company, 'city')) ?>" placeholder="Kampala">
      <datalist id="loc-districts">
        <?php foreach (uganda_districts() as $d): ?>
          <option value="<?= h($d) ?>">
        <?php endforeach; ?>
      </datalist>
    </div>
    <div data-loc-ug <?= $locUg ? '' : 'hidden' ?>>
      <label for="loc_region_ug">Region (Uganda)</label>
      <select id="loc_region_ug" name="loc_region_ug">
        <option value="">Select region</option>
        <?php foreach (uganda_regions() as $reg): ?>
          <option value="<?= h($reg) ?>" <?= $locUgRegion === $reg ? 'selected' : '' ?>><?= h($reg) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div data-loc-other <?= $locUg ? 'hidden' : '' ?>>
      <label for="loc_region_other">Region / state</label>
      <input id="loc_region_other" name="loc_region_other" value="<?= h($locUg ? '' : $locRegion) ?>" placeholder="County or province">
    </div>
    <div>
      <label for="loc_country">Country</label>
      <select id="loc_country" name="loc_country" data-loc-country>
        <option value="">Select country</option>
        <?php foreach (platform_countries() as $cc): ?>
          <option value="<?= h($cc) ?>" <?= $locKnownCountry && $locCountry === $cc ? 'selected' : '' ?>><?= h($cc) ?></option>
        <?php endforeach; ?>
        <option value="other" <?= $locCountry !== '' && !$locKnownCountry ? 'selected' : '' ?>>Other</option>
      </select>
    </div>
    <div data-loc-country-other <?= $locCountry !== '' && !$locKnownCountry ? '' : 'hidden' ?>>
      <label for="loc_country_other">Country name</label>
      <input id="loc_country_other" name="loc_country_other" value="<?= h($locKnownCountry ? '' : $locCountry) ?>">
    </div>
  </div>
  <div class="actions" style="padding:6px 22px 22px">
    <button class="btn sm" type="submit"><?= icon('check', 14) ?>Save location</button>
    <a class="btn ghost sm" href="<?= h(url('admin_locations.php')) ?>"><?= icon('reports', 14) ?>Location reports</a>
  </div>
</form>

<div class="desk-grid">
  <div class="card">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="card-head">
        <h2><?= icon('check', 16) ?>Onboarding</h2>
        <span class="pill<?= $onboardProgress['done'] === $onboardProgress['total'] ? '' : ' warn' ?>"><?= (int) $onboardProgress['done'] ?> / <?= (int) $onboardProgress['total'] ?></span>
      </div>
      <p class="hint onboard-lead">Tick each step as you finish it. The last box is when the client confirms they signed in.</p>
      <ol class="onboard-list">
        <?php $stepN = 0; foreach ($onboardDefs as $key => $label): $stepN++; $isLast = $key === 'login_confirmed'; $when = company_onboard_when($company, $key); ?>
          <li class="onboard-step<?= $isLast ? ' is-last' : '' ?><?= company_onboard_done($company, $key) ? ' is-done' : '' ?>">
            <label class="onboard-tick">
              <input type="checkbox" name="onboard[<?= h($key) ?>]" value="1" <?= company_onboard_done($company, $key) ? 'checked' : '' ?>>
              <span class="onboard-num"><?= $stepN ?></span>
              <span class="onboard-copy">
                <strong><?= h($label) ?></strong>
                <?php if ($when !== ''): ?><span class="onboard-when">Done <?= h($when) ?></span><?php endif; ?>
                <?php if ($isLast): ?><span class="onboard-note">Call or write until they confirm they can open the desk, then tick this box.</span><?php endif; ?>
              </span>
            </label>
          </li>
        <?php endforeach; ?>
      </ol>
      <div class="actions onboard-actions">
        <button class="btn sm" type="submit" name="action" value="onboard_steps"><?= icon('check', 14) ?>Save steps</button>
        <button class="btn ghost sm" type="submit" name="action" value="send_login_credentials"><?= icon('mail', 14) ?>Send login credentials</button>
        <button class="btn ghost sm" type="submit" name="action" value="send_receipt_only"><?= icon('receipt', 14) ?>Send receipt email</button>
      </div>
    </form>
  </div>
  <div class="card">
    <div class="card-head"><h2><?= icon('user', 16) ?>Desk logins</h2></div>
    <?php if (!$members): ?>
      <p class="empty">No users yet.</p>
    <?php else: ?>
      <div class="table-scroll">
      <table class="grid">
        <thead><tr><th>Name</th><th>Title</th><th>Email</th><th>Access</th><th>Status</th><th>Last sign-in</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($members as $m): ?>
            <tr>
              <td><?= h($m['name']) ?></td>
              <td><?= h((string) ($m['job_title'] ?? '')) ?></td>
              <td class="mono"><?= h($m['email']) ?></td>
              <td><?= h(desk_access_label((string) $m['role'], (string) ($m['access'] ?? 'books'))) ?></td>
              <td><?= (($m['status'] ?? 'live') === 'suspended') ? 'Suspended' : 'Live' ?></td>
              <td class="mono"><?php if (user_is_online($m['last_seen_at'] ?? null)): ?><span class="pill">Online</span><?php else: ?><?= h(format_when($m['last_login_at'] ?? $m['last_seen_at'] ?? null)) ?><?php endif; ?></td>
              <td class="row-actions">
                <div class="actions">
                  <a class="btn ghost sm" href="<?= h(url('admin_company.php?id=' . $id . '&edit_user=' . (int) $m['id'])) ?>">Edit</a>
                  <form method="post" onsubmit="return confirm('Reset this password to a new temporary one?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="action" value="reset_user_password">
                    <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                    <button class="btn ghost sm" type="submit">Reset p/w</button>
                  </form>
                  <?php if (($m['status'] ?? 'live') === 'suspended'): ?>
                    <form method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= $id ?>">
                      <input type="hidden" name="action" value="restore_user">
                      <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                      <button class="btn ghost sm" type="submit">Restore</button>
                    </form>
                  <?php else: ?>
                    <form method="post" onsubmit="return confirm('Suspend this login?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= $id ?>">
                      <input type="hidden" name="action" value="suspend_user">
                      <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                      <button class="btn ghost sm" type="submit">Suspend</button>
                    </form>
                  <?php endif; ?>
                  <form method="post" onsubmit="return confirm('Delete this desk login?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                    <button class="btn danger sm" type="submit">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
    <?php if ($editMember): ?>
      <?php
        $eAccess = ((string) ($editMember['access'] ?? 'books')) === 'sales' ? 'sales' : 'books';
        $eFeat = parse_user_features($editMember['features'] ?? '', $eAccess);
        $eIsAdmin = ($editMember['role'] ?? '') === 'admin';
      ?>
    <form class="form" method="post" style="padding-bottom:18px">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="user_id" value="<?= (int) $editMember['id'] ?>">
      <p class="hint">Edit <?= h($editMember['email']) ?>. Leave the password blank to keep the current one.</p>
      <label for="edit_user_name">Name</label>
      <input id="edit_user_name" name="user_name" required value="<?= h((string) $editMember['name']) ?>">
      <label for="edit_user_title">Title</label>
      <input id="edit_user_title" name="user_title" value="<?= h((string) ($editMember['job_title'] ?? '')) ?>">
      <label for="edit_user_email">Email</label>
      <input id="edit_user_email" name="user_email" type="email" required value="<?= h((string) $editMember['email']) ?>">
      <?php if (!$eIsAdmin): ?>
      <label for="edit_user_access">Access</label>
      <select id="edit_user_access" name="user_access" data-access-select>
        <?php foreach (desk_staff_access_levels() as $key => $info): ?>
          <option value="<?= h($key) ?>" <?= $eAccess === $key ? 'selected' : '' ?>><?= h($info['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php render_desk_feature_checks($eFeat, 'features[]', $company); ?>
      <?php endif; ?>
      <label for="edit_user_password">New password (optional)</label>
      <input id="edit_user_password" name="user_password" type="password" minlength="8" autocomplete="new-password">
      <?php if (plan_includes_branches((string) ($company['plan'] ?? 'sme'))): ?>
      <label for="edit_user_branch">Branch</label>
      <select id="edit_user_branch" name="user_branch">
        <?php render_branch_options((int) ($editMember['branch_id'] ?? 0), true, $id); ?>
      </select>
      <?php endif; ?>
      <div class="actions" style="margin-top:12px">
        <button class="btn sm" type="submit"><?= icon('check', 14) ?>Save login</button>
        <a class="btn ghost sm" href="<?= h(url('admin_company.php?id=' . $id)) ?>">Cancel</a>
      </div>
    </form>
    <?php endif; ?>
      <?php if (!$editMember && count($members) < company_user_limit($company)): ?>
    <form class="form" method="post" style="padding-bottom:18px" data-access-features="new">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="action" value="add_user">
      <p class="hint">Seats: <?= count($members) ?> of <?= (int) company_user_limit($company) ?>. This plan allows up to <?= (int) plan_user_limit_max($company) ?> users. You choose how many this desk gets. The first login is the company admin.</p>
      <label for="user_name">Add a desk user</label>
      <input id="user_name" name="user_name" required placeholder="Name">
      <label for="user_title">Title</label>
      <input id="user_title" name="user_title" placeholder="Accountant">
      <label for="user_email">Email</label>
      <input id="user_email" name="user_email" type="email" required>
      <label for="user_access">Access</label>
      <select id="user_access" name="user_access" data-access-select>
        <?php foreach (desk_staff_access_levels() as $key => $info): ?>
          <option value="<?= h($key) ?>"><?= h($info['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php render_desk_feature_checks(desk_feature_defaults('books'), 'features[]', $company); ?>
      <label for="user_password">Temporary password</label>
      <input id="user_password" name="user_password" value="<?= h(default_desk_password()) ?>">
      <?php if (plan_includes_branches((string) ($company['plan'] ?? 'sme'))): ?>
      <label for="user_branch">Branch</label>
      <select id="user_branch" name="user_branch">
        <?php render_branch_options(0, true, $id); ?>
      </select>
      <?php endif; ?>
      <div class="actions" style="margin-top:12px">
        <button class="btn sm" type="submit"><?= icon('plus', 14) ?>Create login</button>
      </div>
    </form>
      <?php elseif (!$editMember): ?>
        <p class="hint">All <?= (int) company_user_limit($company) ?> seats are in use.</p>
      <?php endif; ?>
  </div>
</div>

<form class="card form-wide" method="post" style="margin-top:16px">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <input type="hidden" name="action" value="term">
  <div class="card-head"><h2><?= icon('calendar', 16) ?>Paid term</h2></div>
  <div class="form-grid" style="padding:0 22px">
    <div>
      <label for="paid_from">Paid from</label>
      <input id="paid_from" name="paid_from" type="date" value="<?= h((string) ($company['paid_from'] ?: date('Y-m-d'))) ?>">
    </div>
    <div>
      <label for="paid_term">Number</label>
      <input id="paid_term" name="paid_term" type="number" min="0" max="520" step="0.01" value="<?= h(format_paid_term_number((float) ($company['paid_term'] ?? 0))) ?>">
    </div>
    <div>
      <label for="paid_unit">Unit</label>
      <select id="paid_unit" name="paid_unit">
        <option value="weeks" <?= ($company['paid_unit'] ?? '') === 'weeks' ? 'selected' : '' ?>>Weeks</option>
        <option value="months" <?= ($company['paid_unit'] ?? 'months') === 'months' ? 'selected' : '' ?>>Months</option>
        <option value="years" <?= ($company['paid_unit'] ?? '') === 'years' ? 'selected' : '' ?>>Years</option>
      </select>
    </div>
    <div>
      <label for="fee_amount">Fee for this term</label>
      <input id="fee_amount" name="fee_amount" inputmode="decimal" value="<?= h(company_fee_amount($company) > 0 ? (string) company_fee_amount($company) : '') ?>" placeholder="0">
    </div>
    <div>
      <label for="fee_paid">Amount paid</label>
      <input id="fee_paid" name="fee_paid" inputmode="decimal" value="<?= h(company_fee_paid($company) > 0 ? (string) company_fee_paid($company) : '') ?>" placeholder="Same as fee if left blank">
    </div>
    <div>
      <label for="fee_currency">Fee currency</label>
      <?php currency_field('fee_currency', 'fee_currency', company_fee_currency($company)); ?>
    </div>
  </div>
  <div style="padding:0 22px 22px">
    <p class="hint" style="margin:8px 0 12px">
      <?php if (company_expires_on($company)): ?>
        Current expiry <?= h(format_date($company['expires_at'])) ?> · <?= h(company_remaining_phrase($company)) ?>.
        Balance <?= h(money(company_fee_balance($company), company_fee_currency($company))) ?>.
        Saving recalculates the end date. Set number to 0 to clear the term (fees already collected stay on Finances).
      <?php else: ?>
        Set how many weeks, months or years this client has paid for (decimals such as 1.5 are allowed), and the fee you collected. Expiry is the start date plus that term. Reports list desks one month from that date so you can send a renewal letter.
      <?php endif; ?>
    </p>
    <div class="actions admin-term-actions">
      <button class="btn" type="submit"><?= icon('check') ?>Save paid term</button>
      <button class="btn ghost" type="submit" name="send_receipt" value="1"><?= icon('receipt', 16) ?>Save and send receipt</button>
      <a class="btn ghost" href="<?= h(url('admin_finances.php')) ?>"><?= icon('bank', 16) ?>Finances</a>
    </div>
    <?php if ($receiptPreview): ?>
      <details class="receipt-preview" style="margin-top:16px">
        <summary>Payment receipt template</summary>
        <p class="hint">Sent from <?= h(product_email()) ?> to <?= h($receiptContact['email'] !== '' ? $receiptContact['email'] : 'the company email on file') ?>. Thanks the client for the payment, names the amount received and the subscribed period, and welcomes them to Vellisys.</p>
        <div class="mail-preview">
          <div class="mail-preview-head"><?= h($receiptPreview['subject']) ?></div>
          <iframe title="Payment receipt preview" srcdoc="<?= h(email_html_preview($receiptPreview['html'])) ?>"></iframe>
        </div>
      </details>
    <?php endif; ?>
  </div>
</form>

<form class="card form-wide" method="post" style="margin-top:16px" data-mail-box>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <div class="card-head"><h2><?= icon('send', 16) ?>Sending mailbox</h2></div>
  <div class="form-grid" style="padding:0 22px">
    <div>
      <label for="mail_provider">Mail type</label>
      <select id="mail_provider" name="mail_provider" data-mail-provider>
        <?php foreach (mail_provider_presets() as $key => $preset): ?>
          <option value="<?= h($key) ?>" <?= ($company['mail_provider'] ?? 'hostinger') === $key ? 'selected' : '' ?>><?= h($preset['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="mail_email">Mailbox</label>
      <input id="mail_email" name="mail_email" type="email" value="<?= h((string) ($company['mail_email'] ?? '')) ?>" placeholder="accounts@company.com">
    </div>
    <div>
      <label for="mail_password">Password</label>
      <?php render_password_toggle_field('mail_password', 'mail_password', !empty($company['mail_password']) ? 'Saved · leave blank to keep' : 'Mailbox or Gmail App Password'); ?>
    </div>
    <div>
      <label for="mail_from_name">From name</label>
      <input id="mail_from_name" name="mail_from_name" value="<?= h((string) (($company['mail_from_name'] ?? '') !== '' ? $company['mail_from_name'] : $company['name'])) ?>">
    </div>
    <div>
      <label for="smtp_host">SMTP host</label>
      <input id="smtp_host" name="smtp_host" data-mail-field="smtp_host" value="<?= h((string) ($company['smtp_host'] ?? 'smtp.hostinger.com')) ?>">
    </div>
    <div>
      <label for="smtp_port">SMTP port</label>
      <input id="smtp_port" name="smtp_port" type="number" data-mail-field="smtp_port" value="<?= (int) ($company['smtp_port'] ?? 465) ?>">
    </div>
    <div>
      <label for="smtp_secure">SMTP security</label>
      <select id="smtp_secure" name="smtp_secure" data-mail-field="smtp_secure">
        <?php foreach (['ssl' => 'SSL (465)', 'tls' => 'STARTTLS (587)', 'none' => 'None'] as $k => $label): ?>
          <option value="<?= h($k) ?>" <?= ($company['smtp_secure'] ?? 'ssl') === $k ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="pop_host">POP host</label>
      <input id="pop_host" name="pop_host" data-mail-field="pop_host" value="<?= h((string) ($company['pop_host'] ?? 'pop.hostinger.com')) ?>">
    </div>
    <div>
      <label for="pop_port">POP port</label>
      <input id="pop_port" name="pop_port" type="number" data-mail-field="pop_port" value="<?= (int) ($company['pop_port'] ?? 995) ?>">
    </div>
    <div>
      <label for="imap_host">IMAP host</label>
      <input id="imap_host" name="imap_host" data-mail-field="imap_host" value="<?= h((string) ($company['imap_host'] ?? 'imap.hostinger.com')) ?>">
    </div>
    <div>
      <label for="imap_port">IMAP port</label>
      <input id="imap_port" name="imap_port" type="number" data-mail-field="imap_port" value="<?= (int) ($company['imap_port'] ?? 993) ?>">
    </div>
  </div>
  <div style="padding:0 22px 22px">
    <p class="hint" style="margin:8px 0 8px" data-mail-help><?= h(mail_provider_hint((string) ($company['mail_provider'] ?? 'hostinger'))) ?></p>
    <p class="hint" style="margin:0 0 12px">
      The company desk sends quotations, invoices, receipts, headed letters, debtor reminders, notes to creditors and custom mail from this address and cannot edit it.
      Send test sends a sample in the company's colours, logo and contact details from the company mailbox to that mailbox (and to Your Gmail in super-admin Settings). It does not use the Vellisys letterhead and it is not sent to <?= h(product_email()) ?>.
      <?= company_mail_account($company) ? 'Mailbox is ready to send.' : 'Add the email and password to start sending.' ?>
    </p>
    <div class="actions">
      <button class="btn" type="submit" name="action" value="mailbox"><?= icon('check') ?>Save mailbox</button>
      <button class="btn ghost" type="submit" name="action" value="mailbox_test"><?= icon('send', 16) ?>Send test</button>
    </div>
  </div>
  <script type="application/json" data-mail-presets><?= json_encode(mail_provider_presets(), JSON_UNESCAPED_SLASHES) ?></script>
</form>

<form class="card form-wide" method="post" style="margin-top:16px">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <input type="hidden" name="action" value="profile">
  <div class="card-head"><h2><?= icon('settings', 16) ?>Company</h2></div>
  <div class="form-grid" style="padding:0 22px">
    <div>
      <label for="name">Legal name</label>
      <input id="name" name="name" required value="<?= h($company['name']) ?>">
    </div>
    <div>
      <?php render_nature_of_business_field((string) ($company['nature_of_business'] ?? '')); ?>
    </div>
    <div>
      <label for="status">Status</label>
      <select id="status" name="status">
        <?php foreach (['onboarding' => 'Onboarding', 'live' => 'Live', 'suspended' => 'Suspended'] as $k => $label): ?>
          <option value="<?= h($k) ?>" <?= $company['status'] === $k ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="user_limit">Logins allowed</label>
      <select id="user_limit" name="user_limit" data-user-limit>
        <?php $limitMax = plan_user_limit_max($company); ?>
        <?php for ($n = 1; $n <= 4; $n++): ?>
          <option value="<?= $n ?>" data-min-plan="<?= $n <= 2 ? 'starter' : ($n === 3 ? 'sme' : 'office') ?>" <?= company_user_limit($company) === $n ? 'selected' : '' ?>><?= h(desk_user_limit_choice_label($n)) ?></option>
        <?php endfor; ?>
      </select>
      <p class="hint">Vellisys Start up to 2, Business up to 3, Pro up to 4. You give this desk the number it may use (now <?= (int) $limitMax ?> max on this plan).</p>
    </div>
    <div>
      <label for="plan">Plan</label>
      <select id="plan" name="plan" data-planner-plan data-plan-user-max>
        <?php foreach (company_plan_options() as $key => $label): ?>
          <option value="<?= h($key) ?>" data-max-users="<?= (int) plan_user_limit_max($key) ?>" <?= normalize_company_plan((string) ($company['plan'] ?? 'sme')) === $key ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="hint">Vellisys Business and Pro turn Planner on automatically. You can still switch Planner off below.</p>
    </div>
    <div>
      <label class="check" for="planner_enabled"><input id="planner_enabled" name="planner_enabled" type="checkbox" value="1" data-planner-toggle <?= !empty($company['planner_enabled']) ? 'checked' : '' ?>> Planner on for this desk</label>
      <p class="hint">Notes, budget and calendar for Vellisys Business and Pro. Start stays off unless you enable it here.</p>
    </div>
    <div>
      <label class="check" for="pnl_enabled"><input id="pnl_enabled" name="pnl_enabled" type="checkbox" value="1" data-pnl-toggle <?= !empty($company['pnl_enabled']) ? 'checked' : '' ?>> Profit &amp; Loss on for this desk</label>
      <p class="hint">Pro gets P&amp;L automatically. You can enable bookkeeping, refunds and returns for any plan here.</p>
    </div>
    <div>
      <label class="check" for="stock_enabled"><input id="stock_enabled" name="stock_enabled" type="checkbox" value="1" <?= !empty($company['stock_enabled']) ? 'checked' : '' ?>> Stock management on for this desk</label>
      <p class="hint">Adds Stock and Sale. Purchases sit under Stock. Works on any package.</p>
    </div>
  </div>
  <div style="padding:0 22px 22px">
    <label for="notes">Internal notes</label>
    <textarea id="notes" name="notes" rows="3"><?= h((string) $company['notes']) ?></textarea>
    <?php render_client_fields_admin($company); ?>
    <?php render_desk_kinds_fields($company); ?>
    <div class="actions" style="margin-top:12px">
      <button class="btn" type="submit"><?= icon('check') ?>Save company</button>
    </div>
  </div>
</form>

<form class="card form-wide" method="post" enctype="multipart/form-data" style="margin-top:16px">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <input type="hidden" name="action" value="branding">
  <div class="card-head"><h2><?= icon('palette', 16) ?>Stationery</h2></div>
  <p class="lede" style="padding:0 22px 0">Logo and two brand colours, matching Settings. Primary paints the desk and the strong bars on documents. Accent marks rails, rules and highlights.</p>
  <div class="form-grid" style="padding:0 22px">
    <div>
      <label for="tagline">Tagline</label>
      <input id="tagline" name="tagline" value="<?= h((string) ($brand['tagline'] ?? '')) ?>">
    </div>
    <div>
      <label for="prefix">Prefix</label>
      <input id="prefix" name="prefix" value="<?= h((string) ($brand['prefix'] ?? '')) ?>">
    </div>
    <div>
      <label for="currency-pick">Currency</label>
      <?php currency_field('currency', 'currency', (string) ($brand['currency'] ?? 'UGX'), ['data-fx-home-input' => true]); ?>
      <p class="hint">Choose the desk currency, or type a custom three-letter primary code.</p>
    </div>
    <div>
      <label for="fx_ugx_per_usd">1 USD equals</label>
      <div class="fx-row">
        <input id="fx_ugx_per_usd" name="fx_ugx_per_usd" inputmode="decimal" value="<?= h(rtrim(rtrim(number_format((float) ($brand['fx_ugx_per_usd'] ?? 1), 4, '.', ''), '0'), '.')) ?>">
        <span data-fx-home-label><?= h(normalize_currency((string) ($brand['currency'] ?? 'UGX'), 'UGX')) ?></span>
      </div>
    </div>
    <div>
      <label for="tin">TIN</label>
      <input id="tin" name="tin" value="<?= h((string) ($brand['tin'] ?? '')) ?>">
    </div>
    <div>
      <label for="vat_no">VAT / VRN</label>
      <input id="vat_no" name="vat_no" value="<?= h((string) ($brand['vat_no'] ?? '')) ?>">
    </div>
    <div>
      <label for="phone">Phone</label>
      <input id="phone" name="phone" value="<?= h((string) ($brand['phone'] ?? '')) ?>">
    </div>
    <div>
      <label for="email">Public email</label>
      <input id="email" name="email" type="email" value="<?= h((string) ($brand['email'] ?? '')) ?>">
    </div>
    <div>
      <label for="website">Website</label>
      <input id="website" name="website" value="<?= h((string) ($brand['website'] ?? '')) ?>">
    </div>
    <div>
      <label for="city">City</label>
      <input id="city" name="city" value="<?= h((string) ($brand['city'] ?? '')) ?>">
    </div>
  </div>
  <div style="padding:0 22px 22px">
    <label for="address">Address</label>
    <input id="address" name="address" value="<?= h((string) ($brand['address'] ?? '')) ?>">
    <div class="form-grid">
      <div>
        <label for="bank_name">Bank</label>
        <input id="bank_name" name="bank_name" value="<?= h((string) ($brand['bank_name'] ?? '')) ?>">
      </div>
      <div>
        <label for="account_name">Account name</label>
        <input id="account_name" name="account_name" value="<?= h((string) ($brand['account_name'] ?? '')) ?>">
      </div>
      <div>
        <label for="account_number">Account number</label>
        <input id="account_number" name="account_number" value="<?= h((string) ($brand['account_number'] ?? '')) ?>">
      </div>
      <div>
        <label for="brand_color">Primary</label>
        <div class="color-row" data-color-pair data-color-role="primary">
          <input type="color" id="brand_color" name="brand_color" value="<?= h(parse_hex_color($brand['brand_color'] ?? '', '#82B440')) ?>" data-color-picker>
          <input type="text" maxlength="7" value="<?= h(parse_hex_color($brand['brand_color'] ?? '', '#82B440')) ?>" data-color-hex>
        </div>
      </div>
      <div>
        <label for="brand_accent">Accent</label>
        <div class="color-row" data-color-pair data-color-role="accent">
          <input type="color" id="brand_accent" name="brand_accent" value="<?= h(parse_hex_color($brand['brand_accent'] ?? '', '#C6A15B')) ?>" data-color-picker>
          <input type="text" maxlength="7" value="<?= h(parse_hex_color($brand['brand_accent'] ?? '', '#C6A15B')) ?>" data-color-hex>
        </div>
      </div>
    </div>
    <label for="logo">Logo</label>
    <input id="logo" name="logo" type="file" accept="image/*">
    <?php if (!empty($brand['logo_path'])): ?>
      <div class="logo-preview"><img src="<?= h(logo_url($brand)) ?>" alt=""></div>
    <?php endif; ?>
    <label for="payment_note">Payment note</label>
    <textarea id="payment_note" name="payment_note" rows="3"><?= h((string) ($brand['payment_note'] ?? '')) ?></textarea>
    <label for="invoice_comments">Invoice comments</label>
    <textarea id="invoice_comments" name="invoice_comments" rows="4"><?= h((string) ($brand['invoice_comments'] ?? '')) ?></textarea>
    <p class="hint">Only invoices. Receipts use the receipt comments below.</p>
    <label for="receipt_comments">Receipt comments</label>
    <textarea id="receipt_comments" name="receipt_comments" rows="3"><?= h((string) ($brand['receipt_comments'] ?? '')) ?></textarea>
    <div class="actions" style="margin-top:16px">
      <button class="btn" type="submit"><?= icon('check') ?>Save stationery</button>
    </div>
  </div>
</form>

<div class="card form-wide" style="margin-top:16px" id="reset">
  <div class="card-head"><h2><?= icon('alert', 16) ?>Training reset</h2></div>
  <div style="padding:0 22px 8px">
    <p class="lede">After a training run, clear practice data so this desk is as good as new for official books. Logins, branding, mailbox and paid term stay. Tick what to delete.</p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="action" value="reset_training">
      <label class="check" style="margin-bottom:10px"><input type="checkbox" name="reset_all" value="1" data-reset-all> Everything listed below</label>
      <div class="form-grid">
        <?php foreach ($resetScopes as $key => $label): ?>
          <label class="check"><input type="checkbox" name="reset_scope[]" value="<?= h($key) ?>" data-reset-scope> <?= h($label) ?></label>
        <?php endforeach; ?>
      </div>
      <p class="hint">Clients also clears documents, because sheets hang off client records.</p>
      <label for="reset_confirm">Type <?= h($company['name']) ?> to confirm</label>
      <input id="reset_confirm" name="reset_confirm" required autocomplete="off" placeholder="<?= h($company['name']) ?>">
      <div class="actions" style="margin:12px 0 8px">
        <button class="btn danger" type="submit" onclick="return confirm('Clear the selected practice data on <?= h($company['name']) ?>? This cannot be undone except by restoring a backup.');"><?= icon('alert', 16) ?>Reset selected data</button>
      </div>
    </form>
  </div>
</div>

<div class="card form-wide" style="margin-top:16px" id="backup">
  <div class="card-head"><h2><?= icon('file', 16) ?>Backup restore</h2></div>
  <div style="padding:0 22px 22px">
    <p class="lede">If they saved a Vellisys backup before the reset, upload it here to put documents, clients and stock back.</p>
    <?php if ($deskBackups): ?>
      <div class="table-scroll" style="margin-bottom:16px">
        <table class="grid">
          <thead><tr><th>File</th><th>When</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($deskBackups as $bfile): ?>
              <tr>
                <td class="mono"><?= h($bfile['file']) ?></td>
                <td><?= h(date('j M Y H:i', $bfile['mtime'])) ?></td>
                <td><a class="btn ghost sm" href="<?= h(url('admin_company.php?id=' . $id . '&backup=' . rawurlencode($bfile['file']))) ?>">Download</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="hint">No automatic backups on file for this desk yet.</p>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="action" value="restore_backup">
      <label for="backup_file">Upload backup</label>
      <input id="backup_file" name="backup" type="file" accept=".gz,.json,application/gzip" required>
      <label class="check" style="margin-top:10px"><input type="checkbox" name="restore_branding" value="1"> Also restore letterhead fields from the file</label>
      <p class="hint">Restores clients, stock and documents. Stationery stays unless you tick the box.</p>
      <div class="actions" style="margin-top:12px">
        <button class="btn" type="submit"><?= icon('check') ?>Restore backup</button>
      </div>
    </form>
  </div>
</div>

<div class="card" style="margin-top:16px" id="vellisys-mail">
  <div class="card-head">
    <h2><?= icon('letter', 16) ?>Letters from <?= h(product_email()) ?></h2>
    <a class="btn ghost sm" href="<?= h(url('admin_mail.php')) ?>">All platform mail</a>
  </div>
  <?php if (!$deskEmails): ?>
    <p class="empty">No letters from <?= h(product_email()) ?> to this desk yet.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="grid">
        <thead><tr><th>When</th><th>To</th><th>Subject</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($deskEmails as $row): ?>
            <tr>
              <td class="mono"><?= h(substr((string) $row['created_at'], 0, 16)) ?></td>
              <td class="mono"><?= h($row['to_email']) ?></td>
              <td><?= h($row['subject']) ?></td>
              <td><span class="pill<?= $row['status'] === 'queued' ? ' warn' : '' ?>"><?= h($row['status']) ?></span></td>
              <td><a class="btn ghost sm" href="<?= h(url('admin_mail.php?id=' . (int) $row['id'])) ?>">View</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card form-wide" style="margin-top:16px" id="delete-company">
  <div class="card-head"><h2><?= icon('trash', 16) ?>Delete company</h2></div>
  <div style="padding:0 22px 22px">
    <p class="lede">Remove this desk from Vellisys. Logins, books, stock, branding and the assigned mailbox leave with it. Super-admin fee history stays. This cannot be undone.</p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="action" value="delete_company">
      <label for="delete_confirm">Type <?= h($company['name']) ?> to confirm</label>
      <input id="delete_confirm" name="delete_confirm" required autocomplete="off" placeholder="<?= h($company['name']) ?>">
      <div class="actions" style="margin:12px 0 0">
        <button class="btn danger" type="submit" onclick="return confirm('Delete this company permanently? The desk and its logins cannot be recovered.');"><?= icon('trash', 16) ?>Delete company</button>
      </div>
    </form>
  </div>
</div>
<?php
$featDefaults = json_encode([
    'books' => desk_feature_defaults('books'),
    'sales' => desk_feature_defaults('sales'),
], JSON_UNESCAPED_UNICODE);
layout_end('<script>window.vellisysFeatureDefaults=' . $featDefaults . ';</script><script src="' . h(asset('js/people-access.js')) . '"></script><script>
(function () {
  var form = document.querySelector("[data-admin-location]");
  if (!form) return;
  var country = form.querySelector("[data-loc-country]");
  var ug = form.querySelector("[data-loc-ug]");
  var other = form.querySelector("[data-loc-other]");
  var otherCountry = form.querySelector("[data-loc-country-other]");
  function sync() {
    var v = country ? country.value : "";
    var isUg = v === "Uganda" || v === "";
    if (ug) ug.hidden = !isUg;
    if (other) other.hidden = isUg;
    if (otherCountry) otherCountry.hidden = v !== "other";
  }
  if (country) country.addEventListener("change", sync);
  sync();
})();
(function () {
  var all = document.querySelector("[data-reset-all]");
  var boxes = document.querySelectorAll("[data-reset-scope]");
  if (!all || !boxes.length) return;
  all.addEventListener("change", function () {
    boxes.forEach(function (b) { b.checked = all.checked; });
  });
})();
</script>');
?>
