<?php
/**
 * Bot Handler
 * 
 * Endpoint que recebe mensagens do widget e comunica com a API Node.js.
 * Suporta CORS para permitir embeds em outros domínios.
 */

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não suportado']);
    exit;
}

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Services/LicenseService.php';

use App\Services\LicenseService;

$licenseService = new LicenseService();

// Verificar se tem licença
if (!$licenseService->hasLicense()) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error' => 'Serviço não configurado. Por favor contacte o administrador.',
    ]);
    exit;
}

// Validar licença
$validation = $licenseService->validate();
if (!$validation['valid']) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => $validation['error'] ?? 'Licença inválida',
    ]);
    exit;
}

// Verificar se módulo bot_widget está ativo
if (!$licenseService->moduleEnabled('bot_widget')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'O módulo de chat não está ativo nesta licença.',
    ]);
    exit;
}

// Ler payload
$payload = json_decode(file_get_contents('php://input'), true);

if (!$payload) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Payload inválido']);
    exit;
}

$message = trim($payload['message'] ?? '');
$sessionId = $payload['sessionId'] ?? null;

if ($message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Mensagem vazia']);
    exit;
}

// Verificar créditos
if (!$licenseService->hasCredits('ai_messages')) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Limite de mensagens atingido para este mês. Por favor aguarde ou contacte o administrador.',
    ]);
    exit;
}

// Enviar mensagem para a API
$response = $licenseService->sendBotMessage($message, $sessionId);

// Retornar resposta
if ($response['success']) {
    echo json_encode([
        'success' => true,
        'response' => $response['response'],
        'source' => $response['source'] ?? 'ai',
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $response['error'] ?? 'Erro ao processar mensagem',
    ]);
}
