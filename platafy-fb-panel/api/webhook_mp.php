<?php
/**
 * PLATAFY FB - Webhook Mercado Pago
 * Recebe notificações automáticas de pagamentos e assinaturas
 * URL: https://platafyfb.platafy.com/api/webhook_mp.php
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/license_utils.php';
require_once __DIR__ . '/../includes/mercadopago.php';

// Responder 200 imediatamente para o MP não reenviar
http_response_code(200);

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['type'])) {
    echo json_encode(['received' => true]);
    exit;
}

$type = $input['type'];
$dataId = $input['data']['id'] ?? null;

error_log("[PLATAFY Webhook] Type: {$type} | ID: {$dataId}");

try {
    $pdo = db();
    
    switch ($type) {
        // ========== ASSINATURA ATUALIZADA ==========
        case 'subscription_preapproval':
            if (!$dataId) break;
            
            $subscription = getMPSubscription($dataId);
            if (!$subscription || isset($subscription['error'])) break;
            
            $subStatus = $subscription['status'] ?? '';
            $payerEmail = $subscription['payer_email'] ?? '';
            
            // Buscar licença vinculada
            $stmt = $pdo->prepare("SELECT * FROM licenses WHERE mp_subscription_id = ?");
            $stmt->execute([$dataId]);
            $license = $stmt->fetch();
            
            if ($subStatus === 'authorized' || $subStatus === 'active') {
                if (!$license) {
                    // Nova assinatura — criar licença automaticamente
                    $planType = detectPlanFromSubscription($subscription);
                    $result = createLicense(
                        $subscription['reason'] ?? 'Cliente MP',
                        $payerEmail,
                        $planType,
                        $dataId
                    );
                    
                    // Atualizar email do pagador
                    $pdo->prepare("UPDATE licenses SET mp_payer_email = ? WHERE id = ?")->execute([$payerEmail, $result['id']]);
                    
                    error_log("[PLATAFY Webhook] Nova licença criada: {$result['key']} para {$payerEmail}");
                    
                    // TODO: Enviar e-mail com a chave para o cliente
                } else {
                    // Assinatura reativada
                    $pdo->prepare("UPDATE licenses SET status = 'active', updated_at = NOW() WHERE id = ?")->execute([$license['id']]);
                    logActivity($license['id'], 'subscription_reactivated', "Assinatura reativada via MP");
                }
            }
            
            if ($subStatus === 'cancelled') {
                if ($license) {
                    $pdo->prepare("UPDATE licenses SET status = 'revoked', updated_at = NOW() WHERE id = ?")->execute([$license['id']]);
                    logActivity($license['id'], 'subscription_cancelled', "Assinatura cancelada via MP");
                }
            }
            
            if ($subStatus === 'paused') {
                if ($license) {
                    $pdo->prepare("UPDATE licenses SET status = 'inactive', updated_at = NOW() WHERE id = ?")->execute([$license['id']]);
                    logActivity($license['id'], 'subscription_paused', "Assinatura pausada via MP");
                }
            }
            break;
            
        // ========== PAGAMENTO DE ASSINATURA ==========
        case 'subscription_authorized_payment':
            if (!$dataId) break;
            
            $payment = getMPPayment($dataId);
            if (!$payment || isset($payment['error'])) break;
            
            $payStatus = $payment['status'] ?? '';
            $amount = $payment['transaction_amount'] ?? 0;
            $subId = $payment['metadata']['preapproval_id'] ?? null;
            $payerEmail = $payment['payer']['email'] ?? '';
            
            // Buscar licença
            $license = null;
            if ($subId) {
                $stmt = $pdo->prepare("SELECT * FROM licenses WHERE mp_subscription_id = ?");
                $stmt->execute([$subId]);
                $license = $stmt->fetch();
            }
            
            // Registrar pagamento
            $pdo->prepare("
                INSERT INTO payments (license_id, mp_payment_id, mp_subscription_id, amount, status, payer_email, payment_method, payment_date, raw_data)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ")->execute([
                $license ? $license['id'] : null,
                $dataId,
                $subId,
                $amount,
                $payStatus,
                $payerEmail,
                $payment['payment_method_id'] ?? 'unknown',
                json_encode($payment)
            ]);
            
            if ($payStatus === 'approved' && $license) {
                // Renovar licença
                renewLicense($license['id']);
                logActivity($license['id'], 'payment_approved', "Pagamento aprovado: R$ {$amount}");
            }
            
            if ($payStatus === 'rejected' && $license) {
                logActivity($license['id'], 'payment_rejected', "Pagamento rejeitado: R$ {$amount}");
            }
            break;
            
        // ========== PAGAMENTO COMUM (não assinatura) ==========
        case 'payment':
            // Não usado atualmente, mas registrado para futuro
            error_log("[PLATAFY Webhook] Payment notification: {$dataId}");
            break;
    }
    
} catch (Exception $e) {
    error_log("[PLATAFY Webhook] Error: " . $e->getMessage());
}

echo json_encode(['received' => true]);

// ========== HELPERS ==========
function detectPlanFromSubscription($subscription) {
    $amount = $subscription['auto_recurring']['transaction_amount'] ?? 0;
    $frequency = $subscription['auto_recurring']['frequency'] ?? 1;
    
    $plans = json_decode(PLANS, true);
    foreach ($plans as $key => $plan) {
        if ($plan['price'] == $amount && $plan['frequency'] == $frequency) {
            return $key;
        }
    }
    return 'mensal';
}
