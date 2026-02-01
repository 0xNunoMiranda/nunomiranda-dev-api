<?php
/**
 * SiteForge - Página Principal
 */

// Verificar se está instalado
$lockFile = __DIR__ . '/../storage/.installed';
if (!file_exists($lockFile)) {
    header('Location: setup.php');
    exit;
}

$container = require __DIR__ . '/../src/bootstrap.php';
$config = $container['config'];
$settings = $container['settings'];

$siteName = $config['tenant']['name'] ?? $config['site']['title'] ?? 'SiteForge';
$siteDescription = $config['site']['description'] ?? '';
$modules = $config['modules'] ?? [];
$branding = $settings['branding'] ?? [];
$theme = $config['site']['theme'] ?? [];
$primaryColor = $theme['primary_color'] ?? '#00ffc6';
$isDark = ($theme['background'] ?? 'dark') === 'dark';

// Carregar conteúdo gerado por AI se existir
$generatedContent = [];
$contentFile = __DIR__ . '/generated/content.json';
if (file_exists($contentFile)) {
    $generatedContent = json_decode(file_get_contents($contentFile), true) ?? [];
}

$headline = $branding['headline'] ?? $generatedContent['hero']['headline'] ?? $siteName;
$subheadline = $branding['subheadline'] ?? $generatedContent['hero']['subheadline'] ?? $siteDescription;
$ctaText = $branding['ctaText'] ?? $generatedContent['hero']['cta'] ?? 'Saber Mais';
$ctaLink = $branding['ctaLink'] ?? '#contact';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($siteDescription) ?>">
    <title><?= htmlspecialchars($siteName) ?></title>
    <style>
        :root {
            --primary: <?= $primaryColor ?>;
            --primary-dark: <?= adjustColor($primaryColor, -20) ?>;
            --bg: <?= $isDark ? '#0a0a0f' : '#ffffff' ?>;
            --bg-card: <?= $isDark ? '#12121a' : '#f5f5f5' ?>;
            --text: <?= $isDark ? '#f0f0f5' : '#1a1a1a' ?>;
            --text-muted: <?= $isDark ? '#888' : '#666' ?>;
            --border: <?= $isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)' ?>;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header */
        header {
            padding: 20px 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: var(--bg);
            z-index: 100;
            border-bottom: 1px solid var(--border);
        }
        
        header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
        }
        
        nav a {
            color: var(--text-muted);
            text-decoration: none;
            margin-left: 24px;
            font-size: 14px;
            transition: color 0.2s;
        }
        
        nav a:hover { color: var(--primary); }
        
        /* Hero */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 120px 20px 80px;
            background: linear-gradient(135deg, var(--bg), var(--bg-card));
        }
        
        .hero h1 {
            font-size: clamp(36px, 6vw, 64px);
            font-weight: 700;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--text), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero p {
            font-size: clamp(16px, 2vw, 20px);
            color: var(--text-muted);
            max-width: 600px;
            margin: 0 auto 40px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 16px 32px;
            background: var(--primary);
            color: var(--bg);
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        /* Sections */
        section {
            padding: 100px 0;
        }
        
        section h2 {
            font-size: 36px;
            text-align: center;
            margin-bottom: 60px;
            color: var(--primary);
        }
        
        /* Services Grid */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
        }
        
        .service-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 32px;
            transition: transform 0.2s, border-color 0.2s;
        }
        
        .service-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary);
        }
        
        .service-card h3 {
            font-size: 20px;
            margin-bottom: 12px;
        }
        
        .service-card p {
            color: var(--text-muted);
            font-size: 14px;
        }
        
        /* Contact */
        #contact {
            background: var(--bg-card);
        }
        
        .contact-info {
            max-width: 500px;
            margin: 0 auto;
            text-align: center;
        }
        
        .contact-info p {
            margin-bottom: 12px;
            color: var(--text-muted);
        }
        
        .contact-info a {
            color: var(--primary);
            text-decoration: none;
        }
        
        /* Footer */
        footer {
            padding: 40px 0;
            text-align: center;
            border-top: 1px solid var(--border);
        }
        
        footer p {
            color: var(--text-muted);
            font-size: 14px;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <a href="/" class="logo"><?= htmlspecialchars($siteName) ?></a>
            <nav>
                <a href="#services">Serviços</a>
                <a href="#contact">Contacto</a>
            </nav>
        </div>
    </header>
    
    <main>
        <section class="hero">
            <div>
                <h1><?= htmlspecialchars($headline) ?></h1>
                <p><?= htmlspecialchars($subheadline) ?></p>
                <a href="<?= htmlspecialchars($ctaLink) ?>" class="btn"><?= htmlspecialchars($ctaText) ?></a>
            </div>
        </section>
        
        <?php if (!empty($generatedContent['services']) || !empty($config['tenant']['services'])): ?>
        <section id="services">
            <div class="container">
                <h2>Serviços</h2>
                <div class="services-grid">
                    <?php 
                    $services = $generatedContent['services'] ?? [];
                    foreach ($services as $service): 
                    ?>
                    <div class="service-card">
                        <h3><?= htmlspecialchars($service['name'] ?? '') ?></h3>
                        <p><?= htmlspecialchars($service['description'] ?? '') ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
        
        <section id="contact">
            <div class="container">
                <h2>Contacto</h2>
                <div class="contact-info">
                    <?php if (!empty($config['tenant']['email'])): ?>
                    <p>📧 <a href="mailto:<?= htmlspecialchars($config['tenant']['email']) ?>"><?= htmlspecialchars($config['tenant']['email']) ?></a></p>
                    <?php endif; ?>
                    <?php if (!empty($config['tenant']['phone'])): ?>
                    <p>📞 <a href="tel:<?= htmlspecialchars($config['tenant']['phone']) ?>"><?= htmlspecialchars($config['tenant']['phone']) ?></a></p>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>
    
    <footer>
        <div class="container">
            <p>© <?= date('Y') ?> <?= htmlspecialchars($siteName) ?> · Powered by SiteForge</p>
        </div>
    </footer>
    
    <?php if (!empty($modules['bot_widget']['enabled'])): ?>
    <!-- Bot Widget -->
    <script 
        src="widget/bot.js"
        data-proxy="api/bot.php"
        data-position="<?= htmlspecialchars($modules['bot_widget']['position'] ?? 'bottom-right') ?>"
        data-theme="<?= $isDark ? 'dark' : 'light' ?>">
    </script>
    <?php endif; ?>
</body>
</html>

<?php
function adjustColor(string $hex, int $amount): string {
    $hex = ltrim($hex, '#');
    $r = max(0, min(255, hexdec(substr($hex, 0, 2)) + $amount));
    $g = max(0, min(255, hexdec(substr($hex, 2, 2)) + $amount));
    $b = max(0, min(255, hexdec(substr($hex, 4, 2)) + $amount));
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}
