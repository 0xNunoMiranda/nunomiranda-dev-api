/**
 * Bot Widget - Assistente AI embebido no site
 * 
 * Este widget cria uma janela de chat flutuante que permite
 * aos visitantes interagir com um bot para marcações, suporte e FAQ.
 */

(function() {
  'use strict';

  // Get configuration from script tag
  const script = document.currentScript;
  const config = {
    position: script.dataset.position || 'bottom-right',
    theme: script.dataset.theme || 'dark',
    welcomeMessage: script.dataset.welcome || 'Olá! Como posso ajudar?',
    assistantName: script.dataset.name || 'Assistente',
    apiEndpoint: script.dataset.api || '/api/bot',
  };

  // Styles
  const styles = `
    .bot-widget {
      --bot-accent: #00ffc6;
      --bot-bg: ${config.theme === 'dark' ? '#12121a' : '#ffffff'};
      --bot-text: ${config.theme === 'dark' ? '#f0f0f5' : '#1a1a2e'};
      --bot-muted: ${config.theme === 'dark' ? '#888' : '#666'};
      --bot-border: ${config.theme === 'dark' ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)'};
      
      position: fixed;
      ${config.position.includes('bottom') ? 'bottom: 20px;' : 'top: 20px;'}
      ${config.position.includes('right') ? 'right: 20px;' : 'left: 20px;'}
      z-index: 9999;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .bot-widget-button {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: var(--bot-accent);
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 20px rgba(0, 255, 198, 0.3);
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .bot-widget-button:hover {
      transform: scale(1.05);
      box-shadow: 0 6px 25px rgba(0, 255, 198, 0.4);
    }

    .bot-widget-button svg {
      width: 28px;
      height: 28px;
      fill: #0a0a0f;
    }

    .bot-widget-window {
      position: absolute;
      ${config.position.includes('bottom') ? 'bottom: 70px;' : 'top: 70px;'}
      ${config.position.includes('right') ? 'right: 0;' : 'left: 0;'}
      width: 360px;
      max-width: calc(100vw - 40px);
      height: 500px;
      max-height: calc(100vh - 100px);
      background: var(--bot-bg);
      border: 1px solid var(--bot-border);
      border-radius: 16px;
      display: none;
      flex-direction: column;
      overflow: hidden;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    }

    .bot-widget-window.open {
      display: flex;
      animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .bot-widget-header {
      padding: 1rem;
      background: rgba(0, 255, 198, 0.1);
      border-bottom: 1px solid var(--bot-border);
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .bot-widget-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: var(--bot-accent);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.25rem;
    }

    .bot-widget-info h4 {
      margin: 0;
      font-size: 0.95rem;
      color: var(--bot-text);
    }

    .bot-widget-info p {
      margin: 0;
      font-size: 0.8rem;
      color: var(--bot-muted);
    }

    .bot-widget-close {
      margin-left: auto;
      background: none;
      border: none;
      color: var(--bot-muted);
      cursor: pointer;
      padding: 0.5rem;
      font-size: 1.25rem;
    }

    .bot-widget-messages {
      flex: 1;
      padding: 1rem;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
    }

    .bot-message {
      max-width: 85%;
      padding: 0.75rem 1rem;
      border-radius: 12px;
      font-size: 0.9rem;
      line-height: 1.4;
    }

    .bot-message.bot {
      background: var(--bot-border);
      color: var(--bot-text);
      align-self: flex-start;
      border-bottom-left-radius: 4px;
    }

    .bot-message.user {
      background: var(--bot-accent);
      color: #0a0a0f;
      align-self: flex-end;
      border-bottom-right-radius: 4px;
    }

    .bot-message.typing {
      background: var(--bot-border);
    }

    .bot-message.typing::after {
      content: '...';
      animation: dots 1.5s infinite;
    }

    @keyframes dots {
      0%, 20% { content: '.'; }
      40% { content: '..'; }
      60%, 100% { content: '...'; }
    }

    .bot-quick-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      padding: 0 1rem;
      margin-bottom: 0.5rem;
    }

    .bot-quick-btn {
      padding: 0.5rem 0.875rem;
      background: var(--bot-border);
      border: 1px solid var(--bot-border);
      border-radius: 20px;
      color: var(--bot-text);
      font-size: 0.8rem;
      cursor: pointer;
      transition: all 0.2s;
    }

    .bot-quick-btn:hover {
      background: var(--bot-accent);
      border-color: var(--bot-accent);
      color: #0a0a0f;
    }

    .bot-widget-input {
      display: flex;
      gap: 0.5rem;
      padding: 1rem;
      border-top: 1px solid var(--bot-border);
    }

    .bot-widget-input input {
      flex: 1;
      padding: 0.75rem 1rem;
      background: var(--bot-border);
      border: 1px solid transparent;
      border-radius: 24px;
      color: var(--bot-text);
      font-size: 0.9rem;
    }

    .bot-widget-input input:focus {
      outline: none;
      border-color: var(--bot-accent);
    }

    .bot-widget-input button {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: var(--bot-accent);
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .bot-widget-input button svg {
      width: 18px;
      height: 18px;
      fill: #0a0a0f;
    }
  `;

  // Create widget HTML
  const widgetHTML = `
    <button class="bot-widget-button" aria-label="Abrir chat">
      <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>
    </button>
    <div class="bot-widget-window">
      <div class="bot-widget-header">
        <div class="bot-widget-avatar">🤖</div>
        <div class="bot-widget-info">
          <h4>${config.assistantName}</h4>
          <p>Online agora</p>
        </div>
        <button class="bot-widget-close" aria-label="Fechar">&times;</button>
      </div>
      <div class="bot-widget-messages"></div>
      <div class="bot-quick-actions">
        <button class="bot-quick-btn" data-action="booking">📅 Marcar</button>
        <button class="bot-quick-btn" data-action="hours">🕐 Horários</button>
        <button class="bot-quick-btn" data-action="services">💈 Serviços</button>
        <button class="bot-quick-btn" data-action="support">💬 Suporte</button>
      </div>
      <div class="bot-widget-input">
        <input type="text" placeholder="Escreve a tua mensagem..." />
        <button aria-label="Enviar">
          <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
        </button>
      </div>
    </div>
  `;

  // Session ID
  const sessionId = 'bot_' + Math.random().toString(36).substr(2, 9);

  // Initialize widget
  function init() {
    // Add styles
    const styleEl = document.createElement('style');
    styleEl.textContent = styles;
    document.head.appendChild(styleEl);

    // Create container
    const container = document.getElementById('bot-widget') || document.createElement('div');
    container.id = 'bot-widget';
    container.className = 'bot-widget';
    container.innerHTML = widgetHTML;
    
    if (!document.getElementById('bot-widget')) {
      document.body.appendChild(container);
    }

    // Elements
    const button = container.querySelector('.bot-widget-button');
    const window = container.querySelector('.bot-widget-window');
    const closeBtn = container.querySelector('.bot-widget-close');
    const messagesEl = container.querySelector('.bot-widget-messages');
    const inputEl = container.querySelector('.bot-widget-input input');
    const sendBtn = container.querySelector('.bot-widget-input button');
    const quickBtns = container.querySelectorAll('.bot-quick-btn');

    // State
    let isOpen = false;

    // Toggle window
    function toggle() {
      isOpen = !isOpen;
      window.classList.toggle('open', isOpen);
      if (isOpen && messagesEl.children.length === 0) {
        addMessage(config.welcomeMessage, 'bot');
      }
    }

    // Add message to chat
    function addMessage(text, sender) {
      const msg = document.createElement('div');
      msg.className = `bot-message ${sender}`;
      msg.textContent = text;
      messagesEl.appendChild(msg);
      messagesEl.scrollTop = messagesEl.scrollHeight;
      return msg;
    }

    // Send message
    async function sendMessage(text) {
      if (!text.trim()) return;
      
      addMessage(text, 'user');
      inputEl.value = '';
      
      const typingMsg = addMessage('', 'bot');
      typingMsg.classList.add('typing');

      try {
        const response = await fetch(config.apiEndpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            sessionId,
            message: text,
          }),
        });

        const data = await response.json();
        typingMsg.classList.remove('typing');
        typingMsg.textContent = data.reply || 'Desculpa, não consegui processar o teu pedido.';
      } catch (error) {
        typingMsg.classList.remove('typing');
        typingMsg.textContent = 'Desculpa, estou com dificuldades técnicas. Tenta novamente.';
      }
    }

    // Quick action responses
    const quickResponses = {
      booking: 'Quero fazer uma marcação',
      hours: 'Quais são os horários de funcionamento?',
      services: 'Que serviços têm disponíveis?',
      support: 'Preciso de ajuda',
    };

    // Event listeners
    button.addEventListener('click', toggle);
    closeBtn.addEventListener('click', toggle);
    
    sendBtn.addEventListener('click', () => sendMessage(inputEl.value));
    inputEl.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') sendMessage(inputEl.value);
    });

    quickBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        const action = btn.dataset.action;
        if (quickResponses[action]) {
          sendMessage(quickResponses[action]);
        }
      });
    });
  }

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
