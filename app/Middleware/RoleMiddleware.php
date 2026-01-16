<?php
/**
 * Middleware de Verificação de Role
 * 
 * Verifica se o usuário tem o papel necessário
 */

namespace App\Middleware;

use App\Services\AuthService;

class RoleMiddleware
{
    private static array $allowedRoles = [];

    /**
     * Define os papéis permitidos
     */
    public static function allow(array $roles): callable
    {
        return function () use ($roles) {
            $authService = new AuthService();
            $user = $authService->getUser();
            
            if (!$user) {
                header('Location: /login');
                exit;
            }
            
            $userRole = $user['role'] ?? 'viewer';
            
            // Admin sempre tem acesso
            if ($userRole === 'admin') {
                return true;
            }
            
            if (!in_array($userRole, $roles)) {
                http_response_code(403);
                require dirname(__DIR__, 2) . '/app/Views/errors/403.php';
                exit;
            }
            
            return true;
        };
    }

    /**
     * Verifica se é admin
     */
    public static function admin(): callable
    {
        return self::allow(['admin']);
    }

    /**
     * Verifica se é operador ou superior
     */
    public static function operator(): callable
    {
        return self::allow(['admin', 'operator']);
    }
}
