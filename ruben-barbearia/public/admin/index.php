<?php
/**
 * Admin Panel - Dashboard
 */

session_start();
$container = require __DIR__ . '/../../src/bootstrap.php';
$config = $container['config'];
$auth = $container['auth'];
$apiClient = $container['apiClient'];

// Verificar autenticação
if (!$auth || !$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

// Carregar serviços
$subscriptionService = new \App\Services\SubscriptionService($apiClient, $config['tenant']['id']);
$supportService = new \App\Services\SupportService();
$bookingService = new \App\Services\BookingService();

// Dados para o dashboard
$subscription = $subscriptionService->getCurrentSubscription();
$plans = $subscriptionService->getAvailablePlans();
$ticketStats = $supportService->getTicketStats();
$bookingStats = $bookingService->getBookingStats();
$upcomingBookings = $bookingService->getUpcomingBookings(5);

$siteName = $config['tenant']['name'] ?? 'Admin';
$currentPage = 'dashboard';

// Logout handler
if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard · <?= sanitize($siteName); ?></title>
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
      <a href="index.php" class="nav-item active">
        <span class="nav-icon">📊</span>
        Dashboard
      </a>
      <a href="subscription.php" class="nav-item">
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
        <?php if ($ticketStats['open'] > 0): ?>
        <span class="badge badge-warning"><?= $ticketStats['open']; ?></span>
        <?php endif; ?>
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
        <h1>Dashboard</h1>
        <p class="text-muted">Bem-vindo ao painel de administração</p>
      </div>
      <div class="header-actions">
        <a href="/" target="_blank" class="btn btn-outline">Ver Site</a>
      </div>
    </header>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">📅</div>
        <div class="stat-content">
          <p class="stat-label">Marcações Hoje</p>
          <p class="stat-value"><?= $bookingStats['today']; ?></p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📆</div>
        <div class="stat-content">
          <p class="stat-label">Esta Semana</p>
          <p class="stat-value"><?= $bookingStats['this_week']; ?></p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">⏳</div>
        <div class="stat-content">
          <p class="stat-label">Pendentes</p>
          <p class="stat-value"><?= $bookingStats['pending']; ?></p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">🎫</div>
        <div class="stat-content">
          <p class="stat-label">Tickets Abertos</p>
          <p class="stat-value"><?= $ticketStats['open']; ?></p>
        </div>
      </div>
    </div>

    <!-- Main Grid -->
    <div class="dashboard-grid">
      <!-- Subscription Card -->
      <section class="card">
        <div class="card-header">
          <h3>Subscrição</h3>
          <a href="subscription.php" class="link">Gerir →</a>
        </div>
        <div class="card-body">
          <?php if ($subscription): ?>
          <div class="subscription-info">
            <div class="subscription-status status-<?= $subscription['status']; ?>">
              <?= ucfirst($subscription['status']); ?>
            </div>
            <p class="subscription-plan">
              <?php
              $currentPlan = null;
              foreach ($plans as $plan) {
                  if ($plan['id'] == $subscription['planId']) {
                      $currentPlan = $plan;
                      break;
                  }
              }
              ?>
              <?= $currentPlan ? sanitize($currentPlan['name']) : 'Plano #' . $subscription['planId']; ?>
            </p>
            <p class="subscription-amount">
              <?= format_price($subscription['amountCents']); ?> / 
              <?= $subscription['billingPeriod'] === 'monthly' ? 'mês' : 'ano'; ?>
            </p>
            <?php if ($subscription['nextBillingAt']): ?>
            <p class="text-muted">Próxima cobrança: <?= format_date($subscription['nextBillingAt']); ?></p>
            <?php endif; ?>
          </div>
          <?php else: ?>
          <div class="empty-state">
            <p>Nenhuma subscrição ativa</p>
            <a href="subscription.php" class="btn btn-primary">Escolher Plano</a>
          </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- Upcoming Bookings -->
      <section class="card">
        <div class="card-header">
          <h3>Próximas Marcações</h3>
          <a href="bookings.php" class="link">Ver todas →</a>
        </div>
        <div class="card-body">
          <?php if (count($upcomingBookings) > 0): ?>
          <ul class="booking-list">
            <?php foreach ($upcomingBookings as $booking): ?>
            <li class="booking-item">
              <div class="booking-time">
                <span class="booking-date"><?= format_date($booking['booking_date'], 'd/m'); ?></span>
                <span class="booking-hour"><?= format_time($booking['booking_time']); ?></span>
              </div>
              <div class="booking-details">
                <strong><?= sanitize($booking['customer_name']); ?></strong>
                <span><?= sanitize($booking['service_name']); ?></span>
              </div>
              <span class="badge badge-<?= $booking['status']; ?>"><?= ucfirst($booking['status']); ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php else: ?>
          <div class="empty-state">
            <p>Nenhuma marcação agendada</p>
          </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- Quick Actions -->
      <section class="card">
        <div class="card-header">
          <h3>Ações Rápidas</h3>
        </div>
        <div class="card-body">
          <div class="quick-actions">
            <a href="bookings.php?action=new" class="action-btn">
              <span class="action-icon">➕</span>
              Nova Marcação
            </a>
            <a href="support.php?action=new" class="action-btn">
              <span class="action-icon">🎫</span>
              Novo Ticket
            </a>
            <a href="settings.php" class="action-btn">
              <span class="action-icon">🎨</span>
              Editar Site
            </a>
            <a href="modules.php" class="action-btn">
              <span class="action-icon">🤖</span>
              Config. Bot
            </a>
          </div>
        </div>
      </section>

      <!-- Support Tickets -->
      <section class="card">
        <div class="card-header">
          <h3>Tickets de Suporte</h3>
          <a href="support.php" class="link">Ver todos →</a>
        </div>
        <div class="card-body">
          <div class="ticket-stats">
            <div class="ticket-stat">
              <span class="ticket-count"><?= $ticketStats['open']; ?></span>
              <span class="ticket-label">Abertos</span>
            </div>
            <div class="ticket-stat">
              <span class="ticket-count"><?= $ticketStats['in_progress']; ?></span>
              <span class="ticket-label">Em Progresso</span>
            </div>
            <div class="ticket-stat">
              <span class="ticket-count"><?= $ticketStats['resolved']; ?></span>
              <span class="ticket-label">Resolvidos</span>
            </div>
          </div>
        </div>
      </section>
    </div>
  </main>

  <script src="assets/admin.js"></script>
</body>
</html>
