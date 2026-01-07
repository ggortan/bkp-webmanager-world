<?php
/**
 * Modelo Servidor
 */

namespace App\Models;

class Servidor extends Model
{
    protected static string $table = 'servidores';

    /**
     * Lista servidores de um cliente
     */
    public static function byCliente(int $clienteId): array
    {
        return self::where(['cliente_id' => $clienteId], 'nome ASC');
    }

    /**
     * Lista servidores ativos de um cliente
     */
    public static function ativosByCliente(int $clienteId): array
    {
        $sql = "SELECT * FROM servidores WHERE cliente_id = ? AND ativo = 1 ORDER BY nome ASC";
        return \App\Database::fetchAll($sql, [$clienteId]);
    }

    /**
     * Encontra servidor pelo nome e cliente
     */
    public static function findByNomeAndCliente(string $nome, int $clienteId): ?array
    {
        $sql = "SELECT * FROM servidores WHERE nome = ? AND cliente_id = ?";
        return \App\Database::fetch($sql, [$nome, $clienteId]);
    }

    /**
     * Encontra ou cria servidor
     */
    public static function findOrCreate(int $clienteId, string $nome, array $extraData = []): array
    {
        $servidor = self::findByNomeAndCliente($nome, $clienteId);
        
        if ($servidor) {
            return $servidor;
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
     * Lista servidores com informações do cliente
     */
    public static function allWithCliente(): array
    {
        $sql = "SELECT s.*, c.nome as cliente_nome, c.identificador as cliente_identificador
                FROM servidores s
                LEFT JOIN clientes c ON s.cliente_id = c.id
                ORDER BY c.nome, s.nome";
        
        return \App\Database::fetchAll($sql);
    }

    /**
     * Retorna servidor com estatísticas
     */
    public static function findWithStats(int $id): ?array
    {
        $servidor = self::find($id);
        
        if (!$servidor) {
            return null;
        }
        
        // Última execução
        $sql = "SELECT * FROM execucoes_backup 
                WHERE servidor_id = ? 
                ORDER BY data_inicio DESC LIMIT 1";
        $servidor['ultima_execucao'] = \App\Database::fetch($sql, [$id]);
        
        // Estatísticas
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) as sucesso,
                    SUM(CASE WHEN status = 'falha' THEN 1 ELSE 0 END) as falha
                FROM execucoes_backup 
                WHERE servidor_id = ? AND data_inicio >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        
        $servidor['stats'] = \App\Database::fetch($sql, [$id]);
        
        return $servidor;
    }
}
