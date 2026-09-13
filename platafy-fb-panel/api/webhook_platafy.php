<?php
/**
 * PLATAFY FB - Webhook Checkout Platafy
 * Recebe notificações automáticas de pagamentos e assinaturas do Checkout Platafy
 * URL: https://fb.platafy.com/api/webhook_platafy.php
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/license_utils.php';
require_once __DIR__ . '/../includes/platafy_checkout.php';

// Capturar payload bruto para validação de assinatura
$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody, true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload JSON inválido']);
    exit;
}

// 1. Validação de Assinatura ou Bearer Token (aceita webhooks de Aplicação de API ou Webhooks Gerais do Produto)
$secret = getPlatafyWebhookSecret();
$apiKey = getPlatafyApiKey();

if (!empty($secret) || !empty($apiKey)) {
    $incomingSig = $_SERVER['HTTP_X_GETFY_SIGNATURE'] ?? '';
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    $bearerToken = '';
    if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        $bearerToken = $matches[1];
    }

    // Se veio cabeçalho de assinatura HMAC SHA256 (Padrão API Application)
    if (!empty($incomingSig)) {
        $calculatedSig = hash_hmac('sha256', $rawBody, $secret);
        if (!hash_equals($calculatedSig, $incomingSig)) {
            error_log("[PLATAFY Webhook] Assinatura X-Getfy-Signature inválida.");
            http_response_code(401);
            echo json_encode(['error' => 'Assinatura inválida']);
            exit;
        }
    } 
    // Se veio Bearer Token (Padrão Webhooks de Produto/Loja do Getfy)
    elseif (!empty($bearerToken)) {
        if ($bearerToken !== $secret && $bearerToken !== $apiKey) {
            error_log("[PLATAFY Webhook] Bearer Token do Webhook inválido.");
            http_response_code(401);
            echo json_encode(['error' => 'Token de autenticação inválido']);
            exit;
        }
    }
}

// Responder 200 antecipado ou continuar processamento
http_response_code(200);

$event = $input['event'] ?? '';
$orderId = $input['order_id'] ?? ($input['payload']['order']['id'] ?? null);
$payload = $input['payload'] ?? [];
$order = $payload['order'] ?? [];
$customer = $payload['customer'] ?? [];
$payment = $payload['payment'] ?? [];

error_log("[PLATAFY Webhook] Evento: {$event} | Ordem: {$orderId}");

try {
    $pdo = db();

    switch ($event) {
        // ============================================================
        // PEDIDO / PAGAMENTO APROVADO
        // ============================================================
        case 'order.completed':
        case 'order.paid':
        case 'payment.approved':
            $amount = (float)($order['total'] ?? ($order['amount'] ?? 0));
            $payerEmail = trim($customer['email'] ?? ($order['email'] ?? ''));
            $payerName = trim($customer['name'] ?? ($order['name'] ?? 'Cliente'));
            $payerPhone = trim($customer['phone'] ?? ($order['phone'] ?? ''));
            $paymentMethod = $payment['method'] ?? ($order['payment_method'] ?? 'checkout_platafy');

            // Metadados injetados na criação da sessão
            $metadata = $order['metadata'] ?? [];
            if (!is_array($metadata)) $metadata = [];

            $licenseId = !empty($metadata['license_id']) ? (int)$metadata['license_id'] : null;
            $planKey = !empty($metadata['plan_key']) ? trim($metadata['plan_key']) : null;

            // Se o planKey não foi enviado no metadata, tentar detectar pelo produto, oferta ou valor
            if (!$planKey) {
                $planKey = detectPlan($amount, $payload);
            }

            $license = null;

            // 1. Tentar localizar licença por ID de metadata
            if ($licenseId) {
                $stmt = $pdo->prepare("SELECT * FROM licenses WHERE id = ?");
                $stmt->execute([$licenseId]);
                $license = $stmt->fetch();
            }

            // 2. Se não achou por ID, buscar por e-mail do cliente
            if (!$license && !empty($payerEmail)) {
                $stmt = $pdo->prepare("SELECT * FROM licenses WHERE client_email = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$payerEmail]);
                $license = $stmt->fetch();
            }

            if ($license) {
                // ========================================================
                // ATIVAR OU RENOVAR LICENÇA EXISTENTE
                // ========================================================
                $newExpiry = calculateExpiryDate($planKey);

                $stmtUp = $pdo->prepare("
                    UPDATE licenses SET 
                        status = 'active',
                        plan_type = ?,
                        expires_at = ?,
                        client_name = COALESCE(NULLIF(client_name, ''), ?),
                        client_phone = COALESCE(NULLIF(client_phone, ''), ?),
                        activated_at = COALESCE(activated_at, NOW()),
                        mp_subscription_id = COALESCE(?, mp_subscription_id),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmtUp->execute([
                    $planKey,
                    $newExpiry,
                    $payerName,
                    $payerPhone,
                    (string)$orderId,
                    $license['id']
                ]);

                // Registrar histórico de pagamento
                $pdo->prepare("
                    INSERT INTO payments (license_id, mp_payment_id, amount, status, payer_email, payment_method, payment_date, raw_data)
                    VALUES (?, ?, ?, 'approved', ?, ?, NOW(), ?)
                ")->execute([
                    $license['id'],
                    (string)$orderId,
                    $amount,
                    $payerEmail,
                    $paymentMethod,
                    $rawBody
                ]);

                logActivity(
                    $license['id'], 
                    'payment_approved', 
                    "Pagamento aprovado via Checkout Platafy: R$ {$amount} | Pedido #{$orderId} | Chave: {$license['license_key']}"
                );

                error_log("[PLATAFY Webhook] Licença #{$license['id']} ativada com sucesso para {$payerEmail}!");

            } else {
                // ========================================================
                // CRIAR NOVA LICENÇA AUTOMATICAMENTE (Venda Direta)
                // ========================================================
                $created = createLicense(
                    $payerName,
                    $payerEmail,
                    $planKey,
                    (string)$orderId,
                    $payerPhone
                );

                // Registrar histórico de pagamento
                $pdo->prepare("
                    INSERT INTO payments (license_id, mp_payment_id, amount, status, payer_email, payment_method, payment_date, raw_data)
                    VALUES (?, ?, ?, 'approved', ?, ?, NOW(), ?)
                ")->execute([
                    $created['id'],
                    (string)$orderId,
                    $amount,
                    $payerEmail,
                    $paymentMethod,
                    $rawBody
                ]);

                logActivity(
                    $created['id'],
                    'payment_approved',
                    "Nova licença gerada automaticamente via Checkout Platafy: R$ {$amount} | Pedido #{$orderId} | Chave: {$created['key']}"
                );

                error_log("[PLATAFY Webhook] Nova licença #{$created['id']} gerada automaticamente: {$created['key']} para {$payerEmail}");
            }
            break;

        // ============================================================
        // REEMBOLSO / CHARGEBACK
        // ============================================================
        case 'order.refunded':
        case 'order.chargeback':
            if ($orderId) {
                $stmt = $pdo->prepare("SELECT * FROM licenses WHERE mp_subscription_id = ?");
                $stmt->execute([(string)$orderId]);
                $lic = $stmt->fetch();

                if ($lic) {
                    $pdo->prepare("UPDATE licenses SET status = 'revoked', updated_at = NOW() WHERE id = ?")->execute([$lic['id']]);
                    logActivity($lic['id'], 'license_revoked', "Licença revogada por reembolso/chargeback no Checkout Platafy (Pedido #{$orderId})");
                    error_log("[PLATAFY Webhook] Licença #{$lic['id']} revogada por reembolso.");
                }
            }
            break;
    }

} catch (Exception $e) {
    error_log("[PLATAFY Webhook] Erro ao processar webhook: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

echo json_encode(['success' => true, 'received' => true]);

/**
 * Detecta o plano de forma inteligente pelo nome da oferta, produto ou valor
 */
