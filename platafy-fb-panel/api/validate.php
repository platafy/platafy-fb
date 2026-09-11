<?php
/**
 * PLATAFY FB - API: Validar / Ativar Licença (Extensão v2)
 * POST /api/validate
 * 
 * Endpoint chamado pela extensão Chrome (popup.js e background.js).
 * Recebe { license_key, device_id } no body JSON.
 * Retorna { valid: bool, message, customer_name, validity_period, validity_days, expires_at }.
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
    echo json_encode(['valid' => false, 'message' => 'Código de licença não informado.']);
    exit;
}

// Atualizar licenças expiradas
checkExpiredLicenses();

try {
    $pdo = db();

    // Buscar licença
    $cleanKey = str_replace(['-', ' '], '', strtoupper($licenseKey));
    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ? OR REPLACE(REPLACE(license_key, '-', ''), ' ', '') = ?");
    $stmt->execute([$licenseKey, $cleanKey]);
    $license = $stmt->fetch();

    if (!$license) {
        echo json_encode(['valid' => false, 'message' => 'Licença não encontrada.']);
        exit;
    }

    // Verificar se revogada
    if ($license['status'] === 'revoked') {
        echo json_encode(['valid' => false, 'message' => 'Esta licença foi revogada. Entre em contato com o suporte.']);
        exit;
    }

    // Verificar se expirada
    if ($license['status'] === 'expired' || ($license['expires_at'] && strtotime($license['expires_at']) < time())) {
        $pdo->prepare("UPDATE licenses SET status = 'expired' WHERE id = ?")->execute([$license['id']]);
        echo json_encode(['valid' => false, 'message' => 'Sua licença expirou. Renove para continuar.']);
        exit;
    }

    // Verificar device_id (hwid) — se hwid diferente, atualizar para permitir reconexão sem travar o cliente
    if (!empty($deviceId) && (empty($license['hwid']) || $license['hwid'] !== $deviceId)) {
        $stmtUpdateHwid = $pdo->prepare("UPDATE licenses SET hwid = ? WHERE id = ?");
        $stmtUpdateHwid->execute([$deviceId, $license['id']]);
        $license['hwid'] = $deviceId;
    }

    // Ativar / atualizar licença
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $pdo->prepare("
        UPDATE licenses SET
            status       = 'active',
            hwid         = COALESCE(?, hwid),
            last_check_at = NOW(),
            last_ip      = ?,
            activated_at = COALESCE(activated_at, NOW()),
            updated_at   = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$deviceId ?: null, $clientIp, $license['id']]);

    // Log
    logActivity($license['id'], 'validated', "Licença validada | Device: {$deviceId} | IP: {$clientIp}", $clientIp);

    // Verificar se a licença pertence a um Parceiro White Label
    $brandData = [
        'is_white_label'   => false,
        'brand_name'       => 'PLATAFY FB',
        'brand_logo'       => null,
        'support_whatsapp' => '5521967659802',
        'support_url'      => 'https://api.whatsapp.com/send?phone=5521967659802'
    ];

    if (!empty($license['partner_id'])) {
        try {
            $stmtPartner = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
            $stmtPartner->execute([$license['partner_id']]);
            $partner = $stmtPartner->fetch();

            if ($partner) {
                if ($partner['status'] !== 'active') {
                    echo json_encode([
                        'valid' => false,
                        'message' => 'Esta licença foi temporariamente suspensa pelo parceiro responsável.'
                    ]);
                    exit;
                }

                if (!empty($partner['expires_at']) && strtotime($partner['expires_at']) < time()) {
                    echo json_encode([
                        'valid' => false,
                        'message' => 'A assinatura do parceiro expirou. Entre em contato com o suporte.'
                    ]);
                    exit;
                }

                $brandData = [
                    'is_white_label'   => true,
                    'brand_name'       => $partner['brand_name'] ?: 'PLATAFY FB',
                    'brand_logo'       => $partner['brand_logo_url'] ?: null,
                    'support_whatsapp' => $partner['support_whatsapp'] ?: null,
                    'support_url'      => $partner['support_url'] ?: ($partner['support_whatsapp'] ? 'https://api.whatsapp.com/send?phone=' . preg_replace('/\D/', '', $partner['support_whatsapp']) : null)
                ];
            }
        } catch (Exception $pe) {
            // Em caso de falha de consulta ao parceiro, não derruba a validação
            error_log("[PLATAFY Validate] Partner check error: " . $pe->getMessage());
        }
    }

    // Mapear plan_type do banco para o validity_period que a extensão espera
    $validityPeriod = mapPlanToValidityPeriod($license['plan_type']);
    $validityDays   = calculateValidityDays($license['expires_at']);

    echo json_encode([
        'valid'           => true,
        'message'         => 'Licença ativa e válida.',
        'customer_name'   => $license['client_name'] ?? '',
        'validity_period' => $validityPeriod,
        'validity_days'   => $validityDays,
        'expires_at'      => $license['expires_at'] ?? '',
        'brand'           => $brandData
    ]);

} catch (Exception $e) {
    error_log("[PLATAFY] validate error: " . $e->getMessage());
    echo json_encode(['valid' => false, 'message' => 'Erro interno do servidor.']);
}

/**
 * Mapeia o plan_type do banco de dados para o validity_period que a extensão espera.
 * 
 * Extensão espera: weekly, monthly, annual, custom_days, lifetime
 * Banco possui:    mensal, trimestral, semestral, anual, manual, vitalicio
 */
function mapPlanToValidityPeriod($planType) {
    $map = [
        'mensal'      => 'monthly',
        'trimestral'  => 'custom_days',
        'semestral'   => 'custom_days',
        'anual'       => 'annual',
        'manual'      => 'custom_days',
        'vitalicio'   => 'lifetime',
    ];
    return $map[$planType] ?? 'custom_days';
}

/**
 * Calcula os dias restantes até a expiração.
 */
function calculateValidityDays($expiresAt) {
    if (empty($expiresAt)) return 0;
    $expiry = strtotime($expiresAt);
    if (!$expiry) return 0;
    $remaining = max(0, ceil(($expiry - time()) / 86400));
    return (int) $remaining;
}
