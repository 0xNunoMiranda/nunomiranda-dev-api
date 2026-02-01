<?php
/**
 * Setup Inicial - Assistente de Configuração com Módulos
 * 
 * Este ficheiro guia o utilizador pela configuração inicial do sistema
 * incluindo seleção de módulos e validação de licença.
 */

session_start();

// Verificar se já está instalado
$lockFile = __DIR__ . '/../storage/.installed';
if (file_exists($lockFile) && !isset($_GET['force'])) {
    header('Location: index.php');
    exit;
}

$configFile = __DIR__ . '/../config/config.php';
$configExample = __DIR__ . '/../config/config.example.php';
$schemaFile = __DIR__ . '/../database/schema.sql';

// Carregar configuração existente ou exemplo
$config = file_exists($configFile) ? require $configFile : (file_exists($configExample) ? require $configExample : []);

$step = (int)($_GET['step'] ?? 1);
$totalSteps = 8;
$error = '';
$success = '';

// Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'check_requirements':
            $requirements = checkRequirements();
            if ($requirements['passed']) {
                header('Location: setup.php?step=2');
                exit;
            }
            $error = 'Alguns requisitos não foram cumpridos.';
            break;
            
        case 'validate_license':
            $licenseKey = trim($_POST['license_key'] ?? '');
            $apiUrl = trim($_POST['api_url'] ?? 'http://localhost:3000');
            
            if (empty($licenseKey)) {
                $error = 'Introduz a chave de licença fornecida.';
                break;
            }
            
            // Validar licença na API
            $validation = validateLicenseWithApi($apiUrl, $licenseKey);
            
            if (!$validation['valid']) {
                $error = 'Licença inválida: ' . ($validation['error'] ?? 'Erro desconhecido');
                break;
            }
            
            $_SESSION['setup_license'] = [
                'key' => $licenseKey,
                'data' => $validation['license'],
            ];
            $_SESSION['setup_api'] = [
                'base_url' => $apiUrl,
            ];
            
            header('Location: setup.php?step=3');
            exit;
            break;
            
        case 'save_database':
            $dbConfig = [
                'host' => trim($_POST['db_host'] ?? 'localhost'),
                'port' => (int)($_POST['db_port'] ?? 3306),
                'name' => trim($_POST['db_name'] ?? ''),
                'user' => trim($_POST['db_user'] ?? ''),
                'pass' => $_POST['db_pass'] ?? '',
                'charset' => 'utf8mb4',
            ];
            
            try {
                $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset={$dbConfig['charset']}";
                $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `{$dbConfig['name']}`");
                
                if (file_exists($schemaFile)) {
                    $sql = file_get_contents($schemaFile);
                    $pdo->exec($sql);
                }
                
                $_SESSION['setup_db'] = $dbConfig;
                header('Location: setup.php?step=4');
                exit;
                
            } catch (PDOException $e) {
                $error = 'Erro de conexão: ' . $e->getMessage();
            }
            break;
            
        case 'save_business':
            $_SESSION['setup_business'] = [
                'name' => trim($_POST['business_name'] ?? ''),
                'description' => trim($_POST['business_description'] ?? ''),
                'phone' => trim($_POST['business_phone'] ?? ''),
                'email' => trim($_POST['business_email'] ?? ''),
                'address' => trim($_POST['business_address'] ?? ''),
                'services' => array_filter(array_map('trim', explode("\n", $_POST['business_services'] ?? ''))),
            ];
            header('Location: setup.php?step=5');
            exit;
            break;
            
        case 'save_modules':
            $modules = [
                'static_site' => [
                    'enabled' => isset($_POST['mod_static_site']),
                    'ai_generated' => isset($_POST['mod_static_site_ai']),
                    'theme' => $_POST['site_theme'] ?? 'dark',
                ],
                'bot_widget' => [
                    'enabled' => isset($_POST['mod_bot_widget']),
                    'type' => $_POST['bot_type'] ?? 'faq',
                    'features' => $_POST['bot_features'] ?? ['info'],
                    'position' => $_POST['bot_position'] ?? 'floating',
                    'exportable' => isset($_POST['bot_exportable']),
                ],
                'bot_whatsapp' => [
                    'enabled' => isset($_POST['mod_bot_whatsapp']),
                    'features' => $_POST['whatsapp_features'] ?? ['info'],
                    'phone_number' => $_POST['whatsapp_number'] ?? '',
                ],
                'ai_calls' => [
                    'enabled' => isset($_POST['mod_ai_calls']),
                ],
                'email' => [
                    'enabled' => isset($_POST['mod_email']),
                    'provider' => $_POST['email_provider'] ?? 'smtp',
                ],
                'sms' => [
                    'enabled' => isset($_POST['mod_sms']),
                    'provider' => $_POST['sms_provider'] ?? '',
                ],
                'shop' => [
                    'enabled' => isset($_POST['mod_shop']),
                    'platform' => $_POST['shop_platform'] ?? 'prestashop',
                ],
            ];
            
            $_SESSION['setup_modules'] = $modules;
            
            // Atualizar módulos na API
            $apiUrl = $_SESSION['setup_api']['base_url'] ?? '';
            $licenseKey = $_SESSION['setup_license']['key'] ?? '';
            
            if ($apiUrl && $licenseKey) {
                updateModulesInApi($apiUrl, $licenseKey, $modules);
            }
            
            header('Location: setup.php?step=6');
            exit;
            break;
            
        case 'save_admin':
            $pin = trim($_POST['admin_pin'] ?? '');
            $pinConfirm = trim($_POST['admin_pin_confirm'] ?? '');
            
            if (strlen($pin) < 4) {
                $error = 'O PIN deve ter pelo menos 4 caracteres.';
                break;
            }
            
            if ($pin !== $pinConfirm) {
                $error = 'Os PINs não coincidem.';
                break;
            }
            
            $_SESSION['setup_admin'] = ['pin' => $pin];
            header('Location: setup.php?step=7');
            exit;
            break;
            
        case 'generate_site':
            // Gerar site com AI se selecionado
            $modules = $_SESSION['setup_modules'] ?? [];
            
            if (!empty($modules['static_site']['ai_generated'])) {
                $apiUrl = $_SESSION['setup_api']['base_url'] ?? '';
                $licenseKey = $_SESSION['setup_license']['key'] ?? '';
                $business = $_SESSION['setup_business'] ?? [];
                
                $result = generateSiteWithAi($apiUrl, $licenseKey, $business);
                
                if ($result['success']) {
                    $_SESSION['setup_site_content'] = $result['content'];
                    $success = 'Conteúdo do site gerado com sucesso!';
                } else {
                    $error = 'Erro ao gerar site: ' . ($result['error'] ?? 'Erro desconhecido');
                    break;
                }
            }
            
            header('Location: setup.php?step=8');
            exit;
            break;
            
        case 'finish_setup':
            $finalConfig = buildFinalConfig();
            
            $configContent = "<?php\n/**\n * Configuração gerada pelo Setup - " . date('Y-m-d H:i:s') . "\n */\n\nreturn " . var_export($finalConfig, true) . ";\n";
            
            if (file_put_contents($configFile, $configContent)) {
                // Gerar ficheiro de site se houver conteúdo AI
                if (!empty($_SESSION['setup_site_content'])) {
                    generateSiteFiles($_SESSION['setup_site_content'], $finalConfig);
                }
                
                file_put_contents($lockFile, json_encode([
                    'installed_at' => date('Y-m-d H:i:s'),
                    'license_key' => $_SESSION['setup_license']['key'] ?? '',
                    'modules' => array_keys(array_filter($_SESSION['setup_modules'] ?? [], fn($m) => $m['enabled'] ?? false)),
                ], JSON_PRETTY_PRINT));
                
                // Limpar sessão
                unset($_SESSION['setup_license'], $_SESSION['setup_db'], $_SESSION['setup_business'], 
                      $_SESSION['setup_modules'], $_SESSION['setup_admin'], $_SESSION['setup_api'],
                      $_SESSION['setup_site_content']);
                
                header('Location: setup.php?step=9');
                exit;
            } else {
                $error = 'Não foi possível guardar a configuração.';
            }
            break;
    }
}

