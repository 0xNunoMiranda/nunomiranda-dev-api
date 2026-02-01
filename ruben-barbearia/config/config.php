<?php
/**
 * Configuração central do cliente PHP
 * 
 * Este ficheiro contém todas as configurações do site do cliente,
 * credenciais de API e definições de módulos.
 */

return [
    // ─────────────────────────────────────────────────────────────────────────
    // IDENTIDADE DO TENANT
    // ─────────────────────────────────────────────────────────────────────────
    'tenant' => [
        'id' => 1,
        'slug' => 'ruben-barbearia',
        'name' => 'Ruben Barbearia',
        'email' => 'geral@rubenbarbearia.pt',
        'phone' => '+351 912 345 678',
        'timezone' => 'Europe/Lisbon',
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // LICENÇA E API
    // ─────────────────────────────────────────────────────────────────────────
    'license_key' => '',  // Chave de licença obtida no setup (ntk_xxx.xxx)
    
    'api_url' => 'http://localhost:3000',  // URL da API Node.js
    'site_url' => 'http://localhost/ruben-barbearia/public',  // URL do site PHP
    
    'api' => [
        'base_url' => 'http://localhost:3000',
        'api_key' => 'YOUR_TENANT_API_KEY',
        'timeout' => 30,
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // BASE DE DADOS LOCAL (MySQL)
    // ─────────────────────────────────────────────────────────────────────────
    'database' => [
        'enabled' => true,
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'ruben_barbearia',
        'user' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // ADMIN PANEL
    // ─────────────────────────────────────────────────────────────────────────
    'admin' => [
        'path' => '/admin',
        'pin' => '1234',  // PIN de acesso ao painel
        'session_lifetime' => 3600 * 8,  // 8 horas
        'allowed_ips' => [],  // Vazio = todos permitidos
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // MÓDULOS ATIVOS
    // ─────────────────────────────────────────────────────────────────────────
    'modules' => [
        'bot_widget' => [
            'enabled' => true,
            'position' => 'bottom-right',  // bottom-right, bottom-left
            'theme' => 'dark',
            'welcome_message' => 'Olá! Como posso ajudar?',
            'assistant_name' => 'Assistente Ruben',
            'features' => ['bookings', 'support', 'faq'],
        ],
        'bot_whatsapp' => [
            'enabled' => false,
            'phone_number' => '',
            'webhook_verify_token' => '',
            'access_token' => '',
            'features' => ['bookings', 'support', 'orders'],
        ],
        'shop' => [
            'enabled' => false,
            'type' => 'woocommerce',  // woocommerce, custom
            'wordpress_path' => '/shop',
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // SITE ESTÁTICO
    // ─────────────────────────────────────────────────────────────────────────
    'site' => [
        'title' => 'Ruben Barbearia',
        'description' => 'A melhor barbearia da cidade. Cortes clássicos e modernos.',
        'locale' => 'pt_PT',
        'theme' => [
            'primary_color' => '#00ffc6',
            'background' => 'dark',
        ],
        'social' => [
            'instagram' => 'https://instagram.com/rubenbarbearia',
            'facebook' => 'https://facebook.com/rubenbarbearia',
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
