<?php
header('Content-Type: application/json; charset=utf-8');
$container = require __DIR__ . '/../src/bootstrap.php';
$config = $container['config'];
$settings = $container['settings'];
$ai = $settings['ai_bot'] ?? [];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método não suportado']);
    exit;
}

if (empty($ai['enabled'])) {
    echo json_encode([
        'ok' => false,
        'message' => 'O assistente está temporariamente indisponível.']
    );
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$message = trim($payload['message'] ?? '');

if ($message === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Mensagem vazia']);
    exit;
}

$assistantName = $ai['assistantName'] ?? 'Assistente';
$reply = generate_ai_reply($message, $assistantName, $settings);

// Placeholder: aqui poderíamos chamar a API central para registar o pedido
// usando $config['api']['base_url'] e a API key definida no painel.

$response = [
    'ok' => true,
    'reply' => $reply,
];

echo json_encode($response);

function generate_ai_reply(string $message, string $assistantName, array $settings): string
{
    $messageLower = mb_strtolower($message);
    if (str_contains($messageLower, 'hor') || str_contains($messageLower, 'quando')) {
        return sprintf('%s: tenho disponibilidade amanhã às 10h, 14h e 18h. Qual preferes?', $assistantName);
    }
    if (str_contains($messageLower, 'preço') || str_contains($messageLower, 'quanto')) {
        return sprintf('%s: o corte clássico fica em 18€ e o full service em 25€. Posso bloquear-te um horário agora.', $assistantName);
    }
    if (str_contains($messageLower, 'whatsapp')) {
        $whatsapp = $settings['whatsapp']['number'] ?? '+351 910 000 000';
        return sprintf('%s: também respondo via WhatsApp no %s.', $assistantName, $whatsapp);
    }
    return sprintf('%s: obrigado pela mensagem! Diz-me o melhor dia/horário e trato da reserva contigo.', $assistantName);
}