/**
 * Valida licença com a API Node.js
 */
function validateLicenseWithApi(string $apiUrl, string $licenseKey): array {
    $url = rtrim($apiUrl, '/') . '/api/licenses/' . urlencode($licenseKey) . '/validate';
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['valid' => false, 'error' => 'Não foi possível conectar à API: ' . $curlError];
    }
    
    if ($httpCode !== 200) {
        $data = json_decode($response, true);
        return ['valid' => false, 'error' => $data['error'] ?? 'Erro da API (HTTP ' . $httpCode . ')'];
    }
    
    $data = json_decode($response, true);
    return [
        'valid' => $data['valid'] ?? false,
        'license' => $data['license'] ?? null,
        'error' => $data['error'] ?? null,
    ];
}

/**
 * Atualiza módulos na API
 */
function updateModulesInApi(string $apiUrl, string $licenseKey, array $modules): bool {
    $url = rtrim($apiUrl, '/') . '/api/licenses/' . urlencode($licenseKey) . '/modules';
    
    // Converter para formato da API
    $apiModules = [];
    foreach ($modules as $key => $mod) {
        $apiModules[$key] = $mod['enabled'] ?? false;
    }
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => json_encode(['modules' => $apiModules]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 10,
    ]);
    
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200;
}

/**
 * Gera site com AI
 */
function generateSiteWithAi(string $apiUrl, string $licenseKey, array $business): array {
    $url = rtrim($apiUrl, '/') . '/api/ai/generate-site';
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'licenseKey' => $licenseKey,
            'businessInfo' => [
                'name' => $business['name'] ?? '',
                'description' => $business['description'] ?? '',
                'services' => $business['services'] ?? [],
                'phone' => $business['phone'] ?? '',
                'email' => $business['email'] ?? '',
                'address' => $business['address'] ?? '',
                'style' => $_SESSION['setup_modules']['static_site']['theme'] ?? 'modern',
            ],
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 120,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['success' => false, 'error' => 'Erro de conexão: ' . $curlError];
    }
    
    if ($httpCode !== 200) {
        $data = json_decode($response, true);
        return ['success' => false, 'error' => $data['error'] ?? 'Falha na geração (HTTP ' . $httpCode . ')'];
    }
    
    $data = json_decode($response, true);
    return [
        'success' => $data['success'] ?? false,
        'content' => $data['content'] ?? null,
        'error' => $data['error'] ?? null,
    ];
}

