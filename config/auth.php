<?php
/**
 * Configurações de Autenticação Microsoft Entra (Azure AD)
 * 
 * Carrega a configuração do arquivo central config.php
 */

$config = require __DIR__ . '/config.php';
$azureConfig = $config['azure'];

return [
    'azure' => [
        'client_id' => $azureConfig['client_id'],
        'client_secret' => $azureConfig['client_secret'],
        'tenant_id' => $azureConfig['tenant_id'],
        'redirect_uri' => $azureConfig['redirect_uri'],
        
        // Endpoints OAuth 2.0
        'authorize_url' => 'https://login.microsoftonline.com/' . $azureConfig['tenant_id'] . '/oauth2/v2.0/authorize',
        'token_url' => 'https://login.microsoftonline.com/' . $azureConfig['tenant_id'] . '/oauth2/v2.0/token',
        'user_info_url' => 'https://graph.microsoft.com/v1.0/me',
        
        // Escopos
        'scopes' => [
            'openid',
            'profile',
            'email',
            'User.Read'
        ]
    ],
    
    // Papéis de usuário
    'roles' => [
        'admin' => [
            'id' => 1,
            'name' => 'Administrador',
            'description' => 'Acesso total ao sistema'
        ],
        'operator' => [
            'id' => 2,
            'name' => 'Operador',
            'description' => 'Pode gerenciar backups e clientes'
        ],
        'viewer' => [
            'id' => 3,
            'name' => 'Visualização',
            'description' => 'Apenas visualização de dados'
        ]
    ]
];
