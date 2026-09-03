<?php
/**
 * PLATAFY FB - API Admin: CRUD Licenças
 * Endpoints protegidos por sessão admin
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/license_utils.php';

requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $pdo = db();
    checkExpiredLicenses();
    
    switch ($action) {
        // ========== LISTAR LICENÇAS ==========
        case 'list':
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';
            $page = max(1, intval($_GET['page'] ?? 1));
            $perPage = 20;
            $offset = ($page - 1) * $perPage;
            
            $where = "1=1";
            $params = [];
            
            if ($search) {
                $where .= " AND (license_key LIKE ? OR client_name LIKE ? OR client_email LIKE ? OR client_phone LIKE ?)";
                $searchTerm = "%{$search}%";
                $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
            }
            
            if ($status && in_array($status, ['active','inactive','expired','revoked','pending'])) {
                $where .= " AND status = ?";
                $params[] = $status;
            }
            
            // Count
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM licenses WHERE {$where}");
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();
            
            // Fetch
            $stmt = $pdo->prepare("SELECT * FROM licenses WHERE {$where} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}");
            $stmt->execute($params);
            $licenses = $stmt->fetchAll();
            
            foreach ($licenses as &$lic) {
                if (!empty($lic['client_phone'])) {
                    $lic['whatsapp_link'] = generateWhatsAppLink($lic['client_phone'], $lic['client_name'], $lic['license_key'], $lic['plan_type'], $lic['expires_at']);
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
            
        // ========== CRIAR LICENÇA ==========
        case 'create':
            if ($method !== 'POST') { echo json_encode(['error' => 'Método inválido']); exit; }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $name = $input['client_name'] ?? '';
            $email = $input['client_email'] ?? '';
            $phone = $input['client_phone'] ?? '';
            $plan = $input['plan_type'] ?? 'manual';
            
            $result = createLicense($name, $email, $plan, null, $phone);
            
            echo json_encode(['success' => true, 'license' => $result]);
            break;
            
        // ========== DETALHES ==========
        case 'detail':
            $id = intval($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM licenses WHERE id = ?");
            $stmt->execute([$id]);
            $license = $stmt->fetch();
            
            if (!$license) { echo json_encode(['error' => 'Licença não encontrada']); exit; }
            
            if (!empty($license['client_phone'])) {
                $license['whatsapp_link'] = generateWhatsAppLink($license['client_phone'], $license['client_name'], $license['license_key'], $license['plan_type'], $license['expires_at']);
            }
            
            // Buscar histórico
            $logStmt = $pdo->prepare("SELECT * FROM activity_logs WHERE license_id = ? ORDER BY created_at DESC LIMIT 20");
            $logStmt->execute([$id]);
            $logs = $logStmt->fetchAll();
            
            // Buscar pagamentos
            $payStmt = $pdo->prepare("SELECT * FROM payments WHERE license_id = ? ORDER BY created_at DESC LIMIT 10");
            $payStmt->execute([$id]);
            $payments = $payStmt->fetchAll();
            
            echo json_encode(['success' => true, 'license' => $license, 'logs' => $logs, 'payments' => $payments]);
            break;
            
        // ========== AÇÕES ==========
        case 'activate':
            if ($method !== 'POST') { echo json_encode(['error' => 'Método inválido']); exit; }
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);
            $pdo->prepare("UPDATE licenses SET status = 'active', updated_at = NOW() WHERE id = ?")->execute([$id]);
            logActivity($id, 'activated_admin', 'Ativada manualmente pelo admin');
            echo json_encode(['success' => true]);
            break;
            
        case 'deactivate':
            if ($method !== 'POST') { echo json_encode(['error' => 'Método inválido']); exit; }
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);
            $pdo->prepare("UPDATE licenses SET status = 'inactive', updated_at = NOW() WHERE id = ?")->execute([$id]);
            logActivity($id, 'deactivated', 'Desativada pelo admin');
            echo json_encode(['success' => true]);
            break;
            
        case 'revoke':
            if ($method !== 'POST') { echo json_encode(['error' => 'Método inválido']); exit; }
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);
            revokeLicense($id);
            echo json_encode(['success' => true]);
            break;
            
        case 'renew':
            if ($method !== 'POST') { echo json_encode(['error' => 'Método inválido']); exit; }
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);
            $plan = $input['plan_type'] ?? null;
            $newExpiry = renewLicense($id, $plan);
            echo json_encode(['success' => true, 'new_expiry' => $newExpiry]);
            break;
            
        case 'reset_hwid':
            if ($method !== 'POST') { echo json_encode(['error' => 'Método inválido']); exit; }
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);
            $pdo->prepare("UPDATE licenses SET hwid = NULL, updated_at = NOW() WHERE id = ?")->execute([$id]);
            logActivity($id, 'hwid_reset', 'HWID resetado pelo admin');
            echo json_encode(['success' => true]);
            break;
            
        case 'delete':
            if ($method !== 'POST') { echo json_encode(['error' => 'Método inválido']); exit; }
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);
            $pdo->prepare("DELETE FROM activity_logs WHERE license_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM payments WHERE license_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM licenses WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['error' => 'Ação não reconhecida']);
    }
    
} catch (Exception $e) {
    error_log("[PLATAFY Admin] Error: " . $e->getMessage());
    echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
}
