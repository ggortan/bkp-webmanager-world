<?php
/**
 * Backup WebManager - World Informática
 * 
 * Ponto de entrada da aplicação
 */

// Define constantes
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
if (!defined('APP_START')) {
    define('APP_START', microtime(true));
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', ROOT_PATH . '/logs/php_errors.log');

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Carrega configuração centralizada (global para ser acessada por toda a aplicação)
$GLOBALS['config'] = require ROOT_PATH . '/config/config.php';
$config = $GLOBALS['config'];

// Autoload
spl_autoload_register(function ($class) {
    // Namespaces suportados
    $namespaces = [
        'App\\' => 'app/'
    ];
    
    foreach ($namespaces as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        
        $relativeClass = substr($class, $len);
        $file = ROOT_PATH . '/' . $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// Carrega funções helper globais
require ROOT_PATH . '/app/Helpers/functions.php';

// Carrega configuração do banco de dados
$dbConfig = require ROOT_PATH . '/config/database.php';
\App\Database::configure($dbConfig);

// Configuração de sessão (apenas para requisições web, não API)
$appConfig = require ROOT_PATH . '/config/app.php';
$isApiRequest = preg_match('#/api(/|$)#', $_SERVER['REQUEST_URI'] ?? '');

if (!$isApiRequest && session_status() === PHP_SESSION_NONE) {
    $sessionConfig = $appConfig['session'];
    
    session_name($sessionConfig['name']);
    
    session_set_cookie_params([
        'lifetime' => $sessionConfig['lifetime'] * 60,
        'path' => '/',
        'domain' => '',
        'secure' => $sessionConfig['secure'] && isset($_SERVER['HTTPS']),
        'httponly' => $sessionConfig['httponly'],
        'samesite' => $sessionConfig['samesite']
    ]);
    
    session_start();
}

// Regenera ID da sessão periodicamente (apenas se sessão foi iniciada)
if (session_status() === PHP_SESSION_ACTIVE) {
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// Carrega rotas
require ROOT_PATH . '/routes/web.php';
require ROOT_PATH . '/routes/api.php';

// Despacha a requisição
try {
    \App\Router::dispatch();
} catch (Exception $e) {
    http_response_code(500);
    
    if ($appConfig['debug'] ?? false) {
        echo '<pre>';
        echo 'Erro: ' . $e->getMessage() . "\n";
        echo 'Arquivo: ' . $e->getFile() . ':' . $e->getLine() . "\n";
        echo '<br><br>';
        echo $e->getTraceAsString();
        echo '</pre>';
    } else {
        require ROOT_PATH . '/app/Views/errors/500.php';
    }
}
