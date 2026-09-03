-- ============================================================
-- PLATAFY FB - Schema do Banco de Dados
-- Execute este SQL no phpMyAdmin do seu cPanel
-- ============================================================

CREATE TABLE IF NOT EXISTS admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserir admin padrão (senha: platafy2024)
INSERT INTO admins (username, password_hash) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON DUPLICATE KEY UPDATE username = username;

CREATE TABLE IF NOT EXISTS licenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    license_key VARCHAR(19) UNIQUE NOT NULL,
    client_name VARCHAR(100) DEFAULT NULL,
    client_email VARCHAR(150) DEFAULT NULL,
    client_phone VARCHAR(30) DEFAULT NULL,
    plan_type ENUM('mensal','trimestral','semestral','anual','manual','vitalicio') DEFAULT 'manual',
    status ENUM('active','inactive','expired','revoked','pending') DEFAULT 'inactive',
    hwid VARCHAR(100) DEFAULT NULL,
    activated_at TIMESTAMP NULL DEFAULT NULL,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    last_check_at TIMESTAMP NULL DEFAULT NULL,
    last_ip VARCHAR(45) DEFAULT NULL,
    mp_subscription_id VARCHAR(100) DEFAULT NULL,
    mp_payer_email VARCHAR(150) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_license_key (license_key),
    INDEX idx_status (status),
    INDEX idx_mp_subscription (mp_subscription_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    license_id INT DEFAULT NULL,
    mp_payment_id VARCHAR(100) DEFAULT NULL,
    mp_subscription_id VARCHAR(100) DEFAULT NULL,
    amount DECIMAL(10,2) DEFAULT 0.00,
    status VARCHAR(30) DEFAULT 'pending',
    payer_email VARCHAR(150) DEFAULT NULL,
    payment_method VARCHAR(50) DEFAULT NULL,
    payment_date TIMESTAMP NULL DEFAULT NULL,
    raw_data TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE SET NULL,
    INDEX idx_mp_payment (mp_payment_id),
    INDEX idx_payment_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    license_id INT DEFAULT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE SET NULL,
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
