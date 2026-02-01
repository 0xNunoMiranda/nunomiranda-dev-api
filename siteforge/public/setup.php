<?php
/**
 * SiteForge - Setup Inicial
 * 
 * Assistente de configuração com validação de licença e seleção de módulos.
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

// Regex para validar formato da chave: ntk_xxxxxxxxxxxx.xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
define('LICENSE_KEY_PATTERN', '/^ntk_[a-f0-9]{12}\.[a-f0-9]{48}$/');

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
            
            // Validar formato da chave
            if (!preg_match(LICENSE_KEY_PATTERN, $licenseKey)) {
                $error = 'Formato de chave inválido. O formato deve ser: ntk_xxxxxxxxxxxx.xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
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
            
            $configContent = "<?php\n/**\n * SiteForge - Configuração gerada em " . date('Y-m-d H:i:s') . "\n */\n\nreturn " . var_export($finalConfig, true) . ";\n";
            
            if (file_put_contents($configFile, $configContent)) {
                if (!empty($_SESSION['setup_site_content'])) {
                    generateSiteFiles($_SESSION['setup_site_content'], $finalConfig);
                }
                
                file_put_contents($lockFile, json_encode([
                    'installed_at' => date('Y-m-d H:i:s'),
                    'license_key' => $_SESSION['setup_license']['key'] ?? '',
                    'modules' => array_keys(array_filter($_SESSION['setup_modules'] ?? [], fn($m) => $m['enabled'] ?? false)),
                ], JSON_PRETTY_PRINT));
                
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
 * Valida licença com a API
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
            'businessInfo' => $business,
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 60,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        $data = json_decode($response, true);
        return ['success' => false, 'error' => $data['error'] ?? 'Erro ao gerar site'];
    }
    
    $data = json_decode($response, true);
    return [
        'success' => $data['success'] ?? false,
        'content' => $data['content'] ?? null,
        'error' => $data['error'] ?? null,
    ];
}

/**
 * Verifica requisitos do sistema
 */
function checkRequirements(): array {
    $checks = [
        'php_version' => [
            'label' => 'PHP 8.0+',
            'passed' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'value' => PHP_VERSION,
        ],
        'pdo_mysql' => [
            'label' => 'PDO MySQL',
            'passed' => extension_loaded('pdo_mysql'),
            'value' => extension_loaded('pdo_mysql') ? 'Instalado' : 'Não instalado',
        ],
        'curl' => [
            'label' => 'cURL',
            'passed' => extension_loaded('curl'),
            'value' => extension_loaded('curl') ? 'Instalado' : 'Não instalado',
        ],
        'json' => [
            'label' => 'JSON',
            'passed' => extension_loaded('json'),
            'value' => extension_loaded('json') ? 'Instalado' : 'Não instalado',
        ],
        'mbstring' => [
            'label' => 'Multibyte String',
            'passed' => extension_loaded('mbstring'),
            'value' => extension_loaded('mbstring') ? 'Instalado' : 'Não instalado',
        ],
        'storage_writable' => [
            'label' => 'Pasta storage/ gravável',
            'passed' => is_writable(__DIR__ . '/../storage'),
            'value' => is_writable(__DIR__ . '/../storage') ? 'Sim' : 'Não',
        ],
        'config_writable' => [
            'label' => 'Pasta config/ gravável',
            'passed' => is_writable(__DIR__ . '/../config'),
            'value' => is_writable(__DIR__ . '/../config') ? 'Sim' : 'Não',
        ],
    ];
    
    $allPassed = array_reduce($checks, fn($carry, $check) => $carry && $check['passed'], true);
    
    return ['checks' => $checks, 'passed' => $allPassed];
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
    
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $business['name'] ?? 'site'));
    
    return [
        'tenant' => [
            'id' => $license['data']['tenantId'] ?? 0,
            'slug' => $slug,
            'name' => $business['name'] ?? '',
            'email' => $business['email'] ?? '',
            'phone' => $business['phone'] ?? '',
            'timezone' => 'Europe/Lisbon',
        ],
        'license_key' => $license['key'] ?? '',
        'api_url' => $api['base_url'] ?? 'http://localhost:3000',
        'site_url' => '',
        'api' => [
            'base_url' => $api['base_url'] ?? 'http://localhost:3000',
            'api_key' => '',
            'timeout' => 30,
        ],
        'database' => [
            'enabled' => true,
            'host' => $db['host'] ?? 'localhost',
            'port' => $db['port'] ?? 3306,
            'name' => $db['name'] ?? '',
            'user' => $db['user'] ?? '',
            'password' => $db['pass'] ?? '',
            'charset' => 'utf8mb4',
        ],
        'admin' => [
            'path' => '/admin',
            'slug_path' => '/admin.php',
            'pin' => $admin['pin'] ?? '1234',
            'session_lifetime' => 3600 * 8,
            'allowed_ips' => [],
        ],
        'modules' => $modules,
        'site' => [
            'title' => $business['name'] ?? '',
            'description' => $business['description'] ?? '',
            'locale' => 'pt_PT',
            'theme' => [
                'primary_color' => '#00ffc6',
                'background' => $modules['static_site']['theme'] ?? 'dark',
            ],
            'social' => [
                'instagram' => '',
                'facebook' => '',
            ],
            'analytics' => [
                'google_analytics_id' => '',
                'facebook_pixel_id' => '',
            ],
        ],
        'storage' => [
            'settings_file' => __DIR__ . '/../storage/settings.json',
            'logs_dir' => __DIR__ . '/../storage/logs',
            'cache_dir' => __DIR__ . '/../storage/cache',
        ],
    ];
}

