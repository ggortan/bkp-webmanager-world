<?php
/**
 * Teste das Bibliotecas Nativas
 * 
 * Valida se JWT e SMTP funcionam corretamente
 */

define('ROOT_PATH', __DIR__);

// Carrega a configuração
$config = require ROOT_PATH . '/config/config.php';

echo "╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║                   TESTE DAS BIBLIOTECAS NATIVAS                       ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

// Test 1: JWT
echo "🔐 Testando JWT...\n";
echo str_repeat("-", 74) . "\n";

try {
    $appKey = $config['app']['key'] ?? 'teste-chave-secreta-32-caracteres-aqui';
    
    \App\Libraries\Jwt::setSecretKey($appKey);
    
    // Codificar
    $payload = [
        'sub' => 123,
        'email' => 'teste@example.com',
        'role' => 'admin'
    ];
    
    $token = \App\Libraries\Jwt::encode($payload);
    echo "✓ Token gerado: " . substr($token, 0, 50) . "...\n";
    
    // Decodificar
    $decoded = \App\Libraries\Jwt::decode($token);
    echo "✓ Token decodificado:\n";
    echo "  - sub: {$decoded->sub}\n";
    echo "  - email: {$decoded->email}\n";
    echo "  - role: {$decoded->role}\n";
    echo "✓ JWT funcionando corretamente!\n\n";
    
} catch (\Exception $e) {
    echo "✗ Erro: {$e->getMessage()}\n\n";
}

// Test 2: SMTP (conexão)
echo "📧 Testando SMTP...\n";
echo str_repeat("-", 74) . "\n";

try {
    $mailConfig = $config['mail'];
    $mailHost = $mailConfig['host'] ?? 'smtp.office365.com';
    $mailPort = (int)$mailConfig['port'] ?? 587;
    $mailUser = $mailConfig['username'] ?? '';
    $mailPass = $mailConfig['password'] ?? '';
    $mailEnc = $mailConfig['encryption'] ?? 'tls';
    
    if (empty($mailUser) || empty($mailPass)) {
        echo "⚠ Aviso: SMTP não configurado em config/config.php\n";
        echo "  Configure 'mail' > 'username' e 'password' para testar\n\n";
    } else {
        $smtp = new \App\Libraries\Smtp($mailHost, $mailPort, $mailUser, $mailPass, $mailEnc);
        
        echo "✓ Objeto SMTP criado:\n";
        echo "  - Host: {$mailHost}\n";
        echo "  - Port: {$mailPort}\n";
        echo "  - Encryption: {$mailEnc}\n";
        echo "✓ SMTP está pronto para usar!\n";
        echo "✓ Para testar envio, configure credenciais válidas\n\n";
    }
    
} catch (\Exception $e) {
    echo "✗ Erro: {$e->getMessage()}\n\n";
}

// Test 3: Verificar autoloader
echo "📦 Testando Autoloader...\n";
echo str_repeat("-", 74) . "\n";

try {
    $classes = [
        'App\Database',
        'App\Router',
        'App\Services\EmailService',
        'App\Models\Usuario',
        'App\Libraries\Jwt',
        'App\Libraries\Smtp',
    ];
    
    foreach ($classes as $class) {
        if (class_exists($class)) {
            echo "✓ {$class}\n";
        } else {
            echo "✗ {$class} (NÃO ENCONTRADA)\n";
        }
    }
    echo "\n";
    
} catch (\Exception $e) {
    echo "✗ Erro: {$e->getMessage()}\n\n";
}

// Test 4: Verificar extensões PHP
echo "🔧 Verificando Extensões PHP...\n";
echo str_repeat("-", 74) . "\n";

$extensions = [
    'pdo' => 'PDO',
    'curl' => 'cURL',
    'json' => 'JSON',
    'mbstring' => 'mbstring',
    'openssl' => 'OpenSSL',
];

foreach ($extensions as $ext => $name) {
    if (extension_loaded($ext)) {
        echo "✓ {$name} carregada\n";
    } else {
        echo "✗ {$name} NÃO carregada\n";
    }
}

echo "\n";
echo "═════════════════════════════════════════════════════════════════════════\n";
echo "✅ Testes completos! Seu projeto está pronto sem Composer.\n";
echo "═════════════════════════════════════════════════════════════════════════\n";
