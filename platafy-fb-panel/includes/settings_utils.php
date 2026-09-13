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
                setting_value MEDIUMTEXT DEFAULT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        @$pdo->exec("ALTER TABLE system_settings MODIFY setting_value MEDIUMTEXT DEFAULT NULL;");
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

/**
 * Retorna a lista de planos padrão do PLATAFY FB
 */
function getDefaultPlans() {
    return [
        'mensal' => [
            'name'           => 'Plano Mensal',
            'subtitle'       => 'Acesso completo por 30 dias',
            'price'          => 39.90,
            'billing_note'   => 'Cobrança mensal • Cancele quando quiser',
            'badge'          => '',
            'badge_style'    => '',
            'frequency'      => 1,
            'frequency_type' => 'months',
            'days'           => 30,
            'features'       => [
                'PLATAFY FB 2026 completo',
                '1 ativação em 1 computador',
                'Atualizações durante o acesso',
                'Ideal para começar investindo menos'
            ],
            'active'         => true
        ],
        'semestral' => [
            'name'           => 'Plano Semestral',
            'subtitle'       => 'Acesso completo por 6 meses',
            'price'          => 69.90,
            'billing_note'   => 'Apenas R$ 11,65 por mês no período',
            'badge'          => 'MAIS POPULAR',
            'badge_style'    => 'orange',
            'frequency'      => 6,
            'frequency_type' => 'months',
            'days'           => 180,
            'features'       => [
                'PLATAFY FB 2026 completo',
                '1 ativação em 1 computador',
                'Atualizações durante os 6 meses',
                'Mais economia que o plano mensal'
            ],
            'active'         => true
        ],
        'vitalicio' => [
            'name'           => 'Plano Vitalício',
            'subtitle'       => 'Acesso completo sem data de expiração',
            'price'          => 149.90,
            'billing_note'   => 'Pagamento único • Sem mensalidade',
            'badge'          => 'MELHOR CUSTO-BENEFÍCIO',
            'badge_style'    => 'green',
            'frequency'      => 1200,
            'frequency_type' => 'months',
            'days'           => 36500,
            'features'       => [
                'PLATAFY FB 2026 completo',
                '2 ativações em computadores diferentes',
                'Atualizações futuras incluídas'
            ],
            'active'         => true
        ]
    ];
}

/**
 * Retorna os planos salvos no banco de dados ou os padrões
 */
function getSystemPlans() {
    try {
        $saved = getSetting('subscription_plans_config');
        if (!empty($saved)) {
            $decoded = json_decode($saved, true);
            if (is_array($decoded) && !empty($decoded)) {
                // Garantir que campos essenciais existam em cada plano
                foreach ($decoded as $k => &$plan) {
                    if (!isset($plan['name'])) $plan['name'] = ucfirst($k);
                    if (!isset($plan['price'])) $plan['price'] = 0.00;
                    if (!isset($plan['days'])) $plan['days'] = 30;
                    if (!isset($plan['active'])) $plan['active'] = true;
                    if (!isset($plan['features']) || !is_array($plan['features'])) {
                        $plan['features'] = [];
                    }
                }
                return $decoded;
            }
        }
    } catch (Exception $e) {
        error_log("[PLATAFY Plans] Error loading plans: " . $e->getMessage());
    }
    return getDefaultPlans();
}

/**
 * Salva a lista de planos personalizados no banco de dados
 */
function saveSystemPlans(array $plans) {
    // Sanitização e validação básica
    $cleaned = [];
    foreach ($plans as $key => $p) {
        $cleanKey = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower(trim($key)));
        if (empty($cleanKey)) continue;

        $features = [];
        if (isset($p['features'])) {
            if (is_array($p['features'])) {
                foreach ($p['features'] as $f) {
                    $item = trim((string)$f);
                    if (!empty($item)) $features[] = $item;
                }
            } elseif (is_string($p['features'])) {
                $lines = explode("\n", str_replace("\r", "", $p['features']));
                foreach ($lines as $line) {
                    $item = trim($line);
                    if (!empty($item)) $features[] = $item;
                }
            }
        }

        $cleaned[$cleanKey] = [
            'name'           => trim((string)($p['name'] ?? ucfirst($cleanKey))),
            'subtitle'       => trim((string)($p['subtitle'] ?? '')),
            'price'          => (float)str_replace(',', '.', (string)($p['price'] ?? 0)),
            'billing_note'   => trim((string)($p['billing_note'] ?? '')),
            'badge'          => trim((string)($p['badge'] ?? '')),
            'badge_style'    => in_array($p['badge_style'] ?? '', ['orange', 'green', 'blue']) ? $p['badge_style'] : 'orange',
            'frequency'      => (int)($p['frequency'] ?? 1),
            'frequency_type' => trim((string)($p['frequency_type'] ?? 'months')),
            'days'           => max(1, (int)($p['days'] ?? 30)),
            'features'       => $features,
            'active'         => !isset($p['active']) || $p['active'] === true || $p['active'] === 'true' || $p['active'] === 1 || $p['active'] === '1'
        ];
    }

    if (empty($cleaned)) {
        return false;
    }

    return setSetting('subscription_plans_config', json_encode($cleaned, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

