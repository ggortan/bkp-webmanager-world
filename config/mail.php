<?php
/**
 * Configurações de E-mail
 * 
 * Carrega a configuração do arquivo central config.php
 */

$config = require __DIR__ . '/config.php';

return array_merge($config['mail'], [
    // Configurações de relatórios
    'reports' => [
        'enabled' => true,
        'default_recipients' => [],
        'schedule' => [
            'daily' => '08:00',
            'weekly' => 'monday'
        ]
    ]
]);
