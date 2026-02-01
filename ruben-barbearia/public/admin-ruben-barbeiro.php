<?php
session_start();
$container = require __DIR__ . '/../src/bootstrap.php';
$config = $container['config'];
$settingsStore = $container['settingsStore'];
$settings = $container['settings'];
$apiClient = $container['apiClient'];

$pin = $config['admin']['pin'];
$isAuthenticated = isset($_SESSION['rb_admin_authenticated']) && $_SESSION['rb_admin_authenticated'] === true;
$feedback = null;
$feedbackType = 'info';
$plans = [];

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $config['admin']['slug_path']);
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
            $_SESSION['rb_admin_authenticated'] = true;
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
            // Build customer data
            $customer = [
                'name' => $customerName,
                'email' => $customerEmail,
            ];
            if (!empty($customerPhone)) {
                $customer['phone'] = $customerPhone;
            }

            // For Direct Debit, include SDD mandate info if provided
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

            // Call the billing API to create subscription
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
                
                // Save subscription info locally
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
                
                // Build success message based on payment method response
                $paymentInfo = '';
                
                // Multibanco reference
                if (!empty($methodInfo['entity']) && !empty($methodInfo['reference'])) {
                    $paymentInfo = sprintf(
                        '<br><strong>Multibanco:</strong> Entidade %s · Referência %s · Valor %.2f€',
                        $methodInfo['entity'],
                        $methodInfo['reference'],
                        ($methodInfo['value'] ?? 0)
                    );
                }
                
                // MB WAY push notification
                if (!empty($methodInfo['alias'])) {
                    $paymentInfo = sprintf(
                        '<br><strong>MB WAY:</strong> Pedido enviado para %s',
                        $methodInfo['alias']
                    );
                }
                
                // Checkout URL for card payments
                if (!empty($methodInfo['url'])) {
                    $paymentInfo = sprintf(
                        '<br><a href="%s" target="_blank" class="btn secondary" style="margin-top:0.5rem;">Completar pagamento →</a>',
                        htmlspecialchars($methodInfo['url'])
                    );
                }
                
                $feedback = 'Subscrição criada com sucesso!' . $paymentInfo;
                $feedbackType = 'success';
            } else {
                $errorMsg = $billingResponse['data']['error'] ?? 'Erro ao criar subscrição.';
                $feedback = 'Erro: ' . $errorMsg;
                $feedbackType = 'error';
            }
        } else {
            $feedback = 'Preenche todos os campos obrigatórios (nome, email e plano).';
            $feedbackType = 'error';
        }
    }
}

$branding = $settings['branding'] ?? [];
$ai = $settings['ai_bot'] ?? [];
$whatsapp = $settings['whatsapp'] ?? [];
$subscription = $settings['subscription'] ?? [];

function format_price(int $cents, string $currency = 'EUR'): string {
    $value = number_format($cents / 100, 2, ',', ' ');
    return $value . ' ' . $currency;
}

function get_selected_plan(array $plans, ?int $planId): ?array {
    if (!$planId) return null;
    foreach ($plans as $plan) {
        if ((int)$plan['id'] === $planId) return $plan;
    }
    return null;
}

