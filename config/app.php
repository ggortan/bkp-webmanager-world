<?php
/**
 * Configurações Gerais da Aplicação
 */

return [
    'name' => Env::get('APP_NAME', 'Backup WebManager'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::get('APP_DEBUG', false) === 'true',
    'url' => Env::get('APP_URL', 'http://localhost'),
    'key' => Env::get('APP_KEY', ''),
    'timezone' => 'America/Sao_Paulo',
    'locale' => 'pt_BR',
    
    // Configurações de sessão
    'session' => [
        'name' => 'bkp_webmanager_session',
        'lifetime' => (int) Env::get('SESSION_LIFETIME', 120),
        'secure' => Env::get('SESSION_SECURE', 'true') === 'true',
        'httponly' => true,
        'samesite' => 'Strict'
    ],
    
    // Versão da aplicação
    'version' => '1.0.0'
];
