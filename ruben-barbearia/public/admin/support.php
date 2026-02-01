<?php
/**
 * Admin Panel - Suporte / Tickets
 */

session_start();
$container = require __DIR__ . '/../../src/bootstrap.php';
$config = $container['config'];
$auth = $container['auth'];

if (!$auth || !$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$supportService = new \App\Services\SupportService();

$feedback = null;
$feedbackType = 'info';

// Handle actions
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$ticketId = (int) ($_GET['id'] ?? $_POST['ticket_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'subject' => trim($_POST['subject'] ?? ''),
            'category' => $_POST['category'] ?? 'general',
            'priority' => $_POST['priority'] ?? 'normal',
        ];
        $message = trim($_POST['message'] ?? '');
        
        if ($data['name'] && $data['email'] && $data['subject'] && $message) {
            $newTicketId = $supportService->createTicket($data);
            if ($newTicketId) {
                $supportService->addMessage($newTicketId, 'customer', $message, $data['name']);
                $feedback = 'Ticket criado com sucesso!';
                $feedbackType = 'success';
                $action = 'list';
            } else {
                $feedback = 'Erro ao criar ticket.';
                $feedbackType = 'error';
            }
        } else {
            $feedback = 'Preenche todos os campos obrigatórios.';
            $feedbackType = 'error';
        }
    } elseif ($action === 'reply' && $ticketId > 0) {
        $message = trim($_POST['message'] ?? '');
        if ($message) {
            $supportService->addMessage($ticketId, 'admin', $message, 'Administrador');
            $feedback = 'Resposta enviada.';
            $feedbackType = 'success';
        }
        $action = 'view';
    } elseif ($action === 'update_status' && $ticketId > 0) {
        $status = $_POST['status'] ?? '';
        if ($status) {
            $supportService->updateTicketStatus($ticketId, $status);
            $feedback = 'Estado atualizado.';
            $feedbackType = 'success';
        }
        $action = 'view';
    }
}

// Get data based on action
$tickets = [];
$ticket = null;
$messages = [];
$stats = $supportService->getTicketStats();

if ($action === 'list') {
    $statusFilter = $_GET['status'] ?? null;
    $tickets = $supportService->getAllTickets($statusFilter);
} elseif ($action === 'view' && $ticketId > 0) {
    $ticket = $supportService->getTicketById($ticketId);
    if ($ticket) {
        $messages = $supportService->getTicketMessages($ticketId);
    } else {
        $action = 'list';
        $tickets = $supportService->getAllTickets();
    }
}

$siteName = $config['tenant']['name'] ?? 'Admin';

$statusLabels = [
    'open' => 'Aberto',
    'in_progress' => 'Em Progresso',
    'waiting_customer' => 'Aguarda Cliente',
    'resolved' => 'Resolvido',
    'closed' => 'Fechado',
];

$categoryLabels = [
    'general' => 'Geral',
    'booking' => 'Marcação',
    'billing' => 'Faturação',
    'technical' => 'Técnico',
    'complaint' => 'Reclamação',
];

