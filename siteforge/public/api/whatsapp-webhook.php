<?php
/**
 * WhatsApp Web webhook (from Node.js)
 *
 * Stores inbound/outbound messages in the client database (SiteForge).
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

use App\Database;

$container = require __DIR__ . '/../../src/bootstrap.php';
$config = $container['config'] ?? [];

$expected = $config['license_key'] ?? '';
$provided = $_SERVER['HTTP_X_SITEFORGE_LICENSE'] ?? '';

if (!$expected || !hash_equals($expected, $provided)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$pdo = Database::getInstance();
if (!$pdo) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'Database not available']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

$direction = $payload['direction'] ?? null;
$sessionId = $payload['sessionId'] ?? null;
$phone = $payload['phone'] ?? null;
$content = $payload['content'] ?? null;

if (!in_array($direction, ['inbound', 'outbound'], true) || !is_string($sessionId) || !is_string($content)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Missing required fields']);
    exit;
}

$phone = is_string($phone) ? $phone : null;

Database::beginTransaction();

try {
    $conversation = Database::fetchOne(
        "SELECT id FROM bot_conversations WHERE channel = 'whatsapp' AND session_id = ? LIMIT 1",
        [$sessionId]
    );

    if (!$conversation) {
        $conversationId = Database::insert('bot_conversations', [
            'channel' => 'whatsapp',
            'session_id' => $sessionId,
            'customer_phone' => $phone,
            'status' => 'active',
            'context' => json_encode(new stdClass()),
        ]);
    } else {
        $conversationId = (int) $conversation['id'];
    }

    if (!$conversationId) {
        throw new RuntimeException('Failed to create conversation');
    }

    Database::insert('bot_messages', [
        'conversation_id' => $conversationId,
        'direction' => $direction,
        'message_type' => 'text',
        'content' => $content,
        'metadata' => json_encode([
            'phone' => $phone,
        ]),
    ]);

    Database::commit();
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    Database::rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Failed to persist message']);
}
