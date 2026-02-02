# Estado da Arte - NunoMiranda.dev Platform

**Data:** 1 de Fevereiro de 2026  
**Versão:** 0.1.0

---

## 📋 Visão Geral

Plataforma multi-tenant para criação e gestão de sites/aplicações para clientes, composta por:

1. **API Node.js** - Backend central de gestão de licenças, tenants e serviços
2. **SiteForge (PHP)** - Solução genérica PHP para deploy em clientes

---

## 🟢 Implementado

### API Node.js (Express + TypeScript)

#### Infraestrutura
- [x] Express server com TypeScript
- [x] MySQL connection pool
- [x] Migrações com Knex.js (8 migrações)
- [x] Logger estruturado (Pino)
- [x] Error handler centralizado
- [x] Rate limiting por tenant
- [x] Validação de requests com Zod
- [x] CORS configurado

#### Sistema de Tenants
- [x] CRUD de tenants (`/admin/tenants`)
- [x] Autenticação por API key
- [x] Metadata e contexto por tenant
- [x] Dashboard de uso por tenant

#### Sistema de Licenças
- [x] Criação de licenças (`POST /api/licenses`)
- [x] Validação de licenças (`GET /api/licenses/:key/validate`)
- [x] Formato de chave: `ntk_[12hex].[48hex]`
- [x] Revogação automática de licenças anteriores ao criar nova
- [x] Status: `active`, `trial`, `suspended`, `expired`, `revoked`
- [x] Período trial de 30 dias
- [x] Módulos configuráveis por licença
- [x] Limites de créditos (AI, email, SMS, WhatsApp, chamadas)

#### Módulos Suportados
- [x] `static_site` - Site estático (AI generated ou manual)
- [x] `bot_widget` - Widget de chatbot
- [x] `bot_whatsapp` - Integração WhatsApp
- [x] `ai_calls` - Chamadas com AI
- [x] `email` - Envio de emails
- [x] `sms` - Envio de SMS
- [x] `shop` - E-commerce

#### Serviços AI
- [x] Chat com OpenAI (`/api/ai/chat`)
- [x] Geração de sites (`/api/ai/generate-site`)
- [x] Geração de FAQs (`/api/ai/generate-faqs`)
- [x] Mensagens de bot (`/api/bot/message`)
- [x] Contagem de tokens e custos

#### Billing/Pagamentos
- [x] Integração EasyPay (test mode)
- [x] Criação de subscrições
- [x] Webhooks de pagamento
- [x] Planos de subscrição

#### Admin UI
- [x] Painel web em `/admin/ui/`
- [x] Gestão de tenants
- [x] Visualização de uso
- [x] Autenticação por secret

### SiteForge (PHP)

#### Setup Wizard
- [x] 8 passos de instalação
- [x] Verificação de requisitos (PHP 8.1+, extensões)
- [x] Validação de chave de licença (formato `ntk_xxx.xxx`)
- [x] Configuração de base de dados MySQL/SQLite
- [x] Informações do negócio
- [x] Seleção de módulos
- [x] Criação de admin
- [x] Geração de config.php
- [x] Lock file de instalação

#### Painel Admin
- [x] Autenticação por PIN
- [x] Tabs: Branding, Bot AI, WhatsApp, Subscrição
- [x] Edição de settings em tempo real
- [x] Session timeout

#### Frontend
- [x] Página principal dinâmica (`index.php`)
- [x] Tema dark/light configurável
- [x] Cores personalizáveis
- [x] Conteúdo gerado por AI (opcional)
- [x] Responsivo

#### Bot Widget
- [x] Widget embeddable (JS) (`public/widget/bot.js`)

#### Serviços
- [x] LicenseService - Comunicação com API
- [x] SettingsStore - Gestão de JSON settings
- [x] Bootstrap com autoload
- [x] Helpers (sanitize, csrf, redirect)

#### Segurança
- [x] CSRF protection
- [x] Headers de segurança (.htaccess)
- [x] Proteção de diretórios sensíveis
- [x] Session management
- [x] Password hashing (bcrypt)

---

## 🟡 Em Progresso

### API Node.js
- [ ] Testes automatizados
- [ ] Documentação OpenAPI/Swagger
- [ ] Logs de auditoria completos

### SiteForge
- [ ] Integração WhatsApp Business API
- [ ] Sistema de bookings/reservas

---

## 🔴 Por Implementar

### API Node.js

#### Funcionalidades Core
- [ ] Reset de password de admin
- [ ] 2FA para admin
- [ ] API rate limiting por endpoint
- [ ] Caching (Redis)
- [ ] Filas de jobs (Bull)
- [ ] Backups automáticos

#### Integrações
- [ ] Twilio para SMS
- [ ] SendGrid/Mailgun para emails
- [ ] WhatsApp Business API
- [ ] Google Analytics reporting
- [ ] Facebook Pixel integration

#### AI Avançado
- [ ] Fine-tuning de modelos por cliente
- [ ] Histórico de conversas persistente
- [ ] Análise de sentimento
- [ ] Sugestões proativas

#### Billing Avançado
- [ ] Múltiplos métodos de pagamento
- [ ] Faturas automáticas
- [ ] Upgrade/downgrade de planos
- [ ] Créditos pré-pagos
- [ ] Alertas de limite de uso

### SiteForge

#### Módulos
- [ ] Sistema de e-commerce completo (shop)
- [ ] Gestão de produtos
- [ ] Carrinho de compras
- [ ] Checkout com pagamento
- [ ] Inventário

#### Bot Widget
- [ ] Interface de chat completa
- [ ] Histórico de conversas
- [ ] Transferência para humano
- [ ] Respostas sugeridas
- [ ] Attachments (imagens, ficheiros)