$priorityLabels = [
    'low' => 'Baixa',
    'normal' => 'Normal',
    'high' => 'Alta',
    'urgent' => 'Urgente',
];
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Suporte · <?= sanitize($siteName); ?></title>
  <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="admin-body">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <h2><?= sanitize($siteName); ?></h2>
    </div>
    <nav class="sidebar-nav">
      <a href="index.php" class="nav-item"><span class="nav-icon">📊</span>Dashboard</a>
      <a href="subscription.php" class="nav-item"><span class="nav-icon">💳</span>Subscrição</a>
      <a href="bookings.php" class="nav-item"><span class="nav-icon">📅</span>Marcações</a>
      <a href="support.php" class="nav-item active"><span class="nav-icon">🎫</span>Suporte</a>
      <a href="settings.php" class="nav-item"><span class="nav-icon">⚙️</span>Configurações</a>
      <a href="modules.php" class="nav-item"><span class="nav-icon">🧩</span>Módulos</a>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="main-content">
    <header class="content-header">
      <div>
        <h1>Suporte</h1>
        <p class="text-muted">Gestão de tickets de suporte</p>
      </div>
      <div class="header-actions">
        <?php if ($action !== 'new'): ?>
        <a href="?action=new" class="btn btn-primary">Novo Ticket</a>
        <?php endif; ?>
      </div>
    </header>

    <?php if ($feedback): ?>
    <div class="alert alert-<?= $feedbackType; ?>"><?= sanitize($feedback); ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
      <a href="?status=open" class="stat-pill <?= ($_GET['status'] ?? '') === 'open' ? 'active' : ''; ?>">
        Abertos <span class="count"><?= $stats['open']; ?></span>
      </a>
      <a href="?status=in_progress" class="stat-pill <?= ($_GET['status'] ?? '') === 'in_progress' ? 'active' : ''; ?>">
        Em Progresso <span class="count"><?= $stats['in_progress']; ?></span>
      </a>
      <a href="?status=resolved" class="stat-pill">
        Resolvidos <span class="count"><?= $stats['resolved']; ?></span>
      </a>
      <a href="?" class="stat-pill <?= empty($_GET['status']) && $action === 'list' ? 'active' : ''; ?>">
        Todos <span class="count"><?= $stats['total']; ?></span>
      </a>
    </div>

    <?php if ($action === 'new'): ?>
    <!-- New Ticket Form -->
    <section class="card">
      <div class="card-header">
        <h3>Criar Ticket</h3>
        <a href="?" class="link">← Voltar</a>
      </div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="create">
          <div class="form-grid">
            <div class="form-group">
              <label for="name">Nome *</label>
              <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
              <label for="email">Email *</label>
              <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
              <label for="phone">Telefone</label>
              <input type="tel" id="phone" name="phone">
            </div>
            <div class="form-group">
              <label for="category">Categoria</label>
              <select id="category" name="category">
                <?php foreach ($categoryLabels as $val => $label): ?>
                <option value="<?= $val; ?>"><?= $label; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="priority">Prioridade</label>
              <select id="priority" name="priority">
                <?php foreach ($priorityLabels as $val => $label): ?>
                <option value="<?= $val; ?>" <?= $val === 'normal' ? 'selected' : ''; ?>><?= $label; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label for="subject">Assunto *</label>
            <input type="text" id="subject" name="subject" required>
          </div>
          <div class="form-group">
            <label for="message">Mensagem *</label>
            <textarea id="message" name="message" rows="5" required></textarea>
          </div>
          <button type="submit" class="btn btn-primary">Criar Ticket</button>
        </form>
      </div>
    </section>

    <?php elseif ($action === 'view' && $ticket): ?>
    <!-- View Ticket -->
    <section class="card">
      <div class="card-header">
        <h3><?= sanitize($ticket['ticket_number']); ?> - <?= sanitize($ticket['subject']); ?></h3>
        <a href="?" class="link">← Voltar</a>
      </div>
      <div class="card-body">
        <div class="ticket-meta">
          <span><strong>Cliente:</strong> <?= sanitize($ticket['customer_name']); ?> (<?= sanitize($ticket['customer_email']); ?>)</span>
          <span><strong>Categoria:</strong> <?= $categoryLabels[$ticket['category']] ?? $ticket['category']; ?></span>
          <span><strong>Prioridade:</strong> <?= $priorityLabels[$ticket['priority']] ?? $ticket['priority']; ?></span>
          <span><strong>Criado:</strong> <?= format_date($ticket['created_at'], 'd/m/Y H:i'); ?></span>
        </div>
        
        <!-- Status Update -->
        <form method="POST" class="status-form">
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" name="ticket_id" value="<?= $ticket['id']; ?>">
          <label>Estado:</label>
          <select name="status" onchange="this.form.submit()">
            <?php foreach ($statusLabels as $val => $label): ?>
            <option value="<?= $val; ?>" <?= $ticket['status'] === $val ? 'selected' : ''; ?>><?= $label; ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        
        <!-- Messages -->
        <div class="messages-list">
          <?php foreach ($messages as $msg): ?>
          <div class="message message-<?= $msg['sender_type']; ?>">
            <div class="message-header">
              <strong><?= sanitize($msg['sender_name'] ?? ucfirst($msg['sender_type'])); ?></strong>
              <span class="message-time"><?= format_date($msg['created_at'], 'd/m/Y H:i'); ?></span>
            </div>
            <div class="message-body"><?= nl2br(sanitize($msg['message'])); ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        
        <!-- Reply Form -->
        <form method="POST" class="reply-form">
          <input type="hidden" name="action" value="reply">
          <input type="hidden" name="ticket_id" value="<?= $ticket['id']; ?>">
          <div class="form-group">
            <label for="reply-message">Responder:</label>
            <textarea id="reply-message" name="message" rows="3" required></textarea>
          </div>
          <button type="submit" class="btn btn-primary">Enviar Resposta</button>
        </form>
      </div>
    </section>

    <?php else: ?>
    <!-- Ticket List -->
    <section class="card">
      <div class="card-body">
        <?php if (count($tickets) > 0): ?>
        <table class="data-table">
          <thead>
            <tr>
              <th>Ticket</th>
              <th>Assunto</th>
              <th>Cliente</th>
              <th>Categoria</th>
              <th>Prioridade</th>
              <th>Estado</th>
              <th>Data</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tickets as $t): ?>
            <tr>
              <td><code><?= $t['ticket_number']; ?></code></td>
              <td><?= sanitize($t['subject']); ?></td>
              <td><?= sanitize($t['customer_name']); ?></td>
              <td><?= $categoryLabels[$t['category']] ?? $t['category']; ?></td>
              <td><span class="badge badge-<?= $t['priority']; ?>"><?= $priorityLabels[$t['priority']] ?? $t['priority']; ?></span></td>
              <td><span class="badge badge-<?= $t['status']; ?>"><?= $statusLabels[$t['status']] ?? $t['status']; ?></span></td>
              <td><?= format_date($t['created_at'], 'd/m/Y'); ?></td>
              <td><a href="?action=view&id=<?= $t['id']; ?>" class="btn btn-sm">Ver</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
          <p>Nenhum ticket encontrado.</p>
          <a href="?action=new" class="btn btn-primary">Criar Ticket</a>
        </div>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>
  </main>
</body>
</html>
