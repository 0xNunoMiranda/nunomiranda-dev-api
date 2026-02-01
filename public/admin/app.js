const state = {
  secret: localStorage.getItem('adminSecret') || '',
  tenants: [],
  selectedTenantId: null,
  usage: null,
  plans: [],
};

const els = {
  secretInput: document.getElementById('admin-secret'),
  secretForm: document.getElementById('secret-form'),
  refreshButton: document.getElementById('refresh-tenants'),
  tenantList: document.getElementById('tenant-list'),
  createTenantForm: document.getElementById('create-tenant-form'),
  usageEmpty: document.getElementById('usage-empty'),
  usageContent: document.getElementById('usage-content'),
  totalRequests: document.getElementById('total-requests'),
  requests24h: document.getElementById('requests-24h'),
  requests7d: document.getElementById('requests-7d'),
  windowsToday: document.getElementById('windows-today'),
  requestsToday: document.getElementById('requests-today'),
  tenantStatus: document.getElementById('tenant-status'),
  rateReset: document.getElementById('rate-reset'),
  statusBreakdown: document.getElementById('status-breakdown'),
  rateWindows: document.getElementById('rate-windows'),
  toggleStatus: document.getElementById('toggle-status'),
  createKeyForm: document.getElementById('create-key-form'),
  keyResult: document.getElementById('api-key-result'),
  toast: document.getElementById('toast'),
  // Plans
  plansList: document.getElementById('plans-list'),
  createPlanForm: document.getElementById('create-plan-form'),
  refreshPlans: document.getElementById('refresh-plans'),
};

let toastTimeout;

const statusLabels = {
  active: 'Ativo',
  suspended: 'Suspenso',
  new: 'Novo',
  pending: 'Pendente',
  confirmed: 'Confirmado',
  done: 'Concluído',
  cancelled: 'Cancelado',
};

const ensureSecret = () => {
  if (!state.secret) {
    throw new Error('Define primeiro o segredo administrativo.');
  }
};

const showToast = (message, tone = 'info') => {
  if (!els.toast) return;
  els.toast.textContent = message;
  els.toast.setAttribute('data-tone', tone);
  els.toast.setAttribute('visible', '');
  clearTimeout(toastTimeout);
  toastTimeout = setTimeout(() => {
    els.toast.removeAttribute('visible');
  }, 3200);
};

const apiFetch = async (path, options = {}) => {
  ensureSecret();
  const headers = {
    'Content-Type': 'application/json',
    'x-admin-secret': state.secret,
    ...(options.headers || {}),
  };
  const response = await fetch(path, { ...options, headers });
  if (!response.ok) {
    let message = 'Pedido falhou.';
    try {
      const body = await response.json();
      message = body?.error?.message || message;
    } catch (error) {
      // ignore
    }
    throw new Error(message);
  }
  return response.json();
};

const findTenantById = (tenantId) => state.tenants.find((tenant) => tenant.id === tenantId);

const setSelectedTenant = (tenantId) => {
  state.selectedTenantId = tenantId;
  state.usage = null;
  renderTenantList();
  renderUsage();
};

const loadTenants = async () => {
  if (!state.secret) {
    showToast('Introduz o segredo para carregar clientes.', 'warn');
    return;
  }
  try {
    const { data } = await apiFetch('/admin/tenants');
    state.tenants = data?.tenants || [];
    renderTenantList();
    if (state.selectedTenantId) {
      const stillExists = findTenantById(state.selectedTenantId);
      if (!stillExists) {
        setSelectedTenant(null);
      }
    }
  } catch (error) {
    showToast(error.message, 'error');
  }
};

const loadUsage = async (tenantId) => {
  if (!tenantId) return;
  try {
    const { data } = await apiFetch(`/admin/tenants/${tenantId}/usage`);
    state.usage = data?.usage || null;
    renderUsage();
  } catch (error) {
    showToast(error.message, 'error');
  }
};

