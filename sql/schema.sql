CREATE TABLE IF NOT EXISTS companies (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  status ENUM('onboarding','live','suspended') NOT NULL DEFAULT 'onboarding',
  plan ENUM('starter','sme','office') NOT NULL DEFAULT 'sme',
  planner_enabled TINYINT(1) NOT NULL DEFAULT 0,
  pnl_enabled TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT,
  enabled_kinds TEXT NULL,
  custom_doc TEXT NULL,
  paid_term DECIMAL(8,2) NOT NULL DEFAULT 0,
  paid_unit ENUM('weeks','months','years') NOT NULL DEFAULT 'months',
  paid_from DATE NULL,
  expires_at DATE NULL,
  renewal_notice_sent_at DATETIME NULL,
  user_limit TINYINT UNSIGNED NOT NULL DEFAULT 3,
  fee_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  fee_paid DECIMAL(14,2) NOT NULL DEFAULT 0,
  fee_currency CHAR(3) NOT NULL DEFAULT 'UGX',
  mail_provider VARCHAR(20) NOT NULL DEFAULT 'hostinger',
    mail_email VARCHAR(190) NOT NULL DEFAULT '',
  mail_password TEXT NULL,
  mail_from_name VARCHAR(160) NOT NULL DEFAULT '',
  timezone VARCHAR(64) NOT NULL DEFAULT 'Africa/Kampala',
  smtp_host VARCHAR(190) NOT NULL DEFAULT 'smtp.hostinger.com',
  smtp_port INT UNSIGNED NOT NULL DEFAULT 465,
  smtp_secure VARCHAR(10) NOT NULL DEFAULT 'ssl',
  pop_host VARCHAR(190) NOT NULL DEFAULT 'pop.hostinger.com',
  pop_port INT UNSIGNED NOT NULL DEFAULT 995,
  imap_host VARCHAR(190) NOT NULL DEFAULT 'imap.hostinger.com',
  imap_port INT UNSIGNED NOT NULL DEFAULT 993,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  job_title VARCHAR(80) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('platform','admin','member') NOT NULL DEFAULT 'member',
  access VARCHAR(20) NOT NULL DEFAULT 'books',
  company_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  first_login_at DATETIME NULL,
  welcome_pop_seen_at DATETIME NULL,
  KEY company_id (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS branding (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  tagline VARCHAR(180) DEFAULT '',
  tin VARCHAR(40) DEFAULT '',
  vat_no VARCHAR(40) DEFAULT '',
  address VARCHAR(255) DEFAULT '',
  city VARCHAR(120) DEFAULT '',
  phone VARCHAR(40) DEFAULT '',
  email VARCHAR(190) DEFAULT '',
  website VARCHAR(190) DEFAULT '',
  bank_name VARCHAR(120) DEFAULT '',
  account_name VARCHAR(160) DEFAULT '',
  account_number VARCHAR(80) DEFAULT '',
  brand_color VARCHAR(7) NOT NULL DEFAULT '#82B440',
  brand_accent VARCHAR(7) NOT NULL DEFAULT '#C6A15B',
  brand_deep VARCHAR(7) NOT NULL DEFAULT '#1F3A12',
  logo_path VARCHAR(255) DEFAULT 'assets/img/ofagros-logo.png',
  prefix VARCHAR(12) NOT NULL DEFAULT 'OFG',
  payment_note TEXT,
  invoice_comments TEXT,
  plan ENUM('starter','sme','office') NOT NULL DEFAULT 'sme',
  currency CHAR(3) NOT NULL DEFAULT 'UGX',
  fx_ugx_per_usd DECIMAL(12,4) NOT NULL DEFAULT 3700,
  letter_templates TEXT NULL,
  doc_template VARCHAR(40) NOT NULL DEFAULT 'folio',
  tax_name VARCHAR(40) NOT NULL DEFAULT 'VAT',
  tax_rate DECIMAL(8,4) NOT NULL DEFAULT 0.1800,
  UNIQUE KEY company_id (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS parties (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  kind ENUM('customer','supplier','both') NOT NULL DEFAULT 'customer',
  tin VARCHAR(40) DEFAULT NULL,
  phone VARCHAR(40) DEFAULT NULL,
  phone2 VARCHAR(40) DEFAULT NULL,
  email VARCHAR(190) DEFAULT NULL,
  address TEXT DEFAULT NULL,
  city VARCHAR(120) DEFAULT NULL,
  country VARCHAR(80) DEFAULT NULL,
  contact_person VARCHAR(160) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY company_id (company_id),
  KEY party_status (company_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  kind ENUM('quotation','invoice','receipt','expense','letter','delivery','custom','refund','return_note') NOT NULL,
  sequence INT UNSIGNED NOT NULL,
  number VARCHAR(64) NOT NULL,
  date DATE NOT NULL,
  due_date DATE DEFAULT NULL,
  party_id INT UNSIGNED NOT NULL,
  vat_rate DECIMAL(6,4) NOT NULL DEFAULT 0,
  notes TEXT,
  subject VARCHAR(255) DEFAULT NULL,
  body TEXT,
  custom_values TEXT NULL,
  status ENUM('issued','void') NOT NULL DEFAULT 'issued',
  void_reason VARCHAR(255) DEFAULT NULL,
  related_id INT UNSIGNED DEFAULT NULL,
  payment_method VARCHAR(40) DEFAULT NULL,
  payment_ref VARCHAR(80) DEFAULT NULL,
  allocated_amount DECIMAL(16,2) DEFAULT NULL,
  expense_category VARCHAR(80) DEFAULT NULL,
  letter_template VARCHAR(40) DEFAULT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'UGX',
  doc_template VARCHAR(40) DEFAULT NULL,
  efris_fdn VARCHAR(40) DEFAULT NULL,
  efris_verification VARCHAR(16) DEFAULT NULL,
  efris_payload TEXT,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY company_number (company_id, number),
  KEY kind_seq (company_id, kind, sequence),
  KEY company_kind_date (company_id, kind, date),
  KEY party_id (party_id),
  CONSTRAINT fk_doc_party FOREIGN KEY (party_id) REFERENCES parties(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS document_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id INT UNSIGNED NOT NULL,
  item_name VARCHAR(160) NOT NULL DEFAULT '',
  description TEXT NOT NULL,
  qty DECIMAL(12,2) NOT NULL DEFAULT 1,
  unit VARCHAR(30) DEFAULT 'lot',
  rate DECIMAL(16,2) NOT NULL DEFAULT 0,
  taxed TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_item_doc FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS signups (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS landing_cards (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slot VARCHAR(40) NOT NULL UNIQUE,
  section VARCHAR(40) NOT NULL,
  title VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  image_path VARCHAR(255) NOT NULL DEFAULT '',
  sort INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS emails (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id INT UNSIGNED DEFAULT NULL,
  user_id INT UNSIGNED DEFAULT NULL,
  to_email VARCHAR(190) NOT NULL,
  from_email VARCHAR(190) NOT NULL DEFAULT '',
  subject VARCHAR(255) NOT NULL,
  body TEXT,
  status ENUM('sent','queued','failed') NOT NULL DEFAULT 'queued',
  error VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS questions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS trust_clients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  logo_path VARCHAR(255) NOT NULL DEFAULT '',
  sort INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY sort_id (sort, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS landing_reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  role VARCHAR(160) NOT NULL DEFAULT '',
  quote TEXT NOT NULL,
  sort INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY sort_id (sort, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS landing_review_section (
  id TINYINT UNSIGNED PRIMARY KEY,
  kicker VARCHAR(80) NOT NULL DEFAULT '',
  heading VARCHAR(180) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS renewal_notices (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  to_email VARCHAR(190) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body TEXT,
  status ENUM('sent','queued','failed') NOT NULL DEFAULT 'queued',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY company_id (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS website_orders (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS planner_notes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL DEFAULT 0,
  title VARCHAR(190) NOT NULL,
  body TEXT NULL,
  priority ENUM('low','normal','high','essential') NOT NULL DEFAULT 'normal',
  pinned TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY company_id (company_id),
  KEY company_priority (company_id, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS planner_budget_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL DEFAULT 0,
  title VARCHAR(190) NOT NULL,
  category VARCHAR(120) NOT NULL DEFAULT 'General',
  kind ENUM('income','expense') NOT NULL DEFAULT 'expense',
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  month_key CHAR(7) NOT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY company_month (company_id, month_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS planner_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL DEFAULT 0,
  title VARCHAR(190) NOT NULL,
  body TEXT NULL,
  event_date DATE NOT NULL,
  event_time TIME NULL,
  end_date DATE NULL,
  kind ENUM('appointment','deadline','program','reminder','other') NOT NULL DEFAULT 'appointment',
  priority ENUM('low','normal','high','essential') NOT NULL DEFAULT 'normal',
  party_id INT UNSIGNED NULL,
  document_id INT UNSIGNED NULL,
  done TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY company_date (company_id, event_date),
  KEY company_done (company_id, done)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS pnl_entries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL DEFAULT 0,
  entry_date DATE NOT NULL,
  kind ENUM('income','expense') NOT NULL DEFAULT 'expense',
  category VARCHAR(120) NOT NULL DEFAULT 'General',
  title VARCHAR(190) NOT NULL,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  document_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY company_date (company_id, entry_date),
  KEY company_kind (company_id, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
