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
    
    // Determina o path do cookie baseado na URL da aplicação
    $cookiePath = '/';
    if (isset($config['app']['url'])) {
        $parsedPath = parse_url($config['app']['url'], PHP_URL_PATH);
        if ($parsedPath && $parsedPath !== '/') {
            $cookiePath = rtrim($parsedPath, '/') . '/';
        }
    }
    
    // Verifica HTTPS considerando proxy reverso
    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
    
    session_name($sessionConfig['name']);
    
    session_set_cookie_params([
        'lifetime' => $sessionConfig['lifetime'] * 60,
        'path' => $cookiePath,
        'domain' => '',
        'secure' => $sessionConfig['secure'] && $isHttps,
        'httponly' => $sessionConfig['httponly'],
        'samesite' => $sessionConfig['samesite']
    ]);
    
    session_start();
}

// Regenera ID da sessão periodicamente para segurança (apenas se usuário logado)
// Não regenera durante o processo de login para evitar perda de sessão
if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id'])) {
    // Só regenera se last_regeneration existir e passou tempo suficiente
    if (isset($_SESSION['last_regeneration']) && time() - $_SESSION['last_regeneration'] > 300) {
        // Salva dados importantes antes de regenerar
        $sessionData = $_SESSION;
        session_regenerate_id(true);
        // Restaura dados da sessão
        $_SESSION = $sessionData;
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