const renderTenantCard = (tenant) => {
  const container = document.createElement('article');
  container.className = 'tenant-card';
  container.setAttribute('role', 'listitem');
  container.dataset.id = tenant.id;
  if (tenant.id === state.selectedTenantId) {
    container.setAttribute('selected', '');
  }

  const badge = document.createElement('span');
  badge.className = `badge ${tenant.status}`;
  badge.textContent = statusLabels[tenant.status] || tenant.status;

  const title = document.createElement('h3');
  title.textContent = tenant.name;

  const slug = document.createElement('p');
  slug.className = 'muted';
  slug.textContent = tenant.slug;

  const meta = document.createElement('small');
  const created = tenant.createdAt ? new Date(tenant.createdAt).toLocaleDateString('pt-PT') : '—';
  meta.textContent = `Criado em ${created}`;

  const selectBtn = document.createElement('button');
  selectBtn.type = 'button';
  selectBtn.textContent = 'Ver consumo';
  selectBtn.addEventListener('click', () => {
    setSelectedTenant(tenant.id);
    loadUsage(tenant.id);
  });

  container.appendChild(badge);
  container.appendChild(title);
  container.appendChild(slug);
  container.appendChild(meta);
  container.appendChild(selectBtn);

  return container;
};

const renderTenantList = () => {
  if (!els.tenantList) return;
  els.tenantList.innerHTML = '';
  if (!state.tenants.length) {
    const empty = document.createElement('p');
    empty.className = 'muted';
    empty.textContent = 'Ainda sem clientes. Cria o primeiro acima.';
    els.tenantList.appendChild(empty);
    updateStatusButton();
    return;
  }
  state.tenants.forEach((tenant) => {
    els.tenantList.appendChild(renderTenantCard(tenant));
  });
  updateStatusButton();
};

const formatStatusList = (breakdown = []) => {
  if (!breakdown.length) {
    const li = document.createElement('li');
    li.textContent = 'Sem pedidos registados.';
    return [li];
  }
  return breakdown.map((item) => {
    const li = document.createElement('li');
    const status = statusLabels[item.status] || item.status;
    li.innerHTML = `<span>${status}</span><strong>${item.count}</strong>`;
    return li;
  });
};

const renderRateWindows = (windows = []) => {
  const fragment = document.createDocumentFragment();
  const header = document.createElement('div');
  header.className = 'table-row header';
  header.innerHTML = '<span>Janela</span><span>Segundos</span><span>Pedidos</span>';
  fragment.appendChild(header);

  if (!windows.length) {
    const row = document.createElement('div');
    row.className = 'table-row';
    row.style.gridTemplateColumns = '1fr';
    row.textContent = 'Sem consumo registado.';
    fragment.appendChild(row);
    return fragment;
  }

  windows.forEach((window) => {
    const row = document.createElement('div');
    row.className = 'table-row';
    const start = new Date(window.windowStart).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
    row.innerHTML = `<span>${start}</span><span>${window.windowSeconds}s</span><span>${window.requestCount}</span>`;
    fragment.appendChild(row);
  });

  return fragment;
};

const updateStatusButton = () => {
  if (!els.toggleStatus) return;
  const tenant = findTenantById(state.selectedTenantId);
  if (!tenant) {
    els.toggleStatus.disabled = true;
    els.toggleStatus.textContent = 'Alterar estado';
    return;
  }
  els.toggleStatus.disabled = false;
  els.toggleStatus.textContent = tenant.status === 'active' ? 'Suspender cliente' : 'Reativar cliente';
};

const renderUsage = () => {
  const tenant = findTenantById(state.selectedTenantId);
  if (!tenant || !state.usage) {
    els.usageEmpty.hidden = false;
    els.usageContent.hidden = true;
    updateStatusButton();
    return;
  }

  els.usageEmpty.hidden = true;
  els.usageContent.hidden = false;

  const { totals, rateLimiter, statusBreakdown } = state.usage;
  els.totalRequests.textContent = totals.total.toLocaleString('pt-PT');
  els.requests24h.textContent = totals.last24h.toLocaleString('pt-PT');
  els.requests7d.textContent = totals.last7d.toLocaleString('pt-PT');
  els.windowsToday.textContent = rateLimiter.today.windows.toLocaleString('pt-PT');
  els.requestsToday.textContent = rateLimiter.today.requests.toLocaleString('pt-PT');
  els.tenantStatus.textContent = statusLabels[tenant.status] || tenant.status;
  els.tenantStatus.className = `stat status-pill badge ${tenant.status}`;

  if (rateLimiter.latestResetAt) {
    const date = new Date(rateLimiter.latestResetAt * 1000);
    els.rateReset.textContent = date.toLocaleTimeString('pt-PT');
  } else {
    els.rateReset.textContent = '—';
  }

  els.statusBreakdown.innerHTML = '';
  formatStatusList(statusBreakdown).forEach((item) => els.statusBreakdown.appendChild(item));

  els.rateWindows.innerHTML = '';
  els.rateWindows.appendChild(renderRateWindows(rateLimiter.windows));

  updateStatusButton();
};

