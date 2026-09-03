<?php
/**
 * PLATAFY FB - API Admin: Gerenciamento de Configurações
 * GET/POST /api/admin/settings.php
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/settings_utils.php';

requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'get_settings';

try {
    $pdo = db();

    switch ($action) {
        // ========== OBTER CONFIGURAÇÕES ==========
        case 'get_settings':
            $mpTokenProd = getSetting('mp_access_token', defined('MP_ACCESS_TOKEN') ? MP_ACCESS_TOKEN : '');
            $mpTokenTest = getSetting('mp_access_token_test', defined('MP_ACCESS_TOKEN_TEST') ? MP_ACCESS_TOKEN_TEST : '');
            $mpPublicKey = getSetting('mp_public_key', '');
            $mpUseTest   = getSetting('mp_use_test', defined('MP_USE_TEST') ? (MP_USE_TEST ? 'true' : 'false') : 'true');
            $siteLogo    = getSetting('site_logo', '');

            echo json_encode([
                'success' => true,
                'settings' => [
                    'mp_access_token'      => $mpTokenProd,
                    'mp_access_token_test' => $mpTokenTest,
                    'mp_public_key'        => $mpPublicKey,
                    'mp_use_test'          => $mpUseTest === 'true' || $mpUseTest === '1' || $mpUseTest === true,
                    'site_logo'            => $siteLogo,
                    'site_favicon'         => getSetting('site_favicon', '')
                ]
            ]);
            break;

        // ========== SALVAR CREDENCIAIS MERCADO PAGO ==========
        case 'save_mercadopago':
            if ($method !== 'POST') { echo json_encode(['error' => 'Método inválido']); exit; }

            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?: $_POST;

            $tokenProd = trim($input['mp_access_token'] ?? '');
            $tokenTest = trim($input['mp_access_token_test'] ?? '');
            $publicKey = trim($input['mp_public_key'] ?? '');
            $useTest   = !empty($input['mp_use_test']) ? 'true' : 'false';

            setSetting('mp_access_token', $tokenProd);
            setSetting('mp_access_token_test', $tokenTest);
            setSetting('mp_public_key', $publicKey);
            setSetting('mp_use_test', $useTest);

            echo json_encode(['success' => true, 'message' => 'Configurações do Mercado Pago salvas com sucesso!']);
            break;

        // ========== ALTERAÇÃO DE SENHA ADMIN ==========
        case 'change_password':
            if ($method !== 'POST') { echo json_encode(['error' => 'Método inválido']); exit; }

            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?: $_POST;
            $currentPass = $input['current_password'] ?? '';
            $newPass     = $input['new_password'] ?? '';
            $confirmPass = $input['confirm_password'] ?? '';

            if (empty($currentPass) || empty($newPass)) {
                echo json_encode(['error' => 'Preencha a senha atual e a nova senha.']);
                exit;
            }

            if (strlen($newPass) < 6) {
                echo json_encode(['error' => 'A nova senha deve conter pelo menos 6 caracteres.']);
                exit;
            }

            if ($newPass !== $confirmPass) {
                echo json_encode(['error' => 'A nova senha e a confirmação não conferem.']);
                exit;
            }

            // Garantir que a tabela admins existe
            $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
                id INT PRIMARY KEY AUTO_INCREMENT,
                username VARCHAR(50) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $username = $_SESSION['admin_user'] ?? ADMIN_USERNAME;
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            $authenticated = false;
            if ($admin && password_verify($currentPass, $admin['password_hash'])) {
                $authenticated = true;
            } elseif ($username === ADMIN_USERNAME && password_verify($currentPass, ADMIN_PASSWORD_HASH)) {
                $authenticated = true;
            }

            if (!$authenticated) {
                echo json_encode(['error' => 'A senha atual está incorreta.']);
                exit;
            }

            // Atualizar ou inserir a nova senha
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            if ($admin) {
                $updateStmt = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
                $updateStmt->execute([$newHash, $admin['id']]);
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)");
                $insertStmt->execute([$username, $newHash]);
            }

            echo json_encode(['success' => true, 'message' => 'Senha alterada com sucesso!']);
            break;

        // ========== UPLOAD DE LOGO DO SISTEMA ==========
        case 'upload_logo':
            if ($method !== 'POST') { echo json_encode(['error' => 'Método inválido']); exit; }

            if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['error' => 'Nenhum arquivo enviado ou erro no upload.']);
                exit;
            }

            $file = $_FILES['logo'];

            // Validação de tamanho (máximo 2MB)
            if ($file['size'] > 2 * 1024 * 1024) {
                echo json_encode(['error' => 'O arquivo da logomarca deve ter no máximo 2MB.']);
                exit;
            }

            // Validação de extensão/MIME
            $allowedTypes = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                echo json_encode(['error' => 'Formato inválido. Use PNG, JPG, WEBP ou SVG.']);
                exit;
            }

            // Pasta de destino
            $uploadDir = __DIR__ . '/../../uploads';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'logo_' . time() . '.' . strtolower($ext);
            $targetPath = $uploadDir . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $logoUrl = '/uploads/' . $filename;
                setSetting('site_logo', $logoUrl);
                echo json_encode([
                    'success' => true, 
                    'message' => 'Logomarca atualizada com sucesso!',
                    'logo_url' => $logoUrl
                ]);
            } else {
                echo json_encode(['error' => 'Falha ao salvar a imagem no servidor.']);
            }
            break;

        
                // ========== UPLOAD DE FAVICON DO SISTEMA ==========
        case 'upload_favicon':
            if ($method !== 'POST') { echo json_encode(['error' => 'Método inválido']); exit; }

            if (!isset($_FILES['favicon']) || $_FILES['favicon']['error'] !== UPLOAD_ERR_OK) {
                $errCode = $_FILES['favicon']['error'] ?? 'desconhecido';
                echo json_encode(['error' => "Erro no envio do arquivo (código: {$errCode})."]);
                exit;
            }

            $file = $_FILES['favicon'];

            // Limite flexível de até 10MB para aceitar imagens PNG de alta resolução (512x512, 1024x1024, etc.)
            if ($file['size'] > 10 * 1024 * 1024) {
                echo json_encode(['error' => 'O arquivo do favicon deve ter no máximo 10MB.']);
                exit;
            }

            $allowedTypes = ['image/png', 'image/x-png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/icon', 'image/jpeg', 'image/jpg', 'image/webp', 'image/svg+xml'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $validExts = ['png', 'ico', 'jpg', 'jpeg', 'webp', 'svg'];

            if (!in_array($mimeType, $allowedTypes) && !in_array($ext, $validExts)) {
                echo json_encode(['error' => "Formato inválido ({$mimeType}). Use PNG, ICO, WEBP ou JPG."]);
                exit;
            }

            $uploadDir = __DIR__ . '/../../uploads';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }

            $filename = 'favicon_' . time() . '.' . ($ext ?: 'png');
            $targetPath = $uploadDir . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $faviconUrl = '/uploads/' . $filename;
                setSetting('site_favicon', $faviconUrl);
                echo json_encode([
                    'success' => true, 
                    'message' => 'Favicon atualizado com sucesso!',
                    'favicon_url' => $faviconUrl
                ]);
            } else {
                echo json_encode(['error' => 'Falha ao salvar o favicon no servidor.']);
            }
            break;

        default:
            echo json_encode(['error' => 'Ação não reconhecida']);
    }

} catch (Exception $e) {
    error_log("[PLATAFY Settings API Error] " . $e->getMessage());
    echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
}
