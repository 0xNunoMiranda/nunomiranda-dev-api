/**
 * Migration: Client Licenses & Usage Control
 * 
 * Sistema de licenças e controlo de créditos por cliente
 */

exports.up = async function(knex) {
    // Licenças de clientes (chave única por cliente)
    await knex.raw(`
        CREATE TABLE IF NOT EXISTS client_licenses (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            license_key VARCHAR(64) NOT NULL UNIQUE,
            client_name VARCHAR(255) NOT NULL,
            client_email VARCHAR(255),
            
            -- Módulos ativos (JSON com configuração)
            modules JSON NOT NULL,
            
            -- Limites mensais
            ai_messages_limit INT DEFAULT 100,
            ai_messages_used INT DEFAULT 0,
            email_limit INT DEFAULT 500,
            emails_sent INT DEFAULT 0,
            sms_limit INT DEFAULT 100,
            sms_sent INT DEFAULT 0,
            whatsapp_limit INT DEFAULT 500,
            whatsapp_sent INT DEFAULT 0,
            ai_calls_limit INT DEFAULT 50,
            ai_calls_used INT DEFAULT 0,
            
            -- Período de faturação
            billing_cycle_start DATE,
            billing_cycle_end DATE,
            
            -- Estado
            status ENUM('active', 'suspended', 'expired', 'trial') DEFAULT 'trial',
            trial_ends_at DATETIME,
            
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            INDEX idx_license_key (license_key),
            INDEX idx_tenant (tenant_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);

    // Histórico de uso (para analytics)
    await knex.raw(`
        CREATE TABLE IF NOT EXISTS usage_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            license_id BIGINT UNSIGNED NOT NULL,
            usage_type ENUM('ai_message', 'email', 'sms', 'whatsapp', 'ai_call', 'ai_generation') NOT NULL,
            tokens_used INT DEFAULT 0,
            cost_cents INT DEFAULT 0,
            metadata JSON,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (license_id) REFERENCES client_licenses(id) ON DELETE CASCADE,
            INDEX idx_license (license_id),
            INDEX idx_type (usage_type),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);

    // Configurações de módulos por cliente
    await knex.raw(`
        CREATE TABLE IF NOT EXISTS module_configs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            license_id BIGINT UNSIGNED NOT NULL,
            module_name VARCHAR(50) NOT NULL,
            config JSON NOT NULL,
            is_enabled BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (license_id) REFERENCES client_licenses(id) ON DELETE CASCADE,
            UNIQUE KEY unique_license_module (license_id, module_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);

    // Bot conversations (para histórico e contexto)
    await knex.raw(`
        CREATE TABLE IF NOT EXISTS bot_conversations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            license_id BIGINT UNSIGNED NOT NULL,
            session_id VARCHAR(64) NOT NULL,
            channel ENUM('widget', 'whatsapp', 'api') DEFAULT 'widget',
            customer_phone VARCHAR(20),
            customer_name VARCHAR(255),
            context JSON,
            status ENUM('active', 'closed', 'escalated') DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (license_id) REFERENCES client_licenses(id) ON DELETE CASCADE,
            INDEX idx_session (session_id),
            INDEX idx_license_channel (license_id, channel)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);

    // Bot messages
    await knex.raw(`
        CREATE TABLE IF NOT EXISTS bot_messages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            conversation_id BIGINT UNSIGNED NOT NULL,
            role ENUM('user', 'assistant', 'system') NOT NULL,
            content TEXT NOT NULL,
            tokens_used INT DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (conversation_id) REFERENCES bot_conversations(id) ON DELETE CASCADE,
            INDEX idx_conversation (conversation_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);

    // AI Generated Content (sites, textos, etc.)
    await knex.raw(`
        CREATE TABLE IF NOT EXISTS ai_generations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            license_id BIGINT UNSIGNED NOT NULL,
            generation_type ENUM('site', 'text', 'faq', 'product_description', 'email_template') NOT NULL,
            prompt TEXT NOT NULL,
            result LONGTEXT,
            tokens_used INT DEFAULT 0,
            model VARCHAR(50) DEFAULT 'gpt-4o-mini',
            status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (license_id) REFERENCES client_licenses(id) ON DELETE CASCADE,
            INDEX idx_license_type (license_id, generation_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);
};

exports.down = async function(knex) {
    await knex.raw('DROP TABLE IF EXISTS ai_generations');
    await knex.raw('DROP TABLE IF EXISTS bot_messages');
    await knex.raw('DROP TABLE IF EXISTS bot_conversations');
    await knex.raw('DROP TABLE IF EXISTS module_configs');
    await knex.raw('DROP TABLE IF EXISTS usage_logs');
    await knex.raw('DROP TABLE IF EXISTS client_licenses');
};
