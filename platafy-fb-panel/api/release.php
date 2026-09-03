<?php
/**
 * PLATAFY FB - API: Liberar / Desativar Licença (Extensão v2)
 * POST /api/release.php
 * 
 * Endpoint chamado pela extensão Chrome (popup.js) ao desativar a licença
 * ou pelo painel para liberar vinculação de HWID.
 * Recebe { license_key, device_id } no body JSON.
 * Limpa o HWID (desvincula o dispositivo) permitindo nova ativação.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/license_utils.php';

// Ler dados do body JSON
$input = json_decode(file_get_contents('php://input'), true);

$licenseKey = trim($input['license_key'] ?? '');
$deviceId   = trim($input['device_id'] ?? '');

if (empty($licenseKey)) {
    echo json_encode(['success' => false, 'message' => 'Código de licença não informado.']);
    exit;
}

try {
    $pdo = db();

    // Buscar licença
    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ?");
    $stmt->execute([$licenseKey]);
    $license = $stmt->fetch();

    if (!$license) {
        echo json_encode(['success' => false, 'message' => 'Licença não encontrada.']);
        exit;
    }

    // Limpar HWID (desvincula o dispositivo)
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $pdo->prepare("
        UPDATE licenses SET
            hwid       = NULL,
            last_ip    = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$clientIp, $license['id']]);

    // Log
    logActivity($license['id'], 'released', "Dispositivo desvinculado | Device: {$deviceId} | IP: {$clientIp}", $clientIp);

    echo json_encode([
        'success' => true,
        'message' => 'Licença desvinculada com sucesso.'
    ]);

} catch (Exception $e) {
    error_log("[PLATAFY] release error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
}
