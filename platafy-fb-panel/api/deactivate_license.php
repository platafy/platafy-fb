<?php
/**
 * PLATAFY FB - API: Desativar / Liberar Licença do Dispositivo
 * POST /api/deactivate_license.php
 * 
 * Permite que o cliente desvincule o HWID do seu dispositivo atual para
 * poder ativar a licença em um novo computador (Transferência de Computador).
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

$input = json_decode(file_get_contents('php://input'), true);

$licenseCode = $input['license_code'] ?? '';

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
    
    // Resetar HWID no banco para permitir ativação em novo PC
    $stmt = $pdo->prepare("UPDATE licenses SET hwid = NULL, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$license['id']]);
    
    // Registrar atividade
    logActivity($license['id'], 'deactivated_by_user', "Licença desvinculada pelo usuário | HWID antigo: {$hwid} | IP: {$clientIp}", $clientIp);
    
    echo json_encode([
        'status' => true,
        'message' => 'Licença desvinculada com sucesso! Agora você pode ativá-la no seu novo computador.'
    ]);
    
} catch (Exception $e) {
    error_log("[PLATAFY Deactivate License Error] " . $e->getMessage());
    echo json_encode(['status' => false, 'message' => 'Erro interno ao desativar licença.']);
}
