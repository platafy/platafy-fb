<?php
require_once __DIR__ . '/../config.php';

/**
 * PLATAFY FB - Autenticação Admin
 */

function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.gc_maxlifetime', 28800); // 8 horas no servidor
        session_name('PLATAFY_SESSION');
        
        if (!headers_sent()) {
            if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
                session_set_cookie_params([
                    'lifetime' => 28800,
                    'path' => '/',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            } else {
                @session_set_cookie_params(28800, '/', '', false, true);
            }
        }
        
        @session_start();
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

