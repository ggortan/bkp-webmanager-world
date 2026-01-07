<?php
/**
 * Configurações de E-mail
 */

return [
    'host' => Env::get('MAIL_HOST', 'smtp.office365.com'),
    'port' => (int) Env::get('MAIL_PORT', 587),
    'username' => Env::get('MAIL_USERNAME', ''),
    'password' => Env::get('MAIL_PASSWORD', ''),
    'encryption' => Env::get('MAIL_ENCRYPTION', 'tls'),
    'from' => [
        'address' => Env::get('MAIL_FROM_ADDRESS', ''),
        'name' => Env::get('MAIL_FROM_NAME', 'Backup WebManager')
    ],
    
    // Configurações de relatórios
    'reports' => [
        'enabled' => true,
        'default_recipients' => [],
        'schedule' => [
            'daily' => '08:00',
            'weekly' => 'monday'
        ]
    ]
];
