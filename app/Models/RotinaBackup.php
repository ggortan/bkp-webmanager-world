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
     * Lista rotinas de um servidor (compatibilidade)
     */
    public static function byServidor(int $servidorId): array
    {
        return self::where(['servidor_id' => $servidorId], 'nome ASC');
    }

    /**
     * Lista rotinas ativas de um servidor (compatibilidade)
     */
    public static function ativasByServidor(int $servidorId): array
    {
        $sql = "SELECT * FROM rotinas_backup WHERE servidor_id = ? AND ativa = 1 ORDER BY nome ASC";
        return \App\Database::fetchAll($sql, [$servidorId]);
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
     * Encontra rotina pelo nome e servidor (compatibilidade)
     */
    public static function findByNomeAndServidor(string $nome, int $servidorId): ?array
    {
        $sql = "SELECT * FROM rotinas_backup WHERE nome = ? AND servidor_id = ?";
        return \App\Database::fetch($sql, [$nome, $servidorId]);
    }

    /**
     * Gera uma routine_key única
     */
    public static function generateRoutineKey(): string
    {
        do {
            $key = 'rtk_' . Security::generateToken(32);
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
    public static function findOrCreate(int $servidorId, string $nome, array $extraData = []): array
    {
        $rotina = self::findByNomeAndServidor($nome, $servidorId);
        
        if ($rotina) {
            return $rotina;
        }
        
        // Busca cliente_id do servidor
        $servidor = \App\Models\Servidor::find($servidorId);
        if (!$servidor) {
            throw new \Exception("Servidor não encontrado: $servidorId");
        }
        
        $data = array_merge([
            'cliente_id' => $servidor['cliente_id'],
            'servidor_id' => $servidorId,
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
