-- Schema bootstrap for modules 1-3

CREATE TABLE IF NOT EXISTS tenants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
  default_context VARCHAR(64) NULL,
  metadata LONGTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tenants_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_api_keys (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  public_id VARCHAR(48) NOT NULL UNIQUE,
  label VARCHAR(80) NULL,
  key_hash CHAR(64) NOT NULL,
  salt CHAR(32) NOT NULL,
  scopes_json LONGTEXT NOT NULL,
  last_used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  CONSTRAINT fk_tenant_api_keys_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
    ON DELETE CASCADE,
  INDEX idx_tenant_api_keys_tenant (tenant_id),
  INDEX idx_tenant_api_keys_revoked (revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  description TEXT NULL,
  billing_period ENUM('monthly', 'annual') NOT NULL DEFAULT 'monthly',
  currency CHAR(3) NOT NULL DEFAULT 'EUR',
  price_cents INT UNSIGNED NOT NULL,
  setup_fee_cents INT UNSIGNED NOT NULL DEFAULT 0,
  trial_days INT UNSIGNED NOT NULL DEFAULT 0,
  modules_json LONGTEXT NULL,
  features_json LONGTEXT NULL,
  metadata LONGTEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  archived_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_subscription_plans_period (billing_period),
  INDEX idx_subscription_plans_active (is_active),
  INDEX idx_subscription_plans_archived (archived_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_create_tenant$$
CREATE PROCEDURE sp_create_tenant(
  IN p_name VARCHAR(120),
  IN p_slug VARCHAR(120),
  IN p_default_context VARCHAR(64),
  IN p_metadata LONGTEXT
)
BEGIN
  INSERT INTO tenants (name, slug, default_context, metadata)
  VALUES (p_name, p_slug, p_default_context, JSON_EXTRACT(p_metadata, '$'));

  SELECT * FROM tenants WHERE id = LAST_INSERT_ID();
END$$

DROP PROCEDURE IF EXISTS sp_create_tenant_api_key$$
CREATE PROCEDURE sp_create_tenant_api_key(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_public_id VARCHAR(48),
  IN p_label VARCHAR(80),
  IN p_key_hash CHAR(64),
  IN p_salt CHAR(32),
  IN p_scopes_json LONGTEXT
)
BEGIN
  INSERT INTO tenant_api_keys (tenant_id, public_id, label, key_hash, salt, scopes_json)
  VALUES (p_tenant_id, p_public_id, p_label, p_key_hash, p_salt, JSON_EXTRACT(p_scopes_json, '$'));

  SELECT * FROM tenant_api_keys WHERE id = LAST_INSERT_ID();
END$$

DROP PROCEDURE IF EXISTS sp_revoke_api_key$$
CREATE PROCEDURE sp_revoke_api_key(
  IN p_key_id BIGINT UNSIGNED
)
BEGIN
  UPDATE tenant_api_keys
  SET revoked_at = NOW()
  WHERE id = p_key_id AND revoked_at IS NULL;

  SELECT * FROM tenant_api_keys WHERE id = p_key_id;
END$$

DROP PROCEDURE IF EXISTS sp_lookup_api_key$$
CREATE PROCEDURE sp_lookup_api_key(
  IN p_public_id VARCHAR(48)
)
BEGIN
  UPDATE tenant_api_keys
  SET last_used_at = NOW()
  WHERE public_id = p_public_id AND revoked_at IS NULL
  LIMIT 1;

  SELECT k.*, t.status AS tenant_status
  FROM tenant_api_keys k
  INNER JOIN tenants t ON t.id = k.tenant_id
  WHERE k.public_id = p_public_id
  LIMIT 1;
END$$

DELIMITER ;

CREATE TABLE IF NOT EXISTS tenant_rate_limit_windows (
  tenant_id BIGINT UNSIGNED NOT NULL,
  window_start DATETIME NOT NULL,
  window_seconds INT UNSIGNED NOT NULL DEFAULT 60,
  request_count INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (tenant_id, window_start, window_seconds),
  CONSTRAINT fk_rate_limit_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
    ON DELETE CASCADE,
  INDEX idx_rate_limit_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_check_rate_limit$$
CREATE PROCEDURE sp_check_rate_limit(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_window_seconds INT,
  IN p_limit INT
)
rate_limit:BEGIN
  DECLARE v_window_seconds INT;
  DECLARE v_window_start DATETIME;
  DECLARE v_reset_ts BIGINT;
  DECLARE v_updated INT DEFAULT 0;
  DECLARE v_current_count INT DEFAULT 0;
  DECLARE v_limit INT;

  SET v_limit = IF(p_limit IS NULL OR p_limit < 1, 1, p_limit);
  SET v_window_seconds = IF(p_window_seconds IS NULL OR p_window_seconds < 1, 60, p_window_seconds);
  SET v_window_start = FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP() / v_window_seconds) * v_window_seconds);
  SET v_reset_ts = UNIX_TIMESTAMP(v_window_start) + v_window_seconds;

  DELETE FROM tenant_rate_limit_windows
  WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 DAY);

  INSERT IGNORE INTO tenant_rate_limit_windows (tenant_id, window_start, window_seconds, request_count, updated_at)
  VALUES (p_tenant_id, v_window_start, v_window_seconds, 0, NOW());

  UPDATE tenant_rate_limit_windows
  SET request_count = request_count + 1,
      updated_at = NOW()
  WHERE tenant_id = p_tenant_id
    AND window_start = v_window_start
    AND window_seconds = v_window_seconds
    AND request_count < v_limit;

  SET v_updated = ROW_COUNT();

  SELECT request_count INTO v_current_count
  FROM tenant_rate_limit_windows
  WHERE tenant_id = p_tenant_id
    AND window_start = v_window_start
    AND window_seconds = v_window_seconds
  LIMIT 1;

  IF v_updated = 0 THEN
    SELECT 0 AS allowed, 0 AS remaining, v_reset_ts AS reset_at;
    LEAVE rate_limit;
  END IF;

  SELECT 1 AS allowed, GREATEST(v_limit - v_current_count, 0) AS remaining, v_reset_ts AS reset_at;
END$$

DELIMITER ;

CREATE TABLE IF NOT EXISTS requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  customer_name VARCHAR(120) NOT NULL,
  customer_phone VARCHAR(32) NULL,
  customer_email VARCHAR(160) NULL,
  source VARCHAR(32) NOT NULL DEFAULT 'manual',
  channel VARCHAR(32) NOT NULL DEFAULT 'web',
  status ENUM('new', 'pending', 'confirmed', 'done', 'cancelled') NOT NULL DEFAULT 'new',
  preferred_date DATE NULL,
  preferred_time VARCHAR(16) NULL,
  notes TEXT NULL,
  metadata LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_requests_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
    ON DELETE CASCADE,
  INDEX idx_requests_tenant_status (tenant_id, status),
  INDEX idx_requests_tenant_created (tenant_id, created_at),
  INDEX idx_requests_tenant_preferred (tenant_id, preferred_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS request_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  request_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  payload LONGTEXT NULL,
  created_by VARCHAR(60) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_request_events_request FOREIGN KEY (request_id) REFERENCES requests (id)
    ON DELETE CASCADE,
  INDEX idx_request_events_req (tenant_id, request_id, created_at),
  INDEX idx_request_events_type (tenant_id, event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_insert_request_event$$
CREATE PROCEDURE sp_insert_request_event(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_request_id BIGINT UNSIGNED,
  IN p_event_type VARCHAR(40),
  IN p_payload LONGTEXT,
  IN p_created_by VARCHAR(60)
)
BEGIN
  INSERT INTO request_events (tenant_id, request_id, event_type, payload, created_by)
  VALUES (p_tenant_id, p_request_id, p_event_type, JSON_EXTRACT(p_payload, '$'), p_created_by);

  SELECT * FROM request_events WHERE id = LAST_INSERT_ID();
END$$

DROP PROCEDURE IF EXISTS sp_create_request$$
CREATE PROCEDURE sp_create_request(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_customer_name VARCHAR(120),
  IN p_customer_phone VARCHAR(32),
  IN p_customer_email VARCHAR(160),
  IN p_source VARCHAR(32),
  IN p_channel VARCHAR(32),
  IN p_status VARCHAR(20),
  IN p_preferred_date DATE,
  IN p_preferred_time VARCHAR(16),
  IN p_notes TEXT,
  IN p_metadata LONGTEXT
)
BEGIN
  DECLARE v_request_id BIGINT UNSIGNED;
  DECLARE v_status VARCHAR(20);

  SET v_status = IFNULL(p_status, 'new');

  INSERT INTO requests (
    tenant_id,
    customer_name,
    customer_phone,
    customer_email,
    source,
    channel,
    status,
    preferred_date,
    preferred_time,
    notes,
    metadata
  )
  VALUES (
    p_tenant_id,
    p_customer_name,
    p_customer_phone,
    p_customer_email,
    IFNULL(p_source, 'manual'),
    IFNULL(p_channel, 'web'),
    v_status,
    p_preferred_date,
    p_preferred_time,
    p_notes,
    JSON_EXTRACT(p_metadata, '$')
  );

  SET v_request_id = LAST_INSERT_ID();

  CALL sp_insert_request_event(
    p_tenant_id,
    v_request_id,
    'created',
    JSON_OBJECT('status', v_status, 'channel', IFNULL(p_channel, 'web'), 'source', IFNULL(p_source, 'manual')),
    NULL
  );

  SELECT * FROM requests WHERE id = v_request_id;
END$$

DROP PROCEDURE IF EXISTS sp_get_request_by_id$$
CREATE PROCEDURE sp_get_request_by_id(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_request_id BIGINT UNSIGNED
)
BEGIN
  SELECT *
  FROM requests
  WHERE id = p_request_id AND tenant_id = p_tenant_id
  LIMIT 1;
END$$

DROP PROCEDURE IF EXISTS sp_list_request_events$$
CREATE PROCEDURE sp_list_request_events(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_request_id BIGINT UNSIGNED
)
BEGIN
  SELECT *
  FROM request_events
  WHERE tenant_id = p_tenant_id AND request_id = p_request_id
  ORDER BY created_at DESC;
END$$

DROP PROCEDURE IF EXISTS sp_list_requests$$
CREATE PROCEDURE sp_list_requests(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_status VARCHAR(20),
  IN p_from DATETIME,
  IN p_to DATETIME,
  IN p_query VARCHAR(160),
  IN p_limit INT,
  IN p_offset INT
)
BEGIN
  DECLARE v_limit INT;
  DECLARE v_offset INT;
  SET v_limit = IFNULL(NULLIF(p_limit, 0), 50);
  SET v_limit = LEAST(v_limit, 200);
  SET v_offset = IFNULL(p_offset, 0);

  SELECT *
  FROM requests
  WHERE tenant_id = p_tenant_id
    AND (p_status IS NULL OR status = p_status)
    AND (p_from IS NULL OR created_at >= p_from)
    AND (p_to IS NULL OR created_at <= p_to)
    AND (
      p_query IS NULL
      OR customer_name LIKE CONCAT('%', p_query, '%')
      OR customer_phone LIKE CONCAT('%', p_query, '%')
      OR customer_email LIKE CONCAT('%', p_query, '%')
      OR COALESCE(notes, '') LIKE CONCAT('%', p_query, '%')
    )
  ORDER BY created_at DESC
  LIMIT v_limit OFFSET v_offset;
END$$

DROP PROCEDURE IF EXISTS sp_update_request$$
CREATE PROCEDURE sp_update_request(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_request_id BIGINT UNSIGNED,
  IN p_updates LONGTEXT,
  IN p_actor VARCHAR(60)
)
BEGIN
  DECLARE v_old_status VARCHAR(20);
  DECLARE v_new_status VARCHAR(20);
  DECLARE v_updates JSON;
  DECLARE v_has_notes BOOLEAN DEFAULT FALSE;
  DECLARE v_has_pref_date BOOLEAN DEFAULT FALSE;
  DECLARE v_has_pref_time BOOLEAN DEFAULT FALSE;
  DECLARE v_has_metadata BOOLEAN DEFAULT FALSE;
  DECLARE v_has_status BOOLEAN DEFAULT FALSE;
  DECLARE v_notes TEXT;
  DECLARE v_pref_date DATE;
  DECLARE v_pref_time VARCHAR(16);
  DECLARE v_metadata JSON;

  SET v_updates = JSON_EXTRACT(p_updates, '$');

  SELECT status INTO v_old_status
  FROM requests
  WHERE id = p_request_id AND tenant_id = p_tenant_id
  LIMIT 1;

  IF v_old_status IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'request_not_found';
  END IF;

  SET v_has_status = JSON_CONTAINS_PATH(v_updates, 'one', '$.status');
  SET v_has_notes = JSON_CONTAINS_PATH(v_updates, 'one', '$.notes');
  SET v_has_pref_date = JSON_CONTAINS_PATH(v_updates, 'one', '$.preferred_date');
  SET v_has_pref_time = JSON_CONTAINS_PATH(v_updates, 'one', '$.preferred_time');
  SET v_has_metadata = JSON_CONTAINS_PATH(v_updates, 'one', '$.metadata');

  SET v_new_status = JSON_UNQUOTE(JSON_EXTRACT(v_updates, '$.status'));
  SET v_notes = JSON_UNQUOTE(JSON_EXTRACT(v_updates, '$.notes'));
  SET v_pref_time = JSON_UNQUOTE(JSON_EXTRACT(v_updates, '$.preferred_time'));
  SET v_pref_date = CAST(JSON_UNQUOTE(JSON_EXTRACT(v_updates, '$.preferred_date')) AS DATE);
  SET v_metadata = JSON_EXTRACT(v_updates, '$.metadata');

  UPDATE requests
  SET
    status = IF(v_has_status AND v_new_status IS NOT NULL, v_new_status, status),
    notes = IF(v_has_notes, v_notes, notes),
    preferred_date = IF(v_has_pref_date, v_pref_date, preferred_date),
    preferred_time = IF(v_has_pref_time, v_pref_time, preferred_time),
    metadata = IF(v_has_metadata, v_metadata, metadata),
    updated_at = NOW()
  WHERE id = p_request_id AND tenant_id = p_tenant_id;

  IF v_has_status AND v_new_status IS NOT NULL AND v_new_status <> v_old_status THEN
    CALL sp_insert_request_event(
      p_tenant_id,
      p_request_id,
      'status_changed',
      JSON_OBJECT('from', v_old_status, 'to', v_new_status),
      p_actor
    );
  ELSE
    CALL sp_insert_request_event(
      p_tenant_id,
      p_request_id,
      'updated',
      JSON_OBJECT('fields', JSON_KEYS(v_updates)),
      p_actor
    );
  END IF;

  SELECT * FROM requests WHERE id = p_request_id;
END$$

DROP PROCEDURE IF EXISTS sp_dashboard_summary$$
CREATE PROCEDURE sp_dashboard_summary(
  IN p_tenant_id BIGINT UNSIGNED
)
BEGIN
  SELECT
    COALESCE(SUM(CASE WHEN status IN ('new', 'pending') THEN 1 ELSE 0 END), 0) AS pending_count,
    COALESCE(SUM(CASE WHEN preferred_date = CURDATE() AND status NOT IN ('done', 'cancelled') THEN 1 ELSE 0 END), 0) AS today_count,
    COALESCE(SUM(CASE WHEN preferred_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND status NOT IN ('done', 'cancelled') THEN 1 ELSE 0 END), 0) AS tomorrow_count
  FROM requests
  WHERE tenant_id = p_tenant_id;
END$$

DROP PROCEDURE IF EXISTS sp_dashboard_pending$$
CREATE PROCEDURE sp_dashboard_pending(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_query VARCHAR(160),
  IN p_limit INT
)
BEGIN
  DECLARE v_limit INT DEFAULT 25;
  SET v_limit = IFNULL(NULLIF(p_limit, 0), 25);
  SET v_limit = LEAST(v_limit, 100);

  SELECT *
  FROM requests
  WHERE tenant_id = p_tenant_id
    AND status IN ('new', 'pending')
    AND (
      p_query IS NULL
      OR customer_name LIKE CONCAT('%', p_query, '%')
      OR customer_phone LIKE CONCAT('%', p_query, '%')
    )
  ORDER BY created_at ASC
  LIMIT v_limit;
END$$

DROP PROCEDURE IF EXISTS sp_dashboard_by_date$$
CREATE PROCEDURE sp_dashboard_by_date(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_target_date DATE,
  IN p_query VARCHAR(160),
  IN p_limit INT
)
BEGIN
  DECLARE v_limit INT DEFAULT 25;
  SET v_limit = IFNULL(NULLIF(p_limit, 0), 25);
  SET v_limit = LEAST(v_limit, 100);

  SELECT *
  FROM requests
  WHERE tenant_id = p_tenant_id
    AND preferred_date = p_target_date
    AND status NOT IN ('done', 'cancelled')
    AND (
      p_query IS NULL
      OR customer_name LIKE CONCAT('%', p_query, '%')
      OR customer_phone LIKE CONCAT('%', p_query, '%')
    )
  ORDER BY preferred_time IS NULL, preferred_time ASC, created_at ASC
  LIMIT v_limit;
END$$

DELIMITER ;

-- Billing / Easypay Integration Tables

CREATE TABLE IF NOT EXISTS tenant_subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NOT NULL,
  easypay_subscription_id VARCHAR(64) NULL UNIQUE,
  easypay_frequent_id VARCHAR(64) NULL,
  status ENUM('pending', 'active', 'paused', 'cancelled', 'expired', 'failed') NOT NULL DEFAULT 'pending',
  payment_method ENUM('cc', 'dd', 'mbway', 'multibanco', 'google_pay', 'apple_pay') NOT NULL,
  billing_period ENUM('monthly', 'annual') NOT NULL DEFAULT 'monthly',
  currency CHAR(3) NOT NULL DEFAULT 'EUR',
  amount_cents INT UNSIGNED NOT NULL,
  next_billing_at DATETIME NULL,
  trial_ends_at DATETIME NULL,
  started_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  expires_at DATETIME NULL,
  customer_name VARCHAR(160) NULL,
  customer_email VARCHAR(200) NULL,
  customer_phone VARCHAR(32) NULL,
  customer_fiscal_number VARCHAR(32) NULL,
  sdd_iban VARCHAR(64) NULL,
  sdd_mandate_id VARCHAR(64) NULL,
  metadata LONGTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tenant_subscriptions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT fk_tenant_subscriptions_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans (id) ON DELETE RESTRICT,
  INDEX idx_tenant_subscriptions_tenant (tenant_id),
  INDEX idx_tenant_subscriptions_status (status),
  INDEX idx_tenant_subscriptions_next_billing (next_billing_at),
  INDEX idx_tenant_subscriptions_easypay (easypay_subscription_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_charges (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subscription_id BIGINT UNSIGNED NOT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  easypay_transaction_id VARCHAR(64) NULL UNIQUE,
  easypay_capture_id VARCHAR(64) NULL,
  status ENUM('pending', 'processing', 'paid', 'failed', 'refunded', 'cancelled') NOT NULL DEFAULT 'pending',
  payment_method ENUM('cc', 'dd', 'mbway', 'multibanco', 'google_pay', 'apple_pay') NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'EUR',
  amount_cents INT UNSIGNED NOT NULL,
  paid_at DATETIME NULL,
  failed_at DATETIME NULL,
  failure_reason VARCHAR(255) NULL,
  mb_entity VARCHAR(10) NULL,
  mb_reference VARCHAR(20) NULL,
  mb_expires_at DATETIME NULL,
  metadata LONGTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_subscription_charges_subscription FOREIGN KEY (subscription_id) REFERENCES tenant_subscriptions (id) ON DELETE CASCADE,
  CONSTRAINT fk_subscription_charges_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  INDEX idx_subscription_charges_subscription (subscription_id),
  INDEX idx_subscription_charges_tenant (tenant_id),
  INDEX idx_subscription_charges_status (status),
  INDEX idx_subscription_charges_easypay (easypay_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS easypay_webhooks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(64) NOT NULL,
  easypay_id VARCHAR(64) NULL,
  payload LONGTEXT NOT NULL,
  processed_at DATETIME NULL,
  error_message TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_easypay_webhooks_type (event_type),
  INDEX idx_easypay_webhooks_easypay_id (easypay_id),
  INDEX idx_easypay_webhooks_processed (processed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
