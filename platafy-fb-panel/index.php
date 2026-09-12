<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings_utils.php';

sendSecurityHeaders();

$siteLogo = getSetting('site_logo', '');
$siteFavicon = getSetting('site_favicon', '');

$clientIp = getClientIp();
$rateLimit = checkRateLimit($clientIp);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Honeypot anti-bot invisível
    $honeypot = trim($_POST['security_hp_token'] ?? '');
    if (!empty($honeypot)) {
        // Robô detectado preenchendo campo oculto: simular atraso e falha silenciosa
        sleep(2);
        $error = 'Usuário ou senha incorretos.';
    } elseif ($rateLimit['blocked']) {
        // 2. Proteção contra Força Bruta / Rate Limiting por IP
        $error = $rateLimit['message'];
    } else {
        // 3. Validação de CSRF Token
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($csrfToken)) {
            $error = 'Sua sessão expirou ou a validação de segurança falhou. Por favor, recarregue a página e tente novamente.';
        } else {
            // Aplicar delay progressivo se o IP atingiu >= 5 falhas
            if (!empty($rateLimit['delay'])) {
                sleep((int)$rateLimit['delay']);
            }

            $user = trim($_POST['username'] ?? '');
            $pass = trim($_POST['password'] ?? '');

            // Autenticação Admin Master
            if (loginAdmin($user, $pass)) {
                recordLoginAttempt($clientIp, $user, true);
                header('Location: /dashboard.php');
                exit;
            }

            // Autenticação de Parceiro White Label
            $partnerAuth = loginPartner($user, $pass);
            if ($partnerAuth['success']) {
                recordLoginAttempt($clientIp, $user, true);
                header('Location: /parceiro/dashboard.php');
                exit;
            } else {
                recordLoginAttempt($clientIp, $user, false);
                if (!empty($partnerAuth['message']) && $partnerAuth['message'] !== 'Usuário ou senha incorretos.') {
                    $error = $partnerAuth['message'];
                } else {
                    $error = 'Usuário ou senha incorretos.';
                }

                // Se após esta tentativa o IP foi bloqueado, avisar na hora
                $rateLimit = checkRateLimit($clientIp);
                if ($rateLimit['blocked']) {
                    $error = $rateLimit['message'];
                }
            }
        }
    }
}

if (isAdminLogged()) {
    header('Location: /dashboard.php');
    exit;
}

if (isPartnerLogged()) {
    header('Location: /parceiro/dashboard.php');
    exit;
}

