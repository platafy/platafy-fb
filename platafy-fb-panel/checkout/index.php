<?php
/**
 * PLATAFY FB - Página de Checkout (Pública)
 * URL: https://platafyfb.platafy.com/checkout/
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/license_utils.php';
require_once __DIR__ . '/../includes/mercadopago.php';

$plans = json_decode(PLANS, true);
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $planKey = $_POST['plan'] ?? '';
    
    if (!$name || !$email || !isset($plans[$planKey])) {
        $error = 'Preencha todos os campos corretamente.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'E-mail inválido.';
    } else {
        $plan = $plans[$planKey];
        
        // Criar assinatura no Mercado Pago
        $subData = [
            'reason' => 'PLATAFY FB - ' . $plan['name'],
            'auto_recurring' => [
                'frequency' => $plan['frequency'],
                'frequency_type' => $plan['frequency_type'],
                'transaction_amount' => $plan['price'],
                'currency_id' => 'BRL'
            ],
            'payer_email' => $email,
            'back_url' => SITE_URL . '/checkout/obrigado.php',
            'status' => 'pending'
        ];
        
        $result = mpRequest('/preapproval', 'POST', $subData);
        
        if (isset($result['id']) && isset($result['init_point'])) {
            // Criar licença pendente vinculada à assinatura
            $pdo = db();
            $licenseKey = generateLicenseKey();
            
            $stmt = $pdo->prepare("
                INSERT INTO licenses (license_key, client_name, client_email, plan_type, status, mp_subscription_id, mp_payer_email)
                VALUES (?, ?, ?, ?, 'pending', ?, ?)
            ");
            $stmt->execute([$licenseKey, $name, $email, $planKey, $result['id'], $email]);
            
            logActivity($pdo->lastInsertId(), 'checkout_started', "Checkout iniciado via MP | Sub: {$result['id']}");
            
            // Redirecionar para o Mercado Pago
            header('Location: ' . $result['init_point']);
            exit;
        } else {
            $error = 'Erro ao criar assinatura. Tente novamente. ' . ($result['message'] ?? '');
            error_log("[PLATAFY Checkout] MP Error: " . json_encode($result));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PLATAFY FB - Assine Agora</title>
    <meta name="description" content="Assine o PLATAFY FB e tenha acesso a ferramentas avançadas de gerenciamento de grupos no WhatsApp.">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --neon: #ffaa00;
            --primary: #4d5b9a;
            --bg: #0a0d1a;
            --glass: rgba(10, 13, 30, 0.7);
            --border: rgba(255, 170, 0, 0.2);
            --text: #ffffff;
            --muted: #b0b8cc;
        }
        body {
            background: radial-gradient(ellipse at top, #1a2040, #0a0d1a);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            color: var(--text);
        }
        .header {
            text-align: center;
            padding: 50px 20px 30px;
        }
        .header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 36px;
            color: var(--neon);
            text-shadow: 0 0 20px rgba(255, 170, 0, 0.4);
            letter-spacing: 3px;
            margin-bottom: 10px;
        }
        .header p {
            color: var(--muted);
            font-size: 16px;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            max-width: 1100px;
            margin: 0 auto;
            padding: 20px;
        }
        .plan-card {
            background: var(--glass);
            backdrop-filter: blur(15px);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s;
            position: relative;
            cursor: pointer;
        }
        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.5), 0 0 20px rgba(255,170,0,0.1) inset;
            border-color: rgba(255, 170, 0, 0.4);
        }
        .plan-card.popular {
            border-color: var(--neon);
            box-shadow: 0 0 25px rgba(255, 170, 0, 0.15);
        }
        .plan-card.popular::before {
            content: 'MAIS POPULAR';
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #4d5b9a, #ffaa00);
            color: #0a0d1a;
            font-family: 'Orbitron', sans-serif;
            font-size: 10px;
            font-weight: 700;
            padding: 5px 15px;
            border-radius: 20px;
            letter-spacing: 1px;
        }
        .plan-card.selected {
            border-color: var(--neon);
            background: rgba(255, 170, 0, 0.05);
        }
        .plan-name {
            font-family: 'Orbitron', sans-serif;
            font-size: 16px;
            color: var(--neon);
            margin-bottom: 15px;
            letter-spacing: 1px;
        }
        .plan-price {
            font-size: 38px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .plan-price small {
            font-size: 14px;
            color: var(--muted);
            font-weight: 400;
        }
        .plan-period {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 20px;
        }
        .plan-features {
            list-style: none;
            text-align: left;
            margin-bottom: 25px;
        }
        .plan-features li {
            padding: 6px 0;
            font-size: 13px;
            color: var(--muted);
        }
        .plan-features li::before {
            content: '✓';
            color: var(--neon);
            margin-right: 8px;
            font-weight: 700;
        }
        .plan-savings {
            background: rgba(0, 230, 118, 0.1);
            color: #00e676;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
            margin-bottom: 15px;
        }
        
        /* CHECKOUT FORM */
        .checkout-section {
            max-width: 500px;
            margin: 40px auto;
            padding: 0 20px 50px;
        }
        .checkout-card {
            background: var(--glass);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 30px;
        }
        .checkout-card h3 {
            font-family: 'Orbitron', sans-serif;
            font-size: 16px;
            color: var(--neon);
            text-align: center;
            margin-bottom: 25px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            color: var(--muted);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 13px 16px;
            background: rgba(0,0,0,0.4);
            border: 1px solid rgba(255, 170, 0, 0.15);
            border-radius: 10px;
            color: var(--text);
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            outline: none;
            transition: all 0.3s;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: var(--neon);
            box-shadow: 0 0 15px rgba(255, 170, 0, 0.1);
        }
        .form-group select option { background: #1a2040; }
        
        .checkout-btn {
            width: 100%;
            padding: 16px;
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
        .checkout-btn:hover {
            box-shadow: 0 0 30px rgba(255, 170, 0, 0.4);
            transform: translateY(-2px);
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
        .secure-badge {
            text-align: center;
            margin-top: 20px;
            color: rgba(176, 184, 204, 0.4);
            font-size: 11px;
        }
        .secure-badge span { color: #00e676; }
        
        @media (max-width: 768px) {
            .header h1 { font-size: 24px; }
            .plan-price { font-size: 28px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>PLATAFY FB</h1>
        <p>Gerencie seus grupos do WhatsApp com ferramentas profissionais. Crie grupos em massa, gerencie membros, dispare campanhas e muito mais.</p>
    </div>
    
    <div class="plans-grid">
        <div class="plan-card" onclick="selectPlan('mensal')">
            <div class="plan-name">MENSAL</div>
            <div class="plan-price">R$ 27<small>,00</small></div>
            <div class="plan-period">por mês</div>
            <ul class="plan-features">
                <li>Criação de grupos em massa</li>
                <li>Gestão de membros</li>
                <li>Campanhas de disparo</li>
                <li>Bot automático</li>
                <li>Suporte via WhatsApp</li>
            </ul>
        </div>
        
        <div class="plan-card" onclick="selectPlan('trimestral')">
            <div class="plan-name">TRIMESTRAL</div>
            <div class="plan-savings">Economize 17%</div>
            <div class="plan-price">R$ 67<small>,00</small></div>
            <div class="plan-period">a cada 3 meses</div>
            <ul class="plan-features">
                <li>Tudo do plano mensal</li>
                <li>Prioridade no suporte</li>
                <li>Desconto de R$ 14,00</li>
            </ul>
        </div>
        
        <div class="plan-card popular" onclick="selectPlan('semestral')">
            <div class="plan-name">SEMESTRAL</div>
            <div class="plan-savings">Economize 9%</div>
            <div class="plan-price">R$ 147<small>,00</small></div>
            <div class="plan-period">a cada 6 meses</div>
            <ul class="plan-features">
                <li>Tudo do plano mensal</li>
                <li>Suporte prioritário VIP</li>
                <li>Desconto de R$ 15,00</li>
            </ul>
        </div>
        
        <div class="plan-card" onclick="selectPlan('anual')">
            <div class="plan-name">ANUAL</div>
            <div class="plan-savings">Melhor Custo-Benefício</div>
            <div class="plan-price">R$ 197<small>,00</small></div>
            <div class="plan-period">por ano</div>
            <ul class="plan-features">
                <li>Tudo do plano mensal</li>
                <li>Suporte VIP prioritário</li>
                <li>Economia de R$ 127,00</li>
                <li>Menor preço por mês</li>
            </ul>
        </div>
    </div>
    
    <div class="checkout-section">
        <div class="checkout-card">
            <h3>FINALIZAR ASSINATURA</h3>
            
            <?php if ($error): ?>
                <div class="error-msg"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" id="checkout-form">
                <div class="form-group">
                    <label>Seu Nome</label>
                    <input type="text" name="name" placeholder="Nome completo" required>
                </div>
                <div class="form-group">
                    <label>Seu E-mail</label>
                    <input type="email" name="email" placeholder="seu@email.com" required>
                </div>
                <div class="form-group">
                    <label>Plano Selecionado</label>
                    <select name="plan" id="plan-select" required>
                        <option value="">Selecione um plano acima</option>
                        <option value="mensal">Mensal - R$ 27,00/mês</option>
                        <option value="trimestral">Trimestral - R$ 67,00</option>
                        <option value="semestral">Semestral - R$ 147,00</option>
                        <option value="anual">Anual - R$ 197,00</option>
                    </select>
                </div>
                <button type="submit" class="checkout-btn">ASSINAR AGORA</button>
            </form>
            
            <div class="secure-badge">
                🔒 Pagamento seguro via <span>Mercado Pago</span>
            </div>
        </div>
    </div>
    
    <script>
        function selectPlan(plan) {
            document.getElementById('plan-select').value = plan;
            document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            document.querySelector('.checkout-section').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>
