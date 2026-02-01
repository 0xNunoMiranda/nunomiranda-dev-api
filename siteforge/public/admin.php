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
            ],
        ];

        $settings = merge_settings($settings, $updates);
        $settingsStore->save($settings);
        $feedback = 'Configurações atualizadas.';
        $feedbackType = 'success';
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
$subscription = $settings['subscription'] ?? [];
$tenantName = $config['tenant']['name'] ?? 'SiteForge';
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
