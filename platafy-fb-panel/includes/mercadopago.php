<?php
/**
 * PLATAFY FB - Helper Mercado Pago (cURL)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/settings_utils.php';

function getMPToken() {
    $useTest = getSetting('mp_use_test', defined('MP_USE_TEST') ? (MP_USE_TEST ? 'true' : 'false') : 'true');
    $isTest = ($useTest === 'true' || $useTest === '1' || $useTest === true);
    
    if ($isTest) {
        return getSetting('mp_access_token_test', defined('MP_ACCESS_TOKEN_TEST') ? MP_ACCESS_TOKEN_TEST : '');
    } else {
        return getSetting('mp_access_token', defined('MP_ACCESS_TOKEN') ? MP_ACCESS_TOKEN : '');
    }
}

function mpRequest($endpoint, $method = 'GET', $data = null) {
    $url = "https://api.mercadopago.com" . $endpoint;
    $token = getMPToken();
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'X-Idempotency-Key: ' . uniqid('pf_', true)
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("[PLATAFY MP] cURL error: " . $error);
        return ['error' => $error, 'http_code' => 0];
    }
    
    $result = json_decode($response, true);
    $result['_http_code'] = $httpCode;
    return $result;
}

function createMPPlan($planKey) {
    $plans = json_decode(PLANS, true);
    if (!isset($plans[$planKey])) return null;
    
    $plan = $plans[$planKey];
    
    $data = [
        'reason' => 'PLATAFY FB - ' . $plan['name'],
        'auto_recurring' => [
            'frequency' => $plan['frequency'],
            'frequency_type' => $plan['frequency_type'],
            'transaction_amount' => $plan['price'],
            'currency_id' => 'BRL'
        ],
        'back_url' => CHECKOUT_BACK_URL
    ];
    
    return mpRequest('/preapproval_plan', 'POST', $data);
}

function createMPSubscription($planId, $payerEmail, $clientName = '') {
    $data = [
        'preapproval_plan_id' => $planId,
        'payer_email' => $payerEmail,
        'reason' => 'PLATAFY FB - Assinatura',
        'back_url' => CHECKOUT_BACK_URL,
        'status' => 'pending'
    ];
    
    if ($clientName) {
        $data['card_token_id'] = null; // Will redirect to MP checkout
    }
    
    return mpRequest('/preapproval', 'POST', $data);
}

function getMPSubscription($subscriptionId) {
    return mpRequest("/preapproval/{$subscriptionId}");
}

function cancelMPSubscription($subscriptionId) {
    return mpRequest("/preapproval/{$subscriptionId}", 'PUT', ['status' => 'cancelled']);
}

function getMPPayment($paymentId) {
    return mpRequest("/v1/payments/{$paymentId}");
}
