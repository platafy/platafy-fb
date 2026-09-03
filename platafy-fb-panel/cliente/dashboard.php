<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/license_utils.php';
require_once __DIR__ . '/../includes/settings_utils.php';
$siteLogo = getSetting('site_logo', '');

if (!isset($_SESSION['client_license_id'])) {
    header('Location: /cliente/');
    exit;
}

$pdo = db();
$stmt = $pdo->prepare("SELECT * FROM licenses WHERE id = ?");
$stmt->execute([$_SESSION['client_license_id']]);
$license = $stmt->fetch();

if (!$license) {
    session_destroy();
    header('Location: /cliente/');
    exit;
}

// Ação de desvincular dispositivo (HWID) pelo próprio cliente
$msg = null;
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'release_hwid') {
        $stmt = $pdo->prepare("UPDATE licenses SET hwid = NULL, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$license['id']]);
        logActivity($license['id'], 'client_released_hwid', 'Dispositivo desvinculado pelo próprio cliente no portal');
        $license['hwid'] = null;
        $msg = 'Dispositivo desvinculado com sucesso! Você já pode ativar sua licença no novo computador.';
    }
}

// Status formatado
$statusClass = 'active';
$statusLabel = 'ATIVA';
if ($license['status'] === 'expired' || (strtotime($license['expires_at']) < time() && $license['expires_at'] !== null)) {
    $statusClass = 'expired';
    $statusLabel = 'EXPIRADA';
} elseif ($license['status'] === 'revoked') {
    $statusClass = 'revoked';
    $statusLabel = 'SUSPENSA / REVOGADA';
} elseif ($license['status'] === 'inactive') {
    $statusClass = 'inactive';
    $statusLabel = 'INATIVA';
}

