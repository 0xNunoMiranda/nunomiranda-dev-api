<?php
/**
 * Setup Inicial - Assistente de Configuração
 * 
 * Este ficheiro guia o utilizador pela configuração inicial do sistema.
 */

session_start();

// Verificar se já está instalado
$lockFile = __DIR__ . '/../storage/.installed';
if (file_exists($lockFile) && !isset($_GET['force'])) {
    header('Location: /');
    exit;
}

$configFile = __DIR__ . '/../config/config.php';
$configExample = __DIR__ . '/../config/config.example.php';
$schemaFile = __DIR__ . '/../database/schema.sql';

// Carregar configuração existente ou exemplo
$config = file_exists($configFile) ? require $configFile : require $configExample;

$step = (int)($_GET['step'] ?? 1);
$error = '';
$success = '';

// Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'check_requirements':
            // Verificar requisitos do sistema
            $requirements = checkRequirements();
            if ($requirements['passed']) {
                header('Location: setup.php?step=2');
                exit;
            }
            $error = 'Alguns requisitos não foram cumpridos.';
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
            
            // Testar conexão
            try {
                $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset={$dbConfig['charset']}";
                $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                
                // Criar base de dados se não existir
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `{$dbConfig['name']}`");
                
                // Executar schema
                if (file_exists($schemaFile)) {
                    $sql = file_get_contents($schemaFile);
                    $pdo->exec($sql);
                }
                
                // Guardar configuração na sessão
                $_SESSION['setup_db'] = $dbConfig;
                
                header('Location: setup.php?step=3');
                exit;
                
            } catch (PDOException $e) {
                $error = 'Erro de conexão: ' . $e->getMessage();
            }
            break;
            
        case 'save_business':
            $_SESSION['setup_business'] = [
                'name' => trim($_POST['business_name'] ?? ''),
                'phone' => trim($_POST['business_phone'] ?? ''),
                'email' => trim($_POST['business_email'] ?? ''),
                'address' => trim($_POST['business_address'] ?? ''),
            ];
            header('Location: setup.php?step=4');
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
            
            $_SESSION['setup_admin'] = [
                'pin' => $pin,
            ];
            header('Location: setup.php?step=5');
            exit;
            break;
            
        case 'save_api':
            $_SESSION['setup_api'] = [
                'base_url' => trim($_POST['api_url'] ?? 'http://localhost:3000'),
                'api_key' => trim($_POST['api_key'] ?? ''),
            ];
            header('Location: setup.php?step=6');
            exit;
            break;
            
        case 'finish_setup':
            // Compilar e guardar configuração final
            $finalConfig = buildFinalConfig();
            
            // Guardar ficheiro de configuração
            $configContent = "<?php\n/**\n * Configuração gerada pelo Setup - " . date('Y-m-d H:i:s') . "\n */\n\nreturn " . var_export($finalConfig, true) . ";\n";
            
            if (file_put_contents($configFile, $configContent)) {
                // Criar ficheiro de lock
                file_put_contents($lockFile, date('Y-m-d H:i:s'));
                
                // Limpar sessão de setup
                unset($_SESSION['setup_db'], $_SESSION['setup_business'], $_SESSION['setup_admin'], $_SESSION['setup_api']);
                
                header('Location: setup.php?step=7');
                exit;
            } else {
                $error = 'Não foi possível guardar a configuração. Verifica as permissões da pasta config/.';
            }
            break;
    }
}

/**
 * Verifica os requisitos do sistema
 */
function checkRequirements(): array {
    $requirements = [
        'php_version' => [
            'name' => 'PHP 8.0+',
            'passed' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'current' => PHP_VERSION,
        ],
        'pdo' => [
            'name' => 'PDO Extension',
            'passed' => extension_loaded('pdo'),
            'current' => extension_loaded('pdo') ? 'Instalado' : 'Não instalado',
        ],
        'pdo_mysql' => [
            'name' => 'PDO MySQL',
            'passed' => extension_loaded('pdo_mysql'),
            'current' => extension_loaded('pdo_mysql') ? 'Instalado' : 'Não instalado',
        ],
        'json' => [
            'name' => 'JSON Extension',
            'passed' => extension_loaded('json'),
            'current' => extension_loaded('json') ? 'Instalado' : 'Não instalado',
        ],
        'curl' => [
            'name' => 'cURL Extension',
            'passed' => extension_loaded('curl'),
            'current' => extension_loaded('curl') ? 'Instalado' : 'Não instalado',
        ],
        'config_writable' => [
            'name' => 'Pasta config/ gravável',
            'passed' => is_writable(__DIR__ . '/../config'),
            'current' => is_writable(__DIR__ . '/../config') ? 'Gravável' : 'Sem permissão',
        ],
        'storage_writable' => [
            'name' => 'Pasta storage/ gravável',
            'passed' => is_writable(__DIR__ . '/../storage'),
            'current' => is_writable(__DIR__ . '/../storage') ? 'Gravável' : 'Sem permissão',
        ],
    ];
    
    $allPassed = true;
    foreach ($requirements as $req) {
        if (!$req['passed']) {
            $allPassed = false;
            break;
        }
    }
    
    return [
        'items' => $requirements,
        'passed' => $allPassed,
    ];
}

