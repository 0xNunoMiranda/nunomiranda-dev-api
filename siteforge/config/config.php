<?php
/**
 * SiteForge - Configuração gerada em 2026-02-01 03:02:06
 */

return array (
  'tenant' => 
  array (
    'id' => 0,
    'slug' => 'ruben-barber',
    'name' => 'Ruben Barber',
    'email' => 'nunomrianda@asd.asd',
    'phone' => '+351935120439',
    'timezone' => 'Europe/Lisbon',
  ),
  'license_key' => 'ntk_c0e541030984.86ff4cc89685c092f26e6fcc4443a414bdff323f42fcf42',
  'api_url' => 'http://localhost:3000',
  'site_url' => '',
  'api' => 
  array (
    'base_url' => 'http://localhost:3000',
    'api_key' => '',
    'timeout' => 30,
  ),
  'database' => 
  array (
    'enabled' => true,
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'teste-miranda',
    'user' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
  ),
  'admin' => 
  array (
    'path' => '/admin',
    'slug_path' => '/admin.php',
    'pin' => 'miranda123',
    'session_lifetime' => 28800,
    'allowed_ips' => 
    array (
    ),
  ),
  'modules' => 
  array (
    'static_site' => 
    array (
      'enabled' => true,
      'ai_generated' => false,
      'theme' => 'dark',
    ),
    'bot_widget' => 
    array (
      'enabled' => true,
      'type' => 'faq',
      'features' => 
      array (
        0 => 'info',
      ),
      'position' => 'floating',
      'exportable' => false,
    ),
    'bot_whatsapp' => 
    array (
      'enabled' => true,
      'features' => 
      array (
        0 => 'info',
      ),
      'phone_number' => '',
    ),
    'ai_calls' => 
    array (
      'enabled' => false,
    ),
    'email' => 
    array (
      'enabled' => false,
      'provider' => 'smtp',
    ),
    'sms' => 
    array (
      'enabled' => false,
      'provider' => 'twilio',
    ),
    'shop' => 
    array (
      'enabled' => true,
      'platform' => 'prestashop',
    ),
  ),
  'site' => 
  array (
    'title' => 'Ruben Barber',
    'description' => '',
    'locale' => 'pt_PT',
    'theme' => 
    array (
      'primary_color' => '#00ffc6',
      'background' => 'dark',
    ),
    'social' => 
    array (
      'instagram' => '',
      'facebook' => '',
    ),
    'analytics' => 
    array (
      'google_analytics_id' => '',
      'facebook_pixel_id' => '',
    ),
  ),
  'storage' => 
  array (
    'settings_file' => 'C:\\xampp\\htdocs\\nunomiranda-dev-api\\siteforge\\public/../storage/settings.json',
    'logs_dir' => 'C:\\xampp\\htdocs\\nunomiranda-dev-api\\siteforge\\public/../storage/logs',
    'cache_dir' => 'C:\\xampp\\htdocs\\nunomiranda-dev-api\\siteforge\\public/../storage/cache',
  ),
);
