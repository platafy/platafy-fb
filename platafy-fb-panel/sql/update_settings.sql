-- ============================================================
-- PLATAFY FB - Migração de Banco de Dados: Configurações
-- Execute este script no phpMyAdmin se o seu banco já foi criado
-- ============================================================

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