/**
 * Gera ficheiros do site com conteúdo AI
 */
function generateSiteFiles(array $content, array $config): void {
    // Guardar conteúdo gerado em JSON para uso dinâmico
    $contentFile = __DIR__ . '/../storage/site_content.json';
    file_put_contents($contentFile, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Verifica requisitos
 */
function checkRequirements(): array {
    $requirements = [
        'php_version' => [
            'name' => 'PHP 8.0+',
            'passed' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'current' => PHP_VERSION,
        ],
        'pdo_mysql' => [
            'name' => 'PDO MySQL',
            'passed' => extension_loaded('pdo_mysql'),
            'current' => extension_loaded('pdo_mysql') ? 'Instalado' : 'Não instalado',
        ],
        'curl' => [
            'name' => 'cURL',
            'passed' => extension_loaded('curl'),
            'current' => extension_loaded('curl') ? 'Instalado' : 'Não instalado',
        ],
        'json' => [
            'name' => 'JSON',
            'passed' => extension_loaded('json'),
            'current' => extension_loaded('json') ? 'Instalado' : 'Não instalado',
        ],
        'config_writable' => [
            'name' => 'Pasta config/ gravável',
            'passed' => is_writable(__DIR__ . '/../config'),
            'current' => is_writable(__DIR__ . '/../config') ? 'Sim' : 'Não',
        ],
        'storage_writable' => [
            'name' => 'Pasta storage/ gravável',
            'passed' => is_writable(__DIR__ . '/../storage'),
            'current' => is_writable(__DIR__ . '/../storage') ? 'Sim' : 'Não',
        ],
    ];
    
    $passed = true;
    foreach ($requirements as $req) {
        if (!$req['passed']) {
            $passed = false;
            break;
        }
    }
    
    return ['items' => $requirements, 'passed' => $passed];
}

/**
 * Constrói configuração final
 */
function buildFinalConfig(): array {
    $license = $_SESSION['setup_license'] ?? [];
    $db = $_SESSION['setup_db'] ?? [];
    $business = $_SESSION['setup_business'] ?? [];
    $modules = $_SESSION['setup_modules'] ?? [];
    $admin = $_SESSION['setup_admin'] ?? [];
    $api = $_SESSION['setup_api'] ?? [];
    
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $business['name'] ?? 'meu-negocio'));
    
    return [
        'license_key' => $license['key'] ?? '',
        
        'tenant' => [
            'id' => $license['data']['tenantId'] ?? 1,
            'slug' => $slug,
            'name' => $business['name'] ?? 'Meu Negócio',
        ],
        
        'api' => [
            'base_url' => $api['base_url'] ?? 'http://localhost:3000',
            'timeout' => 30,
        ],
        
        'database' => $db,
        
        'admin' => [
            'path' => '/admin/',
            'pin' => $admin['pin'] ?? '1234',
            'session_lifetime' => 3600 * 8,
        ],
        
        'modules' => $modules,
        
        'site' => [
            'title' => $business['name'] ?? 'Meu Negócio',
            'description' => $business['description'] ?? '',
            'meta_description' => $business['description'] ?? '',
            'phone' => $business['phone'] ?? '',
            'email' => $business['email'] ?? '',
            'address' => $business['address'] ?? '',
            'services' => $business['services'] ?? [],
            'theme' => $modules['static_site']['theme'] ?? 'dark',
            'social' => ['instagram' => '', 'facebook' => ''],
        ],
        
        'storage' => [
            'settings_file' => __DIR__ . '/../storage/settings.json',
            'site_content_file' => __DIR__ . '/../storage/site_content.json',
            'logs_dir' => __DIR__ . '/../storage/logs',
        ],
    ];
}

$requirements = checkRequirements();
$licenseData = $_SESSION['setup_license']['data'] ?? null;

