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

    /**
     * Atualiza status de telemetria (último contato)
     */
    public static function updateTelemetry(int $id, array $telemetryData = []): bool
    {
        $updateData = [
            'last_seen_at' => date('Y-m-d H:i:s'),
            'online_status' => 'online'
        ];
        
        if (!empty($telemetryData)) {
            $updateData['telemetry_data'] = json_encode($telemetryData);
        }
        
        return self::update($id, $updateData);
    }

    /**
     * Verifica e atualiza status offline de hosts
     * Hosts que não enviam telemetria por mais do que seu threshold são marcados offline
     */
    public static function checkOfflineHosts(): int
    {
        $sql = "UPDATE hosts 
                SET online_status = 'offline' 
                WHERE telemetry_enabled = 1 
                AND online_status = 'online'
                AND last_seen_at IS NOT NULL
                AND last_seen_at < DATE_SUB(NOW(), 
                    INTERVAL (telemetry_interval_minutes * telemetry_offline_threshold) MINUTE
                )";
        
        return \App\Database::execute($sql);
    }

    /**
     * Lista hosts offline
     */
    public static function offlineHosts(int $clienteId = null): array
    {
        $sql = "SELECT h.*, c.nome as cliente_nome 
                FROM hosts h
                LEFT JOIN clientes c ON h.cliente_id = c.id
                WHERE h.telemetry_enabled = 1 
                AND h.online_status = 'offline'
                AND h.ativo = 1";
        
        $params = [];
        
        if ($clienteId) {
            $sql .= " AND h.cliente_id = ?";
            $params[] = $clienteId;
        }
        
        $sql .= " ORDER BY h.last_seen_at ASC";
        
        return \App\Database::fetchAll($sql, $params);
    }

    /**
     * Lista hosts online
     */
    public static function onlineHosts(int $clienteId = null): array
    {
        $sql = "SELECT h.*, c.nome as cliente_nome 
                FROM hosts h
                LEFT JOIN clientes c ON h.cliente_id = c.id
                WHERE h.online_status = 'online'
                AND h.ativo = 1";
        
        $params = [];
        
        if ($clienteId) {
            $sql .= " AND h.cliente_id = ?";
            $params[] = $clienteId;
        }
        
        $sql .= " ORDER BY h.nome ASC";
        
        return \App\Database::fetchAll($sql, $params);
    }

    /**
     * Retorna resumo de status de hosts por cliente
     */
    public static function statusSummary(int $clienteId = null): array
    {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN online_status = 'online' THEN 1 ELSE 0 END) as online,
                    SUM(CASE WHEN online_status = 'offline' THEN 1 ELSE 0 END) as offline,
                    SUM(CASE WHEN online_status = 'unknown' OR online_status IS NULL THEN 1 ELSE 0 END) as unknown
                FROM hosts 
                WHERE ativo = 1";
        
        $params = [];
        
        if ($clienteId) {
            $sql .= " AND cliente_id = ?";
            $params[] = $clienteId;
        }
        
        return \App\Database::fetch($sql, $params) ?: [
            'total' => 0,
            'online' => 0,
            'offline' => 0,
            'unknown' => 0
        ];
    }
}
