<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/settings_utils.php';
$siteLogo = getSetting('site_logo', '');

$error = null;

// Se já estiver logado como cliente, redirecionar para o dashboard do cliente
if (isset($_SESSION['client_license_id'])) {
    header('Location: /cliente/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    
    // Limpar telefone para apenas números
    $cleanPhone = preg_replace('/\D/', '', $phone);
    if (str_starts_with($cleanPhone, '55') && strlen($cleanPhone) >= 12) {
        $cleanPhoneWithoutDDI = substr($cleanPhone, 2);
    } else {
        $cleanPhoneWithoutDDI = $cleanPhone;
    }
    
    if (empty($email) || empty($phone)) {
        $error = 'Por favor, informe seu E-mail e WhatsApp de cadastro.';
    } else {
        try {
            $pdo = db();
            // Buscar por email e telefone (aceita com ou sem DDI 55)
            $stmt = $pdo->prepare("
                SELECT * FROM licenses 
                WHERE LOWER(client_email) = ? 
                AND (
                    REPLACE(REPLACE(REPLACE(REPLACE(client_phone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE ?
                    OR REPLACE(REPLACE(REPLACE(REPLACE(client_phone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE ?
                    OR REPLACE(REPLACE(REPLACE(REPLACE(client_phone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE ?
                )
                ORDER BY id DESC LIMIT 1
            ");
            
            $likePhone1 = '%' . $cleanPhoneWithoutDDI;
            $likePhone2 = '%55' . $cleanPhoneWithoutDDI;
            $likePhone3 = '%' . $cleanPhone;
            
            $stmt->execute([$email, $likePhone1, $likePhone2, $likePhone3]);
            $license = $stmt->fetch();
            
            if ($license) {
                $_SESSION['client_license_id'] = $license['id'];
                $_SESSION['client_email'] = $license['client_email'];
                $_SESSION['client_name'] = $license['client_name'];
                header('Location: /cliente/dashboard.php');
                exit;
            } else {
                $error = 'Nenhuma licença encontrada para o E-mail e WhatsApp informados. Verifique os dados.';
            }
        } catch (Exception $e) {
            $error = 'Erro no servidor: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PLATAFY FB - Área do Cliente</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --neon: #ffaa00;
            --neon-glow: rgba(255, 170, 0, 0.4);
            --primary: #4d5b9a;
            --bg-deep: #070914;
            --glass: rgba(13, 17, 38, 0.8);
            --border: rgba(255, 170, 0, 0.25);
            --text: #ffffff;
            --muted: #a0aabf;
            --success: #00e676;
        }
        
        body {
            background: radial-gradient(ellipse at top, #161c38 0%, #070914 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 20px;
            max-width: 440px;
            width: 100%;
            padding: 35px 30px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.6);
            position: relative;
            overflow: hidden;
        }
        
        .login-card::before {
            content: "";
            position: absolute;
            top: 0; left: -100%; width: 100%; height: 2px;
            background: linear-gradient(90deg, transparent, var(--neon), transparent);
            animation: glow 3s infinite;
        }
        
        @keyframes glow { 0% { left: -100%; } 100% { left: 100%; } }
        
        .header {
            text-align: center;
            margin-bottom: 28px;
        }
        
        .header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 26px;
            font-weight: 800;
            color: var(--neon);
            text-shadow: 0 0 15px var(--neon-glow);
            letter-spacing: 2px;
            margin-bottom: 6px;
        }
        
        .header p {
            color: var(--muted);
            font-size: 13px;
        }
        
        .form-group {
            margin-bottom: 18px;
        }
        
        .form-group label {
            display: block;
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 7px;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            background: rgba(5, 7, 18, 0.75);
            border: 1px solid rgba(255, 170, 0, 0.2);
            border-radius: 12px;
            color: var(--text);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            outline: none;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            border-color: var(--neon);
            box-shadow: 0 0 15px rgba(255, 170, 0, 0.25);
        }
        
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #4d5b9a 0%, #ffaa00 100%);
            border: none;
            border-radius: 12px;
            color: #050712;
            font-family: 'Orbitron', sans-serif;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 2px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 8px;
            box-shadow: 0 4px 15px rgba(255, 170, 0, 0.25);
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(255, 170, 0, 0.45);
        }
        
        .error-msg {
            background: rgba(255, 75, 75, 0.12);
            border: 1px solid rgba(255, 75, 75, 0.4);
            color: #ff5252;
            padding: 12px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .footer-links {
            text-align: center;
            margin-top: 22px;
            font-size: 12px;
            color: var(--muted);
        }
        
        .footer-links a {
            color: var(--neon);
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="header">
            <?php if (!empty($siteLogo)): ?>
                <img src="<?= htmlspecialchars($siteLogo) ?>" alt="PLATAFY FB" style="max-height: 50px; margin-bottom: 12px; display: block; margin-left: auto; margin-right: auto;">
            <?php else: ?>
                <h1>ÁREA DO CLIENTE</h1>
            <?php endif; ?>
            <p>Acesse sua licença e gerencie seus acessos</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Seu E-mail de Cadastro</label>
                <input type="email" name="email" placeholder="seuemail@gmail.com" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label>Seu WhatsApp (com DDD)</label>
                <input type="tel" name="phone" id="phone" placeholder="Ex: 21999991234" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
            
            <button type="submit" class="btn-submit">ACESSAR MINHA LICENÇA</button>
        </form>
        
        <div class="footer-links">
            Não tem uma licença? <a href="/checkout/">Adquira agora mesmo</a>
        </div>
    </div>
    
    <script>
        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                let v = e.target.value.replace(/\D/g, '');
                if (v.length > 11) v = v.substring(0, 11);
                e.target.value = v;
            });
        }
    </script>
</body>
</html>
