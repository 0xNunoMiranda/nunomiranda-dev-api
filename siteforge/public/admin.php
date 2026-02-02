<?php
/**
 * SiteForge - Painel de Administração
 */

session_start();
$container = require __DIR__ . '/../src/bootstrap.php';
$config = $container['config'];
$settingsStore = $container['settingsStore'];
$settings = $container['settings'];
$apiClient = $container['apiClient'];

$pin = $config['admin']['pin'] ?? '1234';
$isAuthenticated = isset($_SESSION['sf_admin_authenticated']) && $_SESSION['sf_admin_authenticated'] === true;
$feedback = null;
$feedbackType = 'info';
$plans = [];
$whatsappConnect = null;
$whatsappStatus = null;

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Carregar planos da API
$plansResponse = $apiClient->get('/catalog/plans');
if ($plansResponse['ok']) {
    $plans = $plansResponse['data']['data']['plans'] ?? [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form'] ?? '';

    if ($formType === 'login') {
        $inputPin = $_POST['pin'] ?? '';
        if (hash_equals($pin, $inputPin)) {
            $_SESSION['sf_admin_authenticated'] = true;
            $isAuthenticated = true;
            $feedback = 'Sessão autenticada com sucesso.';
            $feedbackType = 'success';
        } else {
            $feedback = 'PIN inválido.';
            $feedbackType = 'error';
        }
    } elseif ($formType === 'settings' && $isAuthenticated) {
        $faqsParsed = null;
        $productsParsed = null;

        // These fields are only posted from the "Conteúdo" tab. If present, allow empty value to clear.
        if (array_key_exists('faqs_json', $_POST)) {
            $faqsJson = trim((string)($_POST['faqs_json'] ?? ''));
            if ($faqsJson === '') {
                $faqsParsed = [];
            } else {
                $decoded = json_decode($faqsJson, true);
                if (!is_array($decoded)) {
                    $feedback = 'FAQs: JSON inválido (deve ser um array).';
                    $feedbackType = 'error';
                } else {
                    $faqsParsed = array_values(array_filter(array_map(function ($item) {
                        if (!is_array($item)) return null;
                        $q = trim((string)($item['question'] ?? ''));
                        $a = trim((string)($item['answer'] ?? ''));
                        if ($q === '' || $a === '') return null;
                        return ['question' => $q, 'answer' => $a];
                    }, $decoded)));
                }
            }
        }

        if (array_key_exists('shop_products_json', $_POST)) {
            $productsJson = trim((string)($_POST['shop_products_json'] ?? ''));
            if ($productsJson === '') {
                $productsParsed = [];
            } else {
                $decoded = json_decode($productsJson, true);
                if (!is_array($decoded)) {
                    $feedback = 'Produtos: JSON inválido (deve ser um array).';
                    $feedbackType = 'error';
                } else {
                    $productsParsed = array_values(array_filter(array_map(function ($item) {
                        if (!is_array($item)) return null;
                        $name = trim((string)($item['name'] ?? ''));
                        if ($name === '') return null;
                        return [
                            'name' => $name,
                            'price' => trim((string)($item['price'] ?? '')),
                            'description' => trim((string)($item['description'] ?? '')),
                            'url' => trim((string)($item['url'] ?? '')),
                        ];
                    }, $decoded)));
                }
            }
        }

        $updates = [
            'branding' => [
                'headline' => trim($_POST['headline'] ?? ''),
                'subheadline' => trim($_POST['subheadline'] ?? ''),
                'ctaText' => trim($_POST['ctaText'] ?? ''),
                'ctaLink' => trim($_POST['ctaLink'] ?? ''),
            ],
            'ai_bot' => [
                'enabled' => bool_from_post($_POST['ai_enabled'] ?? null),
                'assistantName' => trim($_POST['assistantName'] ?? ''),
                'welcomeMessage' => trim($_POST['welcomeMessage'] ?? ''),
                'apiKey' => trim($_POST['apiKey'] ?? ''),
                'preferredChannel' => trim($_POST['preferredChannel'] ?? 'site'),
            ],
            'whatsapp' => [
                'enabled' => bool_from_post($_POST['wa_enabled'] ?? null),
                'number' => trim($_POST['wa_number'] ?? ''),
                'webhookUrl' => trim($_POST['wa_webhook'] ?? ''),
                'greeting' => trim($_POST['wa_greeting'] ?? ''),
                'messagesPerMinute' => max(1, min(5, (int)($_POST['wa_messages_per_minute'] ?? 5))),
                'promptTemplate' => trim($_POST['wa_prompt'] ?? ''),
                'tags' => [
                    'bookings' => bool_from_post($_POST['wa_tag_bookings'] ?? null),
                    'faqs' => bool_from_post($_POST['wa_tag_faqs'] ?? null),
                    'shop' => bool_from_post($_POST['wa_tag_shop'] ?? null),
                ],
            ],
        ];

        if ($faqsParsed !== null) {
            $updates['faqs'] = [
                'items' => $faqsParsed,
            ];
        }

        if ($productsParsed !== null) {
            $updates['shop'] = [
                'products' => $productsParsed,
            ];
        }

        if ($feedbackType !== 'error') {
            $settings = merge_settings($settings, $updates);
            $settingsStore->save($settings);
            $feedback = 'Configurações atualizadas.';
            $feedbackType = 'success';
        }
    } elseif ($formType === 'whatsapp_connect' && $isAuthenticated) {
        $whatsappCfg = $settings['whatsapp'] ?? [];
        $messagesPerMinute = (int)($whatsappCfg['messagesPerMinute'] ?? 5);
        $messagesPerMinute = max(1, min(5, $messagesPerMinute));

        $tags = $whatsappCfg['tags'] ?? [];
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
        $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $siteUrl = trim((string)($config['site_url'] ?? ''));
        if ($siteUrl === '') {
            $siteUrl = $scheme . '://' . $host . $scriptDir;
        }
        $siteUrl = rtrim($siteUrl, '/');

        $payload = [
            'siteUrl' => $siteUrl,
            'messagesPerMinute' => $messagesPerMinute,
            'promptTemplate' => (string)($whatsappCfg['promptTemplate'] ?? ''),
            'tags' => [
                'bookings' => (bool)($tags['bookings'] ?? true),
                'faqs' => (bool)($tags['faqs'] ?? true),
                'shop' => (bool)($tags['shop'] ?? true),
            ],
        ];

        $resp = $apiClient->post('/whatsapp/connect', $payload);
        if ($resp['ok']) {
            $whatsappConnect = $resp['data']['data'] ?? null;
            $state = is_array($whatsappConnect) ? ($whatsappConnect['state'] ?? null) : null;
            if ($state === 'error') {
                $err = is_array($whatsappConnect) ? ($whatsappConnect['lastError'] ?? null) : null;
                $feedback = 'Falha ao iniciar WhatsApp (estado=error).' . ($err ? (' Erro: ' . $err) : '');
                $feedbackType = 'error';
            } else {
                $feedback = 'A ligar ao WhatsApp. Se aparecer um QR, lê-o com o teu telemóvel.';
                $feedbackType = 'success';
            }
        } else {
            $feedback = 'Falha ao iniciar ligaÃ§Ã£o WhatsApp: ' . ($resp['error'] ?? 'Erro desconhecido');
            $feedbackType = 'error';
        }
    } elseif ($formType === 'whatsapp_disconnect' && $isAuthenticated) {
        $resp = $apiClient->post('/whatsapp/disconnect', []);
        if ($resp['ok']) {
            $feedback = 'SessÃ£o WhatsApp terminada.';
            $feedbackType = 'success';
        } else {
            $feedback = 'Falha ao desligar WhatsApp: ' . ($resp['error'] ?? 'Erro desconhecido');
            $feedbackType = 'error';
        }
    } elseif ($formType === 'select_plan' && $isAuthenticated) {
        $selectedPlanId = (int) ($_POST['plan_id'] ?? 0);
        $paymentMethod = trim($_POST['payment_method'] ?? 'multibanco');
        $customerName = trim($_POST['customer_name'] ?? '');
        $customerEmail = trim($_POST['customer_email'] ?? '');
        $customerPhone = trim($_POST['customer_phone'] ?? '');

        if ($selectedPlanId > 0 && !empty($customerName) && !empty($customerEmail)) {
            $customer = [
                'name' => $customerName,
                'email' => $customerEmail,
            ];
            if (!empty($customerPhone)) {
                $customer['phone'] = $customerPhone;
            }

            $sddMandate = null;
            if ($paymentMethod === 'direct_debit') {
                $iban = trim($_POST['sdd_iban'] ?? '');
                $accountHolder = trim($_POST['sdd_account_holder'] ?? '');
                if (!empty($iban) && !empty($accountHolder)) {
                    $sddMandate = [
                        'iban' => $iban,
                        'accountHolder' => $accountHolder,
                    ];
                }
            }

            $billingPayload = [
                'tenantId' => (int) $config['tenant']['id'],
                'planId' => $selectedPlanId,
                'paymentMethod' => $paymentMethod,
                'customer' => $customer,
            ];
            if ($sddMandate) {
                $billingPayload['sddMandate'] = $sddMandate;
            }

            $billingResponse = $apiClient->post('/billing/subscriptions', $billingPayload);

            if ($billingResponse['ok']) {
                $billingData = $billingResponse['data']['data'] ?? [];
                $methodInfo = $billingData['method'] ?? [];
                
                $settings['subscription'] = [
                    'id' => $billingData['subscriptionId'] ?? null,
                    'selected_plan_id' => $selectedPlanId,
                    'payment_method' => $paymentMethod,
                    'status' => $billingData['status'] ?? 'pending',
                    'selected_at' => date('c'),
                    'method' => $methodInfo,
                    'customer' => [
                        'name' => $customerName,
                        'email' => $customerEmail,
                        'phone' => $customerPhone,
                    ],
                ];
                $settingsStore->save($settings);
                
                $paymentInfo = '';
                
                if (!empty($methodInfo['entity']) && !empty($methodInfo['reference'])) {
                    $paymentInfo = sprintf(
                        '<br><strong>Multibanco:</strong> Entidade %s · Referência %s · Valor %.2f€',
                        $methodInfo['entity'],
                        $methodInfo['reference'],
                        ($methodInfo['value'] ?? 0)
                    );
                }
                
                if (!empty($methodInfo['alias'])) {
                    $paymentInfo = sprintf(
                        '<br><strong>MB WAY:</strong> Pedido enviado para %s',
                        $methodInfo['alias']
                    );
                }
                
                if (!empty($methodInfo['url'])) {
                    $paymentInfo .= sprintf('<br><a href="%s" target="_blank">Pagar agora →</a>', $methodInfo['url']);
                }
                
                $feedback = 'Subscrição criada com sucesso!' . $paymentInfo;
                $feedbackType = 'success';
            } else {
                $feedback = 'Erro ao criar subscrição: ' . ($billingResponse['data']['error'] ?? 'Erro desconhecido');
                $feedbackType = 'error';
            }
        } else {
            $feedback = 'Preenche todos os campos obrigatórios.';
            $feedbackType = 'error';
        }
    }
}

function bool_from_post($value): bool {
    return $value === '1' || $value === 'on' || $value === true;
}

function merge_settings(array $base, array $updates): array {
    foreach ($updates as $key => $value) {
        if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
            $base[$key] = merge_settings($base[$key], $value);
        } else {
            $base[$key] = $value;
        }
    }
    return $base;
}