// Step titles
$stepTitles = [
    1 => 'Requisitos',
    2 => 'Licença',
    3 => 'Base de Dados',
    4 => 'Negócio',
    5 => 'Módulos',
    6 => 'Admin',
    7 => 'Gerar Conteúdo',
    8 => 'Finalizar',
    9 => 'Concluído',
];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - <?php echo $stepTitles[$step] ?? 'Instalação'; ?></title>
    <style>
        :root {
            --accent: #00ffc6;
            --accent-hover: #00e6b3;
            --bg: #0a0a0f;
            --bg-card: #12121a;
            --bg-input: #1a1a25;
            --text: #f0f0f5;
            --text-muted: #888;
            --border: rgba(255,255,255,0.1);
            --success: #22c55e;
            --error: #ef4444;
            --warning: #f59e0b;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 2rem;
            line-height: 1.6;
        }
        
        .setup-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .setup-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .setup-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .setup-header h1 span { color: var(--accent); }
        
        .setup-header p { color: var(--text-muted); }
        
        /* Progress Steps */
        .steps {
            display: flex;
            justify-content: center;
            gap: 0.25rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .step-item {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .step-dot {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .step-dot.active {
            background: var(--accent);
            color: var(--bg);
            box-shadow: 0 0 15px rgba(0, 255, 198, 0.4);
        }
        
        .step-dot.completed {
            background: var(--success);
            color: white;
        }
        
        .step-line {
            width: 15px;
            height: 2px;
            background: var(--border);
        }
        
        .step-line.completed { background: var(--success); }
        
        /* Card */
        .setup-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2rem;
        }
        
        .setup-card h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .setup-card > p {
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }
        
        /* Form */
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.875rem 1rem;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: var(--text-muted);
            font-size: 0.8rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        /* Modules Grid */
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .module-card {
            background: var(--bg-input);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        
        .module-card:hover {
            border-color: rgba(0, 255, 198, 0.5);
        }
        
        .module-card.selected {
            border-color: var(--accent);
            background: rgba(0, 255, 198, 0.05);
        }
        
        .module-card.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .module-card.always-on::after {
            content: 'Incluído';
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            font-size: 0.65rem;
            padding: 0.15rem 0.4rem;
            background: var(--success);
            color: white;
            border-radius: 3px;
        }
        
        .module-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        
        .module-icon {
            font-size: 1.5rem;
        }
        
        .module-header h4 {
            flex: 1;
            font-size: 1rem;
        }
        
        .module-check {
            width: 22px;
            height: 22px;
            border: 2px solid var(--border);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }
        
        .module-card.selected .module-check {
            background: var(--accent);
            border-color: var(--accent);
            color: var(--bg);
        }
        
        .module-desc {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-bottom: 0.75rem;
        }
        
        .module-options {
            display: none;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
            margin-top: 1rem;
        }
        
        .module-card.selected .module-options {
            display: block;
        }
        
        .module-options label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .module-options input[type="checkbox"],
        .module-options input[type="radio"] {
            width: auto;
        }
        
        .module-options select {
            padding: 0.5rem;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
        
        .module-options input[type="text"],
        .module-options input[type="tel"] {
            padding: 0.5rem;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
        
        .module-badge {
            font-size: 0.65rem;
            padding: 0.15rem 0.4rem;
            background: var(--warning);
            color: var(--bg);
            border-radius: 4px;
            margin-left: 0.5rem;
        }
        
        .option-group {
            margin: 0.75rem 0;
        }
        
        .option-group strong {
            display: block;
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 0.25rem;
        }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
        }
        
        .btn-accent {
            background: var(--accent);
            color: var(--bg);
        }
        
        .btn-accent:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: var(--bg-input);
            color: var(--text);
            border: 1px solid var(--border);
        }
        
        .btn-block { width: 100%; }
        
        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .btn-group .btn { flex: 1; }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error);
            color: var(--error);
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
        }
        
        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid var(--warning);
            color: var(--warning);
        }
        
        /* Requirements List */
        .requirements-list {
            list-style: none;
            margin-bottom: 1.5rem;
        }
        
        .requirements-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }
        
        .req-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        .req-status.passed { color: var(--success); }
        .req-status.failed { color: var(--error); }
        
        /* Success Screen */
        .success-content {
            text-align: center;
            padding: 2rem 0;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
        }
        
        /* Info Box */
        .info-box {
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .info-box p {
            margin: 0.25rem 0;
        }
        
        .info-box code {
            color: var(--accent);
            font-family: monospace;
        }
        
        .info-box ul {
            margin: 0.5rem 0 0 1.5rem;
        }
        
        /* Credits Display */
        .credits-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 0.5rem;
            margin: 1rem 0;
        }
        
        .credit-item {
            background: var(--bg-input);
            border-radius: 8px;
            padding: 0.75rem;
            text-align: center;
        }
        
        .credit-item span {
            display: block;
            font-size: 0.7rem;
            color: var(--text-muted);
        }
        
        .credit-item strong {
            color: var(--accent);
            font-size: 1.1rem;
        }
        
        /* Loader */
        .loader {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        
        .loader.active { display: block; }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 640px) {
            .form-row { grid-template-columns: 1fr; }
            .modules-grid { grid-template-columns: 1fr; }
            .steps { gap: 0.15rem; }
            .step-dot { width: 26px; height: 26px; font-size: 0.65rem; }
            .step-line { width: 8px; }
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1>🚀 <span>Setup</span> Inicial</h1>
            <p>Passo <?php echo min($step, 8); ?> de 8 — <?php echo $stepTitles[$step] ?? ''; ?></p>
        </div>
        
        <!-- Progress Steps -->
        <div class="steps">
            <?php for ($i = 1; $i <= 8; $i++): ?>
            <div class="step-item">
                <div class="step-dot <?php echo $i < $step ? 'completed' : ($i === $step ? 'active' : ''); ?>">
                    <?php echo $i < $step ? '✓' : $i; ?>
                </div>
                <?php if ($i < 8): ?>
                <div class="step-line <?php echo $i < $step ? 'completed' : ''; ?>"></div>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>
        
        <div class="setup-card">
            <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($step === 1): ?>
            <!-- Step 1: Requisitos -->
            <h2>1. Verificar Requisitos</h2>
            <p>Vamos verificar se o servidor tem tudo o que precisa.</p>
            
            <ul class="requirements-list">
                <?php foreach ($requirements['items'] as $req): ?>
                <li>
                    <span><?php echo $req['name']; ?></span>
                    <span class="req-status <?php echo $req['passed'] ? 'passed' : 'failed'; ?>">
                        <?php echo $req['passed'] ? '✓' : '✗'; ?>
                        <?php echo $req['current']; ?>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
            
            <form method="POST">
                <input type="hidden" name="action" value="check_requirements">
                <button type="submit" class="btn btn-accent btn-block" <?php echo !$requirements['passed'] ? 'disabled' : ''; ?>>
                    <?php echo $requirements['passed'] ? 'Continuar →' : 'Corrige os requisitos primeiro'; ?>
                </button>
            </form>
            
            <?php elseif ($step === 2): ?>
            <!-- Step 2: Licença -->
            <h2>2. Validar Licença</h2>
            <p>Introduz a chave de licença fornecida pelo administrador do sistema.</p>
            
            <div class="alert alert-warning">
                💡 A licença controla os módulos disponíveis e os créditos mensais para AI, emails, SMS, etc.
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="validate_license">
                
                <div class="form-group">
                    <label>URL da API</label>
                    <input type="url" name="api_url" value="http://localhost:3000" required>
                    <small>URL do servidor da API Node.js</small>
                </div>
                
                <div class="form-group">
                    <label>Chave de Licença *</label>
                    <input type="text" name="license_key" placeholder="ntk_xxxxxxxxxxxx.xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" required 
                           pattern="ntk_[a-f0-9]{12}\.[a-f0-9]{48}" title="Formato: ntk_xxxxxxxxxxxx.xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                    <small>Obtém esta chave junto do administrador do sistema</small>
                </div>
                
                <div class="btn-group">
                    <a href="setup.php?step=1" class="btn btn-secondary">← Voltar</a>
                    <button type="submit" class="btn btn-accent">Validar Licença →</button>
                </div>
            </form>
            
            <?php elseif ($step === 3): ?>
            <!-- Step 3: Base de Dados -->
            <h2>3. Base de Dados</h2>
            <p>Configura a ligação à base de dados MySQL local.</p>
            
            <?php if ($licenseData): ?>
            <div class="info-box">
                <p>✅ Licença válida: <code><?php echo htmlspecialchars($licenseData['clientName'] ?? 'Cliente'); ?></code></p>
                <p>📊 Estado: <code><?php echo htmlspecialchars(ucfirst($licenseData['status'] ?? 'active')); ?></code></p>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="action" value="save_database">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Host</label>
                        <input type="text" name="db_host" value="localhost" required>
                    </div>
                    <div class="form-group">
                        <label>Porta</label>
                        <input type="number" name="db_port" value="3306" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Nome da Base de Dados *</label>
                    <input type="text" name="db_name" placeholder="nome_do_negocio" required>
                    <small>Será criada automaticamente se não existir</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Utilizador</label>
                        <input type="text" name="db_user" value="root" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="db_pass">
                    </div>
                </div>
                
                <div class="btn-group">
                    <a href="setup.php?step=2" class="btn btn-secondary">← Voltar</a>
                    <button type="submit" class="btn btn-accent">Testar e Continuar →</button>
                </div>
            </form>
            
            <?php elseif ($step === 4): ?>
            <!-- Step 4: Informações do Negócio -->
            <h2>4. O Teu Negócio</h2>
            <p>Informações sobre o teu negócio para personalizar o sistema e gerar conteúdo.</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="save_business">
                
                <div class="form-group">
                    <label>Nome do Negócio *</label>
                    <input type="text" name="business_name" placeholder="Barbearia Ruben" required>
                </div>
                
                <div class="form-group">
                    <label>Descrição</label>
                    <textarea name="business_description" rows="3" placeholder="Uma breve descrição do teu negócio... (usado para geração AI)"></textarea>
                    <small>Quanto mais detalhes, melhor a AI poderá gerar conteúdo</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="tel" name="business_phone" placeholder="+351 912 345 678">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="business_email" placeholder="geral@exemplo.pt">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Morada</label>
                    <input type="text" name="business_address" placeholder="Rua Principal, 123, Lisboa">
                </div>
                
                <div class="form-group">
                    <label>Serviços (um por linha)</label>
                    <textarea name="business_services" rows="4" placeholder="Corte de cabelo&#10;Barba&#10;Tratamento capilar"></textarea>
                    <small>Lista os serviços que ofereces</small>
                </div>
                
                <div class="btn-group">
                    <a href="setup.php?step=3" class="btn btn-secondary">← Voltar</a>
                    <button type="submit" class="btn btn-accent">Continuar →</button>
                </div>
            </form>
            
            <?php elseif ($step === 5): ?>
            <!-- Step 5: Seleção de Módulos -->
            <h2>5. Escolher Módulos</h2>
            <p>Seleciona os módulos que queres ativar no teu sistema.</p>
            
            <?php if ($licenseData && isset($licenseData['credits'])): ?>
            <div class="credits-info">
                <div class="credit-item">
                    <strong><?php echo $licenseData['credits']['ai']['remaining'] ?? 0; ?></strong>
                    <span>Msgs AI</span>
                </div>
                <div class="credit-item">
                    <strong><?php echo $licenseData['credits']['email']['remaining'] ?? 0; ?></strong>
                    <span>Emails</span>
                </div>
                <div class="credit-item">
                    <strong><?php echo $licenseData['credits']['sms']['remaining'] ?? 0; ?></strong>
                    <span>SMS</span>
                </div>
                <div class="credit-item">
                    <strong><?php echo $licenseData['credits']['whatsapp']['remaining'] ?? 0; ?></strong>
                    <span>WhatsApp</span>
                </div>
                <div class="credit-item">
                    <strong><?php echo $licenseData['credits']['ai_calls']['remaining'] ?? 0; ?></strong>
                    <span>Chamadas</span>
                </div>
            </div>
            <?php endif; ?>
            
            <form method="POST" id="modulesForm">
                <input type="hidden" name="action" value="save_modules">
                
                <div class="modules-grid">
                    <!-- Site Estático (sempre ativo) -->
                    <div class="module-card selected always-on" data-module="static_site">
                        <div class="module-header">
                            <span class="module-icon">🌐</span>
                            <h4>Site Estático</h4>
                            <div class="module-check">✓</div>
                        </div>
                        <p class="module-desc">Landing page profissional com informações do negócio.</p>
                        <input type="hidden" name="mod_static_site" value="1">
                        <div class="module-options">
                            <div class="option-group">
                                <label>
                                    <input type="checkbox" name="mod_static_site_ai" value="1">
                                    🤖 Gerar conteúdo com AI
                                    <span class="module-badge">5 créditos</span>
                                </label>
                            </div>
                            <div class="option-group">
                                <strong>Tema Visual:</strong>
                                <select name="site_theme">
                                    <option value="dark">🌙 Escuro</option>
                                    <option value="light">☀️ Claro</option>
                                    <option value="minimal">✨ Minimalista</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bot Widget -->
                    <div class="module-card" data-module="bot_widget">
                        <div class="module-header">
                            <span class="module-icon">💬</span>
                            <h4>Bot Assistente</h4>
                            <div class="module-check">✓</div>
                        </div>
                        <p class="module-desc">Widget de chat para atendimento automático no site.</p>
                        <div class="module-options">
                            <input type="hidden" name="mod_bot_widget" value="0" class="module-toggle">
                            
                            <div class="option-group">
                                <strong>Tipo de Bot:</strong>
                                <label>
                                    <input type="radio" name="bot_type" value="faq" checked>
                                    📋 FAQ Estático (sem AI, sem créditos)
                                </label>
                                <label>
                                    <input type="radio" name="bot_type" value="ai">
                                    🤖 AI Inteligente
                                    <span class="module-badge">usa créditos</span>
                                </label>
                                <label>
                                    <input type="radio" name="bot_type" value="hybrid">
                                    🔄 Híbrido (FAQ primeiro, AI se necessário)
                                </label>
                            </div>
                            
                            <div class="option-group">
                                <strong>Funcionalidades:</strong>
                                <label>
                                    <input type="checkbox" name="bot_features[]" value="info" checked>
                                    ℹ️ Informações gerais
                                </label>
                                <label>
                                    <input type="checkbox" name="bot_features[]" value="bookings">
                                    📅 Marcações/Agendamentos
                                </label>
                                <label>
                                    <input type="checkbox" name="bot_features[]" value="shop">
                                    🛍️ Ajuda em compras
                                </label>
                            </div>
                            
                            <div class="option-group">
                                <strong>Posição no Site:</strong>
                                <label>
                                    <input type="radio" name="bot_position" value="floating" checked>
                                    💭 Balão flutuante
                                </label>
                                <label>
                                    <input type="radio" name="bot_position" value="tab">
                                    📑 Aba lateral
                                </label>
                                <label>
                                    <input type="radio" name="bot_position" value="both">
                                    ✅ Ambos
                                </label>
                            </div>
                            
                            <div class="option-group">
                                <label>
                                    <input type="checkbox" name="bot_exportable" value="1">
                                    📤 Permitir exportar widget (usar noutros sites)
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- WhatsApp Bot -->
                    <div class="module-card" data-module="bot_whatsapp">
                        <div class="module-header">
                            <span class="module-icon">📱</span>
                            <h4>Bot WhatsApp</h4>
                            <div class="module-check">✓</div>
                        </div>
                        <p class="module-desc">Atendimento automático via WhatsApp Business API.</p>
                        <div class="module-options">
                            <input type="hidden" name="mod_bot_whatsapp" value="0" class="module-toggle">
                            
                            <div class="option-group">
                                <strong>Funcionalidades:</strong>
                                <label>
                                    <input type="checkbox" name="whatsapp_features[]" value="info" checked>
                                    ℹ️ Informações e suporte
                                </label>
                                <label>
                                    <input type="checkbox" name="whatsapp_features[]" value="bookings">
                                    📅 Marcações
                                </label>
                                <label>
                                    <input type="checkbox" name="whatsapp_features[]" value="shop">
                                    🛒 Compras/Encomendas
                                </label>
                            </div>
                            
                            <div class="option-group">
                                <strong>Número WhatsApp Business:</strong>
                                <input type="tel" name="whatsapp_number" placeholder="+351912345678">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Chamadas AI -->
                    <div class="module-card" data-module="ai_calls">
                        <div class="module-header">
                            <span class="module-icon">📞</span>
                            <h4>Chamadas AI</h4>
                            <div class="module-check">✓</div>
                        </div>
                        <p class="module-desc">Atendimento telefónico automatizado com AI (voz).</p>
                        <div class="module-options">
                            <input type="hidden" name="mod_ai_calls" value="0" class="module-toggle">
                            <div class="alert alert-warning" style="margin-top: 0;">
                                ⚠️ Requer configuração adicional de provider (Twilio/etc) após instalação.
                            </div>
                        </div>
                    </div>
                    
                    <!-- Email -->
                    <div class="module-card" data-module="email">
                        <div class="module-header">
                            <span class="module-icon">✉️</span>
                            <h4>Envio de Emails</h4>
                            <div class="module-check">✓</div>
                        </div>
                        <p class="module-desc">Notificações, confirmações e emails transacionais.</p>
                        <div class="module-options">
                            <input type="hidden" name="mod_email" value="0" class="module-toggle">
                            
                            <div class="option-group">
                                <strong>Provider:</strong>
                                <select name="email_provider">
                                    <option value="smtp">SMTP (Genérico)</option>
                                    <option value="sendgrid">SendGrid</option>
                                    <option value="mailgun">Mailgun</option>
                                    <option value="ses">Amazon SES</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SMS -->
                    <div class="module-card" data-module="sms">
                        <div class="module-header">
                            <span class="module-icon">📨</span>
                            <h4>Envio de SMS</h4>
                            <div class="module-check">✓</div>
                        </div>
                        <p class="module-desc">Notificações e lembretes por SMS.</p>
                        <div class="module-options">
                            <input type="hidden" name="mod_sms" value="0" class="module-toggle">
                            
                            <div class="option-group">
                                <strong>Provider:</strong>
                                <select name="sms_provider">
                                    <option value="twilio">Twilio</option>
                                    <option value="vonage">Vonage/Nexmo</option>
                                    <option value="plivo">Plivo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Loja Online -->
                    <div class="module-card" data-module="shop">
                        <div class="module-header">
                            <span class="module-icon">🛒</span>
                            <h4>Loja Online</h4>
                            <div class="module-check">✓</div>
                        </div>
                        <p class="module-desc">Integração com plataforma de e-commerce.</p>
                        <div class="module-options">
                            <input type="hidden" name="mod_shop" value="0" class="module-toggle">
                            
                            <div class="option-group">
                                <strong>Plataforma:</strong>
                                <select name="shop_platform">
                                    <option value="prestashop">PrestaShop</option>
                                    <option value="woocommerce">WooCommerce</option>
                                    <option value="shopify">Shopify (API)</option>
                                </select>
                            </div>
                            <p style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.5rem;">
                                💡 O site estático será a landing page principal. A loja aparecerá como secção "Loja".
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="btn-group">
                    <a href="setup.php?step=4" class="btn btn-secondary">← Voltar</a>
                    <button type="submit" class="btn btn-accent">Continuar →</button>
                </div>
            </form>
            
            <script>
                document.querySelectorAll('.module-card').forEach(card => {
                    const isAlwaysOn = card.classList.contains('always-on');
                    
                    card.addEventListener('click', (e) => {
                        // Ignorar cliques em inputs e labels
                        if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || 
                            e.target.tagName === 'LABEL' || e.target.tagName === 'OPTION') return;
                        
                        if (isAlwaysOn) return; // Módulo sempre ativo
                        
                        card.classList.toggle('selected');
                        const toggle = card.querySelector('.module-toggle');
                        if (toggle) {
                            toggle.value = card.classList.contains('selected') ? '1' : '0';
                        }
                    });
                });
            </script>
            
            <?php elseif ($step === 6): ?>
            <!-- Step 6: Admin -->
            <h2>6. Acesso Admin</h2>
            <p>Define um PIN para aceder ao painel de administração.</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="save_admin">
                
                <div class="form-group">
                    <label>PIN de Acesso *</label>
                    <input type="password" name="admin_pin" minlength="4" required placeholder="••••••" autocomplete="new-password">
                    <small>Mínimo 4 caracteres. Pode ser numérico ou alfanumérico.</small>
                </div>
                
                <div class="form-group">
                    <label>Confirmar PIN *</label>
                    <input type="password" name="admin_pin_confirm" minlength="4" required placeholder="••••••" autocomplete="new-password">
                </div>
                
                <div class="btn-group">
                    <a href="setup.php?step=5" class="btn btn-secondary">← Voltar</a>
                    <button type="submit" class="btn btn-accent">Continuar →</button>
                </div>
            </form>
            
            <?php elseif ($step === 7): ?>
            <!-- Step 7: Gerar Site -->
            <h2>7. Gerar Conteúdo</h2>
            
            <?php 
            $modules = $_SESSION['setup_modules'] ?? [];
            $generateSite = !empty($modules['static_site']['ai_generated']);
            ?>
            
            <?php if ($generateSite): ?>
            <p>Vamos usar AI para gerar o conteúdo do teu site automaticamente.</p>
            
            <div class="info-box">
                <p>📝 <strong>Negócio:</strong> <code><?php echo htmlspecialchars($_SESSION['setup_business']['name'] ?? ''); ?></code></p>
                <p>📋 <strong>Descrição:</strong> <?php echo htmlspecialchars($_SESSION['setup_business']['description'] ?? 'N/A'); ?></p>
                <p>🎨 <strong>Tema:</strong> <?php echo htmlspecialchars($modules['static_site']['theme'] ?? 'dark'); ?></p>
                <p>💳 <strong>Custo:</strong> 5 créditos AI</p>
            </div>
            
            <div class="alert alert-warning">
                ⏱️ A geração pode demorar até 60 segundos. Não feches esta página.
            </div>
            
            <form method="POST" id="generateForm">
                <input type="hidden" name="action" value="generate_site">
                
                <div id="generateLoader" class="loader">
                    <div class="spinner"></div>
                    <p>A gerar conteúdo com AI...</p>
                </div>
                
                <div class="btn-group" id="generateButtons">
                    <a href="setup.php?step=6" class="btn btn-secondary">← Voltar</a>
                    <button type="submit" class="btn btn-accent" id="generateBtn">🤖 Gerar com AI →</button>
                </div>
            </form>
            
            <script>
                document.getElementById('generateForm').addEventListener('submit', function() {
                    document.getElementById('generateLoader').classList.add('active');
                    document.getElementById('generateButtons').style.display = 'none';
                });
            </script>
            
            <?php else: ?>
            <p>Não selecionaste geração de conteúdo com AI.</p>
            <p>Podes avançar para finalizar a instalação. O conteúdo do site pode ser editado manualmente depois.</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="generate_site">
                <div class="btn-group">
                    <a href="setup.php?step=6" class="btn btn-secondary">← Voltar</a>
                    <button type="submit" class="btn btn-accent">Continuar →</button>
                </div>
            </form>
            <?php endif; ?>
            
            <?php elseif ($step === 8): ?>
            <!-- Step 8: Confirmar -->
            <h2>8. Finalizar Instalação</h2>
            <p>Revê as configurações antes de finalizar.</p>
            
            <div class="info-box">
                <p><strong>🔑 Licença:</strong> <code><?php echo substr($_SESSION['setup_license']['key'] ?? '', 0, 20); ?>...</code></p>
                <p><strong>🏢 Negócio:</strong> <code><?php echo htmlspecialchars($_SESSION['setup_business']['name'] ?? 'N/A'); ?></code></p>
                <p><strong>🗄️ Base de Dados:</strong> <code><?php echo htmlspecialchars($_SESSION['setup_db']['name'] ?? 'N/A'); ?></code></p>
                
                <p style="margin-top: 1rem;"><strong>📦 Módulos Ativos:</strong></p>
                <ul>
                    <?php 
                    $modules = $_SESSION['setup_modules'] ?? [];
                    $moduleNames = [
                        'static_site' => '🌐 Site Estático',
                        'bot_widget' => '💬 Bot Widget',
                        'bot_whatsapp' => '📱 WhatsApp Bot',
                        'ai_calls' => '📞 Chamadas AI',
                        'email' => '✉️ Email',
                        'sms' => '📨 SMS',
                        'shop' => '🛒 Loja Online',
                    ];
                    foreach ($modules as $key => $mod):
                        if (!empty($mod['enabled'])):
                            $extra = '';
                            if ($key === 'bot_widget') {
                                $extra = ' (' . ($mod['type'] ?? 'faq') . ')';
                            }
                    ?>
                    <li><?php echo ($moduleNames[$key] ?? $key) . $extra; ?></li>
                    <?php endif; endforeach; ?>
                </ul>
            </div>
            
            <?php if (!empty($_SESSION['setup_site_content'])): ?>
            <div class="alert alert-success">
                ✅ Conteúdo do site gerado com AI com sucesso!
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="action" value="finish_setup">
                
                <div class="btn-group">
                    <a href="setup.php?step=7" class="btn btn-secondary">← Voltar</a>
                    <button type="submit" class="btn btn-accent">🚀 Finalizar Instalação</button>
                </div>
            </form>
            
            <?php elseif ($step === 9): ?>
            <!-- Success -->
            <div class="success-content">
                <div class="success-icon">✓</div>
                <h2>Instalação Concluída!</h2>
                <p style="color: var(--text-muted); margin-bottom: 1.5rem;">
                    O sistema está configurado e pronto a usar.
                </p>
                
                <div class="info-box" style="text-align: left;">
                    <p><strong>Próximos passos:</strong></p>
                    <ul>
                        <li>Acede ao painel Admin para configurar detalhes</li>
                        <li>Personaliza o conteúdo do site</li>
                        <li>Configura as FAQs do bot (se ativo)</li>
                        <li>Testa o sistema de marcações</li>
                    </ul>
                </div>
                
                <div class="btn-group" style="justify-content: center; margin-top: 2rem;">
                    <a href="index.php" class="btn btn-secondary">Ver Site</a>
                    <a href="admin/" class="btn btn-accent">Abrir Admin</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
