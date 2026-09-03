<?php
/**
 * PLATAFY FB - Configurações do Sistema
 * Domínio: platafyfb.platafy.com
 */

// ============================================================
// BANCO DE DADOS MySQL (Configure com seus dados do cPanel)
// ============================================================
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'platafy_fb');            // Nome do banco criado no cPanel
define('DB_USER', getenv('DB_USER') ?: 'platafy_admin');         // Usuário MySQL do cPanel
define('DB_PASS', getenv('DB_PASS') ?: 'SUA_SENHA_AQUI');       // Senha MySQL do cPanel

// ============================================================
// SEGURANÇA
// ============================================================
define('API_KEY', 'PFFB2C8D5A1E7F9B3G6');   // Deve ser a mesma do background.js
define('SESSION_SECRET', 'platafy_fb_session_2026_' . md5(__FILE__));
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', password_hash('platafy2024', PASSWORD_BCRYPT));

// ============================================================
// MERCADO PAGO
// ============================================================
define('MP_ACCESS_TOKEN', 'SEU_ACCESS_TOKEN_AQUI');        // Token de Produção
define('MP_ACCESS_TOKEN_TEST', 'SEU_TEST_TOKEN_AQUI');     // Token de Teste
define('MP_USE_TEST', true);                                // true = modo teste, false = produção

// ============================================================
// PLANOS DE ASSINATURA
// ============================================================
define('PLANS', json_encode([
    'mensal' => [
        'name' => 'Plano Mensal',
        'price' => 39.90,
        'frequency' => 1,
        'frequency_type' => 'months',
        'days' => 30
    ],
    'semestral' => [
        'name' => 'Plano Semestral',
        'price' => 69.90,
        'frequency' => 6,
        'frequency_type' => 'months',
        'days' => 180
    ],
    'vitalicio' => [
        'name' => 'Plano Vitalício',
        'price' => 149.90,
        'frequency' => 1200,
        'frequency_type' => 'months',
        'days' => 36500
    ]
]));

// ============================================================
// URLs
// ============================================================
define('SITE_URL', getenv('SITE_URL') ?: 'https://fb.platafy.com');
define('WEBHOOK_URL', SITE_URL . '/api/webhook_mp.php');
define('CHECKOUT_BACK_URL', SITE_URL . '/checkout/obrigado.php');

// ============================================================
// E-MAIL (SMTP via cPanel - configure se necessário)
// ============================================================
define('SMTP_ENABLED', false);
define('SMTP_HOST', 'mail.platafy.com');
define('SMTP_USER', 'noreply@platafy.com');
define('SMTP_PASS', 'SUA_SENHA_SMTP');
define('SMTP_PORT', 587);
define('FROM_EMAIL', 'noreply@platafy.com');
define('FROM_NAME', 'PLATAFY FB');

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Error reporting (desativar em produção)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
