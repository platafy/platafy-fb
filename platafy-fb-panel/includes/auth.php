<?php
require_once __DIR__ . '/../config.php';

/**
 * PLATAFY FB - Autenticação & Módulo de Segurança Avançado
 */

/**
 * Detecta se a conexão atual é HTTPS (direta ou via proxies reversos como Cloudflare, Traefik, Nginx)
 */
function isHttpsConnection() {
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') return true;
    if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) return true;
    return false;
}

/**
 * Envia cabeçalhos HTTP defensivos essenciais
 */
function sendSecurityHeaders() {
    if (!headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}

/**
 * Inicia sessão segura com cookies HTTPOnly, SameSite e Secure (se HTTPS)
 */
function startSecureSession() {
    sendSecurityHeaders();
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.gc_maxlifetime', 28800); // 8 horas
        session_name('PLATAFY_SESSION');
        
        $isSecure = isHttpsConnection();
        if (!headers_sent()) {
            if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
                session_set_cookie_params([
                    'lifetime' => 28800,
                    'path' => '/',
                    'httponly' => true,
                    'secure' => $isSecure,
                    'samesite' => 'Lax'
                ]);
            } else {
                @session_set_cookie_params(28800, '/', '', $isSecure, true);
            }
        }
        
        @session_start();
    }
}

/**
 * Gera ou recupera Token CSRF para proteção de formulários
 */
function getCsrfToken() {
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida o Token CSRF de submissões
 */
function verifyCsrfToken($token) {
    startSecureSession();
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Recupera o IP real do cliente com suporte a proxies confiáveis e Cloudflare
 */
function getClientIp() {
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR'
    ];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ips = explode(',', $_SERVER[$h]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Garante a criação da tabela de auditoria e rate limiting de tentativas de login
 */
function ensureLoginAttemptsTable() {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        require_once __DIR__ . '/db.php';
        $pdo = db();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                username VARCHAR(100) NOT NULL,
                attempt_time INT NOT NULL,
                success TINYINT(1) DEFAULT 0,
                INDEX idx_ip_time (ip_address, attempt_time),
                INDEX idx_user_time (username, attempt_time)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Exception $e) {
        // Fallback resiliente se o banco estiver em manutenção
    }
}

/**
 * Checa Rate Limiting por IP para impedir ataques de força bruta
 * Regras:
 * - >= 10 falhas em 15 minutos: Bloqueio do IP com tempo restante
 * - >= 5 falhas em 15 minutos: Delay artificial progressivo (3 segundos)
 */
function checkRateLimit($ip) {
    ensureLoginAttemptsTable();
    $timeWindow = 900; // 15 minutos em segundos
    $currentTime = time();
    $since = $currentTime - $timeWindow;
    
    try {
        require_once __DIR__ . '/db.php';
        $pdo = db();
        
        // Limpeza ocasional de registros velhos (> 48 horas)
        if (mt_rand(1, 25) === 1) {
            $old = $currentTime - 172800;
            $pdo->exec("DELETE FROM login_attempts WHERE attempt_time < {$old}");
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as failed_count, MIN(attempt_time) as oldest_time
            FROM login_attempts
            WHERE ip_address = ? AND success = 0 AND attempt_time >= ?
        ");
        $stmt->execute([$ip, $since]);
        $row = $stmt->fetch();
        
        $failedCount = (int)($row['failed_count'] ?? 0);
        
        if ($failedCount >= 10) {
            $oldest = (int)($row['oldest_time'] ?? $since);
            $remainingSeconds = max(60, $timeWindow - ($currentTime - $oldest));
            $remainingMinutes = max(1, ceil($remainingSeconds / 60));
            return [
                'blocked' => true,
                'message' => "Muitas tentativas incorretas. Seu IP foi temporariamente bloqueado por {$remainingMinutes} minuto(s) por segurança.",
                'delay' => 0
            ];
        }
        
        if ($failedCount >= 5) {
            return [
                'blocked' => false,
                'message' => '',
                'delay' => 3
            ];
        }
    } catch (Exception $e) {
        // Silêncio em caso de fallback
    }
    
    return ['blocked' => false, 'message' => '', 'delay' => 0];
}

/**
 * Registra a tentativa de login no banco e limpa erros se for sucesso
 */
function recordLoginAttempt($ip, $username, $success = false) {
    ensureLoginAttemptsTable();
    try {
        require_once __DIR__ . '/db.php';
        $pdo = db();
        $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, username, attempt_time, success) VALUES (?, ?, ?, ?)");
        $stmt->execute([$ip, $username, time(), $success ? 1 : 0]);
        
        // Limpar tentativas falhas anteriores quando o login for bem-sucedido
        if ($success) {
            $stmtClear = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND success = 0");
            $stmtClear->execute([$ip]);
        }
    } catch (Exception $e) {
        // Silêncio em caso de exceção
    }
}

function loginAdmin($username, $password) {
    // 1. Tentar autenticar pela tabela admins no Banco de Dados (se existir)
    try {
        require_once __DIR__ . '/db.php';
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password_hash'])) {
            startSecureSession();
            @session_regenerate_id(true); // Neutraliza ataques de Fixação de Sessão!
            $_SESSION['admin_logged'] = true;
            $_SESSION['admin_user'] = $username;
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            return true;
        }
    } catch (Exception $e) {
        // Ignora erro de BD se a tabela ainda não existir e usa fallback
    }

    // 2. Fallback para as constantes definidas no config.php
    if ($username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD_HASH)) {
        startSecureSession();
        @session_regenerate_id(true); // Neutraliza ataques de Fixação de Sessão!
        $_SESSION['admin_logged'] = true;
        $_SESSION['admin_user'] = $username;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        return true;
    }
    return false;
}

