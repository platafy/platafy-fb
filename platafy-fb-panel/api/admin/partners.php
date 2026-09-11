<?php
/**
 * PLATAFY FB - API Admin: Gestão de Parceiros White Label
 * Protegido por sessão de Administrador Master
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $pdo = db();

    switch ($action) {
        // ========== LISTAR PARCEIROS ==========
        case 'list':
            $search = trim($_GET['search'] ?? '');
            $status = trim($_GET['status'] ?? '');
            $page = max(1, intval($_GET['page'] ?? 1));
            $perPage = 20;
            $offset = ($page - 1) * $perPage;

            $where = "1=1";
            $params = [];

            if (!empty($search)) {
                $where .= " AND (partner_name LIKE ? OR username LIKE ? OR brand_name LIKE ? OR support_whatsapp LIKE ?)";
                $term = "%{$search}%";
                $params = [$term, $term, $term, $term];
            }

            if (!empty($status) && in_array($status, ['active', 'inactive', 'suspended'])) {
                $where .= " AND status = ?";
                $params[] = $status;
            }

            // Total count
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM partners WHERE {$where}");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            // Fetch partners with license counts
            $stmt = $pdo->prepare("
                SELECT 
                    p.*,
                    COUNT(l.id) AS total_licenses,
                    SUM(CASE WHEN l.status = 'active' THEN 1 ELSE 0 END) AS active_licenses,
                    SUM(CASE WHEN l.status = 'expired' THEN 1 ELSE 0 END) AS expired_licenses
                FROM partners p
                LEFT JOIN licenses l ON l.partner_id = p.id
                WHERE {$where}
                GROUP BY p.id
                ORDER BY p.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}
            ");
            $stmt->execute($params);
            $partners = $stmt->fetchAll();

            // Omit sensitive password hash
            foreach ($partners as &$p) {
                unset($p['password_hash']);
                $maxLic = (int)$p['max_licenses'];
                $totalLic = (int)$p['total_licenses'];
                $p['remaining_licenses'] = max(0, $maxLic - $totalLic);
                $p['is_expired'] = (!empty($p['expires_at']) && strtotime($p['expires_at']) < time());
            }

            echo json_encode([
                'success' => true,
                'partners' => $partners,
                'total' => $total,
                'page' => $page,
                'pages' => ceil($total / $perPage)
            ]);
            break;

        // ========== CRIAR PARCEIRO ==========
        case 'create':
            if ($method !== 'POST') {
                echo json_encode(['error' => 'Método inválido']);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true);

            $partnerName     = trim($input['partner_name'] ?? '');
            $username        = strtolower(trim($input['username'] ?? ''));
            $password        = trim($input['password'] ?? '');
            $brandName       = trim($input['brand_name'] ?? '');
            $brandLogoUrl    = trim($input['brand_logo_url'] ?? '');
            $supportWhatsapp = trim($input['support_whatsapp'] ?? '');
            $supportUrl      = trim($input['support_url'] ?? '');
            $planName        = trim($input['plan_name'] ?? 'White Label Pro');
            $maxLicenses     = max(1, intval($input['max_licenses'] ?? 50));
            $expiresAt       = !empty($input['expires_at']) ? date('Y-m-d H:i:s', strtotime($input['expires_at'])) : null;
            $notes           = trim($input['notes'] ?? '');

            if (empty($partnerName) || empty($username) || empty($password) || empty($brandName)) {
                echo json_encode(['error' => 'Preencha Nome do Parceiro, Usuário, Senha e Nome da Marca.']);
                exit;
            }

            // Checar se username já existe em partners ou admins
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM partners WHERE username = ?");
            $checkStmt->execute([$username]);
            if ($checkStmt->fetchColumn() > 0) {
                echo json_encode(['error' => 'Este nome de usuário já está em uso por outro parceiro.']);
                exit;
            }

            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            $insertStmt = $pdo->prepare("
                INSERT INTO partners (
                    partner_name, username, password_hash, brand_name, brand_logo_url,
                    support_whatsapp, support_url, plan_name, max_licenses, status, expires_at, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)
            ");
            $insertStmt->execute([
                $partnerName, $username, $passwordHash, $brandName, $brandLogoUrl,
                $supportWhatsapp, $supportUrl, $planName, $maxLicenses, $expiresAt, $notes
            ]);

            $partnerId = $pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => 'Parceiro White Label criado com sucesso!',
                'partner_id' => $partnerId
            ]);
            break;

        // ========== ATUALIZAR PARCEIRO ==========
        case 'update':
            if ($method !== 'POST') {
                echo json_encode(['error' => 'Método inválido']);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);

            if ($id <= 0) {
                echo json_encode(['error' => 'ID do parceiro não informado']);
                exit;
            }

            $partnerName     = trim($input['partner_name'] ?? '');
            $brandName       = trim($input['brand_name'] ?? '');
            $brandLogoUrl    = trim($input['brand_logo_url'] ?? '');
            $supportWhatsapp = trim($input['support_whatsapp'] ?? '');
            $supportUrl      = trim($input['support_url'] ?? '');
            $planName        = trim($input['plan_name'] ?? 'White Label Pro');
            $maxLicenses     = max(1, intval($input['max_licenses'] ?? 50));
            $status          = in_array($input['status'] ?? '', ['active', 'inactive', 'suspended']) ? $input['status'] : 'active';
            $expiresAt       = !empty($input['expires_at']) ? date('Y-m-d H:i:s', strtotime($input['expires_at'])) : null;
            $notes           = trim($input['notes'] ?? '');

            $updateStmt = $pdo->prepare("
                UPDATE partners SET
                    partner_name = ?,
                    brand_name = ?,
                    brand_logo_url = ?,
                    support_whatsapp = ?,
                    support_url = ?,
                    plan_name = ?,
                    max_licenses = ?,
                    status = ?,
                    expires_at = ?,
                    notes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([
                $partnerName, $brandName, $brandLogoUrl,
                $supportWhatsapp, $supportUrl, $planName, $maxLicenses, $status, $expiresAt, $notes, $id
            ]);

            echo json_encode(['success' => true, 'message' => 'Parceiro atualizado com sucesso!']);
            break;

        // ========== ALTERAR STATUS (ATIVAR / SUSPENDER) ==========
        case 'toggle_status':
            if ($method !== 'POST') {
                echo json_encode(['error' => 'Método inválido']);
                exit;
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);
            $newStatus = $input['status'] ?? 'active';

            if (!in_array($newStatus, ['active', 'inactive', 'suspended'])) {
                echo json_encode(['error' => 'Status inválido']);
                exit;
            }

            $pdo->prepare("UPDATE partners SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $id]);
            echo json_encode(['success' => true]);
            break;

        // ========== REDEFINIR SENHA DO PARCEIRO ==========
        case 'reset_password':
            if ($method !== 'POST') {
                echo json_encode(['error' => 'Método inválido']);
                exit;
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);
            $newPass = trim($input['password'] ?? '');

            if (empty($newPass) || strlen($newPass) < 4) {
                echo json_encode(['error' => 'A senha deve ter pelo menos 4 caracteres.']);
                exit;
            }

            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE partners SET password_hash = ?, updated_at = NOW() WHERE id = ?")->execute([$newHash, $id]);
            echo json_encode(['success' => true, 'message' => 'Senha do parceiro redefinida com sucesso!']);
            break;

        // ========== EXCLUIR PARCEIRO ==========
        case 'delete':
            if ($method !== 'POST') {
                echo json_encode(['error' => 'Método inválido']);
                exit;
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);

            // Desvincular licenças atribuídas a este parceiro para evitar perda de clientes
            $pdo->prepare("UPDATE licenses SET partner_id = NULL WHERE partner_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM partners WHERE id = ?")->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Parceiro excluído com sucesso!']);
            break;

        default:
            echo json_encode(['error' => 'Ação não reconhecida']);
    }

} catch (Exception $e) {
    error_log("[PLATAFY Admin Partners] Error: " . $e->getMessage());
    echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
}
