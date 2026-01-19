<?php
/**
 * Modelo ExecucaoBackup
 */

namespace App\Models;

class ExecucaoBackup extends Model
{
    protected static string $table = 'execucoes_backup';

    /**
     * Registra uma nova execução de backup
     */
    public static function registrar(array $data): int
    {
        return self::create($data);
    }

    /**
     * Verifica se já existe uma execução duplicada
     * Considera duplicada se: mesma rotina_id + mesma data_inicio + mesmo status
     */
    public static function existsDuplicate(int $rotinaId, string $dataInicio, string $status): bool
    {
        $sql = "SELECT id FROM execucoes_backup 
                WHERE rotina_id = ? AND data_inicio = ? AND status = ?
                LIMIT 1";
        
        $result = \App\Database::fetch($sql, [$rotinaId, $dataInicio, $status]);
        return $result !== null;
    }

    /**
     * Encontra execução existente por rotina_id e data_inicio
     * Retorna a execução se existir, null caso contrário
     */
    public static function findByRotinaAndDataInicio(int $rotinaId, string $dataInicio): ?array
    {
        $sql = "SELECT * FROM execucoes_backup 
                WHERE rotina_id = ? AND data_inicio = ?
                LIMIT 1";
        
        return \App\Database::fetch($sql, [$rotinaId, $dataInicio]);
    }

    /**
     * Registra ou atualiza uma execução de backup (upsert)
     * Se já existe uma execução com mesma rotina_id + data_inicio, atualiza
     * Caso contrário, cria nova
     */
    public static function upsert(array $data): array
    {
        $existing = self::findByRotinaAndDataInicio($data['rotina_id'], $data['data_inicio']);
        
        if ($existing) {
            // Atualiza apenas se o status for diferente ou se for uma atualização válida
            // (ex: de 'executando' para 'sucesso' ou 'falha')
            $shouldUpdate = false;
            
            // Sempre atualiza se o status mudou
            if ($existing['status'] !== $data['status']) {
                $shouldUpdate = true;
            }
            
            // Atualiza se tem data_fim e a existente não tem
            if (!empty($data['data_fim']) && empty($existing['data_fim'])) {
                $shouldUpdate = true;
            }
            
            // Atualiza se tem tamanho e a existente não tem
            if (!empty($data['tamanho_bytes']) && empty($existing['tamanho_bytes'])) {
                $shouldUpdate = true;
            }
            
            if ($shouldUpdate) {
                self::update($existing['id'], $data);
                return [
                    'action' => 'updated',
                    'id' => $existing['id']
                ];
            }
            
            return [
                'action' => 'skipped',
                'id' => $existing['id'],
                'reason' => 'duplicate'
            ];
        }
        
        // Cria nova execução
        $id = self::create($data);
        return [
            'action' => 'created',
            'id' => $id
        ];
    }

    /**
     * Lista execuções de uma rotina
     */
    public static function byRotina(int $rotinaId, int $limit = 50): array
    {
        $sql = "SELECT * FROM execucoes_backup 
                WHERE rotina_id = ? 
                ORDER BY data_inicio DESC 
                LIMIT ?";
        
        return \App\Database::fetchAll($sql, [$rotinaId, $limit]);
    }

    /**
     * Lista execuções de um cliente
     */
    public static function byCliente(int $clienteId, int $limit = 100): array
    {
        $sql = "SELECT e.*, r.nome as rotina_nome, h.nome as host_nome
                FROM execucoes_backup e
                LEFT JOIN rotinas_backup r ON e.rotina_id = r.id
                LEFT JOIN hosts h ON e.host_id = h.id
                WHERE e.cliente_id = ?
                ORDER BY e.data_inicio DESC
                LIMIT ?";
        
        return \App\Database::fetchAll($sql, [$clienteId, $limit]);
    }

