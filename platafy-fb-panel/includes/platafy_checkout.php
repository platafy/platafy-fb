<?php
/**
 * PLATAFY FB - Helper Checkout Platafy (API Pagamentos V1)
 * Documentação: POST /api/v1/checkout/sessions
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/settings_utils.php';

function getPlatafyCheckoutUrl() {
    $url = getSetting('checkout_platafy_url', defined('CHECKOUT_PLATAFY_URL') ? CHECKOUT_PLATAFY_URL : 'https://checkout.platafy.com');
    return rtrim($url, '/');
}

function getPlatafyApiKey() {
    return getSetting('checkout_platafy_api_key', defined('CHECKOUT_PLATAFY_API_KEY') ? CHECKOUT_PLATAFY_API_KEY : '');
}

function getPlatafyWebhookSecret() {
    return getSetting('checkout_platafy_webhook_secret', defined('CHECKOUT_PLATAFY_WEBHOOK_SECRET') ? CHECKOUT_PLATAFY_WEBHOOK_SECRET : '');
}

function getActivePaymentGateway() {
    return getSetting('default_payment_gateway', 'platafy'); // 'platafy' ou 'mercadopago'
}

/**
 * Executa requisição HTTP cURL para a API do Checkout Platafy
 */
function platafyApiRequest($endpoint, $method = 'POST', $data = null) {
    $baseUrl = getPlatafyCheckoutUrl();
    $apiKey = getPlatafyApiKey();

    if (empty($apiKey)) {
        return [
            'success' => false,
            'error' => 'Chave de API do Checkout Platafy não configurada no painel.',
            'http_code' => 0
        ];
    }

    $url = $baseUrl . $endpoint;
    $ch = curl_init();

    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json',
        'Content-Type: application/json'
    ];

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'GET') {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    } else {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("[PLATAFY Checkout API] cURL error: " . $curlError);
        return [
            'success' => false,
            'error' => 'Falha de comunicação com o Checkout Platafy: ' . $curlError,
            'http_code' => 0
        ];
    }

    $result = json_decode($response, true);
    if (!is_array($result)) {
        return [
            'success' => false,
            'error' => 'Resposta inválida do servidor de checkout (HTTP ' . $httpCode . ')',
            'raw_response' => $response,
            'http_code' => $httpCode
        ];
    }

    $result['_http_code'] = $httpCode;
    $result['success'] = ($httpCode >= 200 && $httpCode < 300);

    return $result;
}

/**
 * Cria uma sessão de checkout hospedada no Checkout Platafy
 * 
 * @param array $customer ['name' => ..., 'email' => ..., 'phone' => ...]
 * @param float $amount Valor em reais (ex: 39.90)
 * @param string $planKey 'mensal', 'semestral', 'vitalicio'
 * @param array $license Dados da licença gerada previamente
 * @param string|null $returnUrl URL de redirecionamento após o pagamento
 * @return array
 */
function createPlatafyCheckoutSession($customer, $amount, $planKey, $license, $returnUrl = null) {
    $licenseId = $license['id'] ?? null;
    $licenseKey = $license['key'] ?? ($license['license_key'] ?? '');

    if (!$returnUrl) {
        $returnUrl = SITE_URL . '/checkout/obrigado.php?license_key=' . urlencode($licenseKey);
    }

    $payload = [
        'customer' => [
            'name'  => $customer['name'] ?? 'Cliente',
            'email' => $customer['email'] ?? '',
            'phone' => $customer['phone'] ?? null,
            'cpf'   => $customer['cpf'] ?? null
        ],
        'amount'   => (float)$amount,
        'currency' => 'BRL',
        'metadata' => [
            'license_id'   => (int)$licenseId,
            'license_key'  => (string)$licenseKey,
            'plan_key'     => (string)$planKey,
            'origin'       => 'platafy_fb',
            'client_phone' => $customer['phone'] ?? null
        ],
        'return_url' => $returnUrl,
        'expires_in' => 60 // 60 minutos
    ];

    $res = platafyApiRequest('/api/v1/checkout/sessions', 'POST', $payload);

    if (!empty($res['checkout_url'])) {
        return [
            'success'      => true,
            'checkout_url' => $res['checkout_url'],
            'session_id'   => $res['session_id'] ?? null,
            'expires_at'   => $res['expires_at'] ?? null
        ];
    }

    $errorMessage = $res['message'] ?? ($res['error'] ?? 'Não foi possível gerar a sessão de checkout.');
    error_log("[PLATAFY Checkout API] Erro ao criar sessão: " . json_encode($res));

    return [
        'success' => false,
        'error'   => $errorMessage,
        'raw'     => $res
    ];
}
