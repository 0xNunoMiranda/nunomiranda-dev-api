/**
 * Bot Widget Embeddable
 * 
 * Este ficheiro pode ser incluído em qualquer site externo para
 * adicionar o bot assistente.
 * 
 * Uso: <script src="https://domain.com/widget/bot.js?key=LICENSE_KEY"></script>
 */

(function() {
    'use strict';

    // Get configuration from script tag
    const scriptTag = document.currentScript;
    const licenseKey = scriptTag?.getAttribute('data-key') || new URLSearchParams(scriptTag?.src.split('?')[1]).get('key');
    const apiUrl = scriptTag?.getAttribute('data-api') || '';
    const phpProxy = scriptTag?.getAttribute('data-proxy') || '';  // URL do proxy PHP local
    const position = scriptTag?.getAttribute('data-position') || 'bottom-right';
    const theme = scriptTag?.getAttribute('data-theme') || 'dark';

    // Use PHP proxy if available, otherwise direct to Node.js API
    const botEndpoint = phpProxy || (apiUrl + '/api/bot/message');

    if (!licenseKey && !phpProxy) {
        console.error('Bot Widget: License key or proxy URL is required');
        return;
    }

    // Theme colors
    const themes = {
        dark: {
            primary: '#00ffc6',
            bg: '#12121a',
            bgSecondary: '#1a1a25',
            text: '#f0f0f5',
            textMuted: '#888',
            border: 'rgba(255,255,255,0.1)',
        },
        light: {
            primary: '#0066cc',
            bg: '#ffffff',
            bgSecondary: '#f5f5f5',
            text: '#1a1a1a',
            textMuted: '#666',
            border: 'rgba(0,0,0,0.1)',
        }
    };

    const colors = themes[theme] || themes.dark;

    // Inject styles
    const style = document.createElement('style');
    style.textContent = `
        #bot-widget-container {
            position: fixed;
            ${position.includes('right') ? 'right: 20px;' : 'left: 20px;'}
            ${position.includes('top') ? 'top: 20px;' : 'bottom: 20px;'}
            z-index: 999999;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        #bot-widget-button {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: ${colors.primary};
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        #bot-widget-button:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 25px rgba(0,0,0,0.4);
        }

        #bot-widget-button svg {
            width: 28px;
            height: 28px;
            fill: ${theme === 'dark' ? '#0a0a0f' : '#ffffff'};
        }

        #bot-widget-chat {
            display: none;
            position: absolute;
            ${position.includes('right') ? 'right: 0;' : 'left: 0;'}
            ${position.includes('top') ? 'top: 70px;' : 'bottom: 70px;'}
            width: 380px;
            max-width: calc(100vw - 40px);
            height: 500px;
            max-height: calc(100vh - 100px);
            background: ${colors.bg};
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            overflow: hidden;
            flex-direction: column;
            border: 1px solid ${colors.border};
        }

        #bot-widget-chat.open {
            display: flex;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(${position.includes('top') ? '-10px' : '10px'});
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #bot-widget-header {
            background: ${colors.bgSecondary};
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid ${colors.border};
        }

        #bot-widget-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: ${colors.primary};
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        #bot-widget-header-info {
            flex: 1;
        }

        #bot-widget-header-name {
            font-weight: 600;
            color: ${colors.text};
            font-size: 14px;
        }

        #bot-widget-header-status {
            font-size: 12px;
            color: ${colors.textMuted};
        }

        #bot-widget-close {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            color: ${colors.textMuted};
            font-size: 20px;
            line-height: 1;
        }

        #bot-widget-close:hover {
            color: ${colors.text};
        }

        #bot-widget-messages {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .bot-message {
            max-width: 85%;
            padding: 12px 16px;
            border-radius: 16px;
            font-size: 14px;
            line-height: 1.5;
            animation: fadeIn 0.2s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .bot-message.user {
            align-self: flex-end;
            background: ${colors.primary};
            color: ${theme === 'dark' ? '#0a0a0f' : '#ffffff'};
            border-bottom-right-radius: 4px;
        }

        .bot-message.assistant {
            align-self: flex-start;
            background: ${colors.bgSecondary};
            color: ${colors.text};
            border-bottom-left-radius: 4px;
        }

        #bot-widget-input-container {
            padding: 12px 16px;
            background: ${colors.bgSecondary};
            border-top: 1px solid ${colors.border};
            display: flex;
            gap: 8px;
        }

        #bot-widget-input {
            flex: 1;
            padding: 12px 16px;
            background: ${colors.bg};
            border: 1px solid ${colors.border};
            border-radius: 24px;
            color: ${colors.text};
            font-size: 14px;
            outline: none;
        }

        #bot-widget-input:focus {
            border-color: ${colors.primary};
        }

        #bot-widget-input::placeholder {
            color: ${colors.textMuted};
        }

        #bot-widget-send {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: ${colors.primary};
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.2s;
        }

        #bot-widget-send:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        #bot-widget-send svg {
            width: 20px;
            height: 20px;
            fill: ${theme === 'dark' ? '#0a0a0f' : '#ffffff'};
        }

        .bot-typing {
            display: flex;
            gap: 4px;
            padding: 12px 16px;
            background: ${colors.bgSecondary};
            border-radius: 16px;
            align-self: flex-start;
        }

        .bot-typing span {
            width: 8px;
            height: 8px;
            background: ${colors.textMuted};
            border-radius: 50%;
            animation: typing 1.4s infinite;
        }

        .bot-typing span:nth-child(2) { animation-delay: 0.2s; }
        .bot-typing span:nth-child(3) { animation-delay: 0.4s; }

        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-8px); }
        }

        @media (max-width: 480px) {
            #bot-widget-chat {
                width: calc(100vw - 20px);
                height: calc(100vh - 100px);
                ${position.includes('right') ? 'right: 10px;' : 'left: 10px;'}
            }
        }
    `;
    document.head.appendChild(style);

    // Create widget HTML
    const container = document.createElement('div');
    container.id = 'bot-widget-container';
    container.innerHTML = `
        <button id="bot-widget-button" aria-label="Open chat">
            <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>
        </button>
        <div id="bot-widget-chat">
            <div id="bot-widget-header">
                <div id="bot-widget-avatar">🤖</div>
                <div id="bot-widget-header-info">
                    <div id="bot-widget-header-name">Assistente</div>
                    <div id="bot-widget-header-status">Online</div>
                </div>
                <button id="bot-widget-close" aria-label="Close chat">&times;</button>
            </div>
            <div id="bot-widget-messages"></div>
            <div id="bot-widget-input-container">
                <input type="text" id="bot-widget-input" placeholder="Escreve uma mensagem..." autocomplete="off">
                <button id="bot-widget-send" aria-label="Send message">
                    <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(container);

    // Widget logic
    const widgetButton = document.getElementById('bot-widget-button');
    const widgetChat = document.getElementById('bot-widget-chat');
    const closeButton = document.getElementById('bot-widget-close');
    const messagesContainer = document.getElementById('bot-widget-messages');
    const input = document.getElementById('bot-widget-input');
    const sendButton = document.getElementById('bot-widget-send');

    let isOpen = false;
    let sessionId = 'session_' + Math.random().toString(36).substr(2, 9);
    let isLoading = false;

    function toggleChat() {
        isOpen = !isOpen;
        widgetChat.classList.toggle('open', isOpen);
        if (isOpen && messagesContainer.children.length === 0) {
            addMessage('Olá! 👋 Como posso ajudar?', 'assistant');
        }
    }

    function addMessage(content, role) {
        const msg = document.createElement('div');
        msg.className = `bot-message ${role}`;
        msg.textContent = content;
        messagesContainer.appendChild(msg);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function showTyping() {
        const typing = document.createElement('div');
        typing.className = 'bot-typing';
        typing.id = 'bot-typing-indicator';
        typing.innerHTML = '<span></span><span></span><span></span>';
        messagesContainer.appendChild(typing);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function hideTyping() {
        const typing = document.getElementById('bot-typing-indicator');
        if (typing) typing.remove();
    }

    async function sendMessage() {
        const message = input.value.trim();
        if (!message || isLoading) return;

        addMessage(message, 'user');
        input.value = '';
        isLoading = true;
        sendButton.disabled = true;
        showTyping();

        try {
            const body = phpProxy 
                ? { message, sessionId }  // PHP proxy already has license
                : { licenseKey, sessionId, message };  // Direct to Node.js

            const response = await fetch(botEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });

            const data = await response.json();
            hideTyping();

            if (data.success) {
                addMessage(data.response, 'assistant');
            } else {
                addMessage(data.error || 'Desculpa, ocorreu um erro. Tenta novamente.', 'assistant');
            }
        } catch (error) {
            hideTyping();
            addMessage('Desculpa, não consegui processar a tua mensagem. Tenta novamente.', 'assistant');
        }

        isLoading = false;
        sendButton.disabled = false;
        input.focus();
    }

    // Event listeners
    widgetButton.addEventListener('click', toggleChat);
    closeButton.addEventListener('click', toggleChat);
    sendButton.addEventListener('click', sendMessage);
    input.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });

    // Auto-open after delay (optional)
    // setTimeout(() => { if (!isOpen) toggleChat(); }, 5000);

})();