    /**
     * Obtém estatísticas gerais
     */
    public static function getStats(int $dias = 30): array
    {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) as sucesso,
                    SUM(CASE WHEN status = 'falha' THEN 1 ELSE 0 END) as falha,
                    SUM(CASE WHEN status = 'alerta' THEN 1 ELSE 0 END) as alerta,
                    SUM(CASE WHEN status = 'executando' THEN 1 ELSE 0 END) as executando
                FROM execucoes_backup 
                WHERE data_inicio >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        return \App\Database::fetch($sql, [$dias]) ?? [
            'total' => 0,
            'sucesso' => 0,
            'falha' => 0,
            'alerta' => 0,
            'executando' => 0
        ];
    }

    /**
     * Obtém estatísticas por cliente
     */
    public static function getStatsByCliente(int $dias = 30): array
    {
        $sql = "SELECT 
                    c.id as cliente_id,
                    c.nome as cliente_nome,
                    c.identificador,
                    COUNT(e.id) as total,
                    SUM(CASE WHEN e.status = 'sucesso' THEN 1 ELSE 0 END) as sucesso,
                    SUM(CASE WHEN e.status = 'falha' THEN 1 ELSE 0 END) as falha,
                    SUM(CASE WHEN e.status = 'alerta' THEN 1 ELSE 0 END) as alerta,
                    MAX(e.data_inicio) as ultima_execucao
                FROM clientes c
                LEFT JOIN execucoes_backup e ON c.id = e.cliente_id 
                    AND e.data_inicio >= DATE_SUB(NOW(), INTERVAL ? DAY)
                WHERE c.ativo = 1
                GROUP BY c.id, c.nome, c.identificador
                ORDER BY c.nome";
        
        return \App\Database::fetchAll($sql, [$dias]);
    }

    /**
     * Lista últimas execuções com detalhes
     */
    public static function getRecent(int $limit = 20): array
    {
        $sql = "SELECT e.*, r.nome as rotina_nome, h.nome as host_nome, 
                       c.nome as cliente_nome, c.identificador as cliente_identificador
                FROM execucoes_backup e
                LEFT JOIN rotinas_backup r ON e.rotina_id = r.id
                LEFT JOIN hosts h ON e.host_id = h.id
                LEFT JOIN clientes c ON e.cliente_id = c.id
                ORDER BY e.data_inicio DESC
                LIMIT ?";
        
        return \App\Database::fetchAll($sql, [$limit]);
    }

    /**
     * Obtém execuções por período para gráfico
     */
    public static function getStatsByPeriod(int $dias = 7): array
    {
        $sql = "SELECT 
                    DATE(data_inicio) as data,
                    SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) as sucesso,
                    SUM(CASE WHEN status = 'falha' THEN 1 ELSE 0 END) as falha,
                    SUM(CASE WHEN status = 'alerta' THEN 1 ELSE 0 END) as alerta
                FROM execucoes_backup
                WHERE data_inicio >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(data_inicio)
                ORDER BY data";
        
        return \App\Database::fetchAll($sql, [$dias]);
    }

    /**
     * Obtém a última execução de cada rotina ativa
     */
    public static function getLatestByRotina(): array
    {
        try {
            // Usa uma abordagem mais simples com GROUP BY
            $sql = "SELECT e.id, e.rotina_id, e.cliente_id, e.host_id, e.status, 
                           e.data_inicio, e.data_fim, e.tamanho_bytes, e.destino,
                           e.mensagem_erro, e.detalhes, e.created_at,
                           r.nome as rotina_nome, h.nome as host_nome,
                           c.nome as cliente_nome, c.identificador as cliente_identificador
                    FROM execucoes_backup e
                    INNER JOIN (
                        SELECT rotina_id, MAX(id) as max_id
                        FROM execucoes_backup
                        GROUP BY rotina_id
                    ) latest ON e.id = latest.max_id
                    LEFT JOIN rotinas_backup r ON e.rotina_id = r.id
                    LEFT JOIN hosts h ON e.host_id = h.id
                    LEFT JOIN clientes c ON e.cliente_id = c.id
                    WHERE r.ativo = 1 OR r.id IS NULL
                    ORDER BY e.data_inicio DESC";
            
            return \App\Database::fetchAll($sql) ?: [];
        } catch (\Exception $e) {
            // Fallback: retorna as execuções recentes normais
            return self::getRecent(20);
        }
    }

    /**
     * Obtém estatísticas baseadas na última execução de cada rotina
     */
    public static function getStatsLatest(): array
    {
        try {
            $sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN e.status = 'sucesso' THEN 1 ELSE 0 END) as sucesso,
                        SUM(CASE WHEN e.status = 'falha' THEN 1 ELSE 0 END) as falha,
                        SUM(CASE WHEN e.status = 'alerta' THEN 1 ELSE 0 END) as alerta,
                        SUM(CASE WHEN e.status = 'executando' THEN 1 ELSE 0 END) as executando
                    FROM execucoes_backup e
                    INNER JOIN (
                        SELECT rotina_id, MAX(id) as max_id
                        FROM execucoes_backup
                        GROUP BY rotina_id
                    ) latest ON e.id = latest.max_id
                    LEFT JOIN rotinas_backup r ON e.rotina_id = r.id
                    WHERE r.ativo = 1 OR r.id IS NULL";
            
            $result = \App\Database::fetch($sql);
            
            return $result ?: [
                'total' => 0,
                'sucesso' => 0,
                'falha' => 0,
                'alerta' => 0,
                'executando' => 0
            ];
        } catch (\Exception $e) {
            // Fallback: usa stats dos últimos 30 dias
            return self::getStats(30);
        }
    }

    /**
     * Obtém estatísticas por cliente baseadas na última execução de cada rotina
     */
    public static function getStatsByClienteLatest(): array
    {
        try {
            $sql = "SELECT 
                        c.id as cliente_id,
                        c.nome as cliente_nome,
                        c.identificador,
                        COUNT(e.id) as total,
                        SUM(CASE WHEN e.status = 'sucesso' THEN 1 ELSE 0 END) as sucesso,
                        SUM(CASE WHEN e.status = 'falha' THEN 1 ELSE 0 END) as falha,
                        SUM(CASE WHEN e.status = 'alerta' THEN 1 ELSE 0 END) as alerta,
                        MAX(e.data_inicio) as ultima_execucao
                    FROM clientes c
                    LEFT JOIN rotinas_backup r ON c.id = r.cliente_id AND r.ativo = 1
                    LEFT JOIN execucoes_backup e ON r.id = e.rotina_id AND e.id = (
                        SELECT MAX(e2.id) FROM execucoes_backup e2 WHERE e2.rotina_id = r.id
                    )
                    WHERE c.ativo = 1
                    GROUP BY c.id, c.nome, c.identificador
                    ORDER BY c.nome";
            
            return \App\Database::fetchAll($sql) ?: [];
        } catch (\Exception $e) {
            // Fallback: usa stats por cliente dos últimos 30 dias
            return self::getStatsByCliente(30);
        }
    }

    /**
     * Filtra execuções com paginação
     */
    public static function filter(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1'];
        $params = [];
        
        if (!empty($filters['cliente_id'])) {
            $where[] = 'e.cliente_id = ?';
            $params[] = $filters['cliente_id'];
        }
        
        if (!empty($filters['host_id'])) {
            $where[] = 'e.host_id = ?';
            $params[] = $filters['host_id'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = 'e.status = ?';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['data_inicio'])) {
            $where[] = 'DATE(e.data_inicio) >= ?';
            $params[] = $filters['data_inicio'];
        }
        
        if (!empty($filters['data_fim'])) {
            $where[] = 'DATE(e.data_inicio) <= ?';
            $params[] = $filters['data_fim'];
        }
        
        $whereStr = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        // Total
        $countSql = "SELECT COUNT(*) as total FROM execucoes_backup e WHERE {$whereStr}";
        $total = \App\Database::fetch($countSql, $params)['total'];
        
        // Dados
        $sql = "SELECT e.*, r.nome as rotina_nome, h.nome as host_nome, 
                       c.nome as cliente_nome, c.identificador as cliente_identificador
                FROM execucoes_backup e
                LEFT JOIN rotinas_backup r ON e.rotina_id = r.id
                LEFT JOIN hosts h ON e.host_id = h.id
                LEFT JOIN clientes c ON e.cliente_id = c.id
                WHERE {$whereStr}
                ORDER BY e.data_inicio DESC
                LIMIT {$perPage} OFFSET {$offset}";
        
        $data = \App\Database::fetchAll($sql, $params);
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }
}
