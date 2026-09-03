<?php
/**
 * PLATAFY FB - API: Verificação de Versão de Software
 * GET /api/version_check.php
 * 
 * Endpoint público consultado pela extensão Chrome para verificar
 * se existe uma nova versão disponível para download.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$versionFilePath = __DIR__ . '/../version.json';

if (!file_exists($versionFilePath)) {
    echo json_encode([
        'success' => false,
        'message' => 'Arquivo de controle de versão não encontrado.'
    ]);
    exit;
}

$versionData = json_decode(file_get_contents($versionFilePath), true);
$extensionData = $versionData['extension'] ?? [];

$clientVersion = $_GET['current_version'] ?? '1.0.0';
$latestVersion = $extensionData['latest_version'] ?? '1.0.0';

$updateAvailable = version_compare($latestVersion, $clientVersion, '>');

echo json_encode([
    'success' => true,
    'update_available' => $updateAvailable,
    'current_version' => $clientVersion,
    'latest_version' => $latestVersion,
    'download_url' => $extensionData['download_url'] ?? '',
    'mandatory' => $extensionData['mandatory'] ?? false,
    'changelog' => $extensionData['changelog'] ?? ''
]);
