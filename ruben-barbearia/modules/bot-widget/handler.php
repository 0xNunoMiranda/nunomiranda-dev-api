<?php
/**
 * Bot Widget API Handler
 * 
 * Processa as mensagens do bot widget e retorna respostas apropriadas.
 * Pode ser estendido para integrar com APIs de AI (OpenAI, etc.)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Database;
use App\Services\BookingService;

$db = Database::getInstance();
$bookingService = $GLOBALS['bookingService'] ?? new BookingService($db);

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$sessionId = $input['sessionId'] ?? '';
$message = strtolower(trim($input['message'] ?? ''));

if (empty($sessionId) || empty($message)) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// Store conversation
function saveConversation($db, $sessionId, $userMessage, $botReply) {
    $conversationId = $db->fetchOne(
        "SELECT id FROM bot_conversations WHERE session_id = ?",
        [$sessionId]
    );
    
    if (!$conversationId) {
        $db->insert('bot_conversations', [
            'session_id' => $sessionId,
            'channel' => 'widget',
            'context' => json_encode([]),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $conversationId = $db->getInstance()->lastInsertId();
    } else {
        $conversationId = $conversationId['id'];
    }
    
    $db->insert('bot_messages', [
        'conversation_id' => $conversationId,
        'role' => 'user',
        'message' => $userMessage,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    
    $db->insert('bot_messages', [
        'conversation_id' => $conversationId,
        'role' => 'assistant',
        'message' => $botReply,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

// Intent detection - básico (pode ser expandido com AI)
function detectIntent($message) {
    $intents = [
        'booking' => ['marcar', 'marcação', 'agendar', 'reservar', 'hora', 'disponibilidade'],
        'hours' => ['horário', 'aberto', 'funciona', 'abre', 'fecha', 'horas'],
        'services' => ['serviço', 'serviços', 'preço', 'preços', 'corte', 'barba', 'quanto custa'],
        'support' => ['ajuda', 'problema', 'reclamar', 'suporte', 'dúvida'],
        'greeting' => ['olá', 'ola', 'bom dia', 'boa tarde', 'boa noite', 'hey', 'hi'],
        'thanks' => ['obrigado', 'obrigada', 'thanks', 'agradeço'],
        'cancel' => ['cancelar', 'desmarcar', 'anular'],
    ];
    
    foreach ($intents as $intent => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return $intent;
            }
        }
    }
    
    return 'unknown';
}

// Generate response
function generateResponse($intent, $bookingService) {
    switch ($intent) {
        case 'greeting':
            $greetings = [
                'Olá! 👋 Como posso ajudar? Podes perguntar sobre marcações, serviços, horários ou suporte.',
                'Bem-vindo! Em que posso ser útil hoje?',
                'Olá! Estou aqui para ajudar com marcações, informações sobre serviços ou suporte.',
            ];
            return $greetings[array_rand($greetings)];
            
        case 'booking':
            $slots = $bookingService->getAvailableSlots(date('Y-m-d'));
            if (empty($slots)) {
                return 'Para hoje já não temos disponibilidade, mas podes ligar para marcar para outro dia, ou visita a nossa página de marcações online.';
            }
            $slotsText = implode(', ', array_slice($slots, 0, 5));
            return "Para hoje temos disponibilidade às: $slotsText.\n\nPara fazer uma marcação, por favor indica:\n• O serviço pretendido\n• A data e hora\n• O teu nome e telefone";
            
        case 'hours':
            $hours = $bookingService->getBusinessHours();
            $hoursText = '';
            $days = [
                'monday' => 'Segunda',
                'tuesday' => 'Terça',
                'wednesday' => 'Quarta',
                'thursday' => 'Quinta',
                'friday' => 'Sexta',
                'saturday' => 'Sábado',
                'sunday' => 'Domingo',
            ];
            foreach ($hours as $hour) {
                $dayName = $days[$hour['day_of_week']] ?? $hour['day_of_week'];
                if ($hour['is_closed']) {
                    $hoursText .= "• $dayName: Fechado\n";
                } else {
                    $hoursText .= "• $dayName: {$hour['open_time']} - {$hour['close_time']}\n";
                }
            }
            return "📍 Os nossos horários são:\n\n$hoursText";
            
        case 'services':
            $services = $bookingService->getServices();
            if (empty($services)) {
                return 'Neste momento não tenho a lista de serviços disponível. Por favor contacta-nos diretamente.';
            }
            $servicesText = '';
            foreach ($services as $service) {
                $price = number_format($service['price'], 2, ',', '.');
                $servicesText .= "• {$service['name']} - €{$price} ({$service['duration_minutes']}min)\n";
            }
            return "💈 Os nossos serviços:\n\n$servicesText\nQual serviço gostavas de marcar?";
            
        case 'support':
            return "Lamento se estás com algum problema! 😟\n\nPodes:\n• Descrever aqui o teu problema\n• Enviar email para suporte\n• Ligar diretamente\n\nComo posso ajudar?";
            
        case 'thanks':
            $responses = [
                'De nada! Se precisares de mais alguma coisa, estou aqui. 😊',
                'Sempre às ordens! Tem um ótimo dia!',
                'Foi um prazer ajudar! Até breve!',
            ];
            return $responses[array_rand($responses)];
            
        case 'cancel':
            return "Para cancelar uma marcação, por favor indica o teu nome ou número de telefone associado à marcação, e verifico no sistema.";
            
        default:
            return "Não percebi bem o que pretendes. Posso ajudar com:\n\n📅 **Marcações** - agendar ou verificar disponibilidade\n💈 **Serviços** - ver lista e preços\n🕐 **Horários** - horário de funcionamento\n💬 **Suporte** - ajuda com problemas\n\nO que preferes?";
    }
}

// Process message
$intent = detectIntent($message);
$reply = generateResponse($intent, $bookingService);

// Save to database
try {
    saveConversation($db, $sessionId, $input['message'], $reply);
} catch (Exception $e) {
    // Log error but don't fail
    error_log('Bot conversation save error: ' . $e->getMessage());
}

// Return response
echo json_encode([
    'reply' => $reply,
    'intent' => $intent,
]);
