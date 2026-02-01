<?php
/**
 * Public Site Entry Point
 */

// Verificar se precisa de setup inicial
$lockFile = __DIR__ . '/../storage/.installed';
if (!file_exists($lockFile)) {
    header('Location: setup.php');
    exit;
}

// Load configuration and bootstrap
require_once __DIR__ . '/../src/bootstrap.php';

// Get services from global scope
$bookingService = $GLOBALS['bookingService'] ?? null;

// Fallback branding for backwards compatibility
$branding = $settings['branding'] ?? [];
$ai = $settings['ai_bot'] ?? [];
$whatsapp = $settings['whatsapp'] ?? [];

$headline = $branding['headline'] ?? ($config['site']['title'] ?? 'Barbearia') . ' — precisão artesanal';
$subheadline = $branding['subheadline'] ?? 'Agenda aberta 7 dias por semana. O nosso bot confirma o melhor horário em segundos.';
$ctaText = $branding['ctaText'] ?? 'Marcar corte agora';
$ctaLink = $branding['ctaLink'] ?? '#marcacoes';
$assistantName = $ai['assistantName'] ?? ($config['modules']['bot_widget']['name'] ?? 'Assistente');
$whatsappNumber = $whatsapp['number'] ?? ($config['site']['phone'] ?? '+351 910 000 000');
?>
<!DOCTYPE html>
<html lang="pt-PT">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= sanitize($config['tenant']['name']); ?> · Agenda inteligente</title>
    <link rel="stylesheet" href="assets/styles.css" />
  </head>
  <body>
    <div class="stars" aria-hidden="true"></div>
    <header class="hero">
      <div class="hero-content">
        <p class="eyebrow"><?= sanitize($config['tenant']['name']); ?></p>
        <h1><?= sanitize($headline); ?></h1>
        <p class="lede"><?= sanitize($subheadline); ?></p>
        <div class="cta-group">
          <a class="btn primary" href="<?= sanitize($ctaLink); ?>"><?= sanitize($ctaText); ?></a>
          <a class="btn ghost" href="tel:<?= sanitize(format_phone($whatsappNumber)); ?>">WhatsApp · <?= sanitize($whatsappNumber); ?></a>
        </div>
        <p class="microcopy">Bot AI disponível 24/7 · confirmações manuais feitas pelo Ruben</p>
      </div>
      <div class="hero-visual" role="img" aria-label="Ilustração de tesoura moderna"></div>
    </header>

    <main>
      <section class="panel" id="marcacoes">
        <div class="panel-header">
          <div>
            <p class="eyebrow">Aditivos ativos</p>
            <h2>Reserva inteligente</h2>
          </div>
          <a class="link" href="<?= sanitize($config['admin']['slug_path']); ?>">Painel do cliente</a>
        </div>
        <div class="additives-grid">
          <article>
            <h3>Bot AI no site</h3>
            <p>Conversa natural, recolhe pedidos e sincroniza com a API central.</p>
            <ul>
              <li>Script leve em JavaScript</li>
              <li>Hand-off automático para o staff</li>
              <li>Treinado com cardápio Ruben</li>
            </ul>
          </article>
          <article>
            <h3>WhatsApp turbo</h3>
            <p>Fluxos pré-configurados, mensagens interativas e resposta humana apenas quando necessário.</p>
            <ul>
              <li>CTA dedicado para clientes VIP</li>
              <li>Envio de lembretes</li>
              <li>Integração com os eventos da API</li>
            </ul>
          </article>
          <article>
            <h3>Dashboard central</h3>
            <p>Visão unificada do consumo de aditivos e dos pedidos pendentes.</p>
            <ul>
              <li>KPIs em tempo real</li>
              <li>Logs de eventos do bot</li>
              <li>Rate limiter por tenant</li>
            </ul>
          </article>
        </div>
      </section>

      <section class="panel bot-section">
        <div class="panel-header">
          <div>
            <p class="eyebrow">Conversa instantânea</p>
            <h2><?= sanitize($assistantName); ?> responde-te já</h2>
          </div>
        </div>
        <div class="bot-wrapper">
          <div class="bot-screen" data-gradient="<?= sanitize($config['ui']['bot_gradient']); ?>">
            <div class="bot-messages" id="bot-messages">
              <div class="message bot">
                <p><?= sanitize($ai['welcomeMessage'] ?? 'Olá! Fala comigo para reservar o teu próximo corte.'); ?></p>
              </div>
            </div>
            <form id="bot-form" class="bot-form" autocomplete="off">
              <input type="text" name="message" placeholder="Escreve a tua pergunta" required />
              <button type="submit">Enviar</button>
            </form>
          </div>
          <div class="bot-info">
            <p><strong>Disponível 24/7:</strong> o bot cria pedidos via API multi-tenant e envia eventos para o dashboard.</p>
            <p><strong>Canal preferido:</strong> <?= sanitize($ai['preferredChannel'] ?? 'site'); ?></p>
            <p><strong>WhatsApp:</strong> <?= sanitize($whatsappNumber); ?></p>
          </div>
        </div>
      </section>
    </main>

    <footer>
      <p>© <?= date('Y'); ?> <?= sanitize($config['tenant']['name']); ?> · Tecnologia multi-tenant by Miranda Dev</p>
    </footer>

    <script>
      window.__BOT_SETTINGS__ = {
        assistantName: <?= json_encode($assistantName, JSON_UNESCAPED_UNICODE); ?>,
        endpoint: 'bot-handler.php'
      };
    </script>
    <script src="assets/app.js" type="module"></script>
  </body>
</html>
