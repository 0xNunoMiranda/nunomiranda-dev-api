# Ruben Barbearia - Sistema Completo de Gestão

Sistema completo de gestão para barbearias, integrado com a API Node.js de subscrições e pagamentos.

## 📁 Estrutura do Projeto

```
ruben-barbearia/
├── config/
│   └── config.php              # Configurações centrais
├── database/
│   └── schema.sql              # Schema MySQL local
├── modules/
│   ├── bot-widget/             # Bot assistente (widget)
│   │   ├── widget.js           # JavaScript do widget
│   │   └── handler.php         # API do bot
│   ├── bot-whatsapp/           # Integração WhatsApp (futuro)
│   └── shop/                   # Loja WooCommerce (futuro)
├── public/
│   ├── index.php               # Landing page pública
│   ├── site.php                # Template do site
│   ├── .htaccess               # Routing Apache
│   ├── admin/
│   │   ├── login.php           # Login admin (PIN)
│   │   ├── index.php           # Dashboard
│   │   ├── bookings.php        # Gestão de marcações
│   │   ├── subscription.php    # Gestão de subscrição
│   │   ├── support.php         # Tickets de suporte
│   │   ├── settings.php        # Configurações
│   │   └── assets/
│   │       └── admin.css       # Estilos admin
│   ├── api/
│   │   └── booking.php         # API local de marcações
│   └── assets/
│       ├── css/
│       │   └── styles.css      # Estilos do site
│       ├── js/
│       │   └── app.js          # JavaScript do site
│       └── images/             # Imagens
├── src/
│   ├── bootstrap.php           # Inicialização da aplicação
│   ├── Database.php            # Wrapper PDO
│   ├── Auth.php                # Autenticação admin
│   └── Services/
│       ├── SubscriptionService.php  # API de subscrições
│       ├── SupportService.php       # Tickets de suporte
│       └── BookingService.php       # Marcações
└── storage/
    ├── .installed              # Ficheiro de lock (criado após setup)
    ├── settings.json           # Configurações dinâmicas
    └── logs/                   # Logs da aplicação
```

## 🚀 Instalação

### Opção 1: Setup Automático (Recomendado)

1. Acede ao site: `http://localhost/ruben-barbearia/public/`
2. Serás redirecionado para o **Assistente de Setup**
3. Segue os 6 passos:
   - ✅ Verificação de requisitos
   - ✅ Configuração da base de dados (criada automaticamente)
   - ✅ Informações do negócio
   - ✅ PIN de administração
   - ✅ Integração com API (opcional)
   - ✅ Confirmação e finalização

### Opção 2: Instalação Manual

#### 1. Base de Dados

Cria a base de dados MySQL e importa o schema:

```bash
mysql -u root -p
CREATE DATABASE ruben_barbearia;
USE ruben_barbearia;
SOURCE database/schema.sql;
```

#### 2. Configuração

Edita o ficheiro `config/config.php`:

```php
return [
    'tenant' => [
        'id' => 1,
        'slug' => 'ruben-barbearia',
        'name' => 'O Nome do Teu Negócio',
    ],
    'api' => [
        'base_url' => 'http://localhost:3000',
        'api_key' => 'a-tua-api-key',
    ],
    'database' => [
        'host' => 'localhost',
        'name' => 'ruben_barbearia',
        'user' => 'root',
        'pass' => '',
    ],
    'admin' => [
        'pin' => '1234', // Mudar para um PIN seguro!
    ],
    // ...
];
```

#### 3. Marcar como Instalado

Cria o ficheiro de lock para saltar o setup:

```bash
echo "manual" > storage/.installed
```

#### 4. Permissões

```bash
chmod 755 storage/
chmod 644 storage/settings.json
```

## 🎯 Funcionalidades

### Site Público
- Landing page moderna e responsiva
- Lista de serviços e preços
- Formulário de marcações online
- Galeria de trabalhos
- Informações de contacto
- Bot assistente (widget de chat)

### Painel Admin
- **Dashboard**: Estatísticas e visão geral
- **Marcações**: Criar, ver e gerir marcações
- **Subscrição**: Ver e gerir plano ativo
- **Suporte**: Criar e responder tickets
- **Configurações**: Site, horários, serviços, equipa

### Módulos
- **Bot Widget**: Chat assistente embebido no site
- **Bot WhatsApp**: Integração com WhatsApp (em desenvolvimento)
- **Loja**: Integração WooCommerce (opcional)

## 🔐 Acesso Admin

URL: `http://localhost/ruben-barbearia/public/admin/`
PIN padrão: `1234`

**⚠️ Muda o PIN em produção!**

## 🔗 Integração com API Node.js

O sistema integra com a API Node.js para:
- Gestão de subscrições
- Processamento de pagamentos (Easypay)
- Validação de módulos ativos

### Endpoints Utilizados
- `POST /billing/subscription` - Criar subscrição
- `GET /billing/subscription/:tenantId` - Ver subscrição ativa
- `POST /billing/subscription/:tenantId/cancel` - Cancelar
- `GET /billing/plans` - Listar planos disponíveis

## 🛠️ Desenvolvimento

### Adicionar Novos Serviços

1. Acede ao painel admin
2. Vai a Configurações > Serviços
3. Clica em "+ Adicionar"

### Personalizar o Site

Edita os ficheiros em `public/assets/`:
- `css/styles.css` - Estilos
- `js/app.js` - JavaScript

### Configurar Bot Widget

No `config/config.php`:

```php
'modules' => [
    'bot_widget' => [
        'enabled' => true,
        'name' => 'Assistente',
        'theme' => 'dark', // ou 'light'
        'position' => 'bottom-right',
        'welcome_message' => 'Olá! Como posso ajudar?',
    ],
],
```

## 📱 Responsividade

O site e painel admin são totalmente responsivos, funcionando em:
- Desktop
- Tablet
- Mobile

## 🔒 Segurança

- Autenticação por PIN com sessões
- Proteção contra SQL Injection (PDO prepared statements)
- Sanitização de inputs
- Validação de dados

## 📝 Licença

Propriedade do cliente. Uso permitido apenas para o negócio registado.