$branding = $settings['branding'] ?? [];
$aiBot = $settings['ai_bot'] ?? [];
$whatsapp = $settings['whatsapp'] ?? [];
$faqs = $settings['faqs']['items'] ?? [];
$shopProducts = $settings['shop']['products'] ?? [];
$subscription = $settings['subscription'] ?? [];
$tenantName = $config['tenant']['name'] ?? 'SiteForge';

$faqsJsonValue = json_encode(array_values(is_array($faqs) ? $faqs : []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($faqsJsonValue === false) $faqsJsonValue = "[]";
$shopProductsJsonValue = json_encode(array_values(is_array($shopProducts) ? $shopProducts : []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($shopProductsJsonValue === false) $shopProductsJsonValue = "[]";

if ($isAuthenticated) {
    $statusResp = $apiClient->get('/whatsapp/status');
    if ($statusResp['ok']) {
        $whatsappStatus = $statusResp['data']['data']['status'] ?? null;
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?= htmlspecialchars($tenantName) ?></title>
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
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
        }
        
        .header h1 {
            font-size: 24px;
            color: var(--primary);
        }
        
        .header a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
        }
        
        .header a:hover { color: var(--primary); }
        
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 24px;
        }
        
        .card h2 {
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }
        
        .form-group {
            margin-bottom: 18px;
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
        input[type="tel"],
        input[type="url"],
        textarea,
        select {
            width: 100%;
            padding: 12px 14px;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-size: 14px;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .toggle-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .toggle {
            position: relative;
            width: 48px;
            height: 26px;
        }
        
        .toggle input { opacity: 0; width: 0; height: 0; }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: var(--bg-input);
            border-radius: 26px;
            transition: 0.3s;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background: var(--text-muted);
            border-radius: 50%;
            transition: 0.3s;
        }
        
        .toggle input:checked + .toggle-slider { background: var(--primary); }
        .toggle input:checked + .toggle-slider:before { transform: translateX(22px); background: var(--bg); }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            background: var(--primary);
            color: var(--bg);
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn:hover { background: var(--primary-dark); }
        
        .btn-secondary {
            background: var(--bg-input);
            color: var(--text);
            border: 1px solid var(--border);
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
        
        .login-card {
            max-width: 400px;
            margin: 100px auto;
            text-align: center;
        }
        
        .login-card h1 {
            color: var(--primary);
            margin-bottom: 8px;
        }
        
        .login-card p {
            color: var(--text-muted);
            margin-bottom: 24px;
        }
        
        .nav-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 12px;
        }
        
        .nav-tab {
            padding: 10px 16px;
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 14px;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .nav-tab:hover { color: var(--text); background: var(--bg-input); }
        .nav-tab.active { color: var(--primary); background: rgba(0, 255, 198, 0.1); }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .plan-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .plan-card {
            background: var(--bg-input);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .plan-card:hover { border-color: var(--primary); }
        .plan-card.selected { border-color: var(--primary); background: rgba(0, 255, 198, 0.05); }
        
        .plan-card input[type="radio"] { display: none; }
        
        .plan-name { font-weight: 600; margin-bottom: 4px; }
        .plan-price { color: var(--primary); font-size: 24px; font-weight: 700; }
        .plan-price span { font-size: 14px; color: var(--text-muted); font-weight: normal; }
    </style>
</head>
<body>
    <?php if (!$isAuthenticated): ?>
    <div class="container">
        <div class="card login-card">
            <h1>🔐 Admin</h1>
            <p>Introduz o PIN de acesso</p>
            
            <?php if ($feedback): ?>
            <div class="alert alert-<?= $feedbackType ?>"><?= $feedback ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="form" value="login">
                <div class="form-group">
                    <input type="password" name="pin" placeholder="PIN" required autofocus>
                </div>
                <button type="submit" class="btn" style="width: 100%;">Entrar</button>
            </form>
        </div>
    </div>
    
    <?php else: ?>
    <div class="container">
        <div class="header">
            <h1>⚡ <?= htmlspecialchars($tenantName) ?></h1>
            <div>
                <a href="index.php">← Ver Site</a>
                <a href="?logout" style="margin-left: 16px;">Sair</a>
            </div>
        </div>
        
        <?php if ($feedback): ?>
        <div class="alert alert-<?= $feedbackType ?>"><?= $feedback ?></div>
        <?php endif; ?>
        
        <div class="nav-tabs">
            <button class="nav-tab active" data-tab="branding">Branding</button>
            <button class="nav-tab" data-tab="bot">Bot AI</button>
            <button class="nav-tab" data-tab="whatsapp">WhatsApp</button>
            <button class="nav-tab" data-tab="knowledge">Conteúdo</button>
            <button class="nav-tab" data-tab="subscription">Subscrição</button>
        </div>
        
        <!-- Tab: Branding -->
        <div class="tab-content active" id="tab-branding">
            <div class="card">
                <h2>🎨 Branding</h2>
                <form method="POST">
                    <input type="hidden" name="form" value="settings">
                    
                    <div class="form-group">
                        <label>Título Principal</label>
                        <input type="text" name="headline" value="<?= htmlspecialchars($branding['headline'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Subtítulo</label>
                        <input type="text" name="subheadline" value="<?= htmlspecialchars($branding['subheadline'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Texto do Botão CTA</label>
                        <input type="text" name="ctaText" value="<?= htmlspecialchars($branding['ctaText'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Link do CTA</label>
                        <input type="url" name="ctaLink" value="<?= htmlspecialchars($branding['ctaLink'] ?? '') ?>">
                    </div>

                    <button type="submit" class="btn">Guardar</button>
                </form>
            </div>
        </div>
        
        <!-- Tab: Bot AI -->
        <div class="tab-content" id="tab-bot">
            <div class="card">
                <h2>🤖 Bot AI</h2>
                <form method="POST">
                    <input type="hidden" name="form" value="settings">
                    
                    <div class="form-group">
                        <div class="toggle-group">
                            <label class="toggle">
                                <input type="checkbox" name="ai_enabled" value="1" <?= ($aiBot['enabled'] ?? false) ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span>Ativar Bot AI</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Nome do Assistente</label>
                        <input type="text" name="assistantName" value="<?= htmlspecialchars($aiBot['assistantName'] ?? 'Assistente') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Mensagem de Boas-Vindas</label>
                        <textarea name="welcomeMessage"><?= htmlspecialchars($aiBot['welcomeMessage'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Canal Preferido</label>
                        <select name="preferredChannel">
                            <option value="site" <?= ($aiBot['preferredChannel'] ?? '') === 'site' ? 'selected' : '' ?>>Site (Widget)</option>
                            <option value="whatsapp" <?= ($aiBot['preferredChannel'] ?? '') === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn">Guardar</button>
                </form>
            </div>
        </div>
        
        <!-- Tab: WhatsApp -->
        <div class="tab-content" id="tab-whatsapp">
            <div class="card">
                <h2>💚 WhatsApp</h2>
                <form method="POST">
                    <input type="hidden" name="form" value="settings">
                    
                    <div class="form-group">
                        <div class="toggle-group">
                            <label class="toggle">
                                <input type="checkbox" name="wa_enabled" value="1" <?= ($whatsapp['enabled'] ?? false) ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span>Ativar WhatsApp</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Número WhatsApp</label>
                        <input type="tel" name="wa_number" value="<?= htmlspecialchars($whatsapp['number'] ?? '') ?>" placeholder="+351 912 345 678">
                    </div>
                    
                    <div class="form-group">
                        <label>Mensagem de Saudação</label>
                        <textarea name="wa_greeting"><?= htmlspecialchars($whatsapp['greeting'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Mensagens por minuto (máx 5)</label>
                        <input
                            type="number"
                            name="wa_messages_per_minute"
                            min="1"
                            max="5"
                            value="<?= htmlspecialchars((string)($whatsapp['messagesPerMinute'] ?? 5)) ?>"
                        >
                        <small style="display:block; color: var(--text-muted); margin-top: 6px;">
                            Limite aplicado ao envio via WhatsApp (proteção anti-spam).
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Prompt do WhatsApp</label>
                        <textarea name="wa_prompt" placeholder="Ex: És o assistente do negócio. Usa as tags quando fizer sentido..."><?= htmlspecialchars($whatsapp['promptTemplate'] ?? '') ?></textarea>
                        <small style="display:block; color: var(--text-muted); margin-top: 6px;">
                            Tags disponíveis: <code>{{bookings}}</code>, <code>{{faqs}}</code>, <code>{{shop}}</code>.
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Tags ativas</label>
                        <div style="display:flex; gap: 14px; flex-wrap: wrap;">
                            <label style="display:flex; align-items:center; gap: 8px;">
                                <input type="checkbox" name="wa_tag_bookings" value="1" <?= (($whatsapp['tags']['bookings'] ?? true) ? 'checked' : '') ?>>
                                <span>Marcações</span>
                            </label>
                            <label style="display:flex; align-items:center; gap: 8px;">
                                <input type="checkbox" name="wa_tag_faqs" value="1" <?= (($whatsapp['tags']['faqs'] ?? true) ? 'checked' : '') ?>>
                                <span>FAQs</span>
                            </label>
                            <label style="display:flex; align-items:center; gap: 8px;">
                                <input type="checkbox" name="wa_tag_shop" value="1" <?= (($whatsapp['tags']['shop'] ?? true) ? 'checked' : '') ?>>
                                <span>Vendas online</span>
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">Guardar</button>
                </form>
            </div>

            <div class="card" style="margin-top: 18px;">
                <h3 style="margin-bottom: 10px;">Conexão (QR Code)</h3>

                <?php $status = $whatsappStatus['state'] ?? 'disconnected'; ?>
                <p style="color: var(--text-muted); margin-bottom: 12px;">
                    Estado: <strong style="color: var(--text);"><?= htmlspecialchars((string)$status) ?></strong>
                    <?php if (!empty($whatsappStatus['deviceJid'])): ?>
                        <br><small>Dispositivo: <?= htmlspecialchars((string)$whatsappStatus['deviceJid']) ?></small>
                    <?php endif; ?>
                    <?php if (!empty($whatsappStatus['lastError'])): ?>
                        <br><small style="color: rgba(244,67,54,0.9);">Erro: <?= htmlspecialchars((string)$whatsappStatus['lastError']) ?></small>
                    <?php endif; ?>
                </p>

                <?php
                    $qrDataUrl = is_array($whatsappConnect) ? ($whatsappConnect['qrDataUrl'] ?? null) : null;
                    $qrRaw = is_array($whatsappConnect) ? ($whatsappConnect['qr'] ?? null) : null;
                    if (!$qrDataUrl && is_array($whatsappStatus) && !empty($whatsappStatus['lastQr'])) {
                        $qrRaw = $whatsappStatus['lastQr'];
                    }
                ?>

                <?php if ($qrDataUrl): ?>
                    <div style="display:flex; justify-content:center; margin: 12px 0;">
                        <img src="<?= htmlspecialchars((string)$qrDataUrl) ?>" alt="QR Code WhatsApp" style="background:#fff; padding: 10px; border-radius: 12px; width: 280px; height: 280px;">
                    </div>
                <?php elseif ($qrRaw): ?>
                    <p style="color: var(--text-muted);">
                        QR disponível mas o servidor Node.js não conseguiu gerar imagem. Instala <code>qrcode</code> no Node e tenta de novo.
                    </p>
                    <pre style="white-space: pre-wrap; word-break: break-all; background: var(--bg-input); padding: 12px; border-radius: 10px;"><?= htmlspecialchars((string)$qrRaw) ?></pre>
                <?php endif; ?>

                <div style="display:flex; gap: 10px; flex-wrap: wrap;">
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="form" value="whatsapp_connect">
                        <button type="submit" class="btn">Gerar QR / Ligar</button>
                    </form>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="form" value="whatsapp_disconnect">
                        <button type="submit" class="btn" style="background: rgba(244,67,54,0.15); border-color: rgba(244,67,54,0.4);">Desligar</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tab: Conteúdo -->
        <div class="tab-content" id="tab-knowledge">
            <div class="card">
                <h2>📚 Conteúdo</h2>
                <p style="color: var(--text-muted); margin-bottom: 14px;">
                    Estes dados alimentam as tags <code>{{faqs}}</code> e <code>{{shop}}</code> no WhatsApp.
                </p>
                <form method="POST">
                    <input type="hidden" name="form" value="settings">

                    <div class="form-group">
                        <label>FAQs (JSON)</label>
                        <textarea name="faqs_json" style="min-height: 240px;" placeholder='[{"question":"...","answer":"..."}]'><?= htmlspecialchars($faqsJsonValue) ?></textarea>
                        <small style="display:block; color: var(--text-muted); margin-top: 6px;">
                            Formato: array de itens com <code>question</code> e <code>answer</code>. Deixa vazio e guarda para limpar.
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Produtos (JSON)</label>
                        <textarea name="shop_products_json" style="min-height: 240px;" placeholder='[{"name":"Produto","price":"10.00€","description":"","url":""}]'><?= htmlspecialchars($shopProductsJsonValue) ?></textarea>
                        <small style="display:block; color: var(--text-muted); margin-top: 6px;">
                            Formato: array de itens com <code>name</code>, <code>price</code>, <code>description</code>, <code>url</code>. Deixa vazio e guarda para limpar.
                        </small>
                    </div>

                    <button type="submit" class="btn">Guardar</button>
                </form>
            </div>
        </div>
        
        <!-- Tab: Subscrição -->
        <div class="tab-content" id="tab-subscription">
            <div class="card">
                <h2>💳 Subscrição</h2>
                
                <?php if (!empty($subscription['id'])): ?>
                <div style="background: var(--bg-input); padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                    <p><strong>Estado:</strong> <?= htmlspecialchars(ucfirst($subscription['status'] ?? 'unknown')) ?></p>
                    <p><strong>Método:</strong> <?= htmlspecialchars(ucfirst($subscription['payment_method'] ?? 'N/A')) ?></p>
                    <p><strong>Criada em:</strong> <?= date('d/m/Y H:i', strtotime($subscription['selected_at'] ?? 'now')) ?></p>
                </div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="form" value="select_plan">
                    
                    <div class="form-group">
                        <label>Escolhe um Plano</label>
                        <div class="plan-grid">
                            <?php foreach ($plans as $plan): ?>
                            <label class="plan-card">
                                <input type="radio" name="plan_id" value="<?= $plan['id'] ?>">
                                <div class="plan-name"><?= htmlspecialchars($plan['name']) ?></div>
                                <div class="plan-price"><?= number_format($plan['price'] / 100, 2, ',', '.') ?>€ <span>/mês</span></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Método de Pagamento</label>
                        <select name="payment_method">
                            <option value="multibanco">Multibanco</option>
                            <option value="mbway">MB WAY</option>
                            <option value="card">Cartão</option>
                            <option value="direct_debit">Débito Direto</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Nome *</label>
                        <input type="text" name="customer_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="customer_email" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="tel" name="customer_phone" placeholder="+351 912 345 678">
                    </div>
                    
                    <button type="submit" class="btn">Subscrever</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Tab navigation
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                tab.classList.add('active');
                document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
            });
        });
        
        // Plan selection
        document.querySelectorAll('.plan-card').forEach(card => {
            const radio = card.querySelector('input[type="radio"]');
            card.addEventListener('click', () => {
                document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                radio.checked = true;
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>
