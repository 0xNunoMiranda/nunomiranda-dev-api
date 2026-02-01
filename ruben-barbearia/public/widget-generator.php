<?php
/**
 * Gerador de Código de Embed do Widget
 * 
 * Esta página permite gerar o código de embed personalizado
 * para incluir o bot widget em qualquer site.
 */

require_once __DIR__ . '/../src/bootstrap.php';

// Verificar se está autenticado como admin
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin-ruben-barbeiro.php');
    exit;
}

$config = require __DIR__ . '/../config/config.php';
$licenseKey = $config['license_key'] ?? '';
$apiUrl = rtrim($config['api_url'] ?? 'http://localhost:3000', '/');
$siteUrl = rtrim($config['site_url'] ?? '', '/');
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerador de Widget - Admin</title>
    <style>
        :root {
            --primary: #00ffc6;
            --bg: #12121a;
            --bg-secondary: #1a1a25;
            --text: #f0f0f5;
            --text-muted: #888;
            --border: rgba(255,255,255,0.1);
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
            max-width: 900px;
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
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: var(--text);
            margin-bottom: 8px;
            font-weight: 500;
        }

        .help-text {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        input, select {
            width: 100%;
            padding: 12px 16px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-size: 14px;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .options-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .code-preview {
            background: #0a0a0f;
            border-radius: 12px;
            padding: 20px;
            font-family: 'Fira Code', 'Consolas', monospace;
            font-size: 13px;
            line-height: 1.6;
            overflow-x: auto;
            position: relative;
        }

        .code-preview code {
            color: var(--text-muted);
            white-space: pre-wrap;
            word-break: break-all;
        }

        .code-preview .highlight {
            color: var(--primary);
        }

        .code-preview .string {
            color: #a5d6a7;
        }

        .copy-btn {
            position: absolute;
            top: 12px;
            right: 12px;
            background: var(--primary);
            color: var(--bg);
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.2s;
        }

        .copy-btn:hover {
            transform: scale(1.05);
        }

        .copy-btn.copied {
            background: #4caf50;
        }

        .preview-frame {
            background: #0a0a0f;
            border-radius: 12px;
            height: 400px;
            position: relative;
            overflow: hidden;
        }

        .preview-frame iframe {
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 12px;
        }

        .btn-primary {
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
            transition: all 0.2s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 255, 198, 0.3);
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            color: var(--primary);
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            border: 1px solid var(--primary);
            cursor: pointer;
            transition: all 0.2s;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .tabs {
            display: flex;
            gap: 4px;
            background: var(--bg);
            padding: 4px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .tab {
            flex: 1;
            padding: 10px 16px;
            text-align: center;
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .tab.active {
            background: var(--primary);
            color: var(--bg);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .warning {
            background: rgba(255, 152, 0, 0.1);
            border-left: 3px solid #ff9800;
            padding: 12px 16px;
            border-radius: 0 8px 8px 0;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .success {
            background: rgba(76, 175, 80, 0.1);
            border-left: 3px solid #4caf50;
            padding: 12px 16px;
            border-radius: 0 8px 8px 0;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🤖 Gerador de Widget</h1>
            <a href="/admin-ruben-barbeiro.php" class="back-btn">← Voltar ao Admin</a>
        </div>

        <?php if (empty($licenseKey)): ?>
        <div class="warning">
            <strong>⚠️ Atenção:</strong> Nenhuma chave de licença configurada. 
            <a href="setup.php" style="color: #ff9800;">Configura primeiro no Setup</a>.
        </div>
        <?php else: ?>
        <div class="success">
            <strong>✓ Licença configurada:</strong> <?= htmlspecialchars($licenseKey) ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2>⚙️ Configurações</h2>
            
            <div class="options-row">
                <div class="form-group">
                    <label for="position">Posição</label>
                    <select id="position">
                        <option value="bottom-right">Inferior Direito</option>
                        <option value="bottom-left">Inferior Esquerdo</option>
                        <option value="top-right">Superior Direito</option>
                        <option value="top-left">Superior Esquerdo</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="theme">Tema</label>
                    <select id="theme">
                        <option value="dark">Escuro</option>
                        <option value="light">Claro</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="widgetUrl">URL do Widget</label>
                <input type="text" id="widgetUrl" value="<?= htmlspecialchars($siteUrl) ?>/widget/bot.js" placeholder="https://teu-site.com/widget/bot.js">
                <p class="help-text">URL completo onde o ficheiro bot.js está alojado</p>
            </div>

            <div class="form-group">
                <label for="apiUrl">URL da API</label>
                <input type="text" id="apiUrl" value="<?= htmlspecialchars($apiUrl) ?>" placeholder="https://api.teu-site.com">
                <p class="help-text">URL da API Node.js que processa as mensagens</p>
            </div>
        </div>

        <div class="card">
            <h2>📋 Código de Embed</h2>
            
            <div class="tabs">
                <button class="tab active" data-tab="script">Script Tag</button>
                <button class="tab" data-tab="async">Async Load</button>
                <button class="tab" data-tab="npm">NPM/Import</button>
            </div>

            <div id="script-content" class="tab-content active">
                <div class="code-preview">
                    <button class="copy-btn" onclick="copyCode('script')">Copiar</button>
                    <code id="script-code"></code>
                </div>
                <p class="help-text" style="margin-top: 10px;">Cola este código antes da tag &lt;/body&gt; do teu site.</p>
            </div>

            <div id="async-content" class="tab-content">
                <div class="code-preview">
                    <button class="copy-btn" onclick="copyCode('async')">Copiar</button>
                    <code id="async-code"></code>
                </div>
                <p class="help-text" style="margin-top: 10px;">Carregamento assíncrono para não bloquear o carregamento da página.</p>
            </div>

            <div id="npm-content" class="tab-content">
                <div class="code-preview">
                    <button class="copy-btn" onclick="copyCode('npm')">Copiar</button>
                    <code id="npm-code"></code>
                </div>
                <p class="help-text" style="margin-top: 10px;">Para projetos que usam bundlers (Webpack, Vite, etc.)</p>
            </div>
        </div>

        <div class="card">
            <h2>👁️ Preview</h2>
            <div class="preview-frame">
                <iframe id="preview-iframe" src="about:blank"></iframe>
            </div>
            <div class="actions">
                <button class="btn-primary" onclick="refreshPreview()">🔄 Atualizar Preview</button>
                <a href="widget/preview.html" target="_blank" class="btn-secondary">Abrir em Nova Aba</a>
            </div>
        </div>
    </div>

    <script>
        const licenseKey = '<?= htmlspecialchars($licenseKey) ?>';

        function getSettings() {
            return {
                widgetUrl: document.getElementById('widgetUrl').value,
                apiUrl: document.getElementById('apiUrl').value,
                position: document.getElementById('position').value,
                theme: document.getElementById('theme').value,
            };
        }

        function generateCode() {
            const s = getSettings();
            
            // Script tag version
            const scriptCode = `<script 
    src="${s.widgetUrl}"
    data-key="${licenseKey}"
    data-api="${s.apiUrl}"
    data-position="${s.position}"
    data-theme="${s.theme}">
<\/script>`;

            // Async load version
            const asyncCode = `<script>
(function() {
    var s = document.createElement('script');
    s.src = '${s.widgetUrl}';
    s.setAttribute('data-key', '${licenseKey}');
    s.setAttribute('data-api', '${s.apiUrl}');
    s.setAttribute('data-position', '${s.position}');
    s.setAttribute('data-theme', '${s.theme}');
    s.async = true;
    document.body.appendChild(s);
})();
<\/script>`;

            // NPM/Import version
            const npmCode = `// Install: npm install @nunomiranda/bot-widget

import { initBotWidget } from '@nunomiranda/bot-widget';

initBotWidget({
    licenseKey: '${licenseKey}',
    apiUrl: '${s.apiUrl}',
    position: '${s.position}',
    theme: '${s.theme}',
});`;

            document.getElementById('script-code').textContent = scriptCode;
            document.getElementById('async-code').textContent = asyncCode;
            document.getElementById('npm-code').textContent = npmCode;
        }

        function copyCode(type) {
            const code = document.getElementById(type + '-code').textContent;
            navigator.clipboard.writeText(code).then(() => {
                const btn = event.target;
                btn.textContent = '✓ Copiado!';
                btn.classList.add('copied');
                setTimeout(() => {
                    btn.textContent = 'Copiar';
                    btn.classList.remove('copied');
                }, 2000);
            });
        }

        function refreshPreview() {
            const s = getSettings();
            const iframe = document.getElementById('preview-iframe');
            
            const previewHtml = `
<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            background: ${s.theme === 'dark' ? '#12121a' : '#f5f5f5'};
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: sans-serif;
            color: ${s.theme === 'dark' ? '#888' : '#666'};
        }
    </style>
</head>
<body>
    <p>Clica no botão do chat para testar →</p>
    <script 
        src="${s.widgetUrl}"
        data-key="${licenseKey}"
        data-api="${s.apiUrl}"
        data-position="${s.position}"
        data-theme="${s.theme}">
    <\/script>
</body>
</html>`;
            
            iframe.srcdoc = previewHtml;
        }

        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                tab.classList.add('active');
                document.getElementById(tab.dataset.tab + '-content').classList.add('active');
            });
        });

        // Update on change
        document.querySelectorAll('input, select').forEach(el => {
            el.addEventListener('change', generateCode);
            el.addEventListener('input', generateCode);
        });

        // Initial generation
        generateCode();
        setTimeout(refreshPreview, 500);
    </script>
</body>
</html>