function detectPlan($amount, $payload = []) {
    $searchString = strtolower(
        ($payload['plan']['name'] ?? '') . ' ' .
        ($payload['offer']['name'] ?? '') . ' ' .
        ($payload['product']['name'] ?? '') . ' ' .
        ($payload['order']['description'] ?? '')
    );

    if (str_contains($searchString, 'vitalici') || str_contains($searchString, 'vitalício') || str_contains($searchString, 'lifetime')) {
        return 'vitalicio';
    }
    if (str_contains($searchString, 'semestral') || str_contains($searchString, '6 meses') || str_contains($searchString, 'pro')) {
        return 'semestral';
    }
    if (str_contains($searchString, 'mensal') || str_contains($searchString, '30 dias') || str_contains($searchString, 'starter')) {
        return 'mensal';
    }

    return detectPlanByAmount($amount);
}

/**
 * Detecta o plano mais próximo com base no valor pago
 */
function detectPlanByAmount($amount) {
    $plans = json_decode(PLANS, true);
    if (!is_array($plans)) return 'mensal';

    $closestPlan = 'mensal';
    $minDiff = PHP_FLOAT_MAX;

    foreach ($plans as $key => $plan) {
        $diff = abs((float)$plan['price'] - (float)$amount);
        if ($diff < $minDiff) {
            $minDiff = $diff;
            $closestPlan = $key;
        }
    }

    return $closestPlan;
}
