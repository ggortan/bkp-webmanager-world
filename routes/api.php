<?php
/**
 * Rotas da API
 */

use App\Router;
use App\Controllers\ApiBackupController;
use App\Middleware\ApiAuthMiddleware;

// Registra middleware de API ANTES das rotas
Router::middleware('api_auth', [ApiAuthMiddleware::class, 'handle']);

// Rotas públicas da API
Router::get('/api/status', [ApiBackupController::class, 'status']);

// Rota de debug (REMOVER EM PRODUÇÃO)
Router::post('/api/debug-input', function() {
    header('Content-Type: application/json');
    $raw = file_get_contents('php://input');
    echo json_encode([
        'success' => true,
        'method' => $_SERVER['REQUEST_METHOD'],
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
        'content_length' => $_SERVER['CONTENT_LENGTH'] ?? null,
        'body_length' => strlen($raw),
        'body_preview' => substr($raw, 0, 300),
        'headers' => [
            'auth' => isset($_SERVER['HTTP_AUTHORIZATION']) ? substr($_SERVER['HTTP_AUTHORIZATION'], 0, 20) . '...' : null,
            'api_key' => isset($_SERVER['HTTP_X_API_KEY']) ? substr($_SERVER['HTTP_X_API_KEY'], 0, 8) . '...' : null,
        ]
    ], JSON_PRETTY_PRINT);
});

// Rotas protegidas por API Key
Router::group(['prefix' => '/api', 'middleware' => ['api_auth']], function () {
    
    // Backup
    Router::post('/backup', [ApiBackupController::class, 'store']);
    
    // Telemetria (heartbeat/ping)
    Router::post('/telemetry', [ApiBackupController::class, 'telemetry']);
    Router::post('/heartbeat', [ApiBackupController::class, 'telemetry']); // Alias
    
    // Info do cliente
    Router::get('/me', [ApiBackupController::class, 'me']);
    
    // Rotinas do cliente
    Router::get('/rotinas', [ApiBackupController::class, 'rotinas']);
    
    // Hosts do cliente
    Router::get('/hosts', [ApiBackupController::class, 'hosts']);
    
});
