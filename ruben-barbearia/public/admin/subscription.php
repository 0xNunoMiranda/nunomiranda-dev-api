<?php
/**
 * Admin Panel - Gestão de Subscrição
 */

session_start();
$container = require __DIR__ . '/../../src/bootstrap.php';
$config = $container['config'];
$auth = $container['auth'];
$apiClient = $container['apiClient'];
$settingsStore = $container['settingsStore'];
$settings = $container['settings'];

if (!$auth || !$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$subscriptionService = new \App\Services\SubscriptionService($apiClient, $config['tenant']['id']);

$feedback = null;
$feedbackType = 'info';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'subscribe') {
        $planId = (int) ($_POST['plan_id'] ?? 0);
        $paymentMethod = $_POST['payment_method'] ?? 'multibanco';
        $customerName = trim($_POST['customer_name'] ?? '');
        $customerEmail = trim($_POST['customer_email'] ?? '');
        $customerPhone = trim($_POST['customer_phone'] ?? '');
        
        if ($planId > 0 && $customerName && $customerEmail) {
            $customer = [
                'name' => $customerName,
                'email' => $customerEmail,
            ];
            if ($customerPhone) {
                $customer['phone'] = $customerPhone;
            }
            
            $sddMandate = null;
            if ($paymentMethod === 'direct_debit') {
                $iban = trim($_POST['sdd_iban'] ?? '');
                $accountHolder = trim($_POST['sdd_account_holder'] ?? '');
                if ($iban && $accountHolder) {
                    $sddMandate = ['iban' => $iban, 'accountHolder' => $accountHolder];
                }
            }
            
            $result = $subscriptionService->createSubscription($planId, $paymentMethod, $customer, $sddMandate);
            
            if ($result['success']) {
                $settings['subscription'] = [
                    'id' => $result['data']['subscriptionId'] ?? null,
                    'status' => $result['data']['status'] ?? 'pending',
                    'method' => $result['data']['method'] ?? null,
                ];
                $settingsStore->save($settings);
                
                $feedback = 'Subscrição criada com sucesso!';
                if (!empty($result['data']['method']['entity'])) {
                    $feedback .= sprintf(
                        ' Multibanco: Entidade %s · Referência %s',
                        $result['data']['method']['entity'],
                        $result['data']['method']['reference']
                    );
                }
                $feedbackType = 'success';
            } else {
                $feedback = 'Erro: ' . ($result['error'] ?? 'Falha ao criar subscrição');
                $feedbackType = 'error';
            }
        } else {
            $feedback = 'Preenche todos os campos obrigatórios.';
            $feedbackType = 'error';
        }
    } elseif ($action === 'cancel') {
        $subscriptionId = (int) ($_POST['subscription_id'] ?? 0);
        if ($subscriptionId > 0) {
            $result = $subscriptionService->cancelSubscription($subscriptionId);
            if ($result['success']) {
                $feedback = 'Subscrição cancelada.';
                $feedbackType = 'success';
            } else {
                $feedback = 'Erro ao cancelar: ' . ($result['error'] ?? 'Desconhecido');
                $feedbackType = 'error';
            }
        }
    }
}

$subscription = $subscriptionService->getCurrentSubscription();
$plans = $subscriptionService->getAvailablePlans();

