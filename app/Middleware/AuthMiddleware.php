<?php
/**
 * Middleware de Autenticação
 * 
 * Verifica se o usuário está autenticado
 */

namespace App\Middleware;

use App\Services\AuthService;
use App\Router;

class AuthMiddleware
{
    /**
     * Executa o middleware
     */
    public static function handle(): bool
    {
        $authService = new AuthService();
        
        if (!$authService->isAuthenticated()) {
            // Armazena URL atual para redirecionamento após login
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            
            header('Location: ' . Router::path('/login'));
            exit;
        }
        
        return true;
    }
}
