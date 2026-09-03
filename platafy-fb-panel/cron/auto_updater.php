<?php
/**
 * PLATAFY FB - Script Cron: Automação de Backups, Manutenção e Atualizações
 * Execução via cPanel Cron Job: 0 3 * * * php /caminho/do/seu/painel/cron/auto_updater.php
 */
if (php_sapi_name() !== 'cli' && !defined('CRON_ALLOWED')) {
    // Permitir também se houver uma chave secreta via GET se chamado via URL Webhand
    $cronToken = $_GET['token'] ?? '';
    require_once __DIR__ . '/../config.php';
    if ($cronToken !== API_KEY) {
        http_response_code(403);
        echo "Acesso negado.";
        exit;
    }
} else {
    require_once __DIR__ . '/../config.php';
}

require_once __DIR__ . '/../includes/db.php';

echo "[PLATAFY Cron] Iniciando ciclo de automação e manutenção: " . date('Y-m-d H:i:s') . "\n";

try {
    $pdo = db();
    $backupDir = __DIR__ . '/../backups';
    
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0755, true);
    }
    
    // 1. GERAR BACKUP DO BANCO DE DADOS
    $tables = ['admins', 'licenses', 'payments', 'activity_logs'];
    $dump = "-- PLATAFY FB Auto Backup\n";
    $dump .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
        $row = $stmt->fetch();
        $createSql = $row['Create Table'] ?? '';
        
        $dump .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $dump .= $createSql . ";\n\n";
        
        $dataStmt = $pdo->query("SELECT * FROM `{$table}`");
        $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as $r) {
            $keys = array_keys($r);
            $values = array_values($r);
            $escapedValues = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote($v), $values);
            $dump .= "INSERT INTO `{$table}` (`" . implode("`, `", $keys) . "`) VALUES (" . implode(", ", $escapedValues) . ");\n";
        }
        $dump .= "\n";
    }
    
    $filename = 'auto_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backupDir . '/' . $filename;
    file_put_contents($filepath, $dump);
    
    echo "[PLATAFY Cron] Backup gerado com sucesso: {$filename} (" . filesize($filepath) . " bytes)\n";
    
    // 2. LIMPAR BACKUPS ANTIGOS (Manter os 10 mais recentes)
    $files = glob($backupDir . '/*.sql');
    if (count($files) > 10) {
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
        $toDelete = array_slice($files, 10);
        foreach ($toDelete as $oldFile) {
            @unlink($oldFile);
            echo "[PLATAFY Cron] Backup antigo removido: " . basename($oldFile) . "\n";
        }
    }
    
    // 3. LIMPEZA DE LOGS DE ATIVIDADE COM MAIS DE 60 DIAS
    $cleanStmt = $pdo->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)");
    $cleanStmt->execute();
    $cleanedLogs = $cleanStmt->rowCount();
    echo "[PLATAFY Cron] Logs de atividade limpos: {$cleanedLogs} registros removidos.\n";
    
    // 4. ATUALIZAR STATUS NO ARCHIVO VERSION.JSON
    $versionFilePath = __DIR__ . '/../version.json';
    if (file_exists($versionFilePath)) {
        $vData = json_decode(file_get_contents($versionFilePath), true);
        $vData['panel']['last_backup_at'] = date('Y-m-d H:i:s');
        file_put_contents($versionFilePath, json_encode($vData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    
    echo "[PLATAFY Cron] Ciclo de manutenção concluído com sucesso.\n";

} catch (Exception $e) {
    echo "[PLATAFY Cron Erro] " . $e->getMessage() . "\n";
    error_log("[PLATAFY Cron Error] " . $e->getMessage());
}
