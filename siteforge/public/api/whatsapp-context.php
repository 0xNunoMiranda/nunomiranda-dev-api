<?php
/**
 * WhatsApp prompt context (for Node.js)
 *
 * Returns tag values to be injected into the WhatsApp prompt template:
 * - {{bookings}}
 * - {{faqs}}
 * - {{shop}}
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$container = require __DIR__ . '/../../src/bootstrap.php';
$config = $container['config'] ?? [];

$expected = $config['license_key'] ?? '';
$provided = $_SERVER['HTTP_X_SITEFORGE_LICENSE'] ?? '';

if (!$expected || !hash_equals($expected, $provided)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

/** @var \App\SettingsStore $settingsStore */
$settingsStore = $container['settingsStore'] ?? null;
$settings = $container['settings'] ?? [];

// Bookings context from local DB (if enabled)
$bookingsText = '';
try {
    if (class_exists('\App\Database')) {
        $services = \App\Database::fetchAll(
            'SELECT name, duration_minutes, price_cents FROM services WHERE is_active = 1 ORDER BY sort_order ASC, name ASC LIMIT 25'
        );
        $hours = \App\Database::fetchAll(
            'SELECT day_of_week, is_open, open_time, close_time, break_start, break_end FROM business_hours ORDER BY day_of_week ASC'
        );

        if ($services) {
            $lines = [];
            $lines[] = "Serviços disponíveis:";
            foreach ($services as $s) {
                $price = isset($s['price_cents']) ? number_format(((int)$s['price_cents']) / 100, 2, ',', '.') . "€" : '';
                $dur = isset($s['duration_minutes']) ? ((int)$s['duration_minutes']) . "min" : '';
                $lines[] = "- " . ($s['name'] ?? 'Serviço') . ($dur ? " ({$dur})" : '') . ($price ? " — {$price}" : '');
            }
            $bookingsText .= implode("\n", $lines) . "\n\n";
        }

        if ($hours) {
            $dayNames = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
            $lines = [];
            $lines[] = "Horário:";
            foreach ($hours as $h) {
                $day = $dayNames[(int)($h['day_of_week'] ?? 0)] ?? 'Dia';
                $open = (int)($h['is_open'] ?? 0) === 1;
                if (!$open) {
                    $lines[] = "- {$day}: fechado";
                    continue;
                }
                $openTime = $h['open_time'] ?? '';
                $closeTime = $h['close_time'] ?? '';
                $breakStart = $h['break_start'] ?? null;
                $breakEnd = $h['break_end'] ?? null;
                $break = ($breakStart && $breakEnd) ? " (pausa {$breakStart}-{$breakEnd})" : '';
                $lines[] = "- {$day}: {$openTime}-{$closeTime}{$break}";
            }
            $bookingsText .= implode("\n", $lines);
        }
    }
} catch (Throwable $e) {
    $bookingsText = '';
}

// FAQs/shop are currently stored in settings.json (edited in admin.php).
$faqsText = '';
$shopText = '';

$faqsItems = $settings['faqs']['items'] ?? [];
if (is_array($faqsItems) && !empty($faqsItems)) {
    $lines = [];
    $lines[] = "FAQs:";
    $count = 0;
    foreach ($faqsItems as $item) {
        if ($count >= 50) break;
        if (!is_array($item)) continue;
        $q = trim((string)($item['question'] ?? ''));
        $a = trim((string)($item['answer'] ?? ''));
        if ($q === '' || $a === '') continue;
        $lines[] = "- Q: {$q}";
        $lines[] = "  A: {$a}";
        $count++;
    }
    if ($count > 0) {
        $faqsText = implode("\n", $lines);
    }
}

$products = $settings['shop']['products'] ?? [];
if (is_array($products) && !empty($products)) {
    $lines = [];
    $lines[] = "Produtos:";
    $count = 0;
    foreach ($products as $p) {
        if ($count >= 50) break;
        if (!is_array($p)) continue;
        $name = trim((string)($p['name'] ?? ''));
        if ($name === '') continue;
        $price = trim((string)($p['price'] ?? ''));
        $desc = trim((string)($p['description'] ?? ''));
        $url = trim((string)($p['url'] ?? ''));
        $line = "- {$name}";
        if ($price !== '') $line .= " — {$price}";
        $lines[] = $line;
        if ($desc !== '') $lines[] = "  " . $desc;
        if ($url !== '') $lines[] = "  " . $url;
        $count++;
    }
    if ($count > 0) {
        $shopText = implode("\n", $lines);
    }
}

echo json_encode([
    'ok' => true,
    'data' => [
        'tags' => [
            'bookings' => $bookingsText,
            'faqs' => $faqsText,
            'shop' => $shopText,
        ],
        'settings' => [
            'whatsapp' => $settings['whatsapp'] ?? null,
        ],
    ],
]);
