<?php
/**
 * Rotas Web
 */

use App\Router;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\ClienteController;
use App\Controllers\UsuarioController;
use App\Controllers\BackupController;
use App\Controllers\RelatorioController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;

// Registra middlewares ANTES das rotas
Router::middleware('auth', [AuthMiddleware::class, 'handle']);
Router::middleware('csrf', [CsrfMiddleware::class, 'handle']);
Router::middleware('admin', RoleMiddleware::admin());
Router::middleware('operator', RoleMiddleware::operator());

// Rotas públicas
Router::get('/login', [AuthController::class, 'login']);
Router::get('/auth/redirect', [AuthController::class, 'redirectToAzure']);
Router::get('/auth/callback', [AuthController::class, 'callback']);
Router::get('/logout', [AuthController::class, 'logout']);

// Rota raiz redireciona para dashboard
Router::get('/', function() {
    header('Location: ' . \App\Router::path('/dashboard'));
    exit;
});

// Rotas protegidas
Router::group(['middleware' => ['auth', 'csrf']], function () {
    
    // Dashboard
    Router::get('/dashboard', [DashboardController::class, 'index']);
    
    // Clientes
    Router::get('/clientes', [ClienteController::class, 'index']);
    Router::get('/clientes/criar', [ClienteController::class, 'create'], ['operator']);
    Router::post('/clientes', [ClienteController::class, 'store'], ['operator']);
    Router::get('/clientes/{id}', [ClienteController::class, 'show']);
    Router::get('/clientes/{id}/editar', [ClienteController::class, 'edit'], ['operator']);
    Router::post('/clientes/{id}', [ClienteController::class, 'update'], ['operator']);
    Router::post('/clientes/{id}/delete', [ClienteController::class, 'destroy'], ['admin']);
    Router::post('/clientes/{id}/regenerar-api-key', [ClienteController::class, 'regenerateApiKey'], ['admin']);
    
    // Usuários (apenas admin)
    Router::get('/usuarios', [UsuarioController::class, 'index'], ['admin']);
    Router::get('/usuarios/{id}', [UsuarioController::class, 'show'], ['admin']);
    Router::get('/usuarios/{id}/editar', [UsuarioController::class, 'edit'], ['admin']);
    Router::post('/usuarios/{id}', [UsuarioController::class, 'update'], ['admin']);
    Router::post('/usuarios/{id}/toggle-status', [UsuarioController::class, 'toggleStatus'], ['admin']);
    
    // Backups
    Router::get('/backups', [BackupController::class, 'index']);
    Router::get('/backups/{id}', [BackupController::class, 'show']);
    Router::get('/backups/servidores/{clienteId}', [BackupController::class, 'servidoresByCliente']);
    
    // Relatórios
    Router::get('/relatorios', [RelatorioController::class, 'index']);
    Router::get('/relatorios/geral', [RelatorioController::class, 'geral']);
    Router::get('/relatorios/cliente/{clienteId}', [RelatorioController::class, 'cliente']);
    Router::post('/relatorios/enviar-email', [RelatorioController::class, 'enviarEmail'], ['operator']);
    Router::get('/relatorios/exportar-csv', [RelatorioController::class, 'exportarCsv']);
});