/**
 * Constrói a configuração final
 */
function buildFinalConfig(): array {
    $db = $_SESSION['setup_db'] ?? [];
    $business = $_SESSION['setup_business'] ?? [];
    $admin = $_SESSION['setup_admin'] ?? [];
    $api = $_SESSION['setup_api'] ?? [];
    
    return [
        'tenant' => [
            'id' => 1,
            'slug' => strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $business['name'] ?? 'meu-negocio')),
            'name' => $business['name'] ?? 'Meu Negócio',
        ],
        'api' => [
            'base_url' => $api['base_url'] ?? 'http://localhost:3000',
            'api_key' => $api['api_key'] ?? '',
            'timeout' => 30,
        ],
        'database' => $db,
        'admin' => [
            'path' => '/admin/',
            'pin' => $admin['pin'] ?? '1234',
            'session_lifetime' => 3600 * 8,
        ],
        'modules' => [
            'bot_widget' => [
                'enabled' => true,
                'name' => 'Assistente Virtual',
                'theme' => 'dark',
                'position' => 'bottom-right',
                'welcome_message' => 'Olá! Como posso ajudar?',
            ],
            'bot_whatsapp' => [
                'enabled' => false,
                'number' => '',
                'token' => '',
            ],
            'shop' => [
                'enabled' => false,
                'type' => 'woocommerce',
                'url' => '',
            ],
        ],
        'site' => [
            'title' => $business['name'] ?? 'Meu Negócio',
            'meta_description' => 'Bem-vindo ao ' . ($business['name'] ?? 'nosso negócio'),
            'logo' => '',
            'favicon' => '',
            'theme' => 'dark',
            'phone' => $business['phone'] ?? '',
            'email' => $business['email'] ?? '',
            'address' => $business['address'] ?? '',
            'social' => [
                'instagram' => '',
                'facebook' => '',
            ],
            'google_maps_embed' => '',
            'analytics' => [
                'google' => '',
            ],
        ],
        'storage' => [
            'settings_file' => __DIR__ . '/../storage/settings.json',
            'logs_dir' => __DIR__ . '/../storage/logs',
        ],
    ];
}

