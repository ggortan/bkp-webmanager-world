<?php
/**
 * Arquivo de Configuração Exemplo
 * 
 * INSTRUÇÕES:
 * 1. Copie este arquivo para config.php
 * 2. Edite config.php com suas configurações reais
 * 3. Nunca faça commit de config.php no git (está no .gitignore)
 * 
 * cp config/config.example.php config/config.php
 */

return [
    // ========================================
    // CONFIGURAÇÕES GERAIS DA APLICAÇÃO
    // ========================================
    'app' => [
        'name' => 'Backup WebManager',
        'env' => 'production',  // 'development' ou 'production'
        'debug' => false,       // Mostrar erros detalhados (deixar false em produção)
        'url' => 'https://seu-dominio.com.br/caminho',  // URL base da aplicação
        'key' => 'sua-chave-secreta-aqui-32-caracteres-minimo',
        'timezone' => 'America/Sao_Paulo',
        'locale' => 'pt_BR',
        'version' => '1.0.0',
    ],

    // ========================================
    // BANCO DE DADOS
    // ========================================
    'database' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'seu_banco_de_dados',
        'username' => 'seu_usuario',
        'password' => 'sua_senha',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ],

    // ========================================
    // CONFIGURAÇÕES DE SESSÃO
    // ========================================
    'session' => [
        'name' => 'bkp_webmanager_session',
        'lifetime' => 120,  // em minutos
        'secure' => true,   // Apenas HTTPS
        'httponly' => true,
        'samesite' => 'Strict'
    ],

    // ========================================
    // AUTENTICAÇÃO MICROSOFT ENTRA (AZURE AD)
    // ========================================
    'azure' => [
        'enabled' => true,
        'client_id' => 'seu-client-id-azure',
        'client_secret' => 'seu-client-secret-azure',
        'tenant_id' => 'seu-tenant-id-azure',
        'redirect_uri' => 'https://seu-dominio.com.br/caminho/auth/callback',
    ],

    // ========================================
    // CONFIGURAÇÕES DE EMAIL (SMTP)
    // ========================================
    'mail' => [
        'host' => 'smtp.seu-provedor.com',
        'port' => 587,
        'username' => 'seu-email@dominio.com',
        'password' => 'sua-senha-email',
        'encryption' => 'tls',  // 'tls' ou 'ssl'
        'from' => [
            'address' => 'seu-email@dominio.com',
            'name' => 'Backup WebManager'
        ]
    ],

    // ========================================
    // CAMINHOS DO PROJETO
    // ========================================
    'paths' => [
        'logs' => 'logs',
        'backups' => 'backups',
        'uploads' => 'uploads',
    ],
];
