<?php
declare(strict_types=1);

function db_has_column(mysqli $db, string $table, string $column): bool
{
    $t = $db->real_escape_string($table);
    $c = $db->real_escape_string($column);
    $row = @$db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return $row && $row->num_rows > 0;
}

function folio_migrate(mysqli $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $tables = $db->query("SHOW TABLES LIKE 'users'");
    if (!$tables || $tables->num_rows === 0) {
        return;
    }
    try {
        folio_ensure_public_tables($db);
    } catch (Throwable $e) {
        error_log('Vellisys public tables: ' . $e->getMessage());
    }
    $verRow = @$db->query("SELECT v FROM schema_meta WHERE k='version'");
    if ($verRow && ($r = $verRow->fetch_assoc()) && (int) $r['v'] >= 35) {
        $done = true;
        return;
    }

    $db->query("CREATE TABLE IF NOT EXISTS schema_meta (
      k VARCHAR(40) PRIMARY KEY,
      v VARCHAR(40) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $verRow = $db->query("SELECT v FROM schema_meta WHERE k='version'");
    $ver = 0;
    if ($verRow && ($r = $verRow->fetch_assoc())) {
        $ver = (int) $r['v'];
    }
    if ($ver >= 35) {
        $done = true;
        return;
    }

    if ($ver < 16) {
    $db->query("CREATE TABLE IF NOT EXISTS companies (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(160) NOT NULL,
      status ENUM('onboarding','live','suspended') NOT NULL DEFAULT 'onboarding',
      plan ENUM('starter','sme','office') NOT NULL DEFAULT 'sme',
      notes TEXT,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!db_has_column($db, 'users', 'role')) {
        $db->query("ALTER TABLE users ADD COLUMN role ENUM('platform','member') NOT NULL DEFAULT 'member'");
    }
    if (!db_has_column($db, 'users', 'company_id')) {
        $db->query("ALTER TABLE users ADD COLUMN company_id INT UNSIGNED NULL");
    }
    if (!db_has_column($db, 'branding', 'company_id')) {
        $db->query("ALTER TABLE branding ADD COLUMN company_id INT UNSIGNED NULL");
    }
    if (!db_has_column($db, 'parties', 'company_id')) {
        $db->query("ALTER TABLE parties ADD COLUMN company_id INT UNSIGNED NULL");
    }
    if (!db_has_column($db, 'documents', 'company_id')) {
        $db->query("ALTER TABLE documents ADD COLUMN company_id INT UNSIGNED NULL");
    }
    if (!db_has_column($db, 'documents', 'letter_template')) {
        $db->query("ALTER TABLE documents ADD COLUMN letter_template VARCHAR(40) NULL");
    }
    if (!db_has_column($db, 'branding', 'currency')) {
        $db->query("ALTER TABLE branding ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'UGX'");
    }
    if (!db_has_column($db, 'documents', 'currency')) {
        $db->query("ALTER TABLE documents ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'UGX'");
    }

    $itemRate = $db->query("SHOW COLUMNS FROM document_items LIKE 'rate'");
    $itemCol = $itemRate ? $itemRate->fetch_assoc() : null;
    if ($itemCol && (stripos((string) $itemCol['Type'], 'bigint') !== false || stripos((string) $itemCol['Type'], 'int') !== false)) {
        $db->query('ALTER TABLE document_items MODIFY rate DECIMAL(16,2) NOT NULL DEFAULT 0');
    }
    $itemQty = $db->query("SHOW COLUMNS FROM document_items LIKE 'qty'");
    $qtyCol = $itemQty ? $itemQty->fetch_assoc() : null;
    if ($qtyCol && (stripos((string) $qtyCol['Type'], 'int') !== false || stripos((string) $qtyCol['Type'], 'decimal(10') !== false)) {
        $db->query('ALTER TABLE document_items MODIFY qty DECIMAL(12,2) NOT NULL DEFAULT 1');
    }
    $alloc = $db->query("SHOW COLUMNS FROM documents LIKE 'allocated_amount'");
    $allocCol = $alloc ? $alloc->fetch_assoc() : null;
    if ($allocCol && (stripos((string) $allocCol['Type'], 'bigint') !== false || stripos((string) $allocCol['Type'], 'int') !== false)) {
        $db->query('ALTER TABLE documents MODIFY allocated_amount DECIMAL(16,2) NULL');
    }

    $brandId = $db->query("SHOW COLUMNS FROM branding LIKE 'id'");
    $brandCol = $brandId ? $brandId->fetch_assoc() : null;
    if ($brandCol && stripos((string) $brandCol['Type'], 'tinyint') !== false) {
        $db->query('ALTER TABLE branding MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT');
    }

    $co = $db->query('SELECT COUNT(*) c FROM companies')->fetch_assoc();
    if ((int) $co['c'] === 0) {
        $db->query("INSERT INTO companies (name, status, plan) VALUES ('Ofagros Limited', 'live', 'sme')");
    }
    $cid = (int) $db->query('SELECT id FROM companies ORDER BY id LIMIT 1')->fetch_assoc()['id'];
    $db->query("UPDATE users SET company_id = {$cid} WHERE role = 'member' AND company_id IS NULL");
    $db->query("UPDATE branding SET company_id = {$cid} WHERE company_id IS NULL");
    $db->query("UPDATE parties SET company_id = {$cid} WHERE company_id IS NULL");
    $db->query("UPDATE documents SET company_id = {$cid} WHERE company_id IS NULL");

    $brandCoIdx = $db->query("SHOW INDEX FROM branding WHERE Key_name = 'company_id'");
    if (!$brandCoIdx || $brandCoIdx->num_rows === 0) {
        $db->query('ALTER TABLE branding ADD UNIQUE KEY company_id (company_id)');
    }

    $idx = $db->query("SHOW INDEX FROM documents WHERE Key_name = 'number'");
    if ($idx && $idx->num_rows > 0) {
        $db->query('ALTER TABLE documents DROP INDEX number');
    }
    $idx2 = $db->query("SHOW INDEX FROM documents WHERE Key_name = 'company_number'");
    if (!$idx2 || $idx2->num_rows === 0) {
        $db->query('ALTER TABLE documents ADD UNIQUE KEY company_number (company_id, number)');
    }
    $idx3 = $db->query("SHOW INDEX FROM documents WHERE Key_name = 'company_kind_date'");
    if (!$idx3 || $idx3->num_rows === 0) {
        $db->query('ALTER TABLE documents ADD KEY company_kind_date (company_id, kind, date)');
    }
    if (!db_has_column($db, 'branding', 'letter_templates')) {
        $db->query('ALTER TABLE branding ADD COLUMN letter_templates TEXT NULL');
    }
    if (!db_has_column($db, 'branding', 'doc_template')) {
        $db->query("ALTER TABLE branding ADD COLUMN doc_template VARCHAR(40) NOT NULL DEFAULT 'folio'");
    }
    if (!db_has_column($db, 'documents', 'doc_template')) {
        $db->query('ALTER TABLE documents ADD COLUMN doc_template VARCHAR(40) NULL');
    }
    if (!db_has_column($db, 'branding', 'brand_accent')) {
        $db->query("ALTER TABLE branding ADD COLUMN brand_accent VARCHAR(7) NOT NULL DEFAULT '#C6A15B'");
    }
    if (!db_has_column($db, 'branding', 'brand_deep')) {
        $db->query("ALTER TABLE branding ADD COLUMN brand_deep VARCHAR(7) NOT NULL DEFAULT '#1F3A12'");
    }

    if (!db_has_column($db, 'document_items', 'item_name')) {
        $db->query("ALTER TABLE document_items ADD COLUMN item_name VARCHAR(160) NOT NULL DEFAULT '' AFTER document_id");
    }
    $descCol = $db->query("SHOW COLUMNS FROM document_items LIKE 'description'");
    $descInfo = $descCol ? $descCol->fetch_assoc() : null;
    if ($descInfo && stripos((string) $descInfo['Type'], 'text') === false) {
        $db->query('ALTER TABLE document_items MODIFY description TEXT NOT NULL');
    }
    if (!db_has_column($db, 'branding', 'fx_ugx_per_usd')) {
        $db->query('ALTER TABLE branding ADD COLUMN fx_ugx_per_usd DECIMAL(12,4) NOT NULL DEFAULT 3700');
    }

    $db->query("CREATE TABLE IF NOT EXISTS signups (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(160) NOT NULL,
      company VARCHAR(160) NOT NULL,
      email VARCHAR(190) NOT NULL,
      phone VARCHAR(40) NOT NULL DEFAULT '',
      status ENUM('new','contacted','onboarded','declined') NOT NULL DEFAULT 'new',
      company_id INT UNSIGNED NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY status_created (status, created_at),
      KEY email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    folio_ensure_platform_admin($db);

    folio_migrate_landing_cards($db);
    folio_refresh_landing_copy($db);

    $db->query("CREATE TABLE IF NOT EXISTS questions (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(120) NOT NULL,
      email VARCHAR(190) NOT NULL,
      phone VARCHAR(40) NOT NULL DEFAULT '',
      message TEXT NOT NULL,
      ip_hash CHAR(64) NOT NULL DEFAULT '',
      status ENUM('new','read','replied') NOT NULL DEFAULT 'new',
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY status_created (status, created_at),
      KEY email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    folio_migrate_trust_clients($db);
    folio_migrate_subscriptions($db);
    }

    if ($ver < 20) {
        folio_migrate_mailboxes($db);
        folio_migrate_fees($db);
        folio_migrate_email_from($db);
        folio_refresh_landing_copy($db);
    }

    folio_migrate_landing_reviews($db);
    if ($ver < 22) {
        folio_migrate_desk_kinds($db);
    }
    if ($ver < 23) {
        folio_migrate_signup_source($db);
    }
    if ($ver < 24) {
        folio_migrate_landing_ticker($db);
    }
    if ($ver < 25) {
        folio_migrate_desk_users($db);
    }
    if ($ver < 26) {
        folio_migrate_website_orders($db);
    }
    if ($ver < 27) {
        folio_migrate_landing_pricing($db);
    }
    if ($ver < 28) {
        folio_migrate_order_country($db);
    }
    if ($ver < 29) {
        folio_migrate_order_pending_mail($db);
    }
    if ($ver < 30) {
        folio_migrate_landing_testimonials($db);
    }
    if ($ver < 31) {
        folio_migrate_positioning_ticker($db);
    }
    if ($ver < 32) {
        $db->query("UPDATE landing_pricing SET term_label = 'per year' WHERE LOWER(TRIM(term_label)) = 'first year'");
        $db->query("UPDATE landing_pricing SET lead = REPLACE(lead, 'First year,', 'Billed per year,')");
    }
    if ($ver < 33) {
        folio_migrate_form_indexes($db);
    }
    if ($ver < 34) {
        folio_migrate_onboard_steps($db);
    }
    if ($ver < 35) {
        folio_migrate_self_onboard($db);
        folio_migrate_site_visits($db);
    }

    $db->query("REPLACE INTO schema_meta (k, v) VALUES ('version', '35')");
    $done = true;
}

function folio_migrate_onboard_steps(mysqli $db): void
{
    if (!db_has_column($db, 'companies', 'onboard_steps')) {
        $db->query('ALTER TABLE companies ADD COLUMN onboard_steps TEXT NULL');
    }
}

function folio_migrate_self_onboard(mysqli $db): void
{
    if (!db_has_column($db, 'website_orders', 'company_id')) {
        $db->query('ALTER TABLE website_orders ADD COLUMN company_id INT UNSIGNED NULL');
    }
    if (!db_has_column($db, 'website_orders', 'onboard_token')) {
        $db->query('ALTER TABLE website_orders ADD COLUMN onboard_token VARCHAR(64) NULL');
    }
    if (!db_has_column($db, 'users', 'first_login_at')) {
        $db->query('ALTER TABLE users ADD COLUMN first_login_at DATETIME NULL');
        $db->query("UPDATE users SET first_login_at = created_at WHERE first_login_at IS NULL AND role <> 'platform'");
    }
    $db->query("UPDATE landing_pricing SET register_label = 'Ask a question', register_copy = 'Prefer a call first? {register}', lead = REPLACE(lead, 'Pay, then a Vellisys admin contacts you to open the desk.', 'Pay, then set your admin email and password and finish branding on Settings.') WHERE id > 0");
}

function folio_migrate_site_visits(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS site_visits (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      occurred_at DATETIME NOT NULL,
      path VARCHAR(190) NOT NULL DEFAULT '',
      page_kind VARCHAR(20) NOT NULL DEFAULT 'other',
      country CHAR(2) NOT NULL DEFAULT '',
      country_name VARCHAR(80) NOT NULL DEFAULT '',
      is_app TINYINT UNSIGNED NOT NULL DEFAULT 0,
      device VARCHAR(20) NOT NULL DEFAULT 'desktop',
      ip_hash CHAR(40) NOT NULL DEFAULT '',
      KEY occurred_at (occurred_at),
      KEY page_kind (page_kind),
      KEY country (country)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function folio_migrate_form_indexes(mysqli $db): void
{
    $add = static function (mysqli $db, string $table, string $name, string $ddl): void {
        $exists = @$db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
        if (!$exists || $exists->num_rows === 0) {
            return;
        }
        $idx = @$db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '" . $db->real_escape_string($name) . "'");
        if ($idx && $idx->num_rows === 0) {
            $db->query($ddl);
        }
    };
    $add($db, 'questions', 'ip_hash_created', 'ALTER TABLE questions ADD KEY ip_hash_created (ip_hash, created_at)');
    $add($db, 'signups', 'email_status', 'ALTER TABLE signups ADD KEY email_status (email, status)');
    $add($db, 'website_orders', 'email_created', 'ALTER TABLE website_orders ADD KEY email_created (email, created_at)');
}

function folio_migrate_desk_users(mysqli $db): void
{
    if (!db_has_column($db, 'companies', 'user_limit')) {
        $db->query('ALTER TABLE companies ADD COLUMN user_limit TINYINT UNSIGNED NOT NULL DEFAULT 3');
    }
    $db->query('UPDATE companies SET user_limit = 3 WHERE user_limit IS NULL OR user_limit < 1 OR user_limit > 3');
    if (!db_has_column($db, 'users', 'job_title')) {
        $db->query("ALTER TABLE users ADD COLUMN job_title VARCHAR(80) NOT NULL DEFAULT '' AFTER name");
    }
    if (!db_has_column($db, 'users', 'access')) {
        $db->query("ALTER TABLE users ADD COLUMN access VARCHAR(20) NOT NULL DEFAULT 'books' AFTER role");
    }
    $db->query("ALTER TABLE users MODIFY role ENUM('platform','admin','member') NOT NULL DEFAULT 'member'");
    $companies = $db->query('SELECT id FROM companies');
    if ($companies) {
        while ($c = $companies->fetch_assoc()) {
            $cid = (int) $c['id'];
            $first = $db->query('SELECT id FROM users WHERE company_id = ' . $cid . " AND role <> 'platform' ORDER BY id ASC LIMIT 1");
            $row = $first ? $first->fetch_assoc() : null;
            if ($row) {
                $uid = (int) $row['id'];
                $db->query("UPDATE users SET role = 'admin', access = 'admin' WHERE id = " . $uid);
            }
            $db->query("UPDATE users SET access = 'books' WHERE company_id = " . $cid . " AND role = 'member' AND (access = '' OR access = 'admin')");
        }
    }
}

function folio_migrate_landing_ticker(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS landing_ticker (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      body VARCHAR(220) NOT NULL,
      sort INT NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY sort_id (sort, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $count = $db->query('SELECT COUNT(*) AS c FROM landing_ticker');
    $n = $count ? (int) ($count->fetch_assoc()['c'] ?? 0) : 0;
    if ($n > 0) {
        return;
    }
    foreach (landing_ticker_defaults() as $c) {
        $stmt = $db->prepare('INSERT INTO landing_ticker (body, sort) VALUES (?,?)');
        $stmt->bind_param('si', $c['body'], $c['sort']);
        $stmt->execute();
        $stmt->close();
    }
}

function folio_ensure_public_tables(mysqli $db): void
{
    try {
        if (!db_has_column($db, 'users', 'job_title')) {
            $db->query("ALTER TABLE users ADD COLUMN job_title VARCHAR(80) NOT NULL DEFAULT '' AFTER name");
        }
        if (!db_has_column($db, 'users', 'access')) {
            $db->query("ALTER TABLE users ADD COLUMN access VARCHAR(20) NOT NULL DEFAULT 'books' AFTER role");
        }
    } catch (Throwable $e) {
        error_log('Vellisys users columns: ' . $e->getMessage());
    }
    try {
        if (!db_has_column($db, 'companies', 'user_limit')) {
            $db->query('ALTER TABLE companies ADD COLUMN user_limit TINYINT UNSIGNED NOT NULL DEFAULT 3');
        }
    } catch (Throwable $e) {
        error_log('Vellisys companies columns: ' . $e->getMessage());
    }
    try {
        folio_migrate_website_orders($db);
        folio_migrate_order_country($db);
        folio_migrate_order_pending_mail($db);
    } catch (Throwable $e) {
        error_log('Vellisys website_orders: ' . $e->getMessage());
    }

    $db->query("CREATE TABLE IF NOT EXISTS signups (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(160) NOT NULL,
      company VARCHAR(160) NOT NULL,
      email VARCHAR(190) NOT NULL,
      phone VARCHAR(40) NOT NULL DEFAULT '',
      status ENUM('new','contacted','onboarded','declined') NOT NULL DEFAULT 'new',
      source VARCHAR(20) NOT NULL DEFAULT 'register',
      note TEXT NULL,
      company_id INT UNSIGNED NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY status_created (status, created_at),
      KEY email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    folio_migrate_signup_source($db);

    $db->query("CREATE TABLE IF NOT EXISTS questions (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(120) NOT NULL,
      email VARCHAR(190) NOT NULL,
      phone VARCHAR(40) NOT NULL DEFAULT '',
      message TEXT NOT NULL,
      ip_hash CHAR(64) NOT NULL DEFAULT '',
      status ENUM('new','read','replied') NOT NULL DEFAULT 'new',
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY status_created (status, created_at),
      KEY email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try {
        folio_migrate_landing_pricing($db);
    } catch (Throwable $e) {
        error_log('Vellisys landing pricing: ' . $e->getMessage());
    }
    try {
        $db->query("UPDATE landing_cards SET title = 'We call you', body = 'A Vellisys admin sees the sign-up and reaches out to onboard the company.' WHERE slot = 'steps_2' AND title = 'Request a quote'");
    } catch (Throwable $e) {
        error_log('Vellisys landing steps: ' . $e->getMessage());
    }
}

function folio_migrate_signup_source(mysqli $db): void
{
    if (!db_has_column($db, 'signups', 'source')) {
        $db->query("ALTER TABLE signups ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'register'");
    }
    if (!db_has_column($db, 'signups', 'note')) {
        $db->query('ALTER TABLE signups ADD COLUMN note TEXT NULL');
    }
}

function folio_migrate_desk_kinds(mysqli $db): void
{
    if (!db_has_column($db, 'companies', 'enabled_kinds')) {
        $db->query("ALTER TABLE companies ADD COLUMN enabled_kinds TEXT NULL");
    }
    if (!db_has_column($db, 'companies', 'custom_doc')) {
        $db->query("ALTER TABLE companies ADD COLUMN custom_doc TEXT NULL");
    }
    $db->query("ALTER TABLE documents MODIFY kind ENUM('quotation','invoice','receipt','expense','letter','delivery','custom') NOT NULL");
    if (!db_has_column($db, 'documents', 'custom_values')) {
        $db->query("ALTER TABLE documents ADD COLUMN custom_values TEXT NULL");
    }
    if (!db_has_column($db, 'parties', 'contact_person')) {
        $db->query("ALTER TABLE parties ADD COLUMN contact_person VARCHAR(160) NULL");
    }
    if (!db_has_column($db, 'parties', 'phone2')) {
        $db->query("ALTER TABLE parties ADD COLUMN phone2 VARCHAR(40) NULL");
    }
    if (!db_has_column($db, 'parties', 'city')) {
        $db->query("ALTER TABLE parties ADD COLUMN city VARCHAR(120) NULL");
    }
    if (!db_has_column($db, 'parties', 'country')) {
        $db->query("ALTER TABLE parties ADD COLUMN country VARCHAR(80) NULL");
    }
    if (!db_has_column($db, 'parties', 'notes')) {
        $db->query("ALTER TABLE parties ADD COLUMN notes TEXT NULL");
    }
    $addr = $db->query("SHOW COLUMNS FROM parties LIKE 'address'");
    $col = $addr ? $addr->fetch_assoc() : null;
    if ($col && stripos((string) ($col['Type'] ?? ''), 'varchar') !== false) {
        $db->query('ALTER TABLE parties MODIFY address TEXT NULL');
    }
    $defaults = json_encode(['quotation', 'invoice', 'receipt', 'letter']);
    $db->query("UPDATE companies SET enabled_kinds = '" . $db->real_escape_string($defaults) . "' WHERE enabled_kinds IS NULL OR enabled_kinds = ''");
}

function folio_migrate_email_from(mysqli $db): void
{
    if (!db_has_column($db, 'emails', 'from_email')) {
        $db->query("ALTER TABLE emails ADD COLUMN from_email VARCHAR(190) NOT NULL DEFAULT '' AFTER to_email");
    }
}

function folio_migrate_subscriptions(mysqli $db): void
{
    if (!db_has_column($db, 'companies', 'paid_term')) {
        $db->query('ALTER TABLE companies ADD COLUMN paid_term INT UNSIGNED NOT NULL DEFAULT 0');
    }
    if (!db_has_column($db, 'companies', 'paid_unit')) {
        $db->query("ALTER TABLE companies ADD COLUMN paid_unit ENUM('months','years') NOT NULL DEFAULT 'months'");
    }
    if (!db_has_column($db, 'companies', 'paid_from')) {
        $db->query('ALTER TABLE companies ADD COLUMN paid_from DATE NULL');
    }
    if (!db_has_column($db, 'companies', 'expires_at')) {
        $db->query('ALTER TABLE companies ADD COLUMN expires_at DATE NULL');
    }
    if (!db_has_column($db, 'companies', 'renewal_notice_sent_at')) {
        $db->query('ALTER TABLE companies ADD COLUMN renewal_notice_sent_at DATETIME NULL');
    }
    $db->query("CREATE TABLE IF NOT EXISTS renewal_notices (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      company_id INT UNSIGNED NOT NULL,
      to_email VARCHAR(190) NOT NULL,
      subject VARCHAR(255) NOT NULL,
      body TEXT,
      status ENUM('sent','queued','failed') NOT NULL DEFAULT 'queued',
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY company_id (company_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function folio_migrate_fees(mysqli $db): void
{
    if (!db_has_column($db, 'companies', 'fee_amount')) {
        $db->query('ALTER TABLE companies ADD COLUMN fee_amount DECIMAL(14,2) NOT NULL DEFAULT 0');
    }
    if (!db_has_column($db, 'companies', 'fee_paid')) {
        $db->query('ALTER TABLE companies ADD COLUMN fee_paid DECIMAL(14,2) NOT NULL DEFAULT 0');
    }
    if (!db_has_column($db, 'companies', 'fee_currency')) {
        $db->query("ALTER TABLE companies ADD COLUMN fee_currency CHAR(3) NOT NULL DEFAULT 'UGX'");
    }
    $db->query("UPDATE companies SET fee_amount = 450000, fee_paid = 450000, fee_currency = 'UGX' WHERE name = 'Ofagros Limited' AND fee_amount = 0 AND fee_paid = 0");
}

function folio_migrate_mailboxes(mysqli $db): void
{
    $cols = [
        'mail_provider' => "VARCHAR(20) NOT NULL DEFAULT 'hostinger'",
        'mail_email' => "VARCHAR(190) NOT NULL DEFAULT ''",
        'mail_password' => 'TEXT NULL',
        'mail_from_name' => "VARCHAR(160) NOT NULL DEFAULT ''",
        'smtp_host' => "VARCHAR(190) NOT NULL DEFAULT 'smtp.hostinger.com'",
        'smtp_port' => 'INT UNSIGNED NOT NULL DEFAULT 465',
        'smtp_secure' => "VARCHAR(10) NOT NULL DEFAULT 'ssl'",
        'pop_host' => "VARCHAR(190) NOT NULL DEFAULT 'pop.hostinger.com'",
        'pop_port' => 'INT UNSIGNED NOT NULL DEFAULT 995',
        'imap_host' => "VARCHAR(190) NOT NULL DEFAULT 'imap.hostinger.com'",
        'imap_port' => 'INT UNSIGNED NOT NULL DEFAULT 993',
    ];
    foreach ($cols as $col => $ddl) {
        if (!db_has_column($db, 'companies', $col)) {
            $db->query("ALTER TABLE companies ADD COLUMN {$col} {$ddl}");
        }
    }
}

function folio_migrate_landing_cards(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS landing_cards (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      slot VARCHAR(40) NOT NULL UNIQUE,
      section VARCHAR(40) NOT NULL,
      title VARCHAR(180) NOT NULL,
      body TEXT NOT NULL,
      image_path VARCHAR(255) NOT NULL DEFAULT '',
      sort INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    foreach (landing_card_defaults() as $c) {
        $slot = $db->real_escape_string($c['slot']);
        $found = $db->query("SELECT id FROM landing_cards WHERE slot = '$slot' LIMIT 1");
        if ($found && $found->num_rows > 0) {
            continue;
        }
        $stmt = $db->prepare('INSERT INTO landing_cards (slot, section, title, body, image_path, sort) VALUES (?,?,?,?,?,?)');
        $stmt->bind_param('sssssi', $c['slot'], $c['section'], $c['title'], $c['body'], $c['image_path'], $c['sort']);
        $stmt->execute();
    }
}

function folio_refresh_landing_copy(mysqli $db): void
{
    foreach (landing_card_defaults() as $c) {
        if (!in_array($c['slot'], ['help_1', 'help_3', 'familiar_1', 'help_2', 'steps_1', 'steps_2', 'steps_3'], true)) {
            continue;
        }
        $stmt = $db->prepare('UPDATE landing_cards SET title = ?, body = ? WHERE slot = ?');
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('sss', $c['title'], $c['body'], $c['slot']);
        $stmt->execute();
    }
}

function folio_migrate_landing_reviews(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS landing_reviews (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(120) NOT NULL,
      role VARCHAR(160) NOT NULL DEFAULT '',
      quote TEXT NOT NULL,
      sort INT NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY sort_id (sort, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $count = $db->query('SELECT COUNT(*) AS c FROM landing_reviews');
    $n = $count ? (int) ($count->fetch_assoc()['c'] ?? 0) : 0;
    if ($n > 0) {
        return;
    }
    foreach (landing_review_defaults() as $c) {
        $stmt = $db->prepare('INSERT INTO landing_reviews (name, role, quote, sort) VALUES (?,?,?,?)');
        $stmt->bind_param('sssi', $c['name'], $c['role'], $c['quote'], $c['sort']);
        $stmt->execute();
        $stmt->close();
    }
}

function folio_migrate_trust_clients(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS trust_clients (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(160) NOT NULL,
      logo_path VARCHAR(255) NOT NULL DEFAULT '',
      sort INT NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY sort_id (sort, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $count = $db->query('SELECT COUNT(*) AS c FROM trust_clients');
    $n = $count ? (int) ($count->fetch_assoc()['c'] ?? 0) : 0;
    if ($n > 0) {
        return;
    }
    foreach (trust_client_defaults() as $c) {
        $stmt = $db->prepare('INSERT INTO trust_clients (name, logo_path, sort) VALUES (?,?,?)');
        $stmt->bind_param('ssi', $c['name'], $c['logo_path'], $c['sort']);
        $stmt->execute();
        $stmt->close();
    }
}

function folio_ensure_platform_admin(mysqli $db): void
{
    $email = 'admin@vellisys.ug';
    $hash = password_hash('vellisys-admin-2026', PASSWORD_DEFAULT);
    $found = $db->query("SELECT id FROM users WHERE email = '" . $db->real_escape_string($email) . "' LIMIT 1");
    if ($found && $found->num_rows > 0) {
        $id = (int) $found->fetch_assoc()['id'];
        $stmt = $db->prepare('UPDATE users SET name = ?, password_hash = ?, role = ?, company_id = NULL WHERE id = ?');
        $n = 'Vellisys Admin';
        $role = 'platform';
        $stmt->bind_param('sssi', $n, $hash, $role, $id);
        $stmt->execute();
    } else {
        $stmt = $db->prepare('INSERT INTO users (name, email, password_hash, role, company_id) VALUES (?,?,?,?,NULL)');
        $n = 'Vellisys Admin';
        $role = 'platform';
        $stmt->bind_param('ssss', $n, $email, $hash, $role);
        $stmt->execute();
    }
    $db->query("UPDATE users SET name = 'Vellisys Admin' WHERE role = 'platform' AND email = 'admin@folio.ug'");
}

function folio_migrate_website_orders(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS website_orders (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      public_id CHAR(16) NOT NULL,
      merchant_ref VARCHAR(50) NOT NULL,
      plan VARCHAR(20) NOT NULL,
      currency CHAR(3) NOT NULL DEFAULT 'UGX',
      amount DECIMAL(14,2) NOT NULL DEFAULT 0,
      amount_ugx DECIMAL(14,2) NOT NULL DEFAULT 0,
      name VARCHAR(160) NOT NULL DEFAULT '',
      company VARCHAR(160) NOT NULL DEFAULT '',
      email VARCHAR(190) NOT NULL DEFAULT '',
      phone VARCHAR(40) NOT NULL DEFAULT '',
      city VARCHAR(120) NOT NULL DEFAULT '',
      country VARCHAR(80) NOT NULL DEFAULT '',
      status ENUM('draft','pending','paid','failed','cancelled') NOT NULL DEFAULT 'draft',
      pesapal_tracking VARCHAR(80) NOT NULL DEFAULT '',
      pesapal_redirect TEXT NULL,
      signup_id INT UNSIGNED NULL,
      notified_draft TINYINT UNSIGNED NOT NULL DEFAULT 0,
      notified_pending TINYINT UNSIGNED NOT NULL DEFAULT 0,
      last_error TEXT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY public_id (public_id),
      UNIQUE KEY merchant_ref (merchant_ref),
      KEY status_created (status, created_at),
      KEY email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function folio_migrate_landing_pricing(mysqli $db): void
{
    require_once ROOT_PATH . '/includes/pricing.php';
    $db->query("CREATE TABLE IF NOT EXISTS landing_packages (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      pkg_key VARCHAR(20) NOT NULL,
      name VARCHAR(80) NOT NULL,
      kicker VARCHAR(80) NOT NULL DEFAULT '',
      ribbon VARCHAR(80) NOT NULL DEFAULT '',
      seats TINYINT UNSIGNED NOT NULL DEFAULT 1,
      price_ugx DECIMAL(14,2) NOT NULL DEFAULT 0,
      was_ugx DECIMAL(14,2) NOT NULL DEFAULT 0,
      cta VARCHAR(80) NOT NULL DEFAULT '',
      lead TEXT NOT NULL,
      points TEXT NOT NULL,
      popular TINYINT UNSIGNED NOT NULL DEFAULT 0,
      sort INT NOT NULL DEFAULT 0,
      UNIQUE KEY pkg_key (pkg_key),
      KEY sort_id (sort, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS landing_pricing (
      id TINYINT UNSIGNED PRIMARY KEY,
      kicker VARCHAR(80) NOT NULL DEFAULT '',
      heading VARCHAR(180) NOT NULL DEFAULT '',
      lead TEXT NOT NULL,
      clock_label VARCHAR(80) NOT NULL DEFAULT '',
      term_label VARCHAR(40) NOT NULL DEFAULT '',
      register_copy TEXT NOT NULL,
      register_label VARCHAR(80) NOT NULL DEFAULT '',
      countdown_days TINYINT UNSIGNED NOT NULL DEFAULT 3,
      countdown_hours TINYINT UNSIGNED NOT NULL DEFAULT 12,
      rates_json TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $count = $db->query('SELECT COUNT(*) AS c FROM landing_packages');
    $n = $count ? (int) $count->fetch_assoc()['c'] : 0;
    if ($n === 0) {
        $stmt = $db->prepare('INSERT INTO landing_packages (pkg_key, name, kicker, ribbon, seats, price_ugx, was_ugx, cta, lead, points, popular, sort) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach (pricing_package_defaults() as $pkg) {
            $key = (string) $pkg['key'];
            $name = (string) $pkg['name'];
            $kicker = (string) ($pkg['kicker'] ?? '');
            $ribbon = (string) ($pkg['ribbon'] ?? '');
            $seats = (int) $pkg['seats'];
            $price = (float) $pkg['price_ugx'];
            $was = (float) $pkg['was_ugx'];
            $cta = (string) $pkg['cta'];
            $lead = (string) $pkg['lead'];
            $points = implode("\n", $pkg['points']);
            $popular = !empty($pkg['popular']) ? 1 : 0;
            $sort = (int) $pkg['sort'];
            $stmt->bind_param('ssssiddsssii', $key, $name, $kicker, $ribbon, $seats, $price, $was, $cta, $lead, $points, $popular, $sort);
            $stmt->execute();
        }
    }

    $exists = $db->query('SELECT id FROM landing_pricing WHERE id = 1');
    if (!$exists || $exists->num_rows === 0) {
        $s = pricing_section_defaults();
        $rates = $s['rates'];
        unset($rates['UGX']);
        $json = json_encode($rates);
        $stmt = $db->prepare('INSERT INTO landing_pricing (id, kicker, heading, lead, clock_label, term_label, register_copy, register_label, countdown_days, countdown_hours, rates_json) VALUES (1,?,?,?,?,?,?,?,?,?,?)');
        $kicker = $s['kicker'];
        $heading = $s['heading'];
        $lead = $s['lead'];
        $clock = $s['clock_label'];
        $term = $s['term_label'];
        $reg = $s['register_copy'];
        $regLabel = $s['register_label'];
        $days = (int) $s['countdown_days'];
        $hours = (int) $s['countdown_hours'];
        $stmt->bind_param('sssssssiis', $kicker, $heading, $lead, $clock, $term, $reg, $regLabel, $days, $hours, $json);
        $stmt->execute();
    }
}

function folio_migrate_order_country(mysqli $db): void
{
    if (!db_has_column($db, 'website_orders', 'country')) {
        $db->query("ALTER TABLE website_orders ADD COLUMN country VARCHAR(80) NOT NULL DEFAULT '' AFTER city");
    }
}

function folio_migrate_order_pending_mail(mysqli $db): void
{
    if (!db_has_column($db, 'website_orders', 'notified_pending')) {
        $db->query("ALTER TABLE website_orders ADD COLUMN notified_pending TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER notified_draft");
    }
}

function folio_migrate_landing_testimonials(mysqli $db): void
{
    require_once ROOT_PATH . '/includes/helpers.php';
    $db->query("CREATE TABLE IF NOT EXISTS landing_review_section (
      id TINYINT UNSIGNED PRIMARY KEY,
      kicker VARCHAR(80) NOT NULL DEFAULT '',
      heading VARCHAR(180) NOT NULL DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $exists = $db->query('SELECT id FROM landing_review_section WHERE id = 1');
    if (!$exists || $exists->num_rows === 0) {
        $s = landing_review_section_defaults();
        $stmt = $db->prepare('INSERT INTO landing_review_section (id, kicker, heading) VALUES (1,?,?)');
        $kicker = $s['kicker'];
        $heading = $s['heading'];
        $stmt->bind_param('ss', $kicker, $heading);
        $stmt->execute();
    }
}

function folio_migrate_positioning_ticker(mysqli $db): void
{
    require_once ROOT_PATH . '/includes/helpers.php';
    folio_migrate_landing_ticker($db);
    $lines = [
        ['body' => 'Built for East Africa. Used across Africa and worldwide.', 'sort' => 30],
        ['body' => 'The branded alternative to QuickBooks and other finance software.', 'sort' => 40],
    ];
    foreach ($lines as $c) {
        $stmt = $db->prepare('SELECT id FROM landing_ticker WHERE body = ? LIMIT 1');
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('s', $c['body']);
        $stmt->execute();
        $exists = $stmt->get_result();
        if ($exists && $exists->num_rows > 0) {
            continue;
        }
        $ins = $db->prepare('INSERT INTO landing_ticker (body, sort) VALUES (?,?)');
        if (!$ins) {
            continue;
        }
        $ins->bind_param('si', $c['body'], $c['sort']);
        $ins->execute();
    }
}
