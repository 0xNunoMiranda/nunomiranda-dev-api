<?php
/**
 * Bootstrap - Inicialização da aplicação PHP
 */

// Autoload básico
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Fallback para ficheiros antigos
require_once __DIR__ . '/helpers.php';

// Carregar configuração (nova localização prioritária)
$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) {
    $configPath = __DIR__ . '/config.php'; // fallback antigo
}
$config = require $configPath;

// Inicializar base de dados se configuração nova
if (isset($config['database'])) {
    \App\Database::init($config['database']);
}

// Criar instâncias dos serviços
$apiBaseUrl = $config['api']['base_url'] ?? 'http://localhost:3000';
$apiKey = $config['api']['api_key'] ?? null;
$apiTimeout = $config['api']['timeout'] ?? 30;

// Usar nova classe ou fallback
if (class_exists('\App\ApiClient')) {
    $apiClient = new \App\ApiClient($apiBaseUrl, $apiKey, $apiTimeout);
} else {
    $apiClient = new \App\ApiClient($apiBaseUrl, $apiKey, $apiTimeout);
}

// Auth
$auth = null;
if (class_exists('\App\Auth')) {
    $auth = new \App\Auth($config);
}

// Settings
$settingsFile = $config['storage']['settings_file'] ?? __DIR__ . '/../storage/settings.json';
if (class_exists('\App\SettingsStore')) {
    $settingsStore = new \App\SettingsStore($settingsFile);
} else {
    require_once __DIR__ . '/SettingsStore.php';
    $settingsStore = new SettingsStore($settingsFile);
}
$settings = $settingsStore->load();

// Helper functions globais
function sanitize(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function format_price(int $cents, string $currency = 'EUR'): string
{
    $value = number_format($cents / 100, 2, ',', ' ');
    return $value . ' €';
}

function format_date(string $date, string $format = 'd/m/Y'): string
{
    return date($format, strtotime($date));
}

function format_time(string $time): string
{
    return date('H:i', strtotime($time));
}

function is_ajax(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function get_client_ip(): string
{
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = explode(',', $_SERVER[$header])[0];
            return trim($ip);
        }
    }
    return '0.0.0.0';
}

function format_phone(string $phone): string
{
    return preg_replace('/[^0-9+]/', '', $phone);
}

// Criar serviços e torná-los globais
$db = null;
if (class_exists('\App\Database')) {
    try {
        $db = \App\Database::getInstance();
    } catch (Exception $e) {
        // Database not configured yet
    }
}

// Subscription Service
$subscriptionService = null;
if (class_exists('\App\Services\SubscriptionService') && $apiClient) {
    $subscriptionService = new \App\Services\SubscriptionService($apiClient, $config['tenant']['id'] ?? 1);
}

// Support Service
$supportService = null;
if (class_exists('\App\Services\SupportService') && $db) {
    $supportService = new \App\Services\SupportService($db);
}

// Booking Service
$bookingService = null;
if (class_exists('\App\Services\BookingService') && $db) {
    $bookingService = new \App\Services\BookingService($db);
}

// Set globals for use in pages
$GLOBALS['config'] = $config;
$GLOBALS['apiClient'] = $apiClient;
$GLOBALS['auth'] = $auth;
$GLOBALS['settings'] = $settings;
$GLOBALS['subscriptionService'] = $subscriptionService;
$GLOBALS['supportService'] = $supportService;
$GLOBALS['bookingService'] = $bookingService;

// Retornar container (backwards compatibility)
return [
    'config' => $config,
    'apiClient' => $apiClient,
    'auth' => $auth,
    'settingsStore' => $settingsStore,
    'settings' => $settings,
    'subscriptionService' => $subscriptionService,
    'supportService' => $supportService,
    'bookingService' => $bookingService,
];
