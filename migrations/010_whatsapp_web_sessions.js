/**
 * Migration: WhatsApp Web sessions (Baileys)
 *
 * Stores session/auth state in the Node.js (central) database.
 * Conversations/messages are stored in the client (SiteForge) database.
 */

exports.up = async function up(knex) {
  await knex.raw(`
    CREATE TABLE IF NOT EXISTS whatsapp_web_sessions (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      tenant_id BIGINT UNSIGNED NOT NULL,
      license_id BIGINT UNSIGNED NOT NULL UNIQUE,
      site_url VARCHAR(255) NULL,
      connection_state VARCHAR(32) NOT NULL DEFAULT 'disconnected',
      phone_number VARCHAR(32) NULL,
      device_jid VARCHAR(64) NULL,
      auth_state_json LONGTEXT NULL,
      last_qr LONGTEXT NULL,
      last_error TEXT NULL,
      connected_at DATETIME NULL,
      disconnected_at DATETIME NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_whatsapp_web_sessions_tenant (tenant_id),
      CONSTRAINT fk_whatsapp_web_sessions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
      CONSTRAINT fk_whatsapp_web_sessions_license FOREIGN KEY (license_id) REFERENCES client_licenses (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  `);
};

exports.down = async function down(knex) {
  await knex.schema.dropTableIfExists('whatsapp_web_sessions');
};