$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($siteFavicon ?: '/assets/img/favicon.png') ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>PLATAFY FB - Login Seguro</title>
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0a0d1a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="PLATAFY Admin">
    <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">

    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --neon: #ffaa00;
            --primary: #4d5b9a;
            --bg-deep: #0a0d1a;
            --glass: rgba(10, 13, 30, 0.75);
            --border: rgba(255, 170, 0, 0.3);
            --text: #ffffff;
            --muted: #b0b8cc;
        }
        
        body {
            background: radial-gradient(ellipse at top, #1a2040, #0a0d1a);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            color: var(--text);
            padding: 20px;
        }
        
        @keyframes neonPulse {
            0%, 100% { box-shadow: 0 0 15px rgba(255, 170, 0, 0.15); }
            50% { box-shadow: 0 0 30px rgba(255, 170, 0, 0.3); }
        }
        
        @keyframes glowLine {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .login-card {
            width: 420px;
            max-width: 100%;
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 20px;
            overflow: hidden;
            animation: neonPulse 4s infinite;
            position: relative;
        }
        
        .login-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--neon), transparent);
            animation: glowLine 3s infinite;
        }
        
        .login-header {
            background: linear-gradient(135deg, #0a0d1a 0%, #4d5b9a 100%);
            padding: 35px 25px 25px;
            text-align: center;
            border-bottom: 1px solid var(--border);
        }
        
        .login-header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 26px;
            color: var(--neon);
            text-shadow: 0 0 15px rgba(255, 170, 0, 0.5);
            letter-spacing: 3px;
            margin-bottom: 8px;
        }
        
        .login-header p {
            color: var(--muted);
            font-size: 13px;
            letter-spacing: 1px;
        }
        
        .login-body {
            padding: 35px 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .input-relative {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .field-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--neon);
            display: flex;
            align-items: center;
            pointer-events: none;
        }
        
        .input-relative input {
            width: 100%;
            padding: 14px 44px 14px 44px;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 170, 0, 0.18);
            border-radius: 10px;
            color: var(--text);
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            outline: none;
            transition: all 0.3s;
        }
        
        .input-relative input:focus {
            border-color: var(--neon);
            box-shadow: 0 0 15px rgba(255, 170, 0, 0.2);
            background: rgba(0, 0, 0, 0.55);
        }

        .btn-toggle-pwd {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: var(--muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 6px;
            border-radius: 6px;
            transition: color 0.2s, transform 0.2s;
        }

        .btn-toggle-pwd:hover {
            color: var(--neon);
        }

        .btn-toggle-pwd:active {
            transform: translateY(-50%) scale(0.95);
        }
        
        .login-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #4d5b9a, #ffaa00);
            border: none;
            border-radius: 10px;
            color: #0a0d1a;
            font-family: 'Orbitron', sans-serif;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 2px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .login-btn:hover {
            box-shadow: 0 0 25px rgba(255, 170, 0, 0.4);
            transform: translateY(-2px);
        }
        
        .login-btn:active {
            transform: translateY(1px);
        }
        
        .error-msg {
            background: rgba(255, 85, 85, 0.12);
            border: 1px solid rgba(255, 85, 85, 0.35);
            color: #ff6b6b;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13px;
            text-align: left;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            line-height: 1.4;
        }

        .error-msg svg {
            flex-shrink: 0;
        }
        
        .footer-text {
            text-align: center;
            padding: 18px 20px;
            color: rgba(176, 184, 204, 0.5);
            font-size: 11px;
            border-top: 1px solid rgba(255, 170, 0, 0.1);
            letter-spacing: 0.5px;
        }

        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 15px;
            font-size: 11px;
            color: rgba(176, 184, 204, 0.6);
            letter-spacing: 0.5px;
        }

        .security-badge svg {
            color: #00e676;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <?php if (!empty($siteLogo)): ?>
                <img src="<?= htmlspecialchars($siteLogo) ?>" id="site-logo-img" alt="PLATAFY" style="max-height: 55px; max-width: 260px; margin-bottom: 8px; vertical-align: middle;">
            <?php else: ?>
                <h1 id="site-logo-text">PLATAFY</h1>
            <?php endif; ?>
            <p>PAINEL ADMINISTRATIVO</p>
        </div>
        <div class="login-body">
            <?php if (isset($error)): ?>
                <div class="error-msg">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" autocomplete="on">
                <!-- Proteção CSRF -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                
                <!-- Honeypot anti-robô invisível (não deve ser preenchido por humanos) -->
                <div style="position: absolute; left: -9999px; top: -9999px; opacity: 0; pointer-events: none; z-index: -1;" aria-hidden="true">
                    <label for="security_hp_token">Não preencha este campo</label>
                    <input type="text" id="security_hp_token" name="security_hp_token" tabindex="-1" autocomplete="off" value="">
                </div>

                <div class="form-group">
                    <label for="username">Usuário</label>
                    <div class="input-relative">
                        <span class="field-icon">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        </span>
                        <input type="text" id="username" name="username" placeholder="admin" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label for="loginPassword">Senha</label>
                    <div class="input-relative">
                        <span class="field-icon">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        </span>
                        <input type="password" id="loginPassword" name="password" placeholder="••••••••" required>
                        <button type="button" class="btn-toggle-pwd" onclick="toggleLoginPassword()" title="Mostrar/Ocultar Senha" aria-label="Mostrar/Ocultar Senha">
                            <svg id="eyeIcon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg id="eyeSlashIcon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="login-btn">
                    <span>ENTRAR</span>
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </button>
            </form>

            <div class="security-badge">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                <span>Conexão Segura & Blindada</span>
            </div>
        </div>
        <div class="footer-text">
            PLATAFY FB &copy; <?= date('Y') ?> &bull; Todos os direitos reservados
        </div>
    </div>

    <script>
        function toggleLoginPassword() {
            const pwdInput = document.getElementById('loginPassword');
            const eyeIcon = document.getElementById('eyeIcon');
            const eyeSlashIcon = document.getElementById('eyeSlashIcon');
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                eyeIcon.style.display = 'none';
                eyeSlashIcon.style.display = 'block';
            } else {
                pwdInput.type = 'password';
                eyeIcon.style.display = 'block';
                eyeSlashIcon.style.display = 'none';
            }
        }
    </script>
</body>
</html>
