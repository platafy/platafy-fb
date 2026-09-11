-- ============================================================
-- PLATAFY FB - Migração White Label (Produção)
-- Execute este script no phpMyAdmin do seu cPanel
-- ============================================================

-- 1. Criação da tabela de Parceiros White Label
CREATE TABLE IF NOT EXISTS partners (
    id INT PRIMARY KEY AUTO_INCREMENT,
    partner_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    brand_name VARCHAR(100) NOT NULL,
    brand_logo_url TEXT DEFAULT NULL,
    support_whatsapp VARCHAR(30) DEFAULT NULL,
    support_url TEXT DEFAULT NULL,
    plan_name VARCHAR(100) DEFAULT 'White Label Pro',
    max_licenses INT DEFAULT 50,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    expires_at TIMESTAMP NULL DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_partner_username (username),
    INDEX idx_partner_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Adicionar coluna partner_id na tabela licenses (se não existir)
SET @dbname = DATABASE();
SET @tablename = "licenses";
SET @columnname = "partner_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE licenses ADD COLUMN partner_id INT DEFAULT NULL AFTER id, ADD INDEX idx_partner_id (partner_id);"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
