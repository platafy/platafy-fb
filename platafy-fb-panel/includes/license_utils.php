<?php
/**
 * PLATAFY FB - Utilitários de Licença
 */

function generateLicenseKey() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sem I,O,0,1 para evitar confusão
    $key = '';
    for ($i = 0; $i < 16; $i++) {
        $key .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return substr($key, 0, 4) . '-' . substr($key, 4, 4) . '-' . substr($key, 8, 4) . '-' . substr($key, 12, 4);
}

function calculateExpiryDate($planType) {
    $plans = json_decode(PLANS, true);
    if (isset($plans[$planType])) {
        $days = $plans[$planType]['days'];
        return date('Y-m-d H:i:s', strtotime("+{$days} days"));
    }
    // Manual: 30 days default
    return date('Y-m-d H:i:s', strtotime('+30 days'));
}

function generateWhatsAppLink($phone, $clientName, $licenseKey, $planType, $expiresAt = null) {
    if (empty($phone)) return null;
    
    // Remover caracteres não numéricos
    $cleanPhone = preg_replace('/\D/', '', $phone);
    if (empty($cleanPhone)) return null;
    
    // Adicionar código do país DDI 55 (Brasil) se não houver
    if (strlen($cleanPhone) >= 10 && strlen($cleanPhone) <= 11 && !str_starts_with($cleanPhone, '55')) {
        $cleanPhone = '55' . $cleanPhone;
    }
    
    $plans = json_decode(PLANS, true);
    $planName = isset($plans[$planType]) ? $plans[$planType]['name'] : ucfirst($planType);
    $formattedExpiry = $expiresAt ? date('d/m/Y H:i', strtotime($expiresAt)) : 'Vitalício';
    
    $name = !empty($clientName) ? $clientName : 'Cliente';
    
    $message = "Olá, *{$name}*! 👋\n\n";
    $message .= "Sua licença do *PLATAFY FB* foi gerada com sucesso! 🎉\n\n";
    $message .= "🔑 *Chave de Ativação:* `{$licenseKey}`\n";
    $message .= "📦 *Plano:* {$planName}\n";
    $message .= "📅 *Validade:* {$formattedExpiry}\n\n";
    $message .= "*Como Ativar:*\n";
    $message .= "1. Abra a Extensão PLATAFY FB no Google Chrome\n";
    $message .= "2. Cole a chave de ativação quando solicitado\n\n";
    $message .= "Em caso de dúvidas, responda esta mensagem. Bons negócios! 🚀";
    
    return "https://api.whatsapp.com/send?phone={$cleanPhone}&text=" . urlencode($message);
}

function createLicense($clientName, $clientEmail, $planType, $mpSubscriptionId = null, $clientPhone = null) {
    $pdo = db();
    $key = generateLicenseKey();
    
    // Ensure unique key
    $maxRetries = 10;
    for ($i = 0; $i < $maxRetries; $i++) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM licenses WHERE license_key = ?");
        $stmt->execute([$key]);
        if ($stmt->fetchColumn() == 0) break;
        $key = generateLicenseKey();
    }
    
    $expiresAt = calculateExpiryDate($planType);
    
    $stmt = $pdo->prepare("
        INSERT INTO licenses (license_key, client_name, client_email, client_phone, plan_type, status, expires_at, mp_subscription_id, activated_at)
        VALUES (?, ?, ?, ?, ?, 'active', ?, ?, NOW())
    ");
    $stmt->execute([$key, $clientName, $clientEmail, $clientPhone, $planType, $expiresAt, $mpSubscriptionId]);
    
    $licenseId = $pdo->lastInsertId();
    
    logActivity($licenseId, 'created', "Licença criada: {$key} | Plano: {$planType}" . ($clientPhone ? " | WhatsApp: {$clientPhone}" : ""));
    
    $waLink = generateWhatsAppLink($clientPhone, $clientName, $key, $planType, $expiresAt);
    
    return [
        'id' => $licenseId,
        'key' => $key,
        'expires_at' => $expiresAt,
        'plan_type' => $planType,
        'client_name' => $clientName,
        'client_email' => $clientEmail,
        'client_phone' => $clientPhone,
        'whatsapp_link' => $waLink
    ];
}

function renewLicense($licenseId, $planType = null) {
    $pdo = db();
    
    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE id = ?");
    $stmt->execute([$licenseId]);
    $license = $stmt->fetch();
    
    if (!$license) return false;
    
    $plan = $planType ?: $license['plan_type'];
    $plans = json_decode(PLANS, true);
    $days = isset($plans[$plan]) ? $plans[$plan]['days'] : 30;
    
    // If expired, renew from now. If still active, extend from current expiry.
    $baseDate = ($license['status'] === 'expired' || strtotime($license['expires_at']) < time())
        ? date('Y-m-d H:i:s')
        : $license['expires_at'];
    
    $newExpiry = date('Y-m-d H:i:s', strtotime($baseDate . " +{$days} days"));
    
    $stmt = $pdo->prepare("UPDATE licenses SET status = 'active', expires_at = ?, plan_type = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$newExpiry, $plan, $licenseId]);
    
    logActivity($licenseId, 'renewed', "Licença renovada até {$newExpiry}");
    
    return $newExpiry;
}

function revokeLicense($licenseId) {
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE licenses SET status = 'revoked', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$licenseId]);
    logActivity($licenseId, 'revoked', 'Licença revogada pelo admin');
    return true;
}

function logActivity($licenseId, $action, $details, $ip = null) {
    $pdo = db();
    $ip = $ip ?: ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $stmt = $pdo->prepare("INSERT INTO activity_logs (license_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$licenseId, $action, $details, $ip]);
}

function checkExpiredLicenses() {
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE licenses SET status = 'expired' WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at < NOW()");
    $stmt->execute();
    return $stmt->rowCount();
}
