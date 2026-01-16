<?php
/**
 * Modelo RotinaBackup
 */

namespace App\Models;

class RotinaBackup extends Model
{
    protected static string $table = 'rotinas_backup';

    /**
     * Lista rotinas de um servidor
     */
    public static function byServidor(int $servidorId): array
    {
        return self::where(['servidor_id' => $servidorId], 'nome ASC');
    }

    /**
     * Lista rotinas ativas de um servidor
     */
    public static function ativasByServidor(int $servidorId): array
    {
        $sql = "SELECT * FROM rotinas_backup WHERE servidor_id = ? AND ativa = 1 ORDER BY nome ASC";
        return \App\Database::fetchAll($sql, [$servidorId]);
    }

    /**
     * Encontra rotina pelo nome e servidor
     */
    public static function findByNomeAndServidor(string $nome, int $servidorId): ?array
    {
        $sql = "SELECT * FROM rotinas_backup WHERE nome = ? AND servidor_id = ?";
        return \App\Database::fetch($sql, [$nome, $servidorId]);
    }

    /**
     * Encontra ou cria rotina
     */
    public static function findOrCreate(int $servidorId, string $nome, array $extraData = []): array
    {
        $rotina = self::findByNomeAndServidor($nome, $servidorId);
        
        if ($rotina) {
            return $rotina;
        }
        
        $data = array_merge([
            'servidor_id' => $servidorId,
            'nome' => $nome,
            'ativa' => 1
        ], $extraData);
        
        $id = self::create($data);
        return self::find($id);
    }

    /**
     * Lista rotinas com informações completas
     */
    public static function allWithDetails(): array
    {
        $sql = "SELECT r.*, s.nome as servidor_nome, c.nome as cliente_nome, c.identificador as cliente_identificador
                FROM rotinas_backup r
                LEFT JOIN servidores s ON r.servidor_id = s.id
                LEFT JOIN clientes c ON s.cliente_id = c.id
                ORDER BY c.nome, s.nome, r.nome";
        
        return \App\Database::fetchAll($sql);
    }

    /**
     * Retorna rotina com última execução
     */
    public static function findWithLastExecution(int $id): ?array
    {
        $rotina = self::find($id);
        
        if (!$rotina) {
            return null;
        }
        
        $sql = "SELECT * FROM execucoes_backup 
                WHERE rotina_id = ? 
                ORDER BY data_inicio DESC LIMIT 1";
        
        $rotina['ultima_execucao'] = \App\Database::fetch($sql, [$id]);
        
        return $rotina;
    }
}