#### WhatsApp
- [ ] Webhook receiver
- [ ] Templates de mensagem
- [ ] Envio de media
- [ ] Grupos/broadcast

#### Email
- [ ] Templates de email
- [ ] Campanhas de marketing
- [ ] Automações (welcome, abandoned cart)
- [ ] Unsubscribe management

#### Outros
- [ ] Sistema de bookings/agendamentos
- [ ] Calendário de disponibilidade
- [ ] Notificações push
- [ ] Multi-idioma (i18n)
- [ ] SEO tools
- [ ] Analytics dashboard local
- [ ] Export de dados
- [ ] GDPR compliance tools

---

## 📁 Estrutura de Ficheiros

### API Node.js
```
nunomiranda-dev-api/
├── src/
│   ├── index.ts           # Entry point
│   ├── config.ts          # Configuração (.env)
│   ├── db.ts              # MySQL pool
│   ├── logger.ts          # Pino logger
│   ├── errors.ts          # Custom errors
│   ├── responses.ts       # Response helpers
│   ├── middleware/
│   │   ├── adminSecret.ts
│   │   ├── errorHandler.ts
│   │   ├── tenantAuth.ts
│   │   └── tenantRateLimit.ts
│   ├── routes/
│   │   ├── admin.ts
│   │   ├── billing.ts
│   │   ├── client.ts      # Licenças e AI
│   │   ├── dashboard.ts
│   │   ├── requests.ts
│   │   └── tenant.ts
│   ├── services/
│   │   ├── aiService.ts
│   │   ├── dashboardService.ts
│   │   ├── licenseService.ts
│   │   ├── rateLimitService.ts
│   │   ├── requestService.ts
│   │   └── tenantService.ts
│   └── types/
│       └── express.d.ts
├── migrations/
│   ├── 001_bootstrap.js
│   ├── ...
│   └── 008_license_revoked_status.js
├── public/admin/          # Admin UI
├── .env
├── knexfile.js
├── package.json
└── tsconfig.json
```

### SiteForge (PHP)
```
siteforge/
├── config/
│   └── config.example.php
├── database/
│   └── schema.sql
├── modules/
│   ├── bot/
│   ├── whatsapp/
│   ├── email/
│   └── shop/
├── public/
│   ├── index.php          # Página principal
│   ├── admin.php          # Painel admin
│   ├── setup.php          # Wizard instalação
│   ├── .htaccess
│   ├── api/
│   ├── assets/
│   └── widget/
├── src/
│   ├── bootstrap.php
│   ├── config.php
│   ├── helpers.php
│   ├── SettingsStore.php
│   └── Services/
│       └── LicenseService.php
├── storage/
│   ├── settings.json
│   └── logs/
└── README.md
```

---

## 🔑 Endpoints Principais

### Licenças
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/api/licenses` | Criar licença (revoga anteriores) |
| GET | `/api/licenses/:key/validate` | Validar licença |
| GET | `/api/licenses/:key/credits` | Ver créditos |
| GET | `/api/licenses/:key/usage` | Estatísticas de uso |
| PUT | `/api/licenses/:key/modules` | Atualizar módulos |
| GET | `/api/licenses` | Listar todas |

### AI/Bot
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/api/ai/chat` | Chat genérico |
| POST | `/api/ai/generate-site` | Gerar conteúdo site |
| POST | `/api/ai/generate-faqs` | Gerar FAQs |
| POST | `/api/bot/message` | Mensagem para bot |

### Admin
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/admin/tenants` | Listar tenants |
| POST | `/admin/tenants` | Criar tenant |
| GET | `/admin/tenants/:id/usage` | Uso do tenant |
| GET | `/admin/subscription-plans` | Planos disponíveis |

---

## 🔐 Formato da Chave de Licença

```
ntk_[12 caracteres hex].[48 caracteres hex]

Exemplo:
ntk_371b4bb0c3d7.f80a85ceca3be1479c5386f8ec5a2491179437f83a78773

Regex: /^ntk_[a-f0-9]{12}\.[a-f0-9]{48}$/
```

---

## 📊 Base de Dados

### Tabelas Principais
- `tenants` - Clientes/organizações
- `api_keys` - Chaves de API por tenant
- `client_licenses` - Licenças de software
- `license_usage_log` - Log de uso de créditos
- `bot_sessions` - Sessões de chatbot
- `support_tickets` - Tickets de suporte
- `payment_transactions` - Transações de pagamento
- `tenant_rate_limits` - Limites de rate
- `request_logs` - Logs de requests
- `tenant_subscriptions` - Subscrições ativas

---

## 🚀 Como Executar

### API Node.js
```bash
cd nunomiranda-dev-api
npm install
cp .env.example .env  # Configurar variáveis
npm run migrate       # Executar migrações
npm run dev           # Desenvolvimento
npm run build         # Build produção
npm start             # Produção
```

### SiteForge (Cliente)
1. Copiar pasta `siteforge/` para servidor do cliente
2. Configurar permissões: `chmod -R 777 storage/ config/`
3. Aceder a `https://dominio.com/setup.php`
4. Seguir os 8 passos do wizard
5. Remover `setup.php` após instalação

---

## 📝 Notas de Desenvolvimento

### Prioridades Próximas
1. Widget de bot funcional (JS frontend)
2. Testes automatizados para API
3. Documentação Swagger
4. Sistema de bookings básico
5. Templates de email

### Considerações Técnicas
- PHP mínimo: 8.1
- Node.js mínimo: 18
- MySQL: 8.0+
- Suporte SQLite para instalações simples

### Segurança
- Todas as passwords com bcrypt
- API keys com hash SHA-256 + salt
- CSRF em todos os forms PHP
- Rate limiting por tenant e global
- Headers de segurança configurados

---

*Última atualização: 2026-02-01*