/**
 * Gera ficheiros do site
 */
function generateSiteFiles(array $content, array $config): void {
    $siteDir = __DIR__ . '/generated';
    if (!is_dir($siteDir)) {
        mkdir($siteDir, 0755, true);
    }
    
    file_put_contents($siteDir . '/content.json', json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ============================================================================
// INTERFACE
// ============================================================================
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SiteForge - Setup</title>
    <style>
        :root {
            --primary: #00ffc6;
            --primary-dark: #00d4a4;
            --bg: #0a0a0f;
            --bg-card: #12121a;
            --bg-input: #1a1a25;
            --text: #f0f0f5;
            --text-muted: #888;
            --border: rgba(255,255,255,0.1);
            --success: #4caf50;
            --warning: #ff9800;
            --error: #f44336;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--bg), var(--bg-card));
            min-height: 100vh;
            color: var(--text);
            line-height: 1.6;
        }
        
        .container {
            max-width: 700px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo h1 {
            font-size: 32px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .logo p {
            color: var(--text-muted);
            margin-top: 8px;
        }
        
        .progress {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 40px;
        }
        
        .progress-step {
            width: 40px;
            height: 4px;
            background: var(--border);
            border-radius: 2px;
            transition: background 0.3s;
        }
        
        .progress-step.active { background: var(--primary); }
        .progress-step.done { background: var(--success); }
        
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 24px;
        }
        
        .card h2 {
            color: var(--primary);
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .card .subtitle {
            color: var(--text-muted);
            margin-bottom: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text);
        }
        
        .form-group small {
            display: block;
            color: var(--text-muted);
            font-size: 12px;
            margin-top: 4px;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        input[type="tel"],
        textarea,
        select {
            width: 100%;
            padding: 14px 16px;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text);
            font-size: 15px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 255, 198, 0.1);
        }
        
        textarea { min-height: 100px; resize: vertical; }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 28px;
            background: var(--primary);
            color: var(--bg);
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: var(--bg-input);
            color: var(--text);
            border: 1px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: var(--border);
        }
        
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: rgba(244, 67, 54, 0.15);
            border-left: 3px solid var(--error);
            color: #ff8a80;
        }
        
        .alert-success {
            background: rgba(76, 175, 80, 0.15);
            border-left: 3px solid var(--success);
            color: #a5d6a7;
        }
        
        .requirements-list {
            list-style: none;
        }
        
        .requirements-list li {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .requirements-list li:last-child { border-bottom: none; }
        
        .status-icon { font-size: 18px; }
        .status-pass { color: var(--success); }
        .status-fail { color: var(--error); }
        
        .module-grid {
            display: grid;
            gap: 16px;
        }
        
        .module-card {
            background: var(--bg-input);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .module-card:hover { border-color: var(--primary); }
        .module-card.selected { border-color: var(--primary); background: rgba(0, 255, 198, 0.05); }
        
        .module-card input[type="checkbox"] { display: none; }
        
        .module-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        
        .module-icon {
            font-size: 24px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg);
            border-radius: 8px;
        }
        
        .module-name { font-weight: 600; font-size: 16px; }
        .module-desc { color: var(--text-muted); font-size: 13px; }
        
        .module-options {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
            display: none;
        }
        
        .module-card.selected .module-options { display: block; }
        
        .option-group {
            margin-bottom: 12px;
        }
        
        .option-group label {
            font-size: 13px;
            margin-bottom: 6px;
            display: block;
        }
        
        .option-group select,
        .option-group input {
            font-size: 13px;
            padding: 10px 12px;
        }
        
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            background: var(--bg);
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
        }
        
        .success-icon {
            font-size: 64px;
            text-align: center;
            margin-bottom: 24px;
        }
        
        .key-format-hint {
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 11px;
            color: var(--text-muted);
            background: var(--bg);
            padding: 8px 12px;
            border-radius: 6px;
            margin-top: 8px;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>⚡ SiteForge</h1>
            <p>Assistente de Configuração</p>
        </div>
        
        <?php if ($step <= $totalSteps): ?>
        <div class="progress">
            <?php for ($i = 1; $i <= $totalSteps; $i++): ?>
            <div class="progress-step <?= $i < $step ? 'done' : ($i === $step ? 'active' : '') ?>"></div>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if ($step === 1): ?>
        <!-- STEP 1: REQUISITOS -->
        <div class="card">
            <h2>1. Verificar Requisitos</h2>
            <p class="subtitle">Vamos confirmar que o servidor está preparado.</p>
            
            <?php $requirements = checkRequirements(); ?>
            <ul class="requirements-list">
                <?php foreach ($requirements['checks'] as $check): ?>
                <li>
                    <span><?= $check['label'] ?></span>
                    <span class="status-icon <?= $check['passed'] ? 'status-pass' : 'status-fail' ?>">
                        <?= $check['passed'] ? '✓' : '✕' ?>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
            
            <form method="POST">
                <input type="hidden" name="action" value="check_requirements">
                <div class="btn-group">
                    <button type="submit" class="btn" <?= !$requirements['passed'] ? 'disabled' : '' ?>>
                        Continuar →
                    </button>
                </div>
            </form>
        </div>
        
        <?php elseif ($step === 2): ?>
        <!-- STEP 2: LICENÇA -->
        <div class="card">
            <h2>2. Chave de Licença</h2>
            <p class="subtitle">Introduz a chave de licença fornecida pelo administrador.</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="validate_license">
                
                <div class="form-group">
                    <label for="api_url">URL da API</label>
                    <input type="text" id="api_url" name="api_url" value="http://localhost:3000" required>
                </div>
                
                <div class="form-group">
                    <label for="license_key">Chave de Licença</label>
                    <input type="text" id="license_key" name="license_key" placeholder="ntk_xxxxxxxxxxxx.xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" required>
                    <div class="key-format-hint">
                        Formato: ntk_[12 hex].[48 hex]
                    </div>
                </div>
                
                <div class="btn-group">
                    <a href="setup.php?step=1" class="btn btn-secondary">← Voltar</a>
                    <button type="submit" class="btn">Validar Licença →</button>
                </div>
            </form>
        </div>
        
        <?php elseif ($step === 3): ?>
        <!-- STEP 3: BASE DE DADOS -->
        <div class="card">
            <h2>3. Base de Dados</h2>
            <p class="subtitle">Configura a conexão MySQL local.</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="save_database">
                
                <div class="form-group">
                    <label for="db_host">Host</label>
                    <input type="text" id="db_host" name="db_host" value="localhost" required>
                </div>
                
                <div class="form-group">
                    <label for="db_port">Porta</label>
                    <input type="number" id="db_port" name="db_port" value="3306" required>
                </div>
                
                <div class="form-group">
                    <label for="db_name">Nome da Base de Dados</label>
                    <input type="text" id="db_name" name="db_name" placeholder="meu_site" required>
                    <small>Será criada automaticamente se não existir.</small>
                </div>
                
                <div class="form-group">
                    <label for="db_user">Utilizador</label>
                    <input type="text" id="db_user" name="db_user" value="root" required>
                </div>
                
                <div class="form-group">
                    <label for="db_pass">Password</label>
                    <input type="password" id="db_pass" name="db_pass">
                </div>
                
                <div class="btn-group">
                    <a href="setup.php?step=2" class="btn btn-secondary">← Voltar</a>
                    <button type="submit" class="btn">Testar e Continuar →</button>
                </div>
            </form>
        </div>
        
        <?php elseif ($step === 4): ?>
        <!-- STEP 4: NEGÓCIO -->
        <div class="card">
            <h2>4. Informações do Negócio</h2>
            <p class="subtitle">Dados básicos para personalização do site.</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="save_business">
                
                <div class="form-group">
                    <label for="business_name">Nome do Negócio *</label>
                    <input type="text" id="business_name" name="business_name" required>
                </div>
                
                <div class="form-group">
                    <label for="business_description">Descrição</label>
                    <textarea id="business_description" name="business_description" placeholder="Descreve o teu negócio em 2-3 frases..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="business_email">Email</label>
                    <input type="email" id="business_email" name="business_email">
                </div>
                
                <div class="form-group">
                    <label for="business_phone">Telefone</label>
                    <input type="tel" id="business_phone" name="business_phone" placeholder="+351 912 345 678">
                </div>
                
                <div class="form-group">
                    <label for="business_address">Morada</label>
                    <input type="text" id="business_address" name="business_address">
                </div>
                
                <div class="form-group">
                    <label for="business_services">Serviços (um por linha)</label>
                    <textarea id="business_services" name="business_services" placeholder="Serviço 1&#10;Serviço 2&#10;Serviço 3"></textarea>
                </div>
                
                <div class="btn-group">
                    <a href="setup.php?step=3" class="btn btn-secondary">← Voltar</a>
                    <button type="submit" class="btn">Continuar →</button>
                </div>
            </form>
        </div>
        
        <?php elseif ($step === 5): ?>
        <!-- STEP 5: MÓDULOS -->
        <div class="card">
            <h2>5. Selecionar Módulos</h2>
            <p class="subtitle">Escolhe as funcionalidades que queres ativar.</p>
            
            <form method="POST" id="modulesForm">
                <input type="hidden" name="action" value="save_modules">
                
                <div class="module-grid">
                    <!-- Site Estático -->
                    <label class="module-card" data-module="static_site">
                        <input type="checkbox" name="mod_static_site" value="1">
                        <div class="module-header">
                            <span class="module-icon">🌐</span>
                            <span class="module-name">Site Estático</span>
                        </div>
                        <p class="module-desc">Website profissional com landing page.</p>
                        <div class="module-options">
                            <div class="option-group">
                                <label><input type="checkbox" name="mod_static_site_ai" value="1"> Gerar conteúdo com AI</label>
                            </div>
                            <div class="option-group">
                                <label>Tema</label>
                                <select name="site_theme">
                                    <option value="dark">Escuro</option>
                                    <option value="light">Claro</option>
                                </select>
                            </div>
                        </div>
                    </label>
                    
                    <!-- Bot Widget -->
                    <label class="module-card" data-module="bot_widget">
                        <input type="checkbox" name="mod_bot_widget" value="1">
                        <div class="module-header">
                            <span class="module-icon">💬</span>
                            <span class="module-name">Bot Widget</span>
                        </div>
                        <p class="module-desc">Assistente de chat no site.</p>
                        <div class="module-options">
                            <div class="option-group">
                                <label>Tipo de Bot</label>
                                <select name="bot_type">
                                    <option value="faq">FAQ (respostas fixas)</option>
                                    <option value="ai">AI (inteligente)</option>
                                    <option value="hybrid">Híbrido (FAQ + AI)</option>
                                </select>
                            </div>
                            <div class="option-group">
                                <label>Funcionalidades</label>
                                <div class="checkbox-group">
                                    <label><input type="checkbox" name="bot_features[]" value="info" checked> Informações</label>
                                    <label><input type="checkbox" name="bot_features[]" value="bookings"> Marcações</label>
                                    <label><input type="checkbox" name="bot_features[]" value="shop"> Compras</label>
                                </div>
                            </div>
                            <div class="option-group">
                                <label>Apresentação</label>
                                <select name="bot_position">
                                    <option value="floating">Balão flutuante</option>
                                    <option value="tab">Aba lateral</option>
                                    <option value="embedded">Embutido na página</option>
                                </select>
                            </div>
                            <div class="option-group">
                                <label><input type="checkbox" name="bot_exportable" value="1"> Widget exportável (embed)</label>
                            </div>
                        </div>
                    </label>
                    
                    <!-- Bot WhatsApp -->
                    <label class="module-card" data-module="bot_whatsapp">
                        <input type="checkbox" name="mod_bot_whatsapp" value="1">
                        <div class="module-header">
                            <span class="module-icon">💚</span>
                            <span class="module-name">Bot WhatsApp</span>
                        </div>
                        <p class="module-desc">Atendimento via WhatsApp Business.</p>
                        <div class="module-options">
                            <div class="option-group">
                                <label>Número WhatsApp</label>
                                <input type="tel" name="whatsapp_number" placeholder="+351 912 345 678">
                            </div>
                            <div class="option-group">
                                <label>Funcionalidades</label>
                                <div class="checkbox-group">
                                    <label><input type="checkbox" name="whatsapp_features[]" value="info" checked> Informações</label>
                                    <label><input type="checkbox" name="whatsapp_features[]" value="bookings"> Marcações</label>
                                    <label><input type="checkbox" name="whatsapp_features[]" value="orders"> Pedidos</label>
                                </div>
                            </div>
                        </div>
                    </label>
                    
                    <!-- AI Calls -->
                    <label class="module-card" data-module="ai_calls">
                        <input type="checkbox" name="mod_ai_calls" value="1">
                        <div class="module-header">
                            <span class="module-icon">📞</span>
                            <span class="module-name">AI Calls</span>
                        </div>
                        <p class="module-desc">Chamadas telefónicas com assistente AI.</p>
                    </label>
                    
                    <!-- Email -->
                    <label class="module-card" data-module="email">
                        <input type="checkbox" name="mod_email" value="1">
                        <div class="module-header">
                            <span class="module-icon">📧</span>
                            <span class="module-name">Email</span>
                        </div>
                        <p class="module-desc">Envio de emails transacionais e marketing.</p>
                        <div class="module-options">
                            <div class="option-group">
                                <label>Provider</label>
                                <select name="email_provider">
                                    <option value="smtp">SMTP</option>
                                    <option value="mailgun">Mailgun</option>
                                    <option value="sendgrid">SendGrid</option>
                                </select>
                            </div>
                        </div>
                    </label>
                    
                    <!-- SMS -->
                    <label class="module-card" data-module="sms">
                        <input type="checkbox" name="mod_sms" value="1">
                        <div class="module-header">
                            <span class="module-icon">📱</span>
                            <span class="module-name">SMS</span>
                        </div>
                        <p class="module-desc">Notificações e lembretes por SMS.</p>
                        <div class="module-options">
                            <div class="option-group">
                                <label>Provider</label>
                                <select name="sms_provider">
                                    <option value="twilio">Twilio</option>
                                    <option value="nexmo">Nexmo</option>
                                </select>
                            </div>
                        </div>
                    </label>
                    
                    <!-- Shop -->
                    <label class="module-card" data-module="shop">
                        <input type="checkbox" name="mod_shop" value="1">
                        <div class="module-header">
                            <span class="module-icon">🛒</span>
                            <span class="module-name">Loja Online</span>
                        </div>
                        <p class="module-desc">Integração com plataforma de e-commerce.</p>
                        <div class="module-options">
                            <div class="option-group">
                                <label>Plataforma</label>
                                <select name="shop_platform">
                                    <option value="prestashop">PrestaShop</option>
                                    <option value="woocommerce">WooCommerce</option>
                                    <option value="shopify">Shopify</option>
                                    <option value="custom">Personalizada</option>
                                </select>
                            </div>
                        </div>
                    </label>
                </div>
                
                <div class="btn-group">
                    <a href="setup.php?step=4" class="btn btn-secondary">← Voltar</a>
                    <button type="submit" class="btn">Continuar →</button>
                </div>
            </form>
        </div>
        
        <script>
            document.querySelectorAll('.module-card').forEach(card => {
                const checkbox = card.querySelector('input[type="checkbox"]');
                
                checkbox.addEventListener('change', () => {
                    card.classList.toggle('selected', checkbox.checked);
                });
                
                // Prevent label click from toggling when clicking on inner inputs
                card.querySelectorAll('.module-options input, .module-options select').forEach(el => {
                    el.addEventListener('click', e => e.stopPropagation());
                });
            });
        </script>
        
        <?php elseif ($step === 6): ?>
        <!-- STEP 6: ADMIN -->
        <div class="card">
            <h2>6. Acesso Admin</h2>
            <p class="subtitle">Define o PIN de acesso ao painel de administração.</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="save_admin">
                
                <div class="form-group">
                    <label for="admin_pin">PIN de Acesso</label>
                    <input type="password" id="admin_pin" name="admin_pin" minlength="4" required>
                    <small>Mínimo 4 caracteres. Guarda este PIN em local seguro.</small>
                </div>
                
                <div class="form-group">
                    <label for="admin_pin_confirm">Confirmar PIN</label>
                    <input type="password" id="admin_pin_confirm" name="admin_pin_confirm" minlength="4" required>
                </div>
                
                <div class="btn-group">
                    <a href="setup.php?step=5" class="btn btn-secondary">← Voltar</a>
                    <button type="submit" class="btn">Continuar →</button>
                </div>
            </form>
        </div>
        
        <?php elseif ($step === 7): ?>
        <!-- STEP 7: GERAR CONTEÚDO -->
        <?php $modules = $_SESSION['setup_modules'] ?? []; ?>
        <div class="card">
            <h2>7. Gerar Conteúdo</h2>
            
            <?php if (!empty($modules['static_site']['ai_generated'])): ?>
            <p class="subtitle">Vamos usar AI para criar o conteúdo do teu site.</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="generate_site">
                
                <div style="background: var(--bg-input); padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                    <p><strong>🤖 Geração com AI</strong></p>
                    <p style="color: var(--text-muted); font-size: 14px; margin-top: 8px;">
                        Será gerado conteúdo personalizado para o site com base nas informações do negócio.
                        Este processo pode demorar alguns segundos.
                    </p>
                </div>
                
                <div class="btn-group">
                    <a href="setup.php?step=6" class="btn btn-secondary">← Voltar</a>
                    <button type="submit" class="btn">🚀 Gerar Conteúdo</button>
                </div>
            </form>
            
            <?php else: ?>
            <p class="subtitle">Não há conteúdo para gerar. Podes avançar para a finalização.</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="generate_site">
                <div class="btn-group">
                    <a href="setup.php?step=6" class="btn btn-secondary">← Voltar</a>
                    <button type="submit" class="btn">Continuar →</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
        
        <?php elseif ($step === 8): ?>
        <!-- STEP 8: FINALIZAR -->
        <div class="card">
            <h2>8. Finalizar Instalação</h2>
            <p class="subtitle">Revê as configurações e conclui o setup.</p>
            
            <?php
            $license = $_SESSION['setup_license'] ?? [];
            $business = $_SESSION['setup_business'] ?? [];
            $modules = $_SESSION['setup_modules'] ?? [];
            $activeModules = array_filter($modules, fn($m) => $m['enabled'] ?? false);
            ?>
            
            <div style="background: var(--bg-input); padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                <p><strong>📋 Resumo</strong></p>
                <ul style="margin-top: 12px; padding-left: 20px; color: var(--text-muted);">
                    <li>Negócio: <?= htmlspecialchars($business['name'] ?? 'N/A') ?></li>
                    <li>Licença: <?= htmlspecialchars(substr($license['key'] ?? '', 0, 20)) ?>...</li>
                    <li>Módulos ativos: <?= count($activeModules) ?></li>
                </ul>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="finish_setup">
                <div class="btn-group">
                    <a href="setup.php?step=7" class="btn btn-secondary">← Voltar</a>
                    <button type="submit" class="btn">✅ Concluir Instalação</button>
                </div>
            </form>
        </div>
        
        <?php elseif ($step === 9): ?>
        <!-- STEP 9: SUCESSO -->
        <div class="card" style="text-align: center;">
            <div class="success-icon">🎉</div>
            <h2>Instalação Concluída!</h2>
            <p class="subtitle">O SiteForge está pronto a usar.</p>
            
            <div style="margin: 30px 0;">
                <a href="index.php" class="btn">Ver Site →</a>
                <a href="admin.php" class="btn btn-secondary" style="margin-left: 12px;">Painel Admin</a>
            </div>
        </div>
        <?php endif; ?>
        
        <p style="text-align: center; color: var(--text-muted); font-size: 12px; margin-top: 40px;">
            SiteForge © <?= date('Y') ?> · Powered by NunoMiranda.dev
        </p>
    </div>
</body>
</html>
