# SiteForge

**Sistema genérico de criação de sites com módulos integrados.**

SiteForge é uma solução PHP que permite criar sites personalizados para qualquer tipo de negócio, com suporte a módulos como chatbot AI, integração WhatsApp, e-commerce, e mais.

## ⚡ Funcionalidades

- 🎨 **Branding Personalizado** - Cores, logos e textos personalizáveis
- 🤖 **Bot AI** - Widget de chat com IA integrada
- 📱 **WhatsApp** - Integração com WhatsApp Business
- 📧 **Email** - Sistema de notificações por email
- 🛒 **E-commerce** - Módulo de loja online
- 📞 **Chamadas AI** - Módulo de atendimento por voz

## 📋 Requisitos

- PHP 8.1+
- MySQL/MariaDB ou SQLite
- Extensões: PDO, JSON, cURL, OpenSSL
- Servidor Apache com mod_rewrite

## 🚀 Instalação

### 1. Upload dos ficheiros

```bash
# Copiar pasta siteforge para o servidor
scp -r siteforge/ user@server:/var/www/html/
```

### 2. Configurar permissões

```bash
cd /var/www/html/siteforge
chmod -R 755 .
chmod -R 777 storage/
chmod -R 777 config/
```

### 3. Executar Setup

Aceder a `https://seu-dominio.com/setup.php` e seguir os passos:

1. **Verificação** - Validação de requisitos do sistema
2. **Licença** - Inserir chave de licença (formato: `ntk_xxxx.xxxx`)
3. **Base de Dados** - Configurar conexão MySQL ou SQLite
4. **Negócio** - Informações do negócio/cliente
5. **Módulos** - Selecionar módulos a ativar
6. **Administrador** - Criar conta de admin
7. **Geração** - Criação automática de ficheiros de config
8. **Conclusão** - Setup completo

### 4. Remover ficheiro de setup

```bash
rm public/setup.php
```

## 📁 Estrutura

```
siteforge/
├── config/
│   └── config.php          # Configuração principal
├── database/
│   └── schema.sql          # Schema da base de dados
├── modules/
│   ├── bot/                # Módulo de chatbot
│   ├── whatsapp/           # Módulo WhatsApp
│   ├── email/              # Módulo email
│   └── shop/               # Módulo e-commerce
├── public/
│   ├── index.php           # Página principal
│   ├── admin.php           # Painel de administração
│   ├── setup.php           # Wizard de instalação
│   ├── api/                # Endpoints da API
│   ├── assets/             # CSS, JS, imagens
│   └── widget/             # Widget do bot
├── src/
│   ├── bootstrap.php       # Inicialização
│   ├── config.php          # Loader de config (legacy)
│   ├── helpers.php         # Funções auxiliares
│   ├── SettingsStore.php   # Gestão de settings
│   └── Services/           # Classes de serviços
├── storage/
│   ├── settings.json       # Settings dinâmicos
│   └── logs/               # Logs do sistema
└── README.md
```

## 🔑 Formato da Chave de Licença

```
ntk_[12-hex-chars].[64-hex-chars]

Exemplo:
ntk_bea3832cfabc.80d95c26104852ece8c90315ab0c324f9b02c1850cfca9f0
```

## 🎨 Personalização

### Cores e Tema

Editar em `admin.php` > Branding:
- Cor primária
- Tema (dark/light)
- Logo
- Textos

### Módulos

Ativar/desativar módulos em `admin.php` > Subscrição ou durante o setup.

## 🔒 Segurança

- Chaves de licença validadas via API central
- Passwords com hash bcrypt
- Headers de segurança configurados
- Rate limiting via API
- Sessões com timeout

## 🌐 API Central

A validação de licença e funcionalidades de módulos comunicam com a API central:

```
API_URL: https://api.nunomiranda.dev
```

Endpoints utilizados:
- `POST /requests/:api_key` - Registar pedidos
- `GET /tenant` - Validar tenant
- `POST /admin/*` - Operações administrativas

## 📄 Licença

Proprietário - © <?= date('Y') ?> SiteForge

---

**Desenvolvido por [Nuno Miranda](https://nunomiranda.dev)**