els.secretInput.value = state.secret;

els.secretForm.addEventListener('submit', (event) => {
  event.preventDefault();
  const value = els.secretInput.value.trim();
  state.secret = value;
  localStorage.setItem('adminSecret', value);
  showToast('Segredo guardado.');
  loadTenants();
});

els.refreshButton.addEventListener('click', () => {
  loadTenants();
});

els.createTenantForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const formData = new FormData(event.currentTarget);
  const payload = {
    name: formData.get('name')?.toString().trim(),
    slug: formData.get('slug')?.toString().trim(),
    defaultContext: formData.get('defaultContext')?.toString().trim() || undefined,
    metadata: undefined,
  };

  const metadataRaw = formData.get('metadata')?.toString().trim();
  if (metadataRaw) {
    try {
      payload.metadata = JSON.parse(metadataRaw);
    } catch (error) {
      showToast('Metadata inválida. Usa JSON válido.', 'error');
      return;
    }
  }

  try {
    const { data } = await apiFetch('/admin/tenants', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
    if (data?.tenant) {
      state.tenants = [data.tenant, ...state.tenants];
      renderTenantList();
      showToast('Cliente criado com sucesso.');
      event.currentTarget.reset();
    }
  } catch (error) {
    showToast(error.message, 'error');
  }
});

els.toggleStatus.addEventListener('click', async () => {
  const tenant = findTenantById(state.selectedTenantId);
  if (!tenant) return;
  const nextStatus = tenant.status === 'active' ? 'suspended' : 'active';
  try {
    const { data } = await apiFetch(`/admin/tenants/${tenant.id}/status`, {
      method: 'PATCH',
      body: JSON.stringify({ status: nextStatus }),
    });
    if (data?.tenant) {
      state.tenants = state.tenants.map((t) => (t.id === tenant.id ? data.tenant : t));
      renderTenantList();
      renderUsage();
      showToast(`Cliente agora ${statusLabels[nextStatus] || nextStatus}.`);
    }
  } catch (error) {
    showToast(error.message, 'error');
  }
});

els.createKeyForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const tenant = findTenantById(state.selectedTenantId);
  if (!tenant) {
    showToast('Seleciona um cliente primeiro.', 'warn');
    return;
  }
  const formData = new FormData(event.currentTarget);
  const scopesRaw = formData.get('scopes')?.toString().trim();
  const scopes = scopesRaw
    ? scopesRaw.split(',').map((scope) => scope.trim()).filter(Boolean)
    : undefined;

  try {
    const { data } = await apiFetch(`/admin/tenants/${tenant.id}/keys`, {
      method: 'POST',
      body: JSON.stringify({
        label: formData.get('label')?.toString().trim() || undefined,
        scopes,
      }),
    });
    if (data?.apiKey) {
      els.keyResult.hidden = false;
      els.keyResult.textContent = `Nova chave: ${data.apiKey}`;
      showToast('Chave gerada. Guarda-a em segurança.');
      event.currentTarget.reset();
    }
  } catch (error) {
    showToast(error.message, 'error');
  }
});

// ─────────────────────────────────────────────────────────────────────────────
// SUBSCRIPTION PLANS
// ─────────────────────────────────────────────────────────────────────────────

const loadPlans = async () => {
  if (!state.secret) return;
  try {
    const { data } = await apiFetch('/admin/subscription-plans');
    state.plans = data?.plans || [];
    renderPlans();
  } catch (error) {
    showToast(error.message, 'error');
  }
};

const formatPrice = (cents, currency = 'EUR') => {
  return (cents / 100).toLocaleString('pt-PT', { style: 'currency', currency });
};

