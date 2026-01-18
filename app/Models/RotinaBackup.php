<?php
/**
 * Modelo RotinaBackup
 */

namespace App\Models;

use App\Helpers\Security;

class RotinaBackup extends Model
{
    protected static string $table = 'rotinas_backup';

    /**
     * Lista rotinas de um cliente
     */
    public static function byCliente(int $clienteId): array
    {
        return self::where(['cliente_id' => $clienteId], 'nome ASC');
    }

    /**
     * Lista rotinas ativas de um cliente
     */
    public static function ativasByCliente(int $clienteId): array
    {
        $sql = "SELECT * FROM rotinas_backup WHERE cliente_id = ? AND ativa = 1 ORDER BY nome ASC";
        return \App\Database::fetchAll($sql, [$clienteId]);
    }

    /**
     * Encontra rotina pela routine_key
     */
    public static function findByRoutineKey(string $routineKey): ?array
    {
        return self::findBy('routine_key', $routineKey);
    }

    /**
     * Lista rotinas de um host
     */
    public static function byHost(int $hostId): array
    {
        return self::where(['host_id' => $hostId], 'nome ASC');
    }

    /**
     * Lista rotinas ativas de um host
     */
    public static function ativasByHost(int $hostId): array
    {
        $sql = "SELECT * FROM rotinas_backup WHERE host_id = ? AND ativa = 1 ORDER BY nome ASC";
        return \App\Database::fetchAll($sql, [$hostId]);
    }

    /**
     * Lista rotinas independentes (sem host vinculado)
     */
    public static function independentes(int $clienteId): array
    {
        $sql = "SELECT * FROM rotinas_backup WHERE cliente_id = ? AND host_id IS NULL ORDER BY nome ASC";
        return \App\Database::fetchAll($sql, [$clienteId]);
    }

    /**
     * Lista rotinas com host vinculado
     */
    public static function comHost(int $clienteId): array
    {
        $sql = "SELECT * FROM rotinas_backup WHERE cliente_id = ? AND host_id IS NOT NULL ORDER BY nome ASC";
        return \App\Database::fetchAll($sql, [$clienteId]);
    }

    /**
     * Encontra rotina pelo nome e cliente
     */
    public static function findByNomeAndCliente(string $nome, int $clienteId): ?array
    {
        $sql = "SELECT * FROM rotinas_backup WHERE nome = ? AND cliente_id = ?";
        return \App\Database::fetch($sql, [$nome, $clienteId]);
    }

    /**
     * Encontra rotina pelo nome e host
     */
    public static function findByNomeAndHost(string $nome, int $hostId): ?array
    {
        $sql = "SELECT * FROM rotinas_backup WHERE nome = ? AND host_id = ?";
        return \App\Database::fetch($sql, [$nome, $hostId]);
    }

    /**
     * Gera uma routine_key única
     * Formato: 'rtk_' (4 chars) + 28 hex chars = 32 chars total
     */
    public static function generateRoutineKey(): string
    {
        do {
            // Security::generateToken(14) gera 14 bytes = 28 hex chars
            // Com prefixo 'rtk_' (4 chars) = 32 chars total
            $key = 'rtk_' . Security::generateToken(14);
            $exists = self::findByRoutineKey($key);
        } while ($exists);
        
        return $key;
    }

    /**
     * Cria uma nova rotina vinculada ao cliente
     */
    public static function createForCliente(int $clienteId, string $nome, array $extraData = []): array
    {
        $data = array_merge([
            'cliente_id' => $clienteId,
            'nome' => $nome,
            'routine_key' => self::generateRoutineKey(),
            'ativa' => 1
        ], $extraData);
        
        $id = self::create($data);
        return self::find($id);
    }

    /**
     * Encontra ou cria rotina vinculada ao cliente
     */
    public static function findOrCreateForCliente(int $clienteId, string $nome, array $extraData = []): array
    {
        $rotina = self::findByNomeAndCliente($nome, $clienteId);
        
        if ($rotina) {
            return $rotina;
        }
        
        return self::createForCliente($clienteId, $nome, $extraData);
    }

    /**
     * Encontra ou cria rotina (compatibilidade com API antiga)
     */
    public static function findOrCreate(int $hostId, string $nome, array $extraData = []): array
    {
        $rotina = self::findByNomeAndHost($nome, $hostId);
        
        if ($rotina) {
            return $rotina;
        }
        
        // Busca cliente_id do host
        $host = \App\Models\Host::find($hostId);
        if (!$host) {
            throw new \Exception("Host não encontrado: $hostId");
        }
        
        $data = array_merge([
            'cliente_id' => $host['cliente_id'],
            'host_id' => $hostId,
            'nome' => $nome,
            'routine_key' => self::generateRoutineKey(),
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
        $sql = "SELECT r.*, h.nome as host_nome, c.nome as cliente_nome, c.identificador as cliente_identificador
                FROM rotinas_backup r
                INNER JOIN clientes c ON r.cliente_id = c.id
                LEFT JOIN hosts h ON r.host_id = h.id
                ORDER BY c.nome, h.nome, r.nome";
        
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

    /**
     * Retorna últimas execuções de uma rotina
     */
    public static function getRecentExecutions(int $rotinaId, int $limit = 10): array
    {
        $sql = "SELECT * FROM execucoes_backup 
                WHERE rotina_id = ? 
                ORDER BY data_inicio DESC 
                LIMIT ?";
        
        return \App\Database::fetchAll($sql, [$rotinaId, $limit]);
    }
}
