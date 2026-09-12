<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings_utils.php';

sendSecurityHeaders();

$siteLogo = getSetting('site_logo', '');
$siteFavicon = getSetting('site_favicon', '');
$error = null;

if (isPartnerLogged()) {
    header('Location: /parceiro/dashboard.php');
    exit;
}

$clientIp = getClientIp();
$rateLimit = checkRateLimit($clientIp);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Honeypot anti-bot invisível
    $honeypot = trim($_POST['security_hp_token'] ?? '');
    if (!empty($honeypot)) {
        sleep(2);
        $error = 'Usuário ou senha incorretos.';
    } elseif ($rateLimit['blocked']) {
        // 2. Proteção contra força bruta por IP
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

            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if (empty($username) || empty($password)) {
                $error = 'Por favor, informe seu usuário e senha de parceiro.';
            } else {
                $auth = loginPartner($username, $password);
                if ($auth['success']) {
                    recordLoginAttempt($clientIp, $username, true);
                    header('Location: /parceiro/dashboard.php');
                    exit;
                } else {
                    recordLoginAttempt($clientIp, $username, false);
                    $error = $auth['message'] ?? 'Usuário ou senha incorretos.';

                    $rateLimit = checkRateLimit($clientIp);
                    if ($rateLimit['blocked']) {
                        $error = $rateLimit['message'];
                    }
                }
            }
        }
    }
}

$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($siteFavicon ?: '/assets/img/favicon.png') ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Portal do Parceiro - Login Seguro - PLATAFY FB</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
        .login-card {
            width: 440px;
            max-width: 100%;
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
            position: relative;
        }
        .login-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--neon), transparent);
        }
        .login-header {
            background: linear-gradient(135deg, rgba(10,13,26,0.95), rgba(77,91,154,0.3));
            padding: 35px 25px 25px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 170, 0, 0.15);
        }
        .login-header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 20px;
            color: #fff;
            letter-spacing: 1px;
            margin-top: 10px;
        }
        .partner-badge {
            display: inline-block;
            background: rgba(255, 170, 0, 0.15);
            color: var(--neon);
            border: 1px solid rgba(255, 170, 0, 0.3);
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            letter-spacing: 1px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .login-header p {
            font-size: 13px;
            color: var(--muted);
            margin-top: 6px;
        }
        .login-body {
            padding: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .input-relative {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-relative input {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            padding: 14px 44px 14px 44px;
            color: #fff;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            outline: none;
            transition: all 0.3s;
        }
        .input-relative input:focus {
            border-color: var(--neon);
            box-shadow: 0 0 15px rgba(255, 170, 0, 0.2);
            background: rgba(255, 255, 255, 0.08);
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
        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #ffaa00, #ff8800);
            color: #000;
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 15px;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 20px rgba(255, 170, 0, 0.3);
            transition: all 0.3s;
            margin-top: 10px;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(255, 170, 0, 0.5);
        }
        .error-box {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #ff6b6b;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            line-height: 1.4;
        }
        .error-box svg {
            flex-shrink: 0;
        }
        .login-footer {
            text-align: center;
            padding: 0 30px 25px;
            font-size: 12px;
            color: var(--muted);
        }
        .login-footer a {
            color: var(--neon);
            text-decoration: none;
            font-weight: 600;
        }
        .login-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <?php if (!empty($siteLogo)): ?>
                <img src="<?= htmlspecialchars($siteLogo) ?>" alt="PLATAFY" style="max-height:42px; margin-bottom:12px;">
            <?php endif; ?>
            <br>
            <span class="partner-badge">Área do Parceiro</span>
            <h1>PORTAL WHITE LABEL</h1>
            <p>Acesse o painel para gerenciar suas licenças, cotas e clientes.</p>
        </div>

        <div class="login-body">
            <?php if (!empty($error)): ?>
                <div class="error-box">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="/parceiro/index.php" autocomplete="on">
                <!-- Proteção CSRF -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                
                <!-- Honeypot anti-robô invisível -->
                <div style="position: absolute; left: -9999px; top: -9999px; opacity: 0; pointer-events: none; z-index: -1;" aria-hidden="true">
                    <label for="security_hp_token">Não preencha este campo</label>
                    <input type="text" id="security_hp_token" name="security_hp_token" tabindex="-1" autocomplete="off" value="">
                </div>

                <div class="form-group">
                    <label for="username">Usuário do Parceiro</label>
                    <div class="input-relative">
                        <span class="field-icon">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        </span>
                        <input type="text" id="username" name="username" placeholder="Seu usuário de acesso" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label for="partnerPassword">Senha de Acesso</label>
                    <div class="input-relative">
                        <span class="field-icon">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        </span>
                        <input type="password" id="partnerPassword" name="password" placeholder="Sua senha secreta" required>
                        <button type="button" class="btn-toggle-pwd" onclick="togglePartnerPassword()" title="Mostrar/Ocultar Senha" aria-label="Mostrar/Ocultar Senha">
                            <svg id="eyeIcon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg id="eyeSlashIcon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <span>Acessar Painel do Parceiro</span>
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </button>
            </form>
        </div>

        <div class="login-footer">
            Problemas para acessar? <a href="https://api.whatsapp.com/send?phone=5521967659802" target="_blank">Fale com o Suporte Master</a>
        </div>
    </div>

    <script>
        function togglePartnerPassword() {
            const pwdInput = document.getElementById('partnerPassword');
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
