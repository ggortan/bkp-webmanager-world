<?php
/**
 * Modelo Cliente
 */

namespace App\Models;

use App\Helpers\Security;

class Cliente extends Model
{
    protected static string $table = 'clientes';

    /**
     * Encontra cliente pelo identificador
     */
    public static function findByIdentificador(string $identificador): ?array
    {
        return self::findBy('identificador', $identificador);
    }

    /**
     * Encontra cliente pela API Key
     */
    public static function findByApiKey(string $apiKey): ?array
    {
        return self::findBy('api_key', $apiKey);
    }

    /**
     * Lista clientes ativos
     */
    public static function ativos(): array
    {
        return self::where(['ativo' => 1], 'nome ASC');
    }

    /**
     * Lista clientes para select
     */
    public static function forSelect(): array
    {
        $clientes = self::ativos();
        $result = [];
        
        foreach ($clientes as $cliente) {
            $result[$cliente['id']] = $cliente['nome'] . ' (' . $cliente['identificador'] . ')';
        }
        
        return $result;
    }

    /**
     * Cria um novo cliente com API Key
     */
    public static function createWithApiKey(array $data): int
    {
        $data['api_key'] = Security::generateApiKey();
        return self::create($data);
    }

    /**
     * Regenera API Key do cliente
     */
    public static function regenerateApiKey(int $id): string
    {
        $newApiKey = Security::generateApiKey();
        self::update($id, ['api_key' => $newApiKey]);
        return $newApiKey;
    }

    /**
     * Retorna cliente com estatísticas de backup (baseado na última execução de cada rotina)
     */
    public static function findWithStats(int $id): ?array
    {
        $cliente = self::find($id);
        
        if (!$cliente) {
            return null;
        }
        
        // Conta hosts
        $sql = "SELECT COUNT(*) as total FROM hosts WHERE cliente_id = ?";
        $hosts = \App\Database::fetch($sql, [$id]);
        $cliente['total_hosts'] = $hosts['total'];
        
        // Estatísticas baseadas na última execução de cada rotina
        try {
            $sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN e.status = 'sucesso' THEN 1 ELSE 0 END) as sucesso,
                        SUM(CASE WHEN e.status = 'falha' THEN 1 ELSE 0 END) as falha,
                        SUM(CASE WHEN e.status = 'alerta' THEN 1 ELSE 0 END) as alerta
                    FROM execucoes_backup e
                    INNER JOIN (
                        SELECT rotina_id, MAX(id) as max_id
                        FROM execucoes_backup
                        WHERE cliente_id = ?
                        GROUP BY rotina_id
                    ) latest ON e.id = latest.max_id";
            
            $stats = \App\Database::fetch($sql, [$id]);
        } catch (\Exception $ex) {
            // Fallback: conta dos últimos 30 dias
            $sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) as sucesso,
                        SUM(CASE WHEN status = 'falha' THEN 1 ELSE 0 END) as falha,
                        SUM(CASE WHEN status = 'alerta' THEN 1 ELSE 0 END) as alerta
                    FROM execucoes_backup 
                    WHERE cliente_id = ? AND data_inicio >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            
            $stats = \App\Database::fetch($sql, [$id]);
        }
        
        $cliente['stats_backup'] = $stats ?: ['total' => 0, 'sucesso' => 0, 'falha' => 0, 'alerta' => 0];
        
        return $cliente;
    }
}
