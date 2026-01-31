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

DELIMITER ;
