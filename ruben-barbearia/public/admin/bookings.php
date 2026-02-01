<?php
/**
 * Admin - Bookings Management
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Auth;

Auth::requireAuth();

$bookingService = $GLOBALS['bookingService'];

// Handle actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    
    try {
        switch ($action) {
            case 'update_status':
                $status = $_POST['status'] ?? '';
                if ($bookingId && in_array($status, ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'])) {
                    $bookingService->updateBookingStatus($bookingId, $status);
                    $message = 'Estado da marcação atualizado.';
                }
                break;
                
            case 'delete':
                if ($bookingId) {
                    $bookingService->updateBookingStatus($bookingId, 'cancelled');
                    $message = 'Marcação cancelada.';
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get filter parameters
$filterDate = $_GET['date'] ?? date('Y-m-d');
$filterStatus = $_GET['status'] ?? '';
$filterStaff = $_GET['staff'] ?? '';

// Get bookings
if ($filterDate) {
    $bookings = $bookingService->getBookingsByDate($filterDate);
} else {
    $bookings = $bookingService->getUpcomingBookings(50);
}

// Filter by status
if ($filterStatus) {
    $bookings = array_filter($bookings, fn($b) => $b['status'] === $filterStatus);
}

// Filter by staff
if ($filterStaff) {
    $bookings = array_filter($bookings, fn($b) => $b['staff_id'] == $filterStaff);
}

$staff = $bookingService->getStaff();
$services = $bookingService->getServices();
$stats = $bookingService->getBookingStats();

$statusLabels = [
    'pending' => ['label' => 'Pendente', 'class' => 'warning'],
    'confirmed' => ['label' => 'Confirmada', 'class' => 'info'],
    'completed' => ['label' => 'Concluída', 'class' => 'success'],
    'cancelled' => ['label' => 'Cancelada', 'class' => 'danger'],
    'no_show' => ['label' => 'Faltou', 'class' => 'secondary'],
];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marcações - Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><?php echo htmlspecialchars($config['site']['title'] ?? 'Admin'); ?></h2>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php"><span>📊</span> Dashboard</a>
                <a href="bookings.php" class="active"><span>📅</span> Marcações</a>
                <a href="subscription.php"><span>💳</span> Subscrição</a>
                <a href="support.php"><span>💬</span> Suporte</a>
                <a href="settings.php"><span>⚙️</span> Configurações</a>
            </nav>
            <div class="sidebar-footer">
                <a href="?logout=1" class="logout-btn">Sair</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="content-header">
                <div>
                    <h1>Marcações</h1>
                    <p>Gerir as marcações do negócio</p>
                </div>
                <button onclick="openNewBookingModal()" class="btn btn-accent">
                    + Nova Marcação
                </button>
            </header>

            <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-icon">📅</span>
                    <div class="stat-content">
                        <span class="stat-value"><?php echo $stats['today'] ?? 0; ?></span>
                        <span class="stat-label">Hoje</span>
                    </div>
                </div>
                <div class="stat-card">
                    <span class="stat-icon">📆</span>
                    <div class="stat-content">
                        <span class="stat-value"><?php echo $stats['tomorrow'] ?? 0; ?></span>
                        <span class="stat-label">Amanhã</span>
                    </div>
                </div>
                <div class="stat-card">
                    <span class="stat-icon">⏳</span>
                    <div class="stat-content">
                        <span class="stat-value"><?php echo $stats['pending'] ?? 0; ?></span>
                        <span class="stat-label">Pendentes</span>
                    </div>
                </div>
                <div class="stat-card">
                    <span class="stat-icon">✅</span>
                    <div class="stat-content">
                        <span class="stat-value"><?php echo $stats['this_week'] ?? 0; ?></span>
                        <span class="stat-label">Esta Semana</span>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="filters-form">
                        <div class="form-group">
                            <label>Data</label>
                            <input type="date" name="date" value="<?php echo htmlspecialchars($filterDate); ?>">
                        </div>
                        <div class="form-group">
                            <label>Estado</label>
                            <select name="status">
                                <option value="">Todos</option>
                                <?php foreach ($statusLabels as $key => $status): ?>
                                <option value="<?php echo $key; ?>" <?php echo $filterStatus === $key ? 'selected' : ''; ?>>
                                    <?php echo $status['label']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Profissional</label>
                            <select name="staff">
                                <option value="">Todos</option>
                                <?php foreach ($staff as $member): ?>
                                <option value="<?php echo $member['id']; ?>" <?php echo $filterStaff == $member['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($member['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-secondary">Filtrar</button>
                        <a href="bookings.php" class="btn btn-outline">Limpar</a>
                    </form>
                </div>
            </div>

            <!-- Bookings List -->
            <div class="card">
                <div class="card-header">
                    <h3>Marcações - <?php echo date('d/m/Y', strtotime($filterDate)); ?></h3>
                </div>
                <div class="card-body">
                    <?php if (empty($bookings)): ?>
                    <div class="empty-state">
                        <p>Nenhuma marcação encontrada para esta data.</p>
                    </div>
                    <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Cliente</th>
                                <th>Serviço</th>
                                <th>Profissional</th>
                                <th>Estado</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td>
                                    <strong><?php echo substr($booking['booking_time'], 0, 5); ?></strong>
                                </td>
                                <td>
                                    <div class="customer-info">
                                        <strong><?php echo htmlspecialchars($booking['customer_name']); ?></strong>
                                        <small><?php echo htmlspecialchars($booking['customer_phone']); ?></small>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($booking['service_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($booking['staff_name'] ?? 'Qualquer'); ?></td>
                                <td>
                                    <?php $status = $statusLabels[$booking['status']] ?? ['label' => $booking['status'], 'class' => 'secondary']; ?>
                                    <span class="badge badge-<?php echo $status['class']; ?>">
                                        <?php echo $status['label']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <select name="status" onchange="this.form.submit()" class="status-select">
                                                <?php foreach ($statusLabels as $key => $st): ?>
                                                <option value="<?php echo $key; ?>" <?php echo $booking['status'] === $key ? 'selected' : ''; ?>>
                                                    <?php echo $st['label']; ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                        <button onclick="viewBooking(<?php echo $booking['id']; ?>)" class="btn btn-sm btn-outline">Ver</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- New Booking Modal -->
    <div id="newBookingModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Nova Marcação</h3>
                <button onclick="closeModal('newBookingModal')" class="close-btn">&times;</button>
            </div>
            <form method="POST" action="../api/booking.php" id="newBookingForm">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nome do Cliente *</label>
                            <input type="text" name="customer_name" required>
                        </div>
                        <div class="form-group">
                            <label>Telefone *</label>
                            <input type="tel" name="customer_phone" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Serviço *</label>
                            <select name="service_id" required>
                                <option value="">Selecionar</option>
                                <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['id']; ?>">
                                    <?php echo htmlspecialchars($service['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Profissional</label>
                            <select name="staff_id">
                                <option value="">Sem preferência</option>
                                <?php foreach ($staff as $member): ?>
                                <option value="<?php echo $member['id']; ?>">
                                    <?php echo htmlspecialchars($member['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Data *</label>
                            <input type="date" name="booking_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Hora *</label>
                            <input type="time" name="booking_time" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notas</label>
                        <textarea name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('newBookingModal')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-accent">Criar Marcação</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openNewBookingModal() {
            document.getElementById('newBookingModal').style.display = 'flex';
        }
        
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        
        function viewBooking(id) {
            alert('Ver marcação #' + id + ' - Funcionalidade em desenvolvimento');
        }
        
        // Close modal on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        });

        // Handle new booking form
        document.getElementById('newBookingForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            try {
                const response = await fetch('../api/booking.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (response.ok) {
                    alert('Marcação criada com sucesso!');
                    location.reload();
                } else {
                    alert('Erro: ' + (result.error || 'Falha ao criar marcação'));
                }
            } catch (error) {
                alert('Erro de conexão');
            }
        });
    </script>
</body>
</html>
