<?php
/**
 * PLATAFY FB - Utilitários de Configuração do Sistema
 */
require_once __DIR__ . '/db.php';

function ensureSettingsTable() {
    static $checked = false;
    if ($checked) return;
    try {
        $pdo = db();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS system_settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value TEXT DEFAULT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $checked = true;
    } catch (Exception $e) {
        error_log("[PLATAFY Settings] Table creation error: " . $e->getMessage());
    }
}

function getSetting($key, $default = null) {
    ensureSettingsTable();
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false && $val !== null && $val !== '') ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function setSetting($key, $value) {
    ensureSettingsTable();
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");
        return $stmt->execute([$key, (string)$value]);
    } catch (Exception $e) {
        error_log("[PLATAFY Settings] Set error: " . $e->getMessage());
        return false;
    }
}
