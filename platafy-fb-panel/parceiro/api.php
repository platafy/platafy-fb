<?php
/**
 * PLATAFY FB - API do Parceiro White Label
 * Acesso exclusivo e isolado para o parceiro autenticado
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/license_utils.php';

requirePartner();

$partnerId = (int)$_SESSION['partner_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $pdo = db();
    checkExpiredLicenses();

    // Carregar dados atualizados do parceiro
    $partnerStmt = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
    $partnerStmt->execute([$partnerId]);
    $partner = $partnerStmt->fetch();

    if (!$partner || $partner['status'] !== 'active') {
        echo json_encode(['error' => 'Sua conta de parceiro está inativa ou suspensa.']);
        exit;
    }

    switch ($action) {
        // ========== ESTATÍSTICAS E COTA ==========
        case 'stats':
            $countStmt = $pdo->prepare("
                SELECT 
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) AS expired,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive
                FROM licenses 
                WHERE partner_id = ?
            ");
            $countStmt->execute([$partnerId]);
            $stats = $countStmt->fetch();

            $maxLicenses = (int)$partner['max_licenses'];
            $totalUsed = (int)($stats['total'] ?? 0);
            $remaining = max(0, $maxLicenses - $totalUsed);

            echo json_encode([
                'success' => true,
                'partner' => [
                    'id' => $partner['id'],
                    'name' => $partner['partner_name'],
                    'username' => $partner['username'],
                    'brand_name' => $partner['brand_name'],
                    'brand_logo' => $partner['brand_logo_url'],
                    'support_whatsapp' => $partner['support_whatsapp'],
                    'support_url' => $partner['support_url'],
                    'plan_name' => $partner['plan_name'],
                    'max_licenses' => $maxLicenses,
                    'expires_at' => $partner['expires_at']
                ],
                'stats' => [
                    'total_used' => $totalUsed,
                    'active' => (int)($stats['active'] ?? 0),
                    'expired' => (int)($stats['expired'] ?? 0),
                    'inactive' => (int)($stats['inactive'] ?? 0),
                    'max_licenses' => $maxLicenses,
                    'remaining' => $remaining
                ]
            ]);
            break;

        // ========== LISTAR LICENÇAS DO PARCEIRO ==========
        case 'licenses':
            $search = trim($_GET['search'] ?? '');
            $status = trim($_GET['status'] ?? '');
            $page = max(1, intval($_GET['page'] ?? 1));
            $perPage = 20;
            $offset = ($page - 1) * $perPage;

            $where = "partner_id = ?";
            $params = [$partnerId];

            if (!empty($search)) {
                $where .= " AND (license_key LIKE ? OR client_name LIKE ? OR client_email LIKE ? OR client_phone LIKE ?)";
                $term = "%{$search}%";
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
            }

            if (!empty($status) && in_array($status, ['active', 'inactive', 'expired', 'revoked'])) {
                $where .= " AND status = ?";
                $params[] = $status;
            }

            // Total count
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM licenses WHERE {$where}");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            // Fetch
            $stmt = $pdo->prepare("
                SELECT * FROM licenses 
                WHERE {$where} 
                ORDER BY created_at DESC 
                LIMIT {$perPage} OFFSET {$offset}
            ");
            $stmt->execute($params);
            $licenses = $stmt->fetchAll();

            foreach ($licenses as &$lic) {
                if (!empty($lic['client_phone'])) {
                    $lic['whatsapp_link'] = generateWhatsAppLink(
                        $lic['client_phone'],
                        $lic['client_name'],
                        $lic['license_key'],
                        $lic['plan_type'],
                        $lic['expires_at'],
                        $partner['brand_name']
                    );
                } else {
                    $lic['whatsapp_link'] = null;
                }
            }

            echo json_encode([
                'success' => true,
                'licenses' => $licenses,
                'total' => $total,
                'page' => $page,
                'pages' => ceil($total / $perPage)
            ]);
            break;

        // ========== GERAR NOVA LICENÇA (RESPEITA COTA) ==========
        case 'create':
            if ($method !== 'POST') {
                echo json_encode(['error' => 'Método inválido']);
                exit;
            }

            // 1. Validar cota restante
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM licenses WHERE partner_id = ?");
            $countStmt->execute([$partnerId]);
            $usedLicenses = (int)$countStmt->fetchColumn();

            if ($usedLicenses >= (int)$partner['max_licenses']) {
                echo json_encode([
                    'error' => 'Sua cota máxima de licenças (' . $partner['max_licenses'] . ') foi atingida. Solicite um upgrade de cota com o administrador.'
                ]);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true);

            $name = trim($input['client_name'] ?? '');
            $email = trim($input['client_email'] ?? '');
            $phone = trim($input['client_phone'] ?? '');
            $plan = trim($input['plan_type'] ?? 'mensal');

            if (empty($name)) {
                echo json_encode(['error' => 'Informe o nome do seu cliente.']);
                exit;
            }

            // Criar licença vinculada a este parceiro com a marca dele
            $result = createLicense($name, $email, $plan, null, $phone, $partnerId, $partner['brand_name']);

            echo json_encode([
                'success' => true,
                'license' => $result,
                'message' => 'Licença gerada com sucesso!'
            ]);
            break;

        // ========== ATIVAR LICENÇA ==========
        case 'activate':
            if ($method !== 'POST') { echo json_encode(['error' => 'Método inválido']); exit; }
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);

            $pdo->prepare("UPDATE licenses SET status = 'active', updated_at = NOW() WHERE id = ? AND partner_id = ?")->execute([$id, $partnerId]);
            logActivity($id, 'partner_activated', "Ativada pelo parceiro {$partner['partner_name']}");
            echo json_encode(['success' => true]);
            break;

        // ========== DESATIVAR LICENÇA ==========
        case 'deactivate':
            if ($method !== 'POST') { echo json_encode(['error' => 'Método inválido']); exit; }
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);

            $pdo->prepare("UPDATE licenses SET status = 'inactive', updated_at = NOW() WHERE id = ? AND partner_id = ?")->execute([$id, $partnerId]);
            logActivity($id, 'partner_deactivated', "Desativada pelo parceiro {$partner['partner_name']}");
            echo json_encode(['success' => true]);
            break;

        // ========== RESETAR DISPOSITIVO / HWID ==========
        case 'reset_hwid':
            if ($method !== 'POST') { echo json_encode(['error' => 'Método inválido']); exit; }
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);

            $pdo->prepare("UPDATE licenses SET hwid = NULL, updated_at = NOW() WHERE id = ? AND partner_id = ?")->execute([$id, $partnerId]);
            logActivity($id, 'partner_hwid_reset', "HWID liberado pelo parceiro {$partner['partner_name']}");
            echo json_encode(['success' => true]);
            break;

        // ========== RENOVAR LICENÇA ==========
        case 'renew':
            if ($method !== 'POST') { echo json_encode(['error' => 'Método inválido']); exit; }
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);
            $plan = $input['plan_type'] ?? null;

            // Verificar se a licença pertence a este parceiro
            $check = $pdo->prepare("SELECT id FROM licenses WHERE id = ? AND partner_id = ?");
            $check->execute([$id, $partnerId]);
            if ($check->fetch()) {
                $newExpiry = renewLicense($id, $plan);
                logActivity($id, 'partner_renewed', "Renovada pelo parceiro {$partner['partner_name']}");
                echo json_encode(['success' => true, 'new_expiry' => $newExpiry]);
            } else {
                echo json_encode(['error' => 'Licença não encontrada']);
            }
            break;

        // ========== EXCLUIR LICENÇA (LIBERA COTA) ==========
        case 'delete':
            if ($method !== 'POST') { echo json_encode(['error' => 'Método inválido']); exit; }
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);

            $stmtCheck = $pdo->prepare("SELECT id, license_key FROM licenses WHERE id = ? AND partner_id = ?");
            $stmtCheck->execute([$id, $partnerId]);
            $lic = $stmtCheck->fetch();

            if ($lic) {
                $pdo->prepare("DELETE FROM activity_logs WHERE license_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM payments WHERE license_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM licenses WHERE id = ? AND partner_id = ?")->execute([$id, $partnerId]);
                echo json_encode(['success' => true, 'message' => 'Licença excluída e cota liberada com sucesso!']);
            } else {
                echo json_encode(['error' => 'Licença não encontrada ou sem permissão.']);
            }
            break;

        default:
            echo json_encode(['error' => 'Ação não reconhecida']);
    }

} catch (Exception $e) {
    error_log("[PLATAFY Partner API] Error: " . $e->getMessage());
    echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
}
