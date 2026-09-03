<?php
/**
 * PLATAFY FB - API Admin: Gerenciamento de Versões e Backups
 * GET/POST /api/admin/updates.php
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'info';
$versionFilePath = __DIR__ . '/../../version.json';
$backupDir = __DIR__ . '/../../backups';

if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0755, true);
}

try {
    switch ($action) {
        // ========== OBTER INFORMAÇÕES DE VERSÃO E BACKUPS ==========
        case 'info':
            $data = file_exists($versionFilePath) 
                ? json_decode(file_get_contents($versionFilePath), true) 
                : [];
            
            // Listar arquivos de backup existentes
            $backups = [];
            if (is_dir($backupDir)) {
                $files = glob($backupDir . '/*.sql');
                foreach ($files as $file) {
                    $backups[] = [
                        'filename' => basename($file),
                        'size' => filesize($file),
                        'created_at' => date('Y-m-d H:i:s', filemtime($file))
                    ];
                }
                // Ordenar do mais recente para o mais antigo
                usort($backups, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
            }
            
            echo json_encode([
                'success' => true,
                'version' => $data,
                'backups' => $backups
            ]);
            break;
            
        // ========== PUBLICAR NOVA VERSÃO DA EXTENSÃO ==========
        case 'publish':
            if ($method !== 'POST') { echo json_encode(['error' => 'Método inválido']); exit; }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $newVersion = trim($input['latest_version'] ?? '');
            $downloadUrl = trim($input['download_url'] ?? '');
            $mandatory = !empty($input['mandatory']);
            $changelog = trim($input['changelog'] ?? '');
            
            if (empty($newVersion)) {
                echo json_encode(['error' => 'A nova versão é obrigatória']);
                exit;
            }
            
            $data = file_exists($versionFilePath) ? json_decode(file_get_contents($versionFilePath), true) : [];
            
            $data['extension'] = [
                'latest_version' => $newVersion,
                'download_url' => $downloadUrl ?: ($data['extension']['download_url'] ?? ''),
                'mandatory' => $mandatory,
                'changelog' => $changelog ?: 'Atualizações e melhorias de desempenho.'
            ];
            
            file_put_contents($versionFilePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            
            echo json_encode(['success' => true, 'version' => $data]);
            break;
            
        // ========== GERAR BACKUP MANUAL DO BANCO DE DADOS ==========
        case 'create_backup':
            if ($method !== 'POST') { echo json_encode(['error' => 'Método inválido']); exit; }
            
            $pdo = db();
            $tables = ['admins', 'licenses', 'payments', 'activity_logs'];
            
            $dump = "-- PLATAFY FB Database Backup\n";
            $dump .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";
            
            foreach ($tables as $table) {
                // Table structure
                $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
                $row = $stmt->fetch();
                $createSql = $row['Create Table'] ?? '';
                
                $dump .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $dump .= $createSql . ";\n\n";
                
                // Table data
                $dataStmt = $pdo->query("SELECT * FROM `{$table}`");
                $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($rows as $r) {
                    $keys = array_keys($r);
                    $values = array_values($r);
                    
                    $escapedValues = array_map(function($v) use ($pdo) {
                        return $v === null ? 'NULL' : $pdo->quote($v);
                    }, $values);
                    
                    $dump .= "INSERT INTO `{$table}` (`" . implode("`, `", $keys) . "`) VALUES (" . implode(", ", $escapedValues) . ");\n";
                }
                $dump .= "\n";
            }
            
            $filename = 'backup_platafy_' . date('Y-m-d_H-i-s') . '.sql';
            $filepath = $backupDir . '/' . $filename;
            
            file_put_contents($filepath, $dump);
            
            // Atualizar registro de último backup em version.json
            $vData = file_exists($versionFilePath) ? json_decode(file_get_contents($versionFilePath), true) : [];
            $vData['panel']['last_backup_at'] = date('Y-m-d H:i:s');
            file_put_contents($versionFilePath, json_encode($vData, JSON_PRETTY_PRINT));
            
            echo json_encode([
                'success' => true,
                'filename' => $filename,
                'size' => filesize($filepath),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            break;
            
        default:
            echo json_encode(['error' => 'Ação não reconhecida']);
    }
} catch (Exception $e) {
    error_log("[PLATAFY Admin Updates Error] " . $e->getMessage());
    echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
}
