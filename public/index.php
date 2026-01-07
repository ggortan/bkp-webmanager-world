<?php
/**
 * Backup WebManager - World Informática
 * 
 * Ponto de entrada da aplicação
 */

// Define constantes
define('ROOT_PATH', dirname(__DIR__));
define('APP_START', microtime(true));

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', ROOT_PATH . '/logs/php_errors.log');

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Carrega configuração de ambiente
require ROOT_PATH . '/config/env.php';
Env::load(ROOT_PATH);

// Autoload
spl_autoload_register(function ($class) {
    // Remove namespace prefix
    $prefix = 'App\\';
    $baseDir = ROOT_PATH . '/app/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Carrega autoload do Composer se existir
$composerAutoload = ROOT_PATH . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require $composerAutoload;
}

// Carrega configuração do banco de dados
$dbConfig = require ROOT_PATH . '/config/database.php';
\App\Database::configure($dbConfig);

// Configuração de sessão
$appConfig = require ROOT_PATH . '/config/app.php';

if (session_status() === PHP_SESSION_NONE) {
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

// Regenera ID da sessão periodicamente
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Carrega rotas
require ROOT_PATH . '/routes/web.php';
require ROOT_PATH . '/routes/api.php';

// Despacha a requisição
\App\Router::dispatch();