$siteName = $config['tenant']['name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Subscrição · <?= sanitize($siteName); ?></title>
  <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="admin-body">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <h2><?= sanitize($siteName); ?></h2>
      <span class="badge">Admin</span>
    </div>
    <nav class="sidebar-nav">
      <a href="index.php" class="nav-item">
        <span class="nav-icon">📊</span>
        Dashboard
      </a>
      <a href="subscription.php" class="nav-item active">
        <span class="nav-icon">💳</span>
        Subscrição
      </a>
      <a href="bookings.php" class="nav-item">
        <span class="nav-icon">📅</span>
        Marcações
      </a>
      <a href="support.php" class="nav-item">
        <span class="nav-icon">🎫</span>
        Suporte
      </a>
      <a href="settings.php" class="nav-item">
        <span class="nav-icon">⚙️</span>
        Configurações
      </a>
      <a href="modules.php" class="nav-item">
        <span class="nav-icon">🧩</span>
        Módulos
      </a>
    </nav>
    <div class="sidebar-footer">
      <a href="?logout=1" class="btn btn-outline btn-sm">Terminar Sessão</a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="main-content">
    <header class="content-header">
      <div>
        <h1>Subscrição</h1>
        <p class="text-muted">Gere o teu plano e método de pagamento</p>
      </div>
    </header>

    <?php if ($feedback): ?>
    <div class="alert alert-<?= $feedbackType; ?>"><?= sanitize($feedback); ?></div>
    <?php endif; ?>

    <?php if ($subscription && $subscription['status'] === 'active'): ?>
    <!-- Current Subscription -->
    <section class="card">
      <div class="card-header">
        <h3>Plano Atual</h3>
        <span class="badge badge-success">Ativo</span>
      </div>
      <div class="card-body">
        <?php
        $currentPlan = null;
        foreach ($plans as $plan) {
            if ($plan['id'] == $subscription['planId']) {
                $currentPlan = $plan;
                break;
            }
        }
        ?>
        <div class="subscription-details">
          <div class="subscription-plan-info">
            <h4><?= $currentPlan ? sanitize($currentPlan['name']) : 'Plano #' . $subscription['planId']; ?></h4>
            <p class="plan-price"><?= format_price($subscription['amountCents']); ?> / <?= $subscription['billingPeriod'] === 'monthly' ? 'mês' : 'ano'; ?></p>
          </div>
          <ul class="subscription-meta">
            <li><strong>Método:</strong> <?= ucfirst(str_replace('_', ' ', $subscription['paymentMethod'] ?? 'N/A')); ?></li>
            <li><strong>Desde:</strong> <?= $subscription['startedAt'] ? format_date($subscription['startedAt']) : 'N/A'; ?></li>
            <li><strong>Próxima cobrança:</strong> <?= $subscription['nextBillingAt'] ? format_date($subscription['nextBillingAt']) : 'N/A'; ?></li>
          </ul>
          <?php if ($currentPlan && !empty($currentPlan['features'])): ?>
          <div class="plan-features">
            <h5>Incluído no plano:</h5>
            <ul>
              <?php foreach ($currentPlan['features'] as $feature): ?>
              <li>✓ <?= sanitize($feature); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php endif; ?>
        </div>
        <hr>
        <form method="POST" onsubmit="return confirm('Tens a certeza que queres cancelar a subscrição?');">
          <input type="hidden" name="action" value="cancel">
          <input type="hidden" name="subscription_id" value="<?= $subscription['id']; ?>">
          <button type="submit" class="btn btn-danger">Cancelar Subscrição</button>
        </form>
      </div>
    </section>

    <?php else: ?>
    <!-- Choose Plan -->
    <section class="card">
      <div class="card-header">
        <h3>Escolher Plano</h3>
      </div>
      <div class="card-body">
        <?php if (count($plans) > 0): ?>
        <form method="POST" id="subscription-form">
          <input type="hidden" name="action" value="subscribe">
          
          <div class="plans-grid">
            <?php foreach ($plans as $plan): ?>
            <label class="plan-card">
              <input type="radio" name="plan_id" value="<?= $plan['id']; ?>" required>
              <div class="plan-card-content">
                <h4><?= sanitize($plan['name']); ?></h4>
                <p class="plan-price">
                  <strong><?= format_price($plan['priceCents']); ?></strong>
                  <span>/ <?= $plan['billingPeriod'] === 'monthly' ? 'mês' : 'ano'; ?></span>
                </p>
                <?php if ($plan['trialDays'] > 0): ?>
                <p class="plan-trial"><?= $plan['trialDays']; ?> dias grátis</p>
                <?php endif; ?>
                <?php if (!empty($plan['description'])): ?>
                <p class="plan-description"><?= sanitize($plan['description']); ?></p>
                <?php endif; ?>
                <?php if (!empty($plan['features'])): ?>
                <ul class="plan-features-list">
                  <?php foreach ($plan['features'] as $feature): ?>
                  <li><?= sanitize($feature); ?></li>
                  <?php endforeach; ?>
                </ul>
                <?php endif; ?>
              </div>
            </label>
            <?php endforeach; ?>
          </div>

          <hr>
          
          <h4>Método de Pagamento</h4>
          <div class="payment-methods">
            <label class="payment-method">
              <input type="radio" name="payment_method" value="multibanco" checked>
              <span>🏦 Multibanco</span>
            </label>
            <label class="payment-method">
              <input type="radio" name="payment_method" value="mbway">
              <span>📱 MB Way</span>
            </label>
            <label class="payment-method">
              <input type="radio" name="payment_method" value="credit_card">
              <span>💳 Cartão</span>
            </label>
            <label class="payment-method">
              <input type="radio" name="payment_method" value="direct_debit">
              <span>🏛️ Débito Direto</span>
            </label>
          </div>

          <hr>
          
          <h4>Dados de Faturação</h4>
          <div class="form-grid">
            <div class="form-group">
              <label for="customer_name">Nome Completo *</label>
              <input type="text" id="customer_name" name="customer_name" required>
            </div>
            <div class="form-group">
              <label for="customer_email">Email *</label>
              <input type="email" id="customer_email" name="customer_email" required>
            </div>
            <div class="form-group">
              <label for="customer_phone">Telefone</label>
              <input type="tel" id="customer_phone" name="customer_phone" placeholder="351#XXXXXXXXX">
            </div>
          </div>

          <div id="sdd-fields" class="form-grid" style="display: none;">
            <div class="form-group">
              <label for="sdd_iban">IBAN</label>
              <input type="text" id="sdd_iban" name="sdd_iban" placeholder="PT50...">
            </div>
            <div class="form-group">
              <label for="sdd_account_holder">Titular da Conta</label>
              <input type="text" id="sdd_account_holder" name="sdd_account_holder">
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-lg">Subscrever Agora</button>
        </form>

        <script>
          document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
              document.getElementById('sdd-fields').style.display = 
                this.value === 'direct_debit' ? 'grid' : 'none';
            });
          });
        </script>
        <?php else: ?>
        <div class="empty-state">
          <p>Não há planos disponíveis de momento.</p>
        </div>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>
  </main>
</body>
</html>
