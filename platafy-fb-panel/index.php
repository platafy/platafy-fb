<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings_utils.php';
$siteLogo = getSetting('site_logo', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    
    if (loginAdmin($user, $pass)) {
        header('Location: /dashboard.php');
        exit;
    }
    $error = 'Usuário ou senha incorretos.';
}

if (isAdminLogged()) {
    header('Location: /dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>PLATAFY FB - Login</title>
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0a0d1a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="PLATAFY Admin">
    <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">

    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --neon: #ffaa00;
            --primary: #4d5b9a;
            --bg-deep: #0a0d1a;
            --glass: rgba(10, 13, 30, 0.7);
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
            max-width: 90%;
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
            padding: 35px;
            text-align: center;
            border-bottom: 1px solid var(--border);
        }
        
        .login-header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 28px;
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
            padding: 40px 35px;
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
        
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            background: rgba(0,0,0,0.4);
            border: 1px solid rgba(255, 170, 0, 0.15);
            border-radius: 10px;
            color: var(--text);
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            outline: none;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            border-color: var(--neon);
            box-shadow: 0 0 15px rgba(255, 170, 0, 0.15);
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
        }
        
        .login-btn:hover {
            box-shadow: 0 0 25px rgba(255, 170, 0, 0.4);
            transform: translateY(-2px);
        }
        
        .login-btn:active {
            transform: translateY(1px);
        }
        
        .error-msg {
            background: rgba(255, 85, 85, 0.1);
            border: 1px solid rgba(255, 85, 85, 0.3);
            color: #ff5555;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .footer-text {
            text-align: center;
            padding: 20px;
            color: rgba(176, 184, 204, 0.4);
            font-size: 11px;
            border-top: 1px solid rgba(255, 170, 0, 0.1);
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
                <div class="error-msg"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Usuário</label>
                    <input type="text" name="username" placeholder="admin" required autofocus>
                </div>
                <div class="form-group">
                    <label>Senha</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="login-btn">ENTRAR</button>
            </form>
        </div>
        <div class="footer-text">
            PLATAFY FB &copy; <?= date('Y') ?>
        </div>
    </div>
</body>
</html>
