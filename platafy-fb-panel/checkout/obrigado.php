<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/settings_utils.php';

$siteFavicon = getSetting('site_favicon', '');
$siteLogo    = getSetting('site_logo', '');
$licenseKey  = trim($_GET['license_key'] ?? '');
$license     = null;
$plans       = json_decode(PLANS, true) ?: [];
$planName    = 'Assinatura';
$isPending   = false;

if (!empty($licenseKey)) {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ?");
        $stmt->execute([$licenseKey]);
        $license = $stmt->fetch();
        if ($license) {
            if (isset($plans[$license['plan_type']])) {
                $planName = $plans[$license['plan_type']]['name'];
            }
            if ($license['status'] === 'pending') {
                $isPending = true;
            }
        }
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($siteFavicon ?: '/assets/img/favicon.png') ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PLATAFY FB - Licença Liberada!</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --neon: #ffaa00;
            --neon-glow: rgba(255, 170, 0, 0.4);
            --primary: #4d5b9a;
            --bg-deep: #070914;
            --glass: rgba(13, 17, 38, 0.85);
            --border: rgba(255, 170, 0, 0.3);
            --text: #ffffff;
            --muted: #a0aabf;
            --success: #00e676;
            --warning: #ffb300;
        }
        body {
            background: radial-gradient(ellipse at top, #161c38 0%, #070914 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            color: var(--text);
            padding: 30px 15px;
        }
        .card {
            background: var(--glass);
            backdrop-filter: blur(25px);
            border: 1px solid var(--border);
            border-radius: 24px;
            max-width: 580px;
            width: 100%;
            padding: 40px 35px;
            text-align: center;
            box-shadow: 0 0 50px rgba(0,0,0,0.85);
            position: relative;
            overflow: hidden;
        }
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--neon), transparent);
        }
        .icon {
            font-size: 56px;
            margin-bottom: 15px;
            display: inline-block;
            filter: drop-shadow(0 0 15px rgba(0, 230, 118, 0.6));
            animation: bounce 2s infinite ease-in-out;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
        h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: var(--neon);
            margin-bottom: 10px;
            letter-spacing: 2px;
            text-shadow: 0 0 20px var(--neon-glow);
        }
        .subtitle {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        /* KEY DISPLAY BOX */
        .key-box-wrapper {
            background: rgba(10, 13, 28, 0.9);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 22px;
            margin-bottom: 25px;
            text-align: center;
            box-shadow: inset 0 0 20px rgba(0,0,0,0.6);
        }
        .key-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--muted);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .key-display {
            font-family: 'Courier New', Courier, monospace;
            font-size: 24px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 3px;
            background: rgba(255, 170, 0, 0.08);
            border: 1px dashed rgba(255, 170, 0, 0.4);
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 15px;
            word-break: break-all;
            user-select: all;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 15px;
        }
        .status-badge.active {
            background: rgba(0, 230, 118, 0.15);
            border: 1px solid rgba(0, 230, 118, 0.4);
            color: var(--success);
        }
        .status-badge.pending {
            background: rgba(255, 170, 0, 0.15);
            border: 1px solid rgba(255, 170, 0, 0.4);
            color: var(--warning);
        }

        .btn-copy {
            background: rgba(255, 170, 0, 0.15);
            color: var(--neon);
            border: 1px solid var(--neon);
            padding: 10px 20px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'Inter', sans-serif;
        }
        .btn-copy:hover {
            background: var(--neon);
            color: #070914;
            box-shadow: 0 0 15px var(--neon-glow);
        }

        /* INSTRUCTIONS */
        .info-box {
            background: rgba(255, 170, 0, 0.04);
            border: 1px dashed rgba(255, 170, 0, 0.25);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 25px;
            text-align: left;
        }
        .info-box h4 {
            font-size: 13px;
            color: var(--neon);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .info-box ol {
            padding-left: 20px;
        }
        .info-box li {
            margin-bottom: 8px;
            font-size: 13px;
            color: var(--muted);
            line-height: 1.5;
        }
        .info-box li strong { color: #ffffff; }

        /* ACTION BUTTONS */
        .actions-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn-action {
            padding: 13px 24px;
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            font-size: 12px;
            text-decoration: none;
            border-radius: 10px;
            letter-spacing: 1px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-whatsapp {
            background: linear-gradient(135deg, #00c853, #00e676);
            color: #070914;
        }
        .btn-whatsapp:hover {
            box-shadow: 0 0 25px rgba(0, 230, 118, 0.5);
            transform: translateY(-2px);
        }
        .btn-web {
            background: linear-gradient(135deg, #4d5b9a, var(--neon));
            color: #070914;
        }
        .btn-web:hover {
            box-shadow: 0 0 25px var(--neon-glow);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🎉</div>
        <h1>PAGAMENTO APROVADO!</h1>
        <p class="subtitle">Parabéns! Sua assinatura do <b>PLATAFY FB</b> (<?= htmlspecialchars($planName) ?>) foi confirmada com sucesso.</p>

        <?php if (!empty($licenseKey)): ?>
            <div class="key-box-wrapper">
                <div class="key-title">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    Sua Chave de Ativação
                </div>

                <div class="key-display" id="license-key-display"><?= htmlspecialchars($licenseKey) ?></div>

                <?php if ($isPending): ?>
                    <div>
                        <span class="status-badge pending">
                            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            Processando Ativação Automática...
                        </span>
                    </div>
                <?php else: ?>
                    <div>
                        <span class="status-badge active">
                            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            Licença Ativa e Pronta
                        </span>
                    </div>
                <?php endif; ?>

                <button type="button" class="btn-copy" id="btn-copy-key" onclick="copyKey()">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                    <span>Copiar Chave de Licença</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <h4>
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                Instruções de Ativação:
            </h4>
            <ol>
                <li>Abra o <strong>WhatsApp Web</strong> no navegador Google Chrome.</li>
                <li>Clique no ícone da extensão <strong>PLATAFY FB</strong> no canto superior direito.</li>
                <li>Cole a chave de licença acima e clique em <strong>Ativar</strong> para liberar todas as funções.</li>
            </ol>
        </div>

        <div class="actions-group">
            <a href="https://web.whatsapp.com" class="btn-action btn-whatsapp" target="_blank">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2z"/></svg>
                ABRIR WHATSAPP WEB
            </a>
            <a href="/index.php" class="btn-action btn-web">
                PORTAL DO CLIENTE
            </a>
        </div>
    </div>

    <script>
        function copyKey() {
            const el = document.getElementById('license-key-display');
            if (!el) return;
            const keyText = el.innerText.trim();
            navigator.clipboard.writeText(keyText).then(() => {
                const btn = document.getElementById('btn-copy-key');
                btn.innerHTML = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg><span>Chave Copiada!</span>';
                btn.style.background = 'var(--neon)';
                btn.style.color = '#070914';
                setTimeout(() => {
                    btn.innerHTML = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg><span>Copiar Chave de Licença</span>';
                    btn.style.background = '';
                    btn.style.color = '';
                }, 2500);
            }).catch(() => {
                alert('Chave: ' + keyText);
            });
        }
    </script>
</body>
</html>