$plans = json_decode(PLANS, true);
$planName = isset($plans[$license['plan_type']]) ? $plans[$license['plan_type']]['name'] : ucfirst($license['plan_type']);
$formattedExpiry = $license['expires_at'] ? date('d/m/Y H:i', strtotime($license['expires_at'])) : 'Vitalício';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PLATAFY FB - Minha Licença</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --neon: #ffaa00;
            --neon-glow: rgba(255, 170, 0, 0.4);
            --primary: #4d5b9a;
            --bg-deep: #070914;
            --glass: rgba(13, 17, 38, 0.85);
            --border: rgba(255, 170, 0, 0.25);
            --text: #ffffff;
            --muted: #a0aabf;
            --success: #00e676;
            --danger: #ff5252;
        }
        
        body {
            background: radial-gradient(ellipse at top, #161c38 0%, #070914 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            color: var(--text);
            padding: 30px 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .container {
            max-width: 650px;
            width: 100%;
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .brand {
            font-family: 'Orbitron', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: var(--neon);
            letter-spacing: 2px;
        }
        
        .logout-link {
            color: var(--muted);
            text-decoration: none;
            font-size: 13px;
            padding: 6px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .logout-link:hover {
            color: var(--neon);
            border-color: var(--neon);
        }
        
        .card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.6);
            margin-bottom: 20px;
        }
        
        .user-welcome {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 20px;
        }
        
        .user-welcome strong {
            color: var(--text);
        }
        
        /* BOX DA CHAVE */
        .key-box {
            background: rgba(5, 7, 18, 0.85);
            border: 1px solid var(--neon);
            box-shadow: 0 0 15px rgba(255, 170, 0, 0.15);
            border-radius: 14px;
            padding: 20px;
            text-align: center;
            margin-bottom: 25px;
            position: relative;
        }
        
        .key-title {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        
        .key-value {
            font-family: 'Orbitron', sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: var(--neon);
            letter-spacing: 3px;
            margin-bottom: 12px;
            word-break: break-all;
        }
        
        .btn-copy {
            background: rgba(255, 170, 0, 0.15);
            border: 1px solid var(--border);
            color: var(--neon);
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-copy:hover {
            background: var(--neon);
            color: #000;
        }
        
        /* DETALHES DE STATUS */
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        @media (max-width: 480px) {
            .details-grid { grid-template-columns: 1fr; }
            .key-value { font-size: 18px; }
        }
        
        .detail-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 12px;
            padding: 14px 16px;
        }
        
        .detail-label {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        
        .detail-val {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .status-badge.active { background: rgba(0, 230, 118, 0.15); color: #00e676; border: 1px solid #00e676; }
        .status-badge.expired { background: rgba(255, 82, 82, 0.15); color: #ff5252; border: 1px solid #ff5252; }
        .status-badge.revoked { background: rgba(255, 82, 82, 0.2); color: #ff5252; }
        .status-badge.inactive { background: rgba(160, 170, 191, 0.15); color: #a0aabf; }
        
        /* BOTÕES DE AÇÃO DO CLIENTE */
        .actions-box {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .btn-action {
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            font-family: 'Orbitron', sans-serif;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-renew {
            background: linear-gradient(135deg, #4d5b9a 0%, #ffaa00 100%);
            color: #050712;
            box-shadow: 0 4px 15px rgba(255, 170, 0, 0.25);
        }
        
        .btn-release {
            background: rgba(255, 82, 82, 0.1);
            border: 1px solid rgba(255, 82, 82, 0.3);
            color: #ff5252;
        }
        
        .btn-release:hover {
            background: rgba(255, 82, 82, 0.25);
        }
        
        .btn-download {
            background: rgba(0, 230, 118, 0.1);
            border: 1px solid rgba(0, 230, 118, 0.3);
            color: #00e676;
        }
        
        .btn-download:hover {
            background: rgba(0, 230, 118, 0.2);
        }
        
        .alert-msg {
            background: rgba(0, 230, 118, 0.12);
            border: 1px solid #00e676;
            color: #00e676;
            padding: 12px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="navbar">
            <div class="brand">
                <?php if (!empty($siteLogo)): ?>
                    <img src="<?= htmlspecialchars($siteLogo) ?>" alt="PLATAFY FB" style="max-height: 38px; vertical-align: middle;">
                <?php else: ?>
                    PLATAFY FB
                <?php endif; ?>
            </div>
            <a href="/cliente/logout.php" class="logout-link">Sair</a>
        </div>
        
        <?php if ($msg): ?>
            <div class="alert-msg"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="user-welcome">
                Olá, <strong><?= htmlspecialchars($license['client_name'] ?: 'Cliente') ?></strong> (<?= htmlspecialchars($license['client_email']) ?>)
            </div>
            
            <!-- CHAVE -->
            <div class="key-box">
                <div class="key-title">Sua Chave de Licença</div>
                <div class="key-value" id="lic-key"><?= htmlspecialchars($license['license_key']) ?></div>
                <button class="btn-copy" onclick="copyKey()">📋 COPIAR CHAVE</button>
            </div>
            
            <!-- DETALHES -->
            <div class="details-grid">
                <div class="detail-item">
                    <div class="detail-label">Status da Licença</div>
                    <div class="detail-val">
                        <span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Plano</div>
                    <div class="detail-val"><?= htmlspecialchars($planName) ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Validade</div>
                    <div class="detail-val"><?= $formattedExpiry ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Computador Vinculado</div>
                    <div class="detail-val"><?= $license['hwid'] ? '1 Dispositivo Ativo' : 'Nenhum (Livre)' ?></div>
                </div>
            </div>
            
            <!-- AÇÕES -->
            <div class="actions-box">
                <?php if ($license['hwid']): ?>
                    <form method="POST" onsubmit="return confirm('Deseja desvincular o computador atual para poder usar a licença em outro computador?');">
                        <input type="hidden" name="action" value="release_hwid">
                        <button type="submit" class="btn-action btn-release">
                            🔄 DESVINCULAR COMPUTADOR ATUAL
                        </button>
                    </form>
                <?php endif; ?>
                
                <?php if ($statusClass === 'expired' || $statusClass === 'inactive'): ?>
                    <a href="/checkout/?plan=<?= $license['plan_type'] ?>" class="btn-action btn-renew">
                        ⚡ RENOVAR LICENÇA AGORA
                    </a>
                <?php endif; ?>
                
                <a href="/downloads/platafy-fb.zip" class="btn-action btn-download" download>
                    📥 BAIXAR EXTENSÃO PLATAFY FB (.ZIP)
                </a>
            </div>
        </div>
    </div>
    
    <script>
        function copyKey() {
            const keyText = document.getElementById('lic-key').innerText;
            navigator.clipboard.writeText(keyText).then(() => {
                const btn = document.querySelector('.btn-copy');
                btn.innerText = '✅ COPIADO!';
                setTimeout(() => { btn.innerText = '📋 COPIAR CHAVE'; }, 2000);
            });
        }
    </script>
</body>
</html>
