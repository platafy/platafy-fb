<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/license_utils.php';
require_once __DIR__ . '/../includes/mercadopago.php';

$error = null;
$selectedPlan = $_GET['plan'] ?? 'mensal';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $planKey = trim($_POST['plan'] ?? '');
    
    // Formatar WhatsApp limpo
    $cleanPhone = preg_replace('/\D/', '', $phone);
    if (strlen($cleanPhone) >= 10 && !str_starts_with($cleanPhone, '55')) {
        $cleanPhone = '55' . $cleanPhone;
    }
    
    $plans = json_decode(PLANS, true);
    
    if (empty($name) || empty($email) || empty($phone)) {
        $error = 'Por favor, preencha todos os campos: Nome, E-mail e WhatsApp.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor, insira um e-mail válido.';
    } elseif (!isset($plans[$planKey])) {
        $error = 'Por favor, selecione um plano válido.';
    } else {
        try {
            // Criar licença pendente com Nome, E-mail e WhatsApp gravados
            $license = createLicense($name, $email, $planKey, null, $phone);
            
            // Criar checkout do Mercado Pago
            $plan = $plans[$planKey];
            
            $mpPreferenceData = [
                'items' => [
                    [
                        'title' => 'PLATAFY FB - ' . $plan['name'],
                        'quantity' => 1,
                        'currency_id' => 'BRL',
                        'unit_price' => (float)$plan['price']
                    ]
                ],
                'payer' => [
                    'name' => $name,
                    'email' => $email,
                    'phone' => [
                        'area_code' => substr($cleanPhone, 2, 2),
                        'number' => substr($cleanPhone, 4)
                    ]
                ],
                'external_reference' => (string)$license['id'],
                'back_urls' => [
                    'success' => CHECKOUT_BACK_URL,
                    'pending' => CHECKOUT_BACK_URL,
                    'failure' => SITE_URL . '/checkout/'
                ],
                'auto_return' => 'approved'
            ];
            
            $mpRes = mpRequest('/checkout/preferences', 'POST', $mpPreferenceData);
            
            if (isset($mpRes['init_point'])) {
                header('Location: ' . $mpRes['init_point']);
                exit;
            } elseif (isset($mpRes['sandbox_init_point']) && MP_USE_TEST) {
                header('Location: ' . $mpRes['sandbox_init_point']);
                exit;
            } else {
                $error = 'Erro ao conectar ao Mercado Pago: ' . ($mpRes['message'] ?? 'Verifique as chaves de API.');
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
    <title>PLATAFY FB - Finalizar Assinatura</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --neon: #ffaa00;
            --neon-glow: rgba(255, 170, 0, 0.4);
            --primary: #4d5b9a;
            --bg-deep: #070914;
            --glass: rgba(13, 17, 38, 0.75);
            --glass-card: rgba(18, 24, 52, 0.65);
            --border: rgba(255, 170, 0, 0.25);
            --border-hover: rgba(255, 170, 0, 0.6);
            --text: #ffffff;
            --muted: #a0aabf;
            --success: #00e676;
        }
        
        body {
            background: radial-gradient(ellipse at top, #161c38 0%, #070914 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            color: var(--text);
            padding: 35px 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .header {
            text-align: center;
            margin-bottom: 35px;
            max-width: 700px;
        }
        
        .header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 32px;
            font-weight: 800;
            color: var(--neon);
            text-shadow: 0 0 20px var(--neon-glow);
            letter-spacing: 3px;
            margin-bottom: 10px;
        }
        
        .header p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
        }
        
        /* LAYOUT PRINCIPAL DE 2 COLUNAS */
        .checkout-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            max-width: 1050px;
            width: 100%;
            align-items: start;
        }
        
        @media (max-width: 880px) {
            .checkout-container {
                grid-template-columns: 1fr;
                gap: 25px;
            }
        }
        
        /* COLUNA DA ESQUERDA - CARDS DE PLANOS */
        .plans-column {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .plan-card {
            background: var(--glass-card);
            backdrop-filter: blur(15px);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 22px 24px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .plan-card:hover {
            border-color: var(--border-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        }
        
        .plan-card.selected {
            border-color: var(--neon);
            background: rgba(255, 170, 0, 0.08);
            box-shadow: 0 0 20px rgba(255, 170, 0, 0.25);
        }
        
        .plan-card.selected::before {
            content: "";
            position: absolute;
            top: 0; left: 0; width: 4px; height: 100%;
            background: var(--neon);
            box-shadow: 0 0 10px var(--neon);
        }
        
        .badge {
            position: absolute;
            top: 10px;
            right: 15px;
            background: linear-gradient(135deg, #ffaa00, #ff7700);
            color: #000;
            font-size: 10px;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-green {
            background: linear-gradient(135deg, #00e676, #00b0ff);
            color: #000;
        }
        
        .plan-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .plan-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
            letter-spacing: 1px;
        }
        
        .plan-desc {
            font-size: 12px;
            color: var(--muted);
        }
        
        .plan-price-tag {
            text-align: right;
        }
        
        .plan-price-val {
            font-family: 'Orbitron', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: var(--neon);
        }
        
        .plan-price-sub {
            font-size: 11px;
            color: var(--muted);
        }
        
        /* COLUNA DA DIREITA - FORMULÁRIO */
        .form-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 32px 28px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            position: relative;
        }
        
        .form-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 18px;
            font-weight: 800;
            color: var(--neon);
            text-align: center;
            margin-bottom: 22px;
            letter-spacing: 1.5px;
            text-shadow: 0 0 10px rgba(255, 170, 0, 0.3);
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 13px 15px;
            background: rgba(5, 7, 18, 0.75);
            border: 1px solid rgba(255, 170, 0, 0.2);
            border-radius: 12px;
            color: var(--text);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            outline: none;
            transition: all 0.3s;
        }
        
        .form-group input:focus, .form-group select:focus {
            border-color: var(--neon);
            box-shadow: 0 0 15px rgba(255, 170, 0, 0.25);
        }
        
        .form-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23ffaa00' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: calc(100% - 15px) center;
            cursor: pointer;
        }
        
        .checkout-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #4d5b9a 0%, #ffaa00 100%);
            border: none;
            border-radius: 12px;
            color: #050712;
            font-family: 'Orbitron', sans-serif;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 2px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(255, 170, 0, 0.25);
        }
        
        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(255, 170, 0, 0.45);
        }
        
        .secure-badge {
            text-align: center;
            margin-top: 18px;
            color: var(--muted);
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .secure-badge span {
            color: #00e676;
            font-weight: 700;
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
    </style>
</head>
<body>

    <div class="header">
        <h1>PLATAFY FB</h1>
        <p>Automatize seu Facebook com ferramentas profissionais de alta performance.</p>
    </div>
    
    <div class="checkout-container">
        <!-- COLUNA DA ESQUERDA: PLANOS -->
        <div class="plans-column">
            <!-- PLANO 1: MENSAL -->
            <div class="plan-card <?= $selectedPlan === 'mensal' ? 'selected' : '' ?>" onclick="selectPlan('mensal', this)">
                <div class="plan-info">
                    <div class="plan-title">PLANO MENSAL</div>
                    <div class="plan-desc">Acesso completo por 30 dias</div>
                </div>
                <div class="plan-price-tag">
                    <div class="plan-price-val">R$ 39<small>,00</small></div>
                    <div class="plan-price-sub">cobrado por mês</div>
                </div>
            </div>
            
            <!-- PLANO 2: SEMESTRAL -->
            <div class="plan-card <?= $selectedPlan === 'semestral' ? 'selected' : '' ?>" onclick="selectPlan('semestral', this)">
                <span class="badge">Mais Popular</span>
                <div class="plan-info">
                    <div class="plan-title">PLANO SEMESTRAL</div>
                    <div class="plan-desc">Acesso completo por 6 meses</div>
                </div>
                <div class="plan-price-tag">
                    <div class="plan-price-val">R$ 69<small>,00</small></div>
                    <div class="plan-price-sub">a cada 6 meses</div>
                </div>
            </div>
            
            <!-- PLANO 3: VITALÍCIO -->
            <div class="plan-card <?= $selectedPlan === 'vitalicio' ? 'selected' : '' ?>" onclick="selectPlan('vitalicio', this)">
                <span class="badge badge-green">Melhor Custo-Benefício</span>
                <div class="plan-info">
                    <div class="plan-title">PLANO VITALÍCIO</div>
                    <div class="plan-desc">Acesso permanente + Atualizações</div>
                </div>
                <div class="plan-price-tag">
                    <div class="plan-price-val">R$ 149<small>,90</small></div>
                    <div class="plan-price-sub">pagamento único</div>
                </div>
            </div>
        </div>
        
        <!-- COLUNA DA DIREITA: FORMULÁRIO COMPLETO -->
        <div class="form-card">
            <div class="form-title">FINALIZAR ASSINATURA</div>
            
            <?php if ($error): ?>
                <div class="error-msg"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" id="checkout-form">
                <div class="form-group">
                    <label>Seu Nome Completo</label>
                    <input type="text" name="name" placeholder="Digite seu nome completo" required autofocus value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Seu E-mail</label>
                    <input type="email" name="email" placeholder="seuemail@gmail.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>Seu WhatsApp</label>
                    <input type="tel" name="phone" id="phone" placeholder="(11) 99999-9999" required maxlength="15" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>Plano Selecionado</label>
                    <select name="plan" id="plan-select" required>
                        <option value="mensal" <?= $selectedPlan === 'mensal' ? 'selected' : '' ?>>Mensal - R$ 39,00/mês</option>
                        <option value="semestral" <?= $selectedPlan === 'semestral' ? 'selected' : '' ?>>Semestral - R$ 69,00/6 meses</option>
                        <option value="vitalicio" <?= $selectedPlan === 'vitalicio' ? 'selected' : '' ?>>Vitalício - R$ 149,90 (Único)</option>
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
        function selectPlan(plan, element) {
            document.getElementById('plan-select').value = plan;
            document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
            if (element) {
                element.classList.add('selected');
            }
        }
        
        // Mascara automatica de WhatsApp
        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                let v = e.target.value.replace(/\D/g, '');
                if (v.length > 11) v = v.substring(0, 11);
                if (v.length > 6) {
                    v = '(' + v.substring(0, 2) + ') ' + v.substring(2, 7) + '-' + v.substring(7);
                } else if (v.length > 2) {
                    v = '(' + v.substring(0, 2) + ') ' + v.substring(2);
                } else if (v.length > 0) {
                    v = '(' + v;
                }
                e.target.value = v;
            });
        }
    </script>
</body>
</html>