$requirements = checkRequirements();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Inicial</title>
    <style>
        :root {
            --accent: #00ffc6;
            --bg: #0a0a0f;
            --bg-card: #12121a;
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .setup-container {
            width: 100%;
            max-width: 600px;
        }
        
        .setup-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .setup-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .setup-header h1 span {
            color: var(--accent);
        }
        
        .setup-header p {
            color: var(--text-muted);
        }
        
        .steps {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }
        
        .step-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--border);
            transition: all 0.3s;
        }
        
        .step-dot.active {
            background: var(--accent);
            box-shadow: 0 0 10px var(--accent);
        }
        
        .step-dot.completed {
            background: var(--success);
        }
        
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
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.875rem 1rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus {
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
            filter: brightness(1.1);
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: var(--border);
            color: var(--text);
        }
        
        .btn-block {
            width: 100%;
        }
        
        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .btn-group .btn {
            flex: 1;
        }
        
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
        
        .requirements-list li:last-child {
            border-bottom: none;
        }
        
        .req-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        .req-status.passed {
            color: var(--success);
        }
        
        .req-status.failed {
            color: var(--error);
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
        
        .success-content {
            text-align: center;
        }
        
        .success-content h2 {
            margin-bottom: 1rem;
        }
        
        .success-content p {
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }
        
        .info-box {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem;
            margin: 1.5rem 0;
        }
        
        .info-box code {
            color: var(--accent);
            font-family: monospace;
        }
        
        @media (max-width: 640px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1>🚀 <span>Setup</span> Inicial</h1>
            <p>Configuração do sistema em poucos passos</p>
        </div>
        
        <div class="steps">
            <?php for ($i = 1; $i <= 7; $i++): ?>
            <div class="step-dot <?php echo $i < $step ? 'completed' : ($i === $step ? 'active' : ''); ?>"></div>
            <?php endfor; ?>
        </div>
        
        <div class="setup-card">
            <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($step === 1): ?>
            <!-- Step 1: Requisitos -->
            <h2>1. Verificar Requisitos</h2>
            <p>Vamos verificar se o servidor tem tudo o que precisa.</p>
            
            <ul class="requirements-list">
                <?php foreach ($requirements['items'] as $key => $req): ?>
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
            <!-- Step 2: Base de Dados -->
            <h2>2. Base de Dados</h2>
            <p>Configura a ligação à base de dados MySQL.</p>
            
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
                    <label>Nome da Base de Dados</label>
                    <input type="text" name="db_name" placeholder="ruben_barbearia" required>
                    <small>Será criada se não existir</small>
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
                    <a href="setup.php?step=1" class="btn btn-secondary">← Voltar</a>
                    <button type="submit" class="btn btn-accent">Testar e Continuar →</button>
                </div>
            </form>
            
            <?php elseif ($step === 3): ?>
            <!-- Step 3: Informações do Negócio -->
            <h2>3. O Teu Negócio</h2>
            <p>Informações básicas sobre o teu negócio.</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="save_business">
                
                <div class="form-group">
                    <label>Nome do Negócio *</label>
                    <input type="text" name="business_name" placeholder="Barbearia Ruben" required>
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
                
                <div class="btn-group">
                    <a href="setup.php?step=2" class="btn btn-secondary">← Voltar</a>
                    <button type="submit" class="btn btn-accent">Continuar →</button>
                </div>
            </form>
            
            <?php elseif ($step === 4): ?>
            <!-- Step 4: Admin -->
            <h2>4. Acesso Admin</h2>
            <p>Define um PIN para aceder ao painel de administração.</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="save_admin">
                
                <div class="form-group">
                    <label>PIN de Acesso *</label>
                    <input type="password" name="admin_pin" minlength="4" required placeholder="••••••">
                    <small>Mínimo 4 caracteres. Pode ser numérico ou alfanumérico.</small>
                </div>
                
                <div class="form-group">
                    <label>Confirmar PIN *</label>
                    <input type="password" name="admin_pin_confirm" minlength="4" required placeholder="••••••">
                </div>
                
                <div class="btn-group">
                    <a href="setup.php?step=3" class="btn btn-secondary">← Voltar</a>
                    <button type="submit" class="btn btn-accent">Continuar →</button>
                </div>
            </form>
            
            <?php elseif ($step === 5): ?>
            <!-- Step 5: API -->
            <h2>5. Integração API (Opcional)</h2>
            <p>Conecta com a API Node.js para subscrições e pagamentos.</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="save_api">
                
                <div class="form-group">
                    <label>URL da API</label>
                    <input type="url" name="api_url" value="http://localhost:3000" placeholder="http://localhost:3000">
                    <small>Deixa o valor padrão se não tens a API configurada</small>
                </div>
                
                <div class="form-group">
                    <label>API Key</label>
                    <input type="text" name="api_key" placeholder="A tua chave de API">
                    <small>Obtém esta chave no painel de administração da API</small>
                </div>
                
                <div class="btn-group">
                    <a href="setup.php?step=4" class="btn btn-secondary">← Voltar</a>
                    <button type="submit" class="btn btn-accent">Continuar →</button>
                </div>
            </form>
            
            <?php elseif ($step === 6): ?>
            <!-- Step 6: Confirmar -->
            <h2>6. Confirmar Instalação</h2>
            <p>Revê as configurações antes de finalizar.</p>
            
            <div class="info-box">
                <p><strong>Base de Dados:</strong> <code><?php echo $_SESSION['setup_db']['name'] ?? 'N/A'; ?></code></p>
                <p><strong>Negócio:</strong> <code><?php echo $_SESSION['setup_business']['name'] ?? 'N/A'; ?></code></p>
                <p><strong>API:</strong> <code><?php echo $_SESSION['setup_api']['base_url'] ?? 'N/A'; ?></code></p>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="finish_setup">
                
                <div class="btn-group">
                    <a href="setup.php?step=5" class="btn btn-secondary">← Voltar</a>
                    <button type="submit" class="btn btn-accent">🚀 Finalizar Instalação</button>
                </div>
            </form>
            
            <?php elseif ($step === 7): ?>
            <!-- Step 7: Sucesso -->
            <div class="success-content">
                <div class="success-icon">✓</div>
                <h2>Instalação Concluída!</h2>
                <p>O sistema está pronto a usar.</p>
                <p>Acede ao painel admin com o PIN que definiste.</p>
                
                <div class="btn-group" style="margin-top: 2rem;">
                    <a href="index.php" class="btn btn-secondary">Ver Site</a>
                    <a href="admin/" class="btn btn-accent">Abrir Admin</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
