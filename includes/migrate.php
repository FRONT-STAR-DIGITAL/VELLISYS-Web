<?php
declare(strict_types=1);

function db_has_column(mysqli $db, string $table, string $column): bool
{
    $t = $db->real_escape_string($table);
    $c = $db->real_escape_string($column);
    $row = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
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

    $db->query("CREATE TABLE IF NOT EXISTS schema_meta (
      k VARCHAR(40) PRIMARY KEY,
      v VARCHAR(40) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $verRow = $db->query("SELECT v FROM schema_meta WHERE k='version'");
    $ver = 0;
    if ($verRow && ($r = $verRow->fetch_assoc())) {
        $ver = (int) $r['v'];
    }
    if ($ver >= 16) {
        $done = true;
        return;
    }

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

    $db->query("REPLACE INTO schema_meta (k, v) VALUES ('version', '16')");
    $done = true;
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
        if (!in_array($c['slot'], ['help_1', 'help_3'], true)) {
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
