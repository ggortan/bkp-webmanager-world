<?php
/**
 * Middleware de Autenticação da API
 * 
 * Valida API Key para endpoints da API
 */

namespace App\Middleware;

use App\Models\Cliente;
use App\Services\LogService;

class ApiAuthMiddleware
{
    /**
     * Executa o middleware
     */
    public static function handle(): bool
    {
        $apiKey = self::getApiKey();
        
        if (empty($apiKey)) {
            self::unauthorized('API Key não fornecida');
            return false;
        }
        
        // Valida a API Key
        $cliente = Cliente::findByApiKey($apiKey);
        
        if (!$cliente) {
            LogService::log('warning', 'api', 'Tentativa de acesso com API Key inválida', [
                'api_key' => substr($apiKey, 0, 8) . '...',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            self::unauthorized('API Key inválida');
            return false;
        }
        
        if (!$cliente['ativo']) {
            self::unauthorized('Cliente inativo');
            return false;
        }
        
        // Armazena o cliente na requisição para uso posterior
        $_REQUEST['_cliente'] = $cliente;
        
        return true;
    }
    
    /**
     * Obtém a API Key do header ou query string
     */
    private static function getApiKey(): ?string
    {
        // Verifica header Authorization
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        // Verifica header X-API-Key
        if (!empty($_SERVER['HTTP_X_API_KEY'])) {
            return $_SERVER['HTTP_X_API_KEY'];
        }
        
        // Verifica query string
        if (!empty($_GET['api_key'])) {
            return $_GET['api_key'];
        }
        
        return null;
    }
    
    /**
     * Retorna resposta de não autorizado
     */
    private static function unauthorized(string $message): void
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'status' => 401
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
