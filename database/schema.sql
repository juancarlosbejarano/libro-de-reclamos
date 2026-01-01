-- MariaDB schema for Libro de Reclamaciones

CREATE TABLE IF NOT EXISTS tenants (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(64) NOT NULL,
  name VARCHAR(180) NOT NULL,
  id_type ENUM('ruc','dni') NULL,
  id_number VARCHAR(16) NULL,
  address_full VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tenants_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tenant_domains (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  domain VARCHAR(255) NOT NULL,
  kind ENUM('platform','subdomain','custom') NOT NULL DEFAULT 'custom',
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tenant_domains_domain (domain),
  KEY idx_tenant_domains_tenant (tenant_id),
  CONSTRAINT fk_tenant_domains_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','staff','user','bot') NOT NULL DEFAULT 'user',
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_tenant_email (tenant_id, email),
  KEY idx_users_tenant (tenant_id),
  CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS api_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_api_tokens_hash (token_hash),
  KEY idx_api_tokens_user (user_id),
  KEY idx_api_tokens_tenant (tenant_id),
  CONSTRAINT fk_api_tokens_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_api_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS complaints (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  created_by_user_id INT UNSIGNED NULL,
  customer_name VARCHAR(180) NULL,
  customer_email VARCHAR(255) NULL,
  customer_phone VARCHAR(32) NULL,
  subject VARCHAR(180) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('open','in_progress','closed') NOT NULL DEFAULT 'open',
  chatwoot_conversation_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_complaints_tenant (tenant_id),
  KEY idx_complaints_status (status),
  KEY idx_complaints_customer_email (customer_email),
  CONSTRAINT fk_complaints_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_complaints_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS complaint_responses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  complaint_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  message TEXT NOT NULL,
  email_sent_at DATETIME NULL,
  whatsapp_sent_at DATETIME NULL,
  email_error VARCHAR(255) NULL,
  whatsapp_error VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_responses_complaint (complaint_id),
  KEY idx_responses_tenant (tenant_id),
  CONSTRAINT fk_responses_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_responses_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
  CONSTRAINT fk_responses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS complaint_attachments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  complaint_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_path VARCHAR(500) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_attachments_complaint (complaint_id),
  CONSTRAINT fk_attachments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_attachments_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS domain_provisioning_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  domain VARCHAR(255) NOT NULL,
  action ENUM('alias_create') NOT NULL DEFAULT 'alias_create',
  status ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  last_error TEXT NULL,
  created_at DATETIME NOT NULL,
  processed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_jobs_domain_action (domain, action),
  KEY idx_jobs_status (status),
  KEY idx_jobs_tenant (tenant_id),
  CONSTRAINT fk_jobs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tenant_mail_settings (
  tenant_id INT UNSIGNED NOT NULL,
  driver ENUM('smtp') NOT NULL DEFAULT 'smtp',
  host VARCHAR(255) NOT NULL,
  port INT UNSIGNED NOT NULL DEFAULT 587,
  username VARCHAR(255) NOT NULL,
  password_enc TEXT NOT NULL,
  encryption ENUM('tls','ssl','none') NOT NULL DEFAULT 'tls',
  from_email VARCHAR(255) NOT NULL,
  from_name VARCHAR(180) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (tenant_id),
  CONSTRAINT fk_tenant_mail_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tenant_whatsapp_settings (
  tenant_id INT UNSIGNED NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  chatwoot_base_url VARCHAR(255) NOT NULL,
  account_id INT UNSIGNED NOT NULL,
  inbox_id INT UNSIGNED NOT NULL,
  api_token_enc TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (tenant_id),
  CONSTRAINT fk_tenant_whatsapp_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Platform-level users (system owner/support), not tied to a tenant.
CREATE TABLE IF NOT EXISTS platform_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('owner','support') NOT NULL DEFAULT 'support',
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_platform_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Small key/value store for system status (e.g. cron heartbeats)
CREATE TABLE IF NOT EXISTS system_kv (
  k VARCHAR(64) NOT NULL,
  v TEXT NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (k)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



