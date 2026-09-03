<?php
/**
 * PLATAFY FB - API: Ativar/Validar Licença
 * POST /api/activate_license.php
 * 
 * Este endpoint é chamado pelo background.js da extensão Chrome.
 * Mantém compatibilidade com o formato LB (License Box).
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, LB-API-KEY, LB-URL, LB-IP');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/license_utils.php';

// Verificar API Key
$apiKey = $_SERVER['HTTP_LB_API_KEY'] ?? '';
if ($apiKey !== API_KEY) {
    echo json_encode(['status' => false, 'message' => 'Chave de API inválida.']);
    exit;
}

// Ler dados
$input = json_decode(file_get_contents('php://input'), true);

$licenseCode = $input['license_code'] ?? '';
$clientName = $input['client_name'] ?? 'Cliente PLATAFY';
$productId = $input['product_id'] ?? '';

// Extrair HWID do header LB-URL
$lbUrl = $_SERVER['HTTP_LB_URL'] ?? '';
$hwid = '';
if (preg_match('/^https?:\/\/(.+)\.hwid$/', $lbUrl, $matches)) {
    $hwid = $matches[1];
}

$clientIp = $_SERVER['HTTP_LB_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (empty($licenseCode)) {
    echo json_encode(['status' => false, 'message' => 'Código de licença não informado.']);
    exit;
}

// Atualizar licenças expiradas
checkExpiredLicenses();

try {
    $pdo = db();
    
    // Buscar licença
    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ?");
    $stmt->execute([$licenseCode]);
    $license = $stmt->fetch();
    
    if (!$license) {
        echo json_encode(['status' => false, 'message' => 'Licença não encontrada.']);
        exit;
    }
    
    // Verificar se revogada
    if ($license['status'] === 'revoked') {
        echo json_encode(['status' => false, 'message' => 'Esta licença foi revogada. Entre em contato com o suporte.']);
        exit;
    }
    
    // Verificar se expirada
    if ($license['status'] === 'expired' || ($license['expires_at'] && strtotime($license['expires_at']) < time())) {
        $pdo->prepare("UPDATE licenses SET status = 'expired' WHERE id = ?")->execute([$license['id']]);
        echo json_encode(['status' => false, 'message' => 'Sua licença expirou. Renove para continuar.']);
        exit;
    }
    
    // Verificar HWID (se já vinculado a outra máquina)
    if (!empty($license['hwid']) && !empty($hwid) && $license['hwid'] !== $hwid) {
        echo json_encode([
            'status' => false, 
            'message' => 'Esta licença já está vinculada a outro computador. Desative a licença no computador antigo ou solicite a liberação ao suporte para transferi-la.'
        ]);
        exit;
    }
    
    // Ativar/atualizar licença
    $stmt = $pdo->prepare("
        UPDATE licenses SET 
            status = 'active',
            hwid = COALESCE(?, hwid),
            client_name = ?,
            last_check_at = NOW(),
            last_ip = ?,
            activated_at = COALESCE(activated_at, NOW()),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$hwid ?: null, $clientName, $clientIp, $license['id']]);
    
    // Log
    logActivity($license['id'], 'activated', "Licença ativada | HWID: {$hwid} | IP: {$clientIp}", $clientIp);
    
    echo json_encode([
        'status' => true,
        'message' => 'Licença ativada com sucesso!',
        'end_date' => $license['expires_at'] ? date('Y-m-d', strtotime($license['expires_at'])) : null
    ]);
    
} catch (Exception $e) {
    error_log("[PLATAFY] activate_license error: " . $e->getMessage());
    echo json_encode(['status' => false, 'message' => 'Erro interno do servidor.']);
}
