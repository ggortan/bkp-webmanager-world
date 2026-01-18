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
