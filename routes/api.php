<?php
/**
 * Rotas da API
 */

use App\Router;
use App\Controllers\ApiBackupController;
use App\Middleware\ApiAuthMiddleware;

// Rotas públicas da API
Router::get('/api/status', [ApiBackupController::class, 'status']);

// Rotas protegidas por API Key
Router::group(['prefix' => '/api', 'middleware' => ['api_auth']], function () {
    
    // Backup
    Router::post('/backup', [ApiBackupController::class, 'store']);
    
    // Info do cliente
    Router::get('/me', [ApiBackupController::class, 'me']);
    
});

// Registra middleware de API
Router::middleware('api_auth', [ApiAuthMiddleware::class, 'handle']);
