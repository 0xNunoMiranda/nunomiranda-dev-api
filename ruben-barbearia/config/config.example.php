<?php
/**
 * Configuração do Cliente PHP
 * 
 * Copia este ficheiro para config.php e ajusta os valores
 */

return [
    // Identificação do tenant na API
    'tenant' => [
        'id' => 1,
        'slug' => 'ruben-barbearia',
        'name' => 'Barbearia Ruben',
    ],

    // Conexão com a API Node.js
    'api' => [
        'base_url' => 'http://localhost:3000',
        'api_key' => 'CHANGE_ME_TO_YOUR_API_KEY',
        'timeout' => 30,
    ],

    // Base de dados MySQL local
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'ruben_barbearia',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],

    // Painel de administração
    'admin' => [
        'path' => '/admin/',
        'pin' => '1234',  // ALTERAR EM PRODUÇÃO!
        'session_lifetime' => 3600 * 8, // 8 horas
    ],

    // Módulos disponíveis
    'modules' => [
        'bot_widget' => [
            'enabled' => true,
            'name' => 'Assistente Virtual',
            'theme' => 'dark',
            'position' => 'bottom-right',
            'welcome_message' => 'Olá! Como posso ajudar?',
        ],
        'bot_whatsapp' => [
            'enabled' => false,
            'number' => '',
            'token' => '',
        ],
        'shop' => [
            'enabled' => false,
            'type' => 'woocommerce',
            'url' => '',
        ],
    ],

    // Configurações do site público
    'site' => [
        'title' => 'Barbearia Ruben',
        'meta_description' => 'Barbearia tradicional com serviços de qualidade.',
        'logo' => '',
        'favicon' => '',
        'theme' => 'dark',
        'phone' => '+351 912 345 678',
        'email' => 'geral@ruben-barbearia.pt',
        'address' => 'Rua Principal, 123, Lisboa',
        'social' => [
            'instagram' => 'https://instagram.com/ruben-barbearia',
            'facebook' => '',
        ],
        'google_maps_embed' => '',
        'analytics' => [
            'google' => '',
        ],
    ],

    // Paths de armazenamento
    'storage' => [
        'settings_file' => __DIR__ . '/../storage/settings.json',
        'logs_dir' => __DIR__ . '/../storage/logs',
    ],
];
