CREATE TABLE IF NOT EXISTS companies (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  status ENUM('onboarding','live','suspended') NOT NULL DEFAULT 'onboarding',
  plan ENUM('starter','sme','office') NOT NULL DEFAULT 'sme',
  notes TEXT,
  paid_term INT UNSIGNED NOT NULL DEFAULT 0,
  paid_unit ENUM('months','years') NOT NULL DEFAULT 'months',
  paid_from DATE NULL,
  expires_at DATE NULL,
  renewal_notice_sent_at DATETIME NULL,
  mail_provider VARCHAR(20) NOT NULL DEFAULT 'hostinger',
  mail_email VARCHAR(190) NOT NULL DEFAULT '',
  mail_password TEXT NULL,
  mail_from_name VARCHAR(160) NOT NULL DEFAULT '',
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
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('platform','member') NOT NULL DEFAULT 'member',
  company_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
  UNIQUE KEY company_id (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS parties (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  kind ENUM('customer','supplier','both') NOT NULL DEFAULT 'customer',
  tin VARCHAR(40) DEFAULT NULL,
  phone VARCHAR(40) DEFAULT NULL,
  email VARCHAR(190) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY company_id (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  kind ENUM('quotation','invoice','receipt','expense','letter') NOT NULL,
  sequence INT UNSIGNED NOT NULL,
  number VARCHAR(64) NOT NULL,
  date DATE NOT NULL,
  due_date DATE DEFAULT NULL,
  party_id INT UNSIGNED NOT NULL,
  vat_rate DECIMAL(6,4) NOT NULL DEFAULT 0,
  notes TEXT,
  subject VARCHAR(255) DEFAULT NULL,
  body TEXT,
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
