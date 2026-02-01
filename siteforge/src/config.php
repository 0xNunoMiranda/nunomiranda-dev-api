<?php
/**
 * SiteForge - Configuração Legacy (Fallback)
 * 
 * Este ficheiro é usado como fallback se config/config.php não existir.
 * Após o setup, a configuração principal estará em config/config.php
 */

return [
    'tenant' => [
        'id' => null,
        'slug' => '',
        'name' => 'SiteForge',
    ],
    'api' => [
        'base_url' => 'http://localhost:3000',
        'requests_endpoint' => '/requests',
        'timeout' => 30,
    ],
    'admin' => [
        'pin' => '',
        'slug_path' => '/admin',
    ],
    'ui' => [
        'bot_gradient' => 'linear-gradient(135deg, #34d399, #22d3ee)',
        'accent_color' => '#00ffc6',
    ],
];
