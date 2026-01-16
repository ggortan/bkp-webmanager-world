<?php
/**
 * Middleware CSRF
 * 
 * Valida token CSRF em requisições POST
 */

namespace App\Middleware;

use App\Helpers\Security;

class CsrfMiddleware
{
    /**
     * Executa o middleware
     */
    public static function handle(): bool
    {
        // Apenas verifica em métodos que modificam dados
        $method = $_SERVER['REQUEST_METHOD'];
        
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return true;
        }
        
        // Ignora requisições de API (usam API Key)
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/api/') === 0) {
            return true;
        }
        
        $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        
        if (!Security::validateCsrfToken($token)) {
            http_response_code(419);
            
            if (self::isAjax()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => 'Token CSRF inválido ou expirado',
                    'status' => 419
                ], JSON_UNESCAPED_UNICODE);
            } else {
                require dirname(__DIR__, 2) . '/app/Views/errors/419.php';
            }
            
            exit;
        }
        
        return true;
    }
    
    /**
     * Verifica se é requisição AJAX
     */
    private static function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
