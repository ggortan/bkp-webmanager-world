<?php
/**
 * Configurações de Autenticação Microsoft Entra (Azure AD)
 */

return [
    'azure' => [
        'client_id' => Env::get('AZURE_CLIENT_ID', ''),
        'client_secret' => Env::get('AZURE_CLIENT_SECRET', ''),
        'tenant_id' => Env::get('AZURE_TENANT_ID', ''),
        'redirect_uri' => Env::get('AZURE_REDIRECT_URI', ''),
        
        // Endpoints OAuth 2.0
        'authorize_url' => 'https://login.microsoftonline.com/' . Env::get('AZURE_TENANT_ID', '') . '/oauth2/v2.0/authorize',
        'token_url' => 'https://login.microsoftonline.com/' . Env::get('AZURE_TENANT_ID', '') . '/oauth2/v2.0/token',
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