$currentPlan = get_selected_plan($plans, $subscription['selected_plan_id'] ?? null);
?>
<!DOCTYPE html>
<html lang="pt-PT">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Painel · <?= sanitize($config['tenant']['name']); ?></title>
    <link rel="stylesheet" href="assets/styles.css" />
    <style>
      .plans-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-top: 1rem; }
      .plan-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 1.5rem; position: relative; transition: border-color 0.2s, transform 0.2s; cursor: pointer; }
      .plan-card:hover { border-color: var(--accent, #00ffc6); transform: translateY(-2px); }
      .plan-card.selected { border-color: var(--accent, #00ffc6); box-shadow: 0 0 20px rgba(0, 255, 198, 0.15); }
      .plan-card input[type="radio"] { position: absolute; top: 1rem; right: 1rem; accent-color: var(--accent, #00ffc6); width: 1.25rem; height: 1.25rem; }
      .plan-card h3 { margin: 0 0 0.5rem; font-size: 1.25rem; }
      .plan-card .price { font-size: 2rem; font-weight: 700; color: var(--accent, #00ffc6); }
      .plan-card .period { font-size: 0.875rem; color: rgba(255,255,255,0.6); }
      .plan-card .description { margin: 1rem 0; font-size: 0.9rem; color: rgba(255,255,255,0.7); }
      .plan-card .features { list-style: none; padding: 0; margin: 1rem 0 0; font-size: 0.85rem; }
      .plan-card .features li { padding: 0.25rem 0; display: flex; align-items: center; gap: 0.5rem; }
      .plan-card .features li::before { content: "✓"; color: var(--accent, #00ffc6); font-weight: bold; }
      .plan-card .modules { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1); }
      .plan-card .modules h4 { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: rgba(255,255,255,0.5); margin: 0 0 0.5rem; }
      .plan-card .module-tag { display: inline-block; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; padding: 0.25rem 0.5rem; font-size: 0.75rem; margin: 0.25rem 0.25rem 0 0; }
      .payment-methods { display: flex; flex-wrap: wrap; gap: 0.75rem; margin: 1rem 0; }
      .payment-method { display: flex; align-items: center; gap: 0.5rem; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 0.75rem 1rem; cursor: pointer; transition: border-color 0.2s; }
      .payment-method:hover, .payment-method:has(input:checked) { border-color: var(--accent, #00ffc6); }
      .payment-method input { accent-color: var(--accent, #00ffc6); }
      .current-plan-badge { display: inline-block; background: var(--accent, #00ffc6); color: #0a0a0a; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; padding: 0.25rem 0.5rem; border-radius: 4px; margin-left: 0.5rem; }
      .toast.success { background: rgba(34, 197, 94, 0.9); }
      .toast.error { background: rgba(239, 68, 68, 0.9); }
    </style>
  </head>
  <body class="admin">
    <div class="stars" aria-hidden="true"></div>
    <header class="hero compact">
      <div>
        <p class="eyebrow">Console privada</p>
        <h1><?= sanitize($config['tenant']['name']); ?> · aditivos</h1>
        <p class="lede">Ativa ou desativa o bot AI, WhatsApp turbo e ajusta o conteúdo da landing page.</p>
      </div>
      <div>
        <a class="link" href="/">← Voltar ao site</a>
      </div>
    </header>

    <?php if ($feedback): ?>
      <div class="toast <?= $feedbackType; ?>" visible><?= sanitize($feedback); ?></div>
    <?php endif; ?>

    <?php if (!$isAuthenticated): ?>
      <main class="auth-panel">
        <form class="panel" method="post">
          <input type="hidden" name="form" value="login" />
          <h2>Entrar</h2>
          <label>
            PIN administrativo
            <input type="password" name="pin" required />
          </label>
          <button type="submit" class="btn primary">Aceder</button>
        </form>
      </main>
    <?php else: ?>
      <main class="admin-grid">
        <section class="panel">
          <div class="panel-header">
            <div>
              <p class="eyebrow">Landing page</p>
              <h2>Branding</h2>
            </div>
            <a class="link" href="?logout=1">Terminar sessão</a>
          </div>
          <form method="post" class="stack" autocomplete="off">
            <input type="hidden" name="form" value="settings" />
            <label>
              Headline
              <input type="text" name="headline" value="<?= sanitize($branding['headline'] ?? ''); ?>" required />
            </label>
            <label>
              Subheadline
              <textarea name="subheadline" rows="2" required><?= sanitize($branding['subheadline'] ?? ''); ?></textarea>
            </label>
            <div class="form-grid">
              <label>
                CTA texto
                <input type="text" name="ctaText" value="<?= sanitize($branding['ctaText'] ?? ''); ?>" required />
              </label>
              <label>
                CTA link
                <input type="text" name="ctaLink" value="<?= sanitize($branding['ctaLink'] ?? '#marcacoes'); ?>" required />
              </label>
            </div>
            <hr />
            <div class="toggle-row">
              <label>
                <input type="checkbox" name="ai_enabled" value="1" <?= !empty($ai['enabled']) ? 'checked' : ''; ?> />
                Bot AI ativo
              </label>
              <label>
                Nome do assistente
                <input type="text" name="assistantName" value="<?= sanitize($ai['assistantName'] ?? ''); ?>" />
              </label>
            </div>
            <label>
              Mensagem de boas-vindas
              <textarea name="welcomeMessage" rows="2"><?= sanitize($ai['welcomeMessage'] ?? ''); ?></textarea>
            </label>
            <div class="form-grid">
              <label>
                API Key do bot
                <input type="text" name="apiKey" value="<?= sanitize($ai['apiKey'] ?? ''); ?>" placeholder="sk-live-..." />
              </label>
              <label>
                Canal preferido
                <input type="text" name="preferredChannel" value="<?= sanitize($ai['preferredChannel'] ?? 'site'); ?>" />
              </label>
            </div>
            <hr />
            <div class="toggle-row">
              <label>
                <input type="checkbox" name="wa_enabled" value="1" <?= !empty($whatsapp['enabled']) ? 'checked' : ''; ?> />
                WhatsApp turbo ativo
              </label>
            </div>
            <div class="form-grid">
              <label>
                Número
                <input type="text" name="wa_number" value="<?= sanitize($whatsapp['number'] ?? ''); ?>" />
              </label>
              <label>
                Webhook URL
                <input type="text" name="wa_webhook" value="<?= sanitize($whatsapp['webhookUrl'] ?? ''); ?>" placeholder="https://..." />
              </label>
            </div>
            <label>
              Saudação automática
              <textarea name="wa_greeting" rows="2"><?= sanitize($whatsapp['greeting'] ?? ''); ?></textarea>
            </label>
            <button type="submit" class="btn primary">Guardar alterações</button>
          </form>
        </section>

        <section class="panel">
          <div class="panel-header">
            <div>
              <p class="eyebrow">Integração com API</p>
              <h2>Resumo rápido</h2>
            </div>
          </div>
          <ul class="status-list">
            <li><span>Tenant</span><strong><?= sanitize($config['tenant']['slug']); ?> (#<?= (int) $config['tenant']['id']; ?>)</strong></li>
            <li><span>Endpoint API</span><strong><?= sanitize($config['api']['base_url']); ?></strong></li>
            <li><span>Rate limit</span><strong>gerido pelo core</strong></li>
            <li><span>Bot</span><strong><?= !empty($ai['enabled']) ? 'Ativo' : 'Desligado'; ?></strong></li>
            <li><span>WhatsApp</span><strong><?= !empty($whatsapp['enabled']) ? 'Ativo' : 'Desligado'; ?></strong></li>
            <li>
              <span>Plano atual</span>
              <strong>
                <?php if ($currentPlan): ?>
                  <?= sanitize($currentPlan['name']); ?> (<?= format_price($currentPlan['priceCents'], $currentPlan['currency']); ?>/<?= $currentPlan['billingPeriod'] === 'monthly' ? 'mês' : 'ano'; ?>)
                <?php else: ?>
                  Nenhum plano selecionado
                <?php endif; ?>
              </strong>
            </li>
          </ul>
          <p class="muted">Os pedidos enviados pelo bot são encaminhados para a API multi-tenant utilizando a chave configurada acima.</p>
        </section>

        <?php if (count($plans) > 0): ?>
        <section class="panel" style="grid-column: 1 / -1;">
          <div class="panel-header">
            <div>
              <p class="eyebrow">Subscrição</p>
              <h2>Escolhe o teu plano</h2>
            </div>
          </div>

          <?php 
          // Show current subscription status if exists
          $activeSubscription = $subscription['status'] ?? null;
          $methodInfo = $subscription['method'] ?? [];
          if ($activeSubscription && $activeSubscription !== 'canceled'):
          ?>
          <div style="background: rgba(0, 255, 198, 0.1); border: 1px solid rgba(0, 255, 198, 0.3); border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
            <h4 style="margin: 0 0 0.5rem; color: var(--accent, #00ffc6);">📋 Subscrição ativa</h4>
            <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.9rem;">
              <li><strong>Estado:</strong> <?= sanitize(ucfirst($activeSubscription)); ?></li>
              <?php if ($currentPlan): ?>
              <li><strong>Plano:</strong> <?= sanitize($currentPlan['name']); ?> (<?= format_price($currentPlan['priceCents'], $currentPlan['currency']); ?>)</li>
              <?php endif; ?>
              <li><strong>Método:</strong> <?= sanitize(ucfirst(str_replace('_', ' ', $subscription['payment_method'] ?? 'N/A'))); ?></li>
              <?php if (!empty($methodInfo['entity'])): ?>
              <li><strong>Multibanco:</strong> Entidade <?= sanitize($methodInfo['entity']); ?> · Ref. <?= sanitize($methodInfo['reference']); ?></li>
              <?php endif; ?>
              <?php if (!empty($methodInfo['url'])): ?>
              <li><a href="<?= htmlspecialchars($methodInfo['url']); ?>" target="_blank" class="link">Completar pagamento →</a></li>
              <?php endif; ?>
            </ul>
          </div>
          <?php endif; ?>

          <form method="post" autocomplete="off">
            <input type="hidden" name="form" value="select_plan" />
            
            <div class="plans-grid">
              <?php foreach ($plans as $plan): 
                $isCurrentPlan = $currentPlan && (int)$currentPlan['id'] === (int)$plan['id'];
              ?>
              <label class="plan-card <?= $isCurrentPlan ? 'selected' : ''; ?>">
                <input type="radio" name="plan_id" value="<?= (int)$plan['id']; ?>" <?= $isCurrentPlan ? 'checked' : ''; ?> required />
                <h3>
                  <?= sanitize($plan['name']); ?>
                  <?php if ($isCurrentPlan): ?><span class="current-plan-badge">Atual</span><?php endif; ?>
                </h3>
                <div class="price"><?= format_price($plan['priceCents'], $plan['currency']); ?></div>
                <div class="period"><?= $plan['billingPeriod'] === 'monthly' ? 'por mês' : 'por ano'; ?><?php if ($plan['trialDays'] > 0): ?> · <?= (int)$plan['trialDays']; ?> dias grátis<?php endif; ?></div>
                
                <?php if (!empty($plan['description'])): ?>
                <p class="description"><?= sanitize($plan['description']); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($plan['features'])): ?>
                <ul class="features">
                  <?php foreach ($plan['features'] as $feature): ?>
                  <li><?= sanitize($feature); ?></li>
                  <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                
                <?php if (!empty($plan['modules'])): ?>
                <div class="modules">
                  <h4>Módulos incluídos</h4>
                  <?php foreach ($plan['modules'] as $module): ?>
                  <span class="module-tag"><?= sanitize($module['name']); ?></span>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
              </label>
              <?php endforeach; ?>
            </div>
            
            <hr style="margin: 2rem 0;" />
            
            <h3 style="margin-bottom: 0.5rem;">Método de pagamento recorrente</h3>
            <p class="muted" style="margin-top: 0;">Escolhe como desejas pagar a tua subscrição mensal/anual.</p>
            
            <div class="payment-methods">
              <label class="payment-method">
                <input type="radio" name="payment_method" value="multibanco" <?= ($subscription['payment_method'] ?? 'multibanco') === 'multibanco' ? 'checked' : ''; ?> />
                <span>🏦 Multibanco</span>
              </label>
              <label class="payment-method">
                <input type="radio" name="payment_method" value="mbway" <?= ($subscription['payment_method'] ?? '') === 'mbway' ? 'checked' : ''; ?> />
                <span>📱 MB Way</span>
              </label>
              <label class="payment-method">
                <input type="radio" name="payment_method" value="direct_debit" <?= ($subscription['payment_method'] ?? '') === 'direct_debit' ? 'checked' : ''; ?> />
                <span>🏛️ Débito Direto</span>
              </label>
              <label class="payment-method">
                <input type="radio" name="payment_method" value="credit_card" <?= ($subscription['payment_method'] ?? '') === 'credit_card' ? 'checked' : ''; ?> />
                <span>💳 Cartão de Crédito</span>
              </label>
            </div>

            <hr style="margin: 2rem 0;" />
            
            <h3 style="margin-bottom: 0.5rem;">Dados de faturação</h3>
            <p class="muted" style="margin-top: 0;">Informação necessária para processar o pagamento.</p>
            
            <div class="form-grid" style="margin-top: 1rem;">
              <label>
                Nome completo *
                <input type="text" name="customer_name" value="<?= sanitize($subscription['customer']['name'] ?? ''); ?>" required />
              </label>
              <label>
                Email *
                <input type="email" name="customer_email" value="<?= sanitize($subscription['customer']['email'] ?? ''); ?>" required />
              </label>
            </div>
            
            <div class="form-grid">
              <label>
                Telefone (para MB Way)
                <input type="tel" name="customer_phone" value="<?= sanitize($subscription['customer']['phone'] ?? ''); ?>" placeholder="351#XXXXXXXXX" />
              </label>
            </div>

            <!-- SDD (Direct Debit) fields - shown via JS when direct_debit selected -->
            <div id="sdd-fields" class="form-grid" style="display: none; margin-top: 1rem; padding: 1rem; background: rgba(255,255,255,0.03); border-radius: 8px;">
              <label style="grid-column: 1 / -1;">
                <strong>Dados para Débito Direto (SEPA)</strong>
              </label>
              <label>
                IBAN
                <input type="text" name="sdd_iban" placeholder="PT50..." />
              </label>
              <label>
                Titular da conta
                <input type="text" name="sdd_account_holder" placeholder="Nome do titular" />
              </label>
            </div>
            
            <button type="submit" class="btn primary" style="margin-top: 1.5rem;">Confirmar plano e pagar</button>
          </form>

          <script>
            (function() {
              const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
              const sddFields = document.getElementById('sdd-fields');
              
              function toggleSddFields() {
                const selected = document.querySelector('input[name="payment_method"]:checked');
                if (selected && selected.value === 'direct_debit') {
                  sddFields.style.display = 'grid';
                } else {
                  sddFields.style.display = 'none';
                }
              }
              
              paymentRadios.forEach(r => r.addEventListener('change', toggleSddFields));
              toggleSddFields();
            })();
          </script>
        </section>
        <?php else: ?>
        <section class="panel" style="grid-column: 1 / -1;">
          <div class="panel-header">
            <div>
              <p class="eyebrow">Subscrição</p>
              <h2>Planos de subscrição</h2>
            </div>
          </div>
          <p class="muted">Não há planos disponíveis de momento. Contacta o administrador.</p>
        </section>
        <?php endif; ?>
      </main>
    <?php endif; ?>
  </body>
</html>
