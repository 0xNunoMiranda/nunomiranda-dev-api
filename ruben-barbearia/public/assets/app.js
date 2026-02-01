const state = {
  messagesEl: document.getElementById('bot-messages'),
  form: document.getElementById('bot-form'),
  endpoint: window.__BOT_SETTINGS__?.endpoint ?? 'bot-handler.php',
  assistantName: window.__BOT_SETTINGS__?.assistantName ?? 'Assistente'
};

const createBubble = (text, role) => {
  const div = document.createElement('div');
  div.className = `message ${role}`;
  div.innerHTML = `<p>${text}</p>`;
  return div;
};

const scrollToBottom = () => {
  if (!state.messagesEl) return;
  state.messagesEl.scrollTop = state.messagesEl.scrollHeight;
};

const sendMessage = async (text) => {
  if (!state.messagesEl) return;
  state.messagesEl.appendChild(createBubble(text, 'user'));
  scrollToBottom();

  try {
    const response = await fetch(state.endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text })
    });
    const body = await response.json();
    if (body?.ok && body.reply) {
      state.messagesEl.appendChild(createBubble(body.reply, 'bot'));
    } else {
      state.messagesEl.appendChild(createBubble(`${state.assistantName}: Estou com dificuldades técnicas. Tenta novamente em instantes.`, 'bot'));
    }
  } catch (error) {
    state.messagesEl.appendChild(createBubble(`${state.assistantName}: Não consegui responder agora.`, 'bot'));
  }
  scrollToBottom();
};

if (state.form) {
  state.form.addEventListener('submit', (event) => {
    event.preventDefault();
    const formData = new FormData(state.form);
    const text = formData.get('message')?.toString().trim();
    if (!text) return;
    state.form.reset();
    sendMessage(text);
  });
}
