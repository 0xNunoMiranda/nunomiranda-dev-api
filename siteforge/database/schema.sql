-- ═══════════════════════════════════════════════════════════════════════════
-- SCHEMA DO CLIENTE PHP
-- Base de dados local para o tenant
-- ═══════════════════════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS ruben_barbearia
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE ruben_barbearia;

-- ─────────────────────────────────────────────────────────────────────────────
-- CONFIGURAÇÕES LOCAIS
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(100) PRIMARY KEY,
  `value` JSON NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
-- SESSÕES DE ADMIN
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS admin_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_token VARCHAR(64) NOT NULL UNIQUE,
  ip_address VARCHAR(45),
  user_agent TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  INDEX idx_token (session_token),
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
-- PEDIDOS DE SUPORTE
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS support_tickets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_number VARCHAR(20) NOT NULL UNIQUE,
  customer_name VARCHAR(160) NOT NULL,
  customer_email VARCHAR(255) NOT NULL,
  customer_phone VARCHAR(32),
  subject VARCHAR(255) NOT NULL,
  category ENUM('general', 'booking', 'billing', 'technical', 'complaint') DEFAULT 'general',
  priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
  status ENUM('open', 'in_progress', 'waiting_customer', 'resolved', 'closed') DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  resolved_at TIMESTAMP NULL,
  INDEX idx_status (status),
  INDEX idx_email (customer_email),
  INDEX idx_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS support_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT UNSIGNED NOT NULL,
  sender_type ENUM('customer', 'admin', 'bot') NOT NULL,
  sender_name VARCHAR(160),
  message TEXT NOT NULL,
  attachments JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
  INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
-- MARCAÇÕES / BOOKINGS
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_ref VARCHAR(20) NOT NULL UNIQUE,
  customer_name VARCHAR(160) NOT NULL,
  customer_email VARCHAR(255),
  customer_phone VARCHAR(32) NOT NULL,
  service_id INT UNSIGNED,
  service_name VARCHAR(100) NOT NULL,
  staff_id INT UNSIGNED,
  staff_name VARCHAR(100),
  booking_date DATE NOT NULL,
  booking_time TIME NOT NULL,
  duration_minutes INT UNSIGNED DEFAULT 30,
  price_cents INT UNSIGNED DEFAULT 0,
  status ENUM('pending', 'confirmed', 'cancelled', 'completed', 'no_show') DEFAULT 'pending',
  notes TEXT,
  source ENUM('website', 'whatsapp', 'phone', 'walkin', 'bot') DEFAULT 'website',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_date (booking_date),
  INDEX idx_status (status),
  INDEX idx_phone (customer_phone)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
-- SERVIÇOS
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  duration_minutes INT UNSIGNED DEFAULT 30,
  price_cents INT UNSIGNED NOT NULL,
  is_active TINYINT(1) DEFAULT 1,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
-- STAFF / FUNCIONÁRIOS
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS staff (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(255),
  phone VARCHAR(32),
  role VARCHAR(50) DEFAULT 'barber',
  avatar_url VARCHAR(500),
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
-- HORÁRIOS DE FUNCIONAMENTO
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS business_hours (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  day_of_week TINYINT UNSIGNED NOT NULL, -- 0=Sunday, 1=Monday, etc.
  is_open TINYINT(1) DEFAULT 1,
  open_time TIME,
  close_time TIME,
  break_start TIME,
  break_end TIME,
  UNIQUE KEY unique_day (day_of_week)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
-- CONVERSAS DO BOT
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS bot_conversations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  channel ENUM('widget', 'whatsapp') NOT NULL,
  session_id VARCHAR(64) NOT NULL,
  customer_phone VARCHAR(32),
  customer_name VARCHAR(160),
  status ENUM('active', 'ended', 'transferred') DEFAULT 'active',
  context JSON,
  started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ended_at TIMESTAMP NULL,
  INDEX idx_session (session_id),
  INDEX idx_channel (channel),
  INDEX idx_phone (customer_phone)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bot_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT UNSIGNED NOT NULL,
  direction ENUM('inbound', 'outbound') NOT NULL,
  message_type ENUM('text', 'image', 'audio', 'document', 'location', 'button', 'list') DEFAULT 'text',
  content TEXT NOT NULL,
  metadata JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (conversation_id) REFERENCES bot_conversations(id) ON DELETE CASCADE,
  INDEX idx_conversation (conversation_id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
-- DADOS INICIAIS
-- ─────────────────────────────────────────────────────────────────────────────

-- Horários padrão (Segunda a Sábado, 9h-19h)
INSERT INTO business_hours (day_of_week, is_open, open_time, close_time, break_start, break_end) VALUES
  (0, 0, NULL, NULL, NULL, NULL),           -- Domingo fechado
  (1, 1, '09:00', '19:00', '13:00', '14:00'), -- Segunda
  (2, 1, '09:00', '19:00', '13:00', '14:00'), -- Terça
  (3, 1, '09:00', '19:00', '13:00', '14:00'), -- Quarta
  (4, 1, '09:00', '19:00', '13:00', '14:00'), -- Quinta
  (5, 1, '09:00', '19:00', '13:00', '14:00'), -- Sexta
  (6, 1, '09:00', '17:00', NULL, NULL)        -- Sábado
ON DUPLICATE KEY UPDATE day_of_week = day_of_week;

-- Serviços exemplo
INSERT INTO services (name, description, duration_minutes, price_cents, sort_order) VALUES
  ('Corte de Cabelo', 'Corte clássico ou moderno', 30, 1500, 1),
  ('Barba', 'Aparar e desenhar barba', 20, 1000, 2),
  ('Corte + Barba', 'Pacote completo', 45, 2200, 3),
  ('Corte Criança', 'Até 12 anos', 25, 1000, 4)
ON DUPLICATE KEY UPDATE name = name;

-- Staff exemplo
INSERT INTO staff (name, role) VALUES
  ('Ruben', 'owner'),
  ('Miguel', 'barber')
ON DUPLICATE KEY UPDATE name = name;
