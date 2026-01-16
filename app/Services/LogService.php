<?php
/**
 * Serviço de Log
 * 
 * Gerencia registro de logs no sistema
 */

namespace App\Services;

use App\Database;
use App\Helpers\Security;

class LogService
{
    /**
     * Registra um log
     */
    public static function log(
        string $tipo, 
        string $categoria, 
        string $mensagem, 
        ?array $dados = null, 
        ?int $usuarioId = null
    ): int {
        $data = [
            'tipo' => $tipo,
            'categoria' => $categoria,
            'mensagem' => $mensagem,
            'dados' => $dados ? json_encode($dados) : null,
            'usuario_id' => $usuarioId ?? ($_SESSION['user_id'] ?? null),
            'ip' => Security::getClientIp(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
        ];
        
        return Database::insert('logs', $data);
    }

    /**
     * Log de informação
     */
    public static function info(string $categoria, string $mensagem, ?array $dados = null): int
    {
        return self::log('info', $categoria, $mensagem, $dados);
    }

    /**
     * Log de aviso
     */
    public static function warning(string $categoria, string $mensagem, ?array $dados = null): int
    {
        return self::log('warning', $categoria, $mensagem, $dados);
    }

    /**
     * Log de erro
     */
    public static function error(string $categoria, string $mensagem, ?array $dados = null): int
    {
        return self::log('error', $categoria, $mensagem, $dados);
    }

    /**
     * Log de debug
     */
    public static function debug(string $categoria, string $mensagem, ?array $dados = null): int
    {
        return self::log('debug', $categoria, $mensagem, $dados);
    }

    /**
     * Log de API
     */
    public static function api(string $mensagem, ?array $dados = null): int
    {
        return self::log('api', 'api', $mensagem, $dados);
    }
}
