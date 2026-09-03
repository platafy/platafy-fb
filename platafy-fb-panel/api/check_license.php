<?php
/**
 * PLATAFY FB - API: Revalidação de Licença em Tempo Real
 * POST /api/check_license.php
 * 
 * Chamado em segundo plano pela extensão para verificar se a licença
 * continua ativa, não expirou e se o HWID confere.
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
    echo json_encode(['status' => false, 'active' => false, 'message' => 'Chave de API inválida.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$licenseCode = $input['license_code'] ?? '';

// Extrair HWID do header LB-URL
$lbUrl = $_SERVER['HTTP_LB_URL'] ?? '';
$hwid = '';
if (preg_match('/^https?:\/\/(.+)\.hwid$/', $lbUrl, $matches)) {
    $hwid = $matches[1];
}

if (empty($licenseCode)) {
    echo json_encode(['status' => false, 'active' => false, 'message' => 'Código de licença não informado.']);
    exit;
}

// Atualizar licenças expiradas no banco
checkExpiredLicenses();

try {
    $pdo = db();
    
    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ?");
    $stmt->execute([$licenseCode]);
    $license = $stmt->fetch();
    
    if (!$license) {
        echo json_encode(['status' => false, 'active' => false, 'code' => 'NOT_FOUND', 'message' => 'Licença não encontrada.']);
        exit;
    }
    
    if ($license['status'] === 'revoked') {
        echo json_encode(['status' => false, 'active' => false, 'code' => 'REVOKED', 'message' => 'Sua licença foi revogada pelo administrador.']);
        exit;
    }
    
    if ($license['status'] === 'expired' || ($license['expires_at'] && strtotime($license['expires_at']) < time())) {
        $pdo->prepare("UPDATE licenses SET status = 'expired' WHERE id = ?")->execute([$license['id']]);
        echo json_encode(['status' => false, 'active' => false, 'code' => 'EXPIRED', 'message' => 'Sua licença expirou. Renove para continuar.']);
        exit;
    }
    
    if ($license['status'] !== 'active') {
        echo json_encode(['status' => false, 'active' => false, 'code' => 'INACTIVE', 'message' => 'Sua licença está inativa.']);
        exit;
    }
    
    // Verificar conflito de HWID (se a licença foi ativada em outro dispositivo)
    if (!empty($license['hwid']) && !empty($hwid) && $license['hwid'] !== $hwid) {
        echo json_encode([
            'status' => false, 
            'active' => false, 
            'code' => 'HWID_MISMATCH', 
            'message' => 'Licença ativa em outro computador. Faça a transferência para este dispositivo.'
        ]);
        exit;
    }
    
    // Atualizar último check
    $pdo->prepare("UPDATE licenses SET last_check_at = NOW() WHERE id = ?")->execute([$license['id']]);
    
    echo json_encode([
        'status' => true,
        'active' => true,
        'message' => 'Licença ativa e válida.',
        'expires_at' => $license['expires_at'],
        'client_name' => $license['client_name']
    ]);
    
} catch (Exception $e) {
    error_log("[PLATAFY Check License Error] " . $e->getMessage());
    echo json_encode(['status' => false, 'active' => false, 'message' => 'Erro interno ao validar licença.']);
}
