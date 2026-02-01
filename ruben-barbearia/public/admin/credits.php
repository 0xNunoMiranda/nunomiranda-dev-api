<?php
/**
 * Painel de Créditos e Uso
 * 
 * Mostra estatísticas de uso da licença e créditos restantes.
 */

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Services/LicenseService.php';

use App\Services\LicenseService;

session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin-ruben-barbeiro.php');
    exit;
}

$licenseService = new LicenseService();
$validation = $licenseService->validate();
$credits = $licenseService->getCredits();
$usage = $licenseService->getUsageStats(30);

$hasLicense = $licenseService->hasLicense();
$isValid = $validation['valid'] ?? false;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créditos e Uso - Admin</title>
    <style>
        :root {
            --primary: #00ffc6;
            --bg: #12121a;
            --bg-secondary: #1a1a25;
            --text: #f0f0f5;
            --text-muted: #888;
            --border: rgba(255,255,255,0.1);
            --success: #4caf50;
            --warning: #ff9800;
            --danger: #f44336;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--bg), var(--bg-secondary));
            min-height: 100vh;
            color: var(--text);
            padding: 40px 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        h1 {
            color: var(--primary);
            font-size: 28px;
        }

        .back-btn {
            color: var(--text-muted);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn:hover {
            color: var(--primary);
        }

        .license-status {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .status-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .status-icon.valid {
            background: rgba(76, 175, 80, 0.2);
        }

        .status-icon.invalid {
            background: rgba(244, 67, 54, 0.2);
        }

        .status-info h2 {
            color: var(--text);
            font-size: 18px;
            margin-bottom: 4px;
        }

        .status-info p {
            color: var(--text-muted);
            font-size: 14px;
        }

        .license-key {
            font-family: 'Consolas', monospace;
            background: var(--bg);
            padding: 4px 8px;
            border-radius: 4px;
            color: var(--primary);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
        }

        .stat-card h3 {
            color: var(--text-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .progress-bar {
            height: 8px;
            background: var(--bg);
            border-radius: 4px;
            margin-top: 12px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 4px;
            transition: width 0.3s;
        }

        .progress-fill.warning {
            background: var(--warning);
        }

        .progress-fill.danger {
            background: var(--danger);
        }

        .card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .card h2 {
            color: var(--primary);
            font-size: 18px;
            margin-bottom: 20px;
        }

        .modules-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }

        .module-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: var(--bg);
            border-radius: 8px;
        }

        .module-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .module-icon.active {
            background: rgba(76, 175, 80, 0.2);
        }

        .module-icon.inactive {
            background: rgba(255, 255, 255, 0.05);
        }

        .module-name {
            font-size: 14px;
            color: var(--text);
        }

        .module-status {
            margin-left: auto;
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .module-status.active {
            background: rgba(76, 175, 80, 0.2);
            color: var(--success);
        }

        .module-status.inactive {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-muted);
        }

        .usage-table {
            width: 100%;
            border-collapse: collapse;
        }

        .usage-table th,
        .usage-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .usage-table th {
            color: var(--text-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .usage-table td {
            font-size: 14px;
        }

        .error-box {
            background: rgba(244, 67, 54, 0.1);
            border-left: 3px solid var(--danger);
            padding: 16px;
            border-radius: 0 8px 8px 0;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary);
            color: var(--bg);
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            border: none;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Créditos e Uso</h1>
            <a href="/admin-ruben-barbeiro.php" class="back-btn">← Voltar ao Admin</a>
        </div>

        <?php if (!$hasLicense): ?>
        <div class="error-box">
            <strong>⚠️ Sem Licença</strong>
            <p>Nenhuma chave de licença configurada. <a href="setup.php" style="color: var(--primary);">Configura primeiro no Setup</a>.</p>
        </div>
        <?php else: ?>

        <div class="license-status">
            <div class="status-icon <?= $isValid ? 'valid' : 'invalid' ?>">
                <?= $isValid ? '✓' : '✕' ?>
            </div>
            <div class="status-info">
                <h2>Licença <?= $isValid ? 'Válida' : 'Inválida' ?></h2>
                <p>
                    Chave: <span class="license-key"><?= htmlspecialchars($licenseService->getLicenseKey()) ?></span>
                    <?php if ($isValid && isset($validation['expiresAt'])): ?>
                    | Expira: <?= date('d/m/Y', strtotime($validation['expiresAt'])) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if ($isValid && $credits['success']): 
            $c = $credits['credits'];
        ?>
        <div class="stats-grid">
            <?php 
            $creditTypes = [
                'ai_messages' => ['icon' => '💬', 'label' => 'Mensagens AI'],
                'email' => ['icon' => '📧', 'label' => 'Emails'],
                'sms' => ['icon' => '📱', 'label' => 'SMS'],
                'whatsapp' => ['icon' => '💚', 'label' => 'WhatsApp'],
                'ai_calls' => ['icon' => '🤖', 'label' => 'Chamadas AI'],
            ];
            
            foreach ($creditTypes as $type => $info):
                $used = $c[$type . '_used'] ?? 0;
                $limit = $c[$type . '_limit'] ?? 0;
                $remaining = $c[$type . '_remaining'] ?? 0;
                $percentage = $limit > 0 ? ($used / $limit) * 100 : 0;
                $barClass = $percentage > 90 ? 'danger' : ($percentage > 70 ? 'warning' : '');
            ?>
            <div class="stat-card">
                <h3><?= $info['icon'] ?> <?= $info['label'] ?></h3>
                <div class="stat-value"><?= number_format($remaining) ?></div>
                <div class="stat-label"><?= number_format($used) ?> / <?= number_format($limit) ?> usados</div>
                <div class="progress-bar">
                    <div class="progress-fill <?= $barClass ?>" style="width: <?= min(100, $percentage) ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($isValid && isset($validation['modules'])): ?>
        <div class="card">
            <h2>🧩 Módulos Ativos</h2>
            <div class="modules-list">
                <?php 
                $moduleInfo = [
                    'static_site' => ['icon' => '🌐', 'name' => 'Site Estático'],
                    'bot_widget' => ['icon' => '💬', 'name' => 'Bot Widget'],
                    'bot_whatsapp' => ['icon' => '💚', 'name' => 'Bot WhatsApp'],
                    'ai_calls' => ['icon' => '📞', 'name' => 'AI Calls'],
                    'email' => ['icon' => '📧', 'name' => 'Email'],
                    'sms' => ['icon' => '📱', 'name' => 'SMS'],
                    'shop' => ['icon' => '🛒', 'name' => 'Loja'],
                ];
                
                foreach ($validation['modules'] as $module => $active):
                    $info = $moduleInfo[$module] ?? ['icon' => '📦', 'name' => ucfirst($module)];
                ?>
                <div class="module-item">
                    <div class="module-icon <?= $active ? 'active' : 'inactive' ?>">
                        <?= $info['icon'] ?>
                    </div>
                    <span class="module-name"><?= $info['name'] ?></span>
                    <span class="module-status <?= $active ? 'active' : 'inactive' ?>">
                        <?= $active ? 'Ativo' : 'Inativo' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($usage['success'] && !empty($usage['data'])): ?>
        <div class="card">
            <h2>📈 Uso Recente (30 dias)</h2>
            <table class="usage-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Quantidade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($usage['data'], 0, 20) as $row): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($row['date'])) ?></td>
                        <td><?= htmlspecialchars($row['type']) ?></td>
                        <td><?= number_format($row['count']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</body>
</html>
