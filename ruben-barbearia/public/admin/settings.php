<?php
/**
 * Admin - Settings Page
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Auth;
use App\Database;

Auth::requireAuth();

$db = Database::getInstance();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_site':
                // Update site settings in database
                $fields = ['title', 'phone', 'email', 'address'];
                foreach ($fields as $field) {
                    if (isset($_POST[$field])) {
                        $db->query(
                            "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                             ON DUPLICATE KEY UPDATE setting_value = ?",
                            ["site_$field", $_POST[$field], $_POST[$field]]
                        );
                    }
                }
                $message = 'Configurações do site atualizadas.';
                break;
                
            case 'update_business_hours':
                $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                foreach ($days as $day) {
                    $isClosed = isset($_POST["closed_$day"]);
                    $openTime = $_POST["open_$day"] ?? '09:00';
                    $closeTime = $_POST["close_$day"] ?? '18:00';
                    
                    $db->query(
                        "UPDATE business_hours SET open_time = ?, close_time = ?, is_closed = ? WHERE day_of_week = ?",
                        [$openTime, $closeTime, $isClosed ? 1 : 0, $day]
                    );
                }
                $message = 'Horários de funcionamento atualizados.';
                break;
                
            case 'add_service':
                $name = trim($_POST['service_name'] ?? '');
                $price = floatval($_POST['service_price'] ?? 0);
                $duration = intval($_POST['service_duration'] ?? 30);
                $description = trim($_POST['service_description'] ?? '');
                
                if ($name && $price > 0) {
                    $db->insert('services', [
                        'name' => $name,
                        'price' => $price,
                        'duration_minutes' => $duration,
                        'description' => $description,
                        'is_active' => 1,
                    ]);
                    $message = 'Serviço adicionado com sucesso.';
                }
                break;
                
            case 'toggle_service':
                $serviceId = (int)($_POST['service_id'] ?? 0);
                $active = (int)($_POST['is_active'] ?? 0);
                if ($serviceId) {
                    $db->update('services', ['is_active' => $active], 'id = ?', [$serviceId]);
                    $message = 'Estado do serviço atualizado.';
                }
                break;
                
            case 'add_staff':
                $name = trim($_POST['staff_name'] ?? '');
                $phone = trim($_POST['staff_phone'] ?? '');
                $email = trim($_POST['staff_email'] ?? '');
                
                if ($name) {
                    $db->insert('staff', [
                        'name' => $name,
                        'phone' => $phone,
                        'email' => $email,
                        'is_active' => 1,
                    ]);
                    $message = 'Profissional adicionado com sucesso.';
                }
                break;
                
            case 'toggle_staff':
                $staffId = (int)($_POST['staff_id'] ?? 0);
                $active = (int)($_POST['is_active'] ?? 0);
                if ($staffId) {
                    $db->update('staff', ['is_active' => $active], 'id = ?', [$staffId]);
                    $message = 'Estado do profissional atualizado.';
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current data
$bookingService = $GLOBALS['bookingService'];
$services = $bookingService->getServices();
$staff = $bookingService->getStaff();
$businessHours = $bookingService->getBusinessHours();

// Index by day for easier access
$hoursByDay = [];
foreach ($businessHours as $hour) {
    $hoursByDay[$hour['day_of_week']] = $hour;
}

// Get site settings from database
$siteSettings = [];
$settingsRows = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'site_%'");
foreach ($settingsRows as $row) {
    $key = str_replace('site_', '', $row['setting_key']);
    $siteSettings[$key] = $row['setting_value'];
}

// Fallback to config
$siteSettings = array_merge([
    'title' => $config['site']['title'] ?? '',
    'phone' => $config['site']['phone'] ?? '',
    'email' => $config['site']['email'] ?? '',
    'address' => $config['site']['address'] ?? '',
], $siteSettings);

$dayNames = [
    'monday' => 'Segunda-feira',
    'tuesday' => 'Terça-feira',
    'wednesday' => 'Quarta-feira',
    'thursday' => 'Quinta-feira',
    'friday' => 'Sexta-feira',
    'saturday' => 'Sábado',
    'sunday' => 'Domingo',
];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Admin</title>
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
                <a href="bookings.php"><span>📅</span> Marcações</a>
                <a href="subscription.php"><span>💳</span> Subscrição</a>
                <a href="support.php"><span>💬</span> Suporte</a>
                <a href="settings.php" class="active"><span>⚙️</span> Configurações</a>
            </nav>
            <div class="sidebar-footer">
                <a href="?logout=1" class="logout-btn">Sair</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="content-header">
                <div>
                    <h1>Configurações</h1>
                    <p>Gerir configurações do negócio</p>
                </div>
            </header>

            <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab-btn active" onclick="showTab('site')">🏢 Site</button>
                <button class="tab-btn" onclick="showTab('hours')">🕐 Horários</button>
                <button class="tab-btn" onclick="showTab('services')">💈 Serviços</button>
                <button class="tab-btn" onclick="showTab('staff')">👤 Equipa</button>
            </div>

            <!-- Site Settings -->
            <div id="tab-site" class="tab-content active">
                <div class="card">
                    <div class="card-header">
                        <h3>Informações do Site</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_site">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Nome do Negócio</label>
                                    <input type="text" name="title" value="<?php echo htmlspecialchars($siteSettings['title']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Telefone</label>
                                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($siteSettings['phone']); ?>">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($siteSettings['email']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Morada</label>
                                    <input type="text" name="address" value="<?php echo htmlspecialchars($siteSettings['address']); ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-accent">Guardar Alterações</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Business Hours -->
            <div id="tab-hours" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3>Horários de Funcionamento</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_business_hours">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Dia</th>
                                        <th>Abertura</th>
                                        <th>Fecho</th>
                                        <th>Fechado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dayNames as $day => $name): ?>
                                    <?php $hours = $hoursByDay[$day] ?? ['open_time' => '09:00', 'close_time' => '18:00', 'is_closed' => 0]; ?>
                                    <tr>
                                        <td><strong><?php echo $name; ?></strong></td>
                                        <td>
                                            <input type="time" name="open_<?php echo $day; ?>" 
                                                   value="<?php echo substr($hours['open_time'], 0, 5); ?>">
                                        </td>
                                        <td>
                                            <input type="time" name="close_<?php echo $day; ?>" 
                                                   value="<?php echo substr($hours['close_time'], 0, 5); ?>">
                                        </td>
                                        <td>
                                            <label class="checkbox">
                                                <input type="checkbox" name="closed_<?php echo $day; ?>" 
                                                       <?php echo $hours['is_closed'] ? 'checked' : ''; ?>>
                                                <span>Fechado</span>
                                            </label>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <button type="submit" class="btn btn-accent">Guardar Horários</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Services -->
            <div id="tab-services" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3>Serviços</h3>
                        <button onclick="document.getElementById('addServiceForm').style.display='block'" class="btn btn-sm btn-accent">
                            + Adicionar
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- Add Service Form -->
                        <form method="POST" id="addServiceForm" style="display: none; margin-bottom: 2rem;">
                            <input type="hidden" name="action" value="add_service">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Nome do Serviço *</label>
                                    <input type="text" name="service_name" required>
                                </div>
                                <div class="form-group">
                                    <label>Preço (€) *</label>
                                    <input type="number" name="service_price" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Duração (minutos)</label>
                                    <input type="number" name="service_duration" value="30" min="5">
                                </div>
                                <div class="form-group">
                                    <label>Descrição</label>
                                    <input type="text" name="service_description">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-accent">Adicionar Serviço</button>
                            <button type="button" onclick="this.form.style.display='none'" class="btn btn-secondary">Cancelar</button>
                        </form>

                        <!-- Services List -->
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Serviço</th>
                                    <th>Preço</th>
                                    <th>Duração</th>
                                    <th>Estado</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($services as $service): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($service['name']); ?></strong>
                                        <?php if (!empty($service['description'])): ?>
                                        <br><small><?php echo htmlspecialchars($service['description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>€<?php echo number_format($service['price'], 2, ',', '.'); ?></td>
                                    <td><?php echo $service['duration_minutes']; ?> min</td>
                                    <td>
                                        <span class="badge badge-<?php echo $service['is_active'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $service['is_active'] ? 'Ativo' : 'Inativo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_service">
                                            <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                            <input type="hidden" name="is_active" value="<?php echo $service['is_active'] ? 0 : 1; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline">
                                                <?php echo $service['is_active'] ? 'Desativar' : 'Ativar'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Staff -->
            <div id="tab-staff" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3>Equipa</h3>
                        <button onclick="document.getElementById('addStaffForm').style.display='block'" class="btn btn-sm btn-accent">
                            + Adicionar
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- Add Staff Form -->
                        <form method="POST" id="addStaffForm" style="display: none; margin-bottom: 2rem;">
                            <input type="hidden" name="action" value="add_staff">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Nome *</label>
                                    <input type="text" name="staff_name" required>
                                </div>
                                <div class="form-group">
                                    <label>Telefone</label>
                                    <input type="tel" name="staff_phone">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="staff_email">
                            </div>
                            <button type="submit" class="btn btn-accent">Adicionar Profissional</button>
                            <button type="button" onclick="this.form.style.display='none'" class="btn btn-secondary">Cancelar</button>
                        </form>

                        <!-- Staff List -->
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Contacto</th>
                                    <th>Estado</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staff as $member): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($member['name']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($member['phone'] ?? '-'); ?>
                                        <?php if (!empty($member['email'])): ?>
                                        <br><small><?php echo htmlspecialchars($member['email']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $member['is_active'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $member['is_active'] ? 'Ativo' : 'Inativo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_staff">
                                            <input type="hidden" name="staff_id" value="<?php echo $member['id']; ?>">
                                            <input type="hidden" name="is_active" value="<?php echo $member['is_active'] ? 0 : 1; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline">
                                                <?php echo $member['is_active'] ? 'Desativar' : 'Ativar'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function showTab(tabId) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById('tab-' + tabId).classList.add('active');
            event.target.classList.add('active');
        }
    </script>

    <style>
        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.5rem;
        }
        
        .tab-btn {
            padding: 0.75rem 1.25rem;
            background: transparent;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            border-radius: var(--radius-sm);
            transition: all 0.2s;
        }
        
        .tab-btn:hover {
            background: var(--bg-secondary);
        }
        
        .tab-btn.active {
            background: var(--accent);
            color: var(--bg-primary);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        
        .checkbox input {
            width: auto;
        }
    </style>
</body>
</html>
