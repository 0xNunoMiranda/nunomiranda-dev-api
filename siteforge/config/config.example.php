<?php
/**
 * SiteForge - Configuração Central
 * 
 * Este ficheiro contém todas as configurações do site do cliente.
 * Copiar para config.php e preencher os valores.
 */

return [
    // ─────────────────────────────────────────────────────────────────────────
    // IDENTIDADE DO TENANT
    // ─────────────────────────────────────────────────────────────────────────
    'tenant' => [
        'id' => 0,
        'slug' => '',
        'name' => '',
        'email' => '',
        'phone' => '',
        'timezone' => 'Europe/Lisbon',
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // LICENÇA E API
    // ─────────────────────────────────────────────────────────────────────────
    'license_key' => '',  // Chave de licença (formato: ntk_xxxxxxxxxxxx.xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx)
    
    'api_url' => 'http://localhost:3000',
    'site_url' => '',
    
    'api' => [
        'base_url' => 'http://localhost:3000',
        'api_key' => '',
        'timeout' => 30,
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // BASE DE DADOS LOCAL (MySQL)
    // ─────────────────────────────────────────────────────────────────────────
    'database' => [
        'enabled' => true,
        'host' => 'localhost',
        'port' => 3306,
        'name' => '',
        'user' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // ADMIN PANEL
    // ─────────────────────────────────────────────────────────────────────────
    'admin' => [
        'path' => '/admin',
        'slug_path' => '/admin.php',
        'pin' => '',
        'session_lifetime' => 3600 * 8,
        'allowed_ips' => [],
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // MÓDULOS ATIVOS
    // ─────────────────────────────────────────────────────────────────────────
    'modules' => [
        'static_site' => [
            'enabled' => false,
            'ai_generated' => false,
            'theme' => 'dark',
        ],
        'bot_widget' => [
            'enabled' => false,
            'type' => 'faq',
            'position' => 'bottom-right',
            'theme' => 'dark',
            'welcome_message' => 'Olá! Como posso ajudar?',
            'assistant_name' => 'Assistente',
            'features' => ['info', 'support'],
        ],
        'bot_whatsapp' => [
            'enabled' => false,
            'phone_number' => '',
            'webhook_verify_token' => '',
            'access_token' => '',
            'features' => ['info', 'support'],
        ],
        'ai_calls' => [
            'enabled' => false,
        ],
        'email' => [
            'enabled' => false,
            'provider' => 'smtp',
        ],
        'sms' => [
            'enabled' => false,
            'provider' => '',
        ],
        'shop' => [
            'enabled' => false,
            'platform' => 'prestashop',
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // SITE
    // ─────────────────────────────────────────────────────────────────────────
    'site' => [
        'title' => '',
        'description' => '',
        'locale' => 'pt_PT',
        'theme' => [
            'primary_color' => '#00ffc6',
            'background' => 'dark',
        ],
        'social' => [
            'instagram' => '',
            'facebook' => '',
        ],
        'analytics' => [
            'google_analytics_id' => '',
            'facebook_pixel_id' => '',
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // STORAGE
    // ─────────────────────────────────────────────────────────────────────────
    'storage' => [
        'settings_file' => __DIR__ . '/../storage/settings.json',
        'logs_dir' => __DIR__ . '/../storage/logs',
        'cache_dir' => __DIR__ . '/../storage/cache',
    ],
];
