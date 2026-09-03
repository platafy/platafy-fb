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

require_once __DIR__ . '/../includes/settings_utils.php';
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

$versionData = file_exists($versionFilePath) ? json_decode(file_get_contents($versionFilePath), true) : [];
$extensionData = $versionData['extension'] ?? [];

$latestVersion = getSetting('ext_latest_version') ?: ($extensionData['latest_version'] ?? '4.1.0');
$downloadUrl = getSetting('ext_download_url') ?: ($extensionData['download_url'] ?? 'https://fb.platafy.com/downloads/platafy-fb.zip');
$mandatory = getSetting('ext_mandatory') !== null ? (getSetting('ext_mandatory') === '1') : ($extensionData['mandatory'] ?? false);
$changelog = getSetting('ext_changelog') ?: ($extensionData['changelog'] ?? 'Versão inicial do PLATAFY FB automação de postagem no Facebook.');

$clientVersion = $_GET['current_version'] ?? '4.0.0';

$updateAvailable = version_compare($latestVersion, $clientVersion, '>');

echo json_encode([
    'success' => true,
    'update_available' => $updateAvailable,
    'current_version' => $clientVersion,
    'latest_version' => $latestVersion,
    'download_url' => $downloadUrl,
    'mandatory' => $mandatory,
    'changelog' => $changelog
]);