function isAdminLogged() {
    startSecureSession();
    if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
        return false;
    }
    
    // Expira por inatividade após 8 horas (28800 segundos)
    $lastActivity = $_SESSION['last_activity'] ?? $_SESSION['login_time'] ?? 0;
    if ($lastActivity > 0 && (time() - $lastActivity) > 28800) {
        logoutAdmin();
        return false;
    }

    // Atualiza tempo de atividade para manter a sessão ativa enquanto usa o painel
    $_SESSION['last_activity'] = time();
    return true;
}

function requireAdmin() {
    if (!isAdminLogged()) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isApi = (strpos($uri, '/api/') !== false) || 
                 (strpos($uri, 'api/') !== false) || 
                 (strpos($accept, 'application/json') !== false);

        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json');
            die(json_encode([
                'error' => 'Não autorizado',
                'message' => 'Sua sessão expirou ou você não possui permissão. Por favor, faça login novamente.',
                'auth_required' => true
            ]));
        }
        header('Location: /index.php');
        exit;
    }
}

function logoutAdmin() {
    startSecureSession();
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    @session_destroy();
}

/**
 * Autenticação de Parceiro White Label
 */
function loginPartner($username, $password) {
    try {
        require_once __DIR__ . '/db.php';
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM partners WHERE username = ?");
        $stmt->execute([$username]);
        $partner = $stmt->fetch();
        if ($partner && password_verify($password, $partner['password_hash'])) {
            if ($partner['status'] !== 'active') {
                return ['success' => false, 'message' => 'Conta de parceiro inativa ou suspensa. Contate o suporte.'];
            }
            if (!empty($partner['expires_at']) && strtotime($partner['expires_at']) < time()) {
                return ['success' => false, 'message' => 'Sua assinatura White Label expirou. Renove seu plano.'];
            }
            startSecureSession();
            $_SESSION['partner_logged'] = true;
            $_SESSION['partner_id'] = (int)$partner['id'];
            $_SESSION['partner_user'] = $partner['username'];
            $_SESSION['partner_name'] = $partner['partner_name'];
            $_SESSION['partner_brand'] = $partner['brand_name'];
            $_SESSION['partner_logo'] = $partner['brand_logo_url'] ?? '';
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            return ['success' => true];
        }
    } catch (Exception $e) {
        error_log("[PLATAFY Partner Auth] Error: " . $e->getMessage());
    }
    return ['success' => false, 'message' => 'Usuário ou senha incorretos.'];
}

function isPartnerLogged() {
    startSecureSession();
    if (!isset($_SESSION['partner_logged']) || $_SESSION['partner_logged'] !== true || empty($_SESSION['partner_id'])) {
        return false;
    }
    $lastActivity = $_SESSION['last_activity'] ?? $_SESSION['login_time'] ?? 0;
    if ($lastActivity > 0 && (time() - $lastActivity) > 28800) {
        logoutPartner();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

function getPartnerSession() {
    if (!isPartnerLogged()) return null;
    return [
        'id' => $_SESSION['partner_id'],
        'username' => $_SESSION['partner_user'] ?? '',
        'name' => $_SESSION['partner_name'] ?? '',
        'brand' => $_SESSION['partner_brand'] ?? '',
        'logo' => $_SESSION['partner_logo'] ?? '',
    ];
}

function requirePartner() {
    if (!isPartnerLogged()) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isApi = (strpos($uri, '/api') !== false) || (strpos($accept, 'application/json') !== false);
        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json');
            die(json_encode([
                'error' => 'Não autorizado',
                'message' => 'Sessão de parceiro expirada ou inválida.',
                'auth_required' => true
            ]));
        }
        header('Location: /parceiro/index.php');
        exit;
    }
}

function logoutPartner() {
    startSecureSession();
    unset($_SESSION['partner_logged'], $_SESSION['partner_id'], $_SESSION['partner_user'], $_SESSION['partner_name'], $_SESSION['partner_brand'], $_SESSION['partner_logo']);
}

