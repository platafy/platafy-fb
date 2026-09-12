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

            // Determinar o usuário atual do admin
            $currentAdminUser = $_SESSION['admin_user'] ?? (defined('ADMIN_USERNAME') ? ADMIN_USERNAME : 'admin');
            try {
                $stmtAdmin = $pdo->prepare("SELECT username FROM admins WHERE username = ?");
                $stmtAdmin->execute([$currentAdminUser]);
                $rowAdmin = $stmtAdmin->fetch();
                if (!$rowAdmin) {
                    $rowFirst = $pdo->query("SELECT username FROM admins ORDER BY id ASC LIMIT 1")->fetch();
                    if ($rowFirst && !empty($rowFirst['username'])) {
                        $currentAdminUser = $rowFirst['username'];
                    }
                }
            } catch (Exception $e) {}

            echo json_encode([
                'success' => true,
                'settings' => [
                    'mp_access_token'      => $mpTokenProd,
                    'mp_access_token_test' => $mpTokenTest,
                    'mp_public_key'        => $mpPublicKey,
                    'mp_use_test'          => $mpUseTest === 'true' || $mpUseTest === '1' || $mpUseTest === true,
                    'site_logo'            => $siteLogo,
                    'site_favicon'         => getSetting('site_favicon', ''),
                    'admin_username'       => $currentAdminUser
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

        // ========== ALTERAÇÃO DE CREDENCIAIS (USUÁRIO E SENHA) ADMIN ==========
        case 'change_credentials':
        case 'change_password':
            if ($method !== 'POST') { echo json_encode(['error' => 'Método inválido']); exit; }

            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?: $_POST;
            $currentPass = $input['current_password'] ?? '';
            $newUsername = trim($input['new_username'] ?? '');
            $newPass     = $input['new_password'] ?? '';
            $confirmPass = $input['confirm_password'] ?? '';

            if (empty($currentPass)) {
                echo json_encode(['error' => 'Por favor, informe sua senha atual para confirmar as alterações.']);
                exit;
            }

            // Garantir que a tabela admins existe
            $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
                id INT PRIMARY KEY AUTO_INCREMENT,
                username VARCHAR(50) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $sessionUsername = $_SESSION['admin_user'] ?? (defined('ADMIN_USERNAME') ? ADMIN_USERNAME : 'admin');

            // Buscar registro do admin no banco
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$sessionUsername]);
            $admin = $stmt->fetch();

            // Se não encontrou pelo sessionUsername, tentar buscar o primeiro admin cadastrado
            if (!$admin) {
                $stmtFirst = $pdo->query("SELECT * FROM admins ORDER BY id ASC LIMIT 1");
                $admin = $stmtFirst->fetch();
            }

            // Validar autenticidade da senha atual
            $authenticated = false;
            if ($admin && password_verify($currentPass, $admin['password_hash'])) {
                $authenticated = true;
            } elseif (defined('ADMIN_PASSWORD_HASH') && password_verify($currentPass, ADMIN_PASSWORD_HASH)) {
                $authenticated = true;
            }

            if (!$authenticated) {
                echo json_encode(['error' => 'A senha atual informada está incorreta.']);
                exit;
            }

            // Se não foi enviado novo username, manter o atual
            if (empty($newUsername)) {
                $newUsername = $admin ? $admin['username'] : $sessionUsername;
            }

            // Validação de formato do novo usuário
            if (strlen($newUsername) < 3 || strlen($newUsername) > 50) {
                echo json_encode(['error' => 'O nome de usuário deve ter entre 3 e 50 caracteres.']);
                exit;
            }

            if (!preg_match('/^[a-zA-Z0-9_.\-@]+$/', $newUsername)) {
                echo json_encode(['error' => 'O nome de usuário contém caracteres inválidos. Utilize apenas letras, números, sublinhado (_), ponto (.) ou hífen (-).']);
                exit;
            }

            // Checar colisão com parceiros White Label
            try {
                $stmtPartner = $pdo->prepare("SELECT COUNT(*) FROM partners WHERE username = ?");
                $stmtPartner->execute([$newUsername]);
                if ((int)$stmtPartner->fetchColumn() > 0) {
                    echo json_encode(['error' => 'Este nome de usuário já está sendo utilizado por um parceiro.']);
                    exit;
                }
            } catch (Exception $e) {}

            // Se o username mudou, verificar se já está em uso por outro admin
            if ($admin && $newUsername !== $admin['username']) {
                $checkStmt = $pdo->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
                $checkStmt->execute([$newUsername, $admin['id']]);
                if ($checkStmt->fetch()) {
                    echo json_encode(['error' => 'Este nome de usuário já está sendo utilizado por outro administrador.']);
                    exit;
                }
            } elseif (!$admin && $newUsername !== $sessionUsername) {
                $checkStmt = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
                $checkStmt->execute([$newUsername]);
                if ($checkStmt->fetch()) {
                    echo json_encode(['error' => 'Este nome de usuário já está sendo utilizado por outro administrador.']);
                    exit;
                }
            }

            // Validar nova senha caso tenha sido fornecida
            $finalHash = null;
            $passwordChanged = false;
            if (!empty($newPass)) {
                if (strlen($newPass) < 6) {
                    echo json_encode(['error' => 'A nova senha deve conter pelo menos 6 caracteres.']);
                    exit;
                }
                if ($newPass !== $confirmPass) {
                    echo json_encode(['error' => 'A nova senha e a confirmação não conferem.']);
                    exit;
                }
                $finalHash = password_hash($newPass, PASSWORD_BCRYPT);
                $passwordChanged = true;
            } else {
                // Manter hash existente
                $finalHash = $admin ? $admin['password_hash'] : password_hash($currentPass, PASSWORD_BCRYPT);
            }

            // Atualizar ou inserir na tabela admins
            if ($admin) {
                $updateStmt = $pdo->prepare("UPDATE admins SET username = ?, password_hash = ? WHERE id = ?");
                $updateStmt->execute([$newUsername, $finalHash, $admin['id']]);
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)");
                $insertStmt->execute([$newUsername, $finalHash]);
            }

            // Atualizar sessão ativa com o novo nome de usuário
            $_SESSION['admin_user'] = $newUsername;

            $usernameChanged = ($admin && $newUsername !== $admin['username']) || (!$admin && $newUsername !== $sessionUsername);
            if ($usernameChanged && $passwordChanged) {
                $msg = "Usuário e senha alterados com sucesso! Novo usuário: '{$newUsername}'.";
            } elseif ($usernameChanged) {
                $msg = "Usuário do sistema alterado com sucesso para '{$newUsername}'!";
            } elseif ($passwordChanged) {
                $msg = "Senha de acesso alterada com sucesso!";
            } else {
                $msg = "Credenciais validadas (nenhuma alteração necessária).";
            }

            echo json_encode([
                'success' => true,
                'message' => $msg,
                'username' => $newUsername
            ]);
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
                @mkdir($uploadDir, 0777, true);
            }
            @chmod($uploadDir, 0777);

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'logo_' . time() . '.' . strtolower($ext);
            $targetPath = $uploadDir . '/' . $filename;
            $savedSuccessfully = false;
            $logoUrl = '';

            // 1. Tentar salvar como arquivo físico na pasta uploads
            if (@move_uploaded_file($file['tmp_name'], $targetPath) || @copy($file['tmp_name'], $targetPath)) {
                @chmod($targetPath, 0666);
                $logoUrl = '/uploads/' . $filename;
                $savedSuccessfully = true;
            } else {
                // 2. Fallback resiliente: se a pasta uploads estiver bloqueada por permissão do volume Docker,
                // codifica a imagem em Base64 Data URI e salva no banco sem perder nada!
                $fileContent = @file_get_contents($file['tmp_name']);
                if ($fileContent && strlen($fileContent) > 0) {
                    $base64 = base64_encode($fileContent);
                    $actualMime = $mimeType ?: 'image/png';
                    $logoUrl = "data:{$actualMime};base64,{$base64}";
                    $savedSuccessfully = true;
                }
            }

            if ($savedSuccessfully && !empty($logoUrl)) {
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
                @mkdir($uploadDir, 0777, true);
            }
            @chmod($uploadDir, 0777);

            $filename = 'favicon_' . time() . '.' . ($ext ?: 'png');
            $targetPath = $uploadDir . '/' . $filename;
            $savedSuccessfully = false;
            $faviconUrl = '';

            // 1. Tentar salvar como arquivo físico na pasta uploads
            if (@move_uploaded_file($file['tmp_name'], $targetPath) || @copy($file['tmp_name'], $targetPath)) {
                @chmod($targetPath, 0666);
                $faviconUrl = '/uploads/' . $filename;
                $savedSuccessfully = true;
            } else {
                // 2. Fallback resiliente: se a pasta uploads estiver bloqueada por permissão do volume Docker,
                // codifica o favicon em Base64 Data URI e salva no banco de dados!
                $fileContent = @file_get_contents($file['tmp_name']);
                if ($fileContent && strlen($fileContent) > 0) {
                    $base64 = base64_encode($fileContent);
                    $actualMime = $mimeType ?: 'image/png';
                    $faviconUrl = "data:{$actualMime};base64,{$base64}";
                    $savedSuccessfully = true;
                }
            }

            if ($savedSuccessfully && !empty($faviconUrl)) {
                setSetting('site_favicon', $faviconUrl);
                echo json_encode([
                    'success' => true, 
                    'message' => 'Favicon atualizado com sucesso!',
                    'favicon_url' => $faviconUrl
                ]);
            } else {
                echo json_encode(['error' => 'Falha ao processar e salvar o favicon no servidor.']);
            }
            break;

        default:
            echo json_encode(['error' => 'Ação não reconhecida']);
    }

} catch (Exception $e) {
    error_log("[PLATAFY Settings API Error] " . $e->getMessage());
    echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
}
