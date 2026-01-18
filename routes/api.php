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