const renderPlanCard = (plan) => {
  const card = document.createElement('article');
  card.className = 'plan-card';
  card.dataset.id = plan.id;
  if (!plan.isActive) card.classList.add('inactive');

  const badge = document.createElement('span');
  badge.className = `badge ${plan.isActive ? 'active' : 'suspended'}`;
  badge.textContent = plan.isActive ? 'Ativo' : 'Inativo';

  const title = document.createElement('h3');
  title.textContent = plan.name;

  const price = document.createElement('p');
  price.className = 'plan-price';
  price.innerHTML = `<strong>${formatPrice(plan.priceCents, plan.currency)}</strong> / ${plan.billingPeriod === 'monthly' ? 'mês' : 'ano'}`;

  const details = document.createElement('div');
  details.className = 'plan-details';
  
  if (plan.description) {
    const desc = document.createElement('p');
    desc.className = 'muted';
    desc.textContent = plan.description;
    details.appendChild(desc);
  }

  if (plan.trialDays > 0) {
    const trial = document.createElement('small');
    trial.textContent = `${plan.trialDays} dias de trial`;
    details.appendChild(trial);
  }

  if (plan.features && plan.features.length > 0) {
    const featuresList = document.createElement('ul');
    featuresList.className = 'plan-features';
    plan.features.forEach((f) => {
      const li = document.createElement('li');
      li.textContent = f;
      featuresList.appendChild(li);
    });
    details.appendChild(featuresList);
  }

  const actions = document.createElement('div');
  actions.className = 'plan-actions';

  const toggleBtn = document.createElement('button');
  toggleBtn.type = 'button';
  toggleBtn.textContent = plan.isActive ? 'Desativar' : 'Ativar';
  toggleBtn.addEventListener('click', () => togglePlanStatus(plan));

  const deleteBtn = document.createElement('button');
  deleteBtn.type = 'button';
  deleteBtn.className = 'danger';
  deleteBtn.textContent = 'Arquivar';
  deleteBtn.addEventListener('click', () => archivePlan(plan));

  actions.appendChild(toggleBtn);
  actions.appendChild(deleteBtn);

  card.appendChild(badge);
  card.appendChild(title);
  card.appendChild(price);
  card.appendChild(details);
  card.appendChild(actions);

  return card;
};

const renderPlans = () => {
  if (!els.plansList) return;
  els.plansList.innerHTML = '';
  
  if (state.plans.length === 0) {
    els.plansList.innerHTML = '<p class="muted">Nenhum plano criado ainda.</p>';
    return;
  }

  state.plans.forEach((plan) => {
    els.plansList.appendChild(renderPlanCard(plan));
  });
};

const togglePlanStatus = async (plan) => {
  try {
    const { data } = await apiFetch(`/admin/subscription-plans/${plan.id}`, {
      method: 'PATCH',
      body: JSON.stringify({ isActive: !plan.isActive }),
    });
    if (data?.plan) {
      state.plans = state.plans.map((p) => (p.id === plan.id ? data.plan : p));
      renderPlans();
      showToast(`Plano ${data.plan.isActive ? 'ativado' : 'desativado'}.`);
    }
  } catch (error) {
    showToast(error.message, 'error');
  }
};

const archivePlan = async (plan) => {
  if (!confirm(`Tens a certeza que queres arquivar o plano "${plan.name}"?`)) return;
  try {
    await apiFetch(`/admin/subscription-plans/${plan.id}`, { method: 'DELETE' });
    state.plans = state.plans.filter((p) => p.id !== plan.id);
    renderPlans();
    showToast('Plano arquivado.');
  } catch (error) {
    showToast(error.message, 'error');
  }
};

if (els.createPlanForm) {
  els.createPlanForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    
    const featuresRaw = formData.get('features')?.toString().trim();
    const features = featuresRaw
      ? featuresRaw.split('\n').map((f) => f.trim()).filter(Boolean)
      : [];

    const payload = {
      name: formData.get('name')?.toString().trim(),
      priceCents: parseInt(formData.get('priceCents')?.toString() || '0', 10),
      billingPeriod: formData.get('billingPeriod')?.toString() || 'monthly',
      trialDays: parseInt(formData.get('trialDays')?.toString() || '0', 10),
      description: formData.get('description')?.toString().trim() || undefined,
      features: features.length > 0 ? features : undefined,
    };

    try {
      const { data } = await apiFetch('/admin/subscription-plans', {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      if (data?.plan) {
        state.plans.push(data.plan);
        renderPlans();
        showToast('Plano criado com sucesso!');
        event.currentTarget.reset();
      }
    } catch (error) {
      showToast(error.message, 'error');
    }
  });
}

if (els.refreshPlans) {
  els.refreshPlans.addEventListener('click', loadPlans);
}

if (state.secret) {
  loadTenants();
  loadPlans();
}

window.addEventListener('focus', () => {
  if (state.secret) {
    loadTenants();
    loadPlans();
  }
});
