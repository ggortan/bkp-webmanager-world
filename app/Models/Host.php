<?php
/**
 * Modelo Host
 */

namespace App\Models;

class Host extends Model
{
    protected static string $table = 'hosts';

    /**
     * Lista hosts de um cliente
     */
    public static function byCliente(int $clienteId): array
    {
        return self::where(['cliente_id' => $clienteId], 'nome ASC');
    }

    /**
     * Lista hosts ativos de um cliente
     */
    public static function ativosByCliente(int $clienteId): array
    {
        $sql = "SELECT * FROM hosts WHERE cliente_id = ? AND ativo = 1 ORDER BY nome ASC";
        return \App\Database::fetchAll($sql, [$clienteId]);
    }

    /**
     * Encontra host pelo nome e cliente
     */
    public static function findByNomeAndCliente(string $nome, int $clienteId): ?array
    {
        $sql = "SELECT * FROM hosts WHERE nome = ? AND cliente_id = ?";
        return \App\Database::fetch($sql, [$nome, $clienteId]);
    }

    /**
     * Encontra ou cria host
     */
    public static function findOrCreate(int $clienteId, string $nome, array $extraData = []): array
    {
        $host = self::findByNomeAndCliente($nome, $clienteId);
        
        if ($host) {
            return $host;
        }
        
        $data = array_merge([
            'cliente_id' => $clienteId,
            'nome' => $nome,
            'ativo' => 1
        ], $extraData);
        
        $id = self::create($data);
        return self::find($id);
    }

    /**
     * Lista hosts com informações do cliente
     */
    public static function allWithCliente(): array
    {
        $sql = "SELECT h.*, c.nome as cliente_nome, c.identificador as cliente_identificador
                FROM hosts h
                LEFT JOIN clientes c ON h.cliente_id = c.id
                ORDER BY c.nome, h.nome";
        
        return \App\Database::fetchAll($sql);
    }

    /**
     * Retorna host com estatísticas
     */
    public static function withStats(int $id): ?array
    {
        $host = self::find($id);
        
        if (!$host) {
            return null;
        }
        
        // Contagem de rotinas
        $sql = "SELECT COUNT(*) as total FROM rotinas_backup WHERE host_id = ?";
        $result = \App\Database::fetch($sql, [$id]);
        $host['total_rotinas'] = $result['total'] ?? 0;
        
        // Última execução
        $sql = "SELECT * FROM execucoes_backup 
                WHERE host_id = ? 
                ORDER BY data_inicio DESC LIMIT 1";
        $host['ultima_execucao'] = \App\Database::fetch($sql, [$id]);
        
        // Estatísticas dos últimos 7 dias
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) as sucesso,
                    SUM(CASE WHEN status = 'falha' THEN 1 ELSE 0 END) as falha
                FROM execucoes_backup 
                WHERE host_id = ? AND data_inicio >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        
        $host['stats'] = \App\Database::fetch($sql, [$id]);
        
        return $host;
    }

    /**
     * Verifica se pode deletar o host
     */
    public static function canDelete(int $id): bool
    {
        $sql = "SELECT COUNT(*) as total FROM rotinas_backup WHERE host_id = ? AND ativa = 1";
        $result = \App\Database::fetch($sql, [$id]);
        
        return ($result['total'] ?? 0) === 0;
    }

    /**
     * Alterna o status do host
     */
    public static function toggleStatus(int $id): bool
    {
        $host = self::find($id);
        
        if (!$host) {
            return false;
        }
        
        $novoStatus = $host['ativo'] ? 0 : 1;
        
        return self::update($id, ['ativo' => $novoStatus]);
    }

    /**
     * Retorna host com última execução (compatibilidade)
     * @deprecated Use withStats() instead
     */
    public static function findWithStats(int $id): ?array
    {
        return self::withStats($id);
    }
}
