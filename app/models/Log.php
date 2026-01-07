<?php
/**
 * Modelo Log
 */

namespace App\Models;

class Log extends Model
{
    protected static string $table = 'logs';

    /**
     * Lista logs recentes
     */
    public static function recent(int $limit = 100): array
    {
        $sql = "SELECT l.*, u.nome as usuario_nome
                FROM logs l
                LEFT JOIN usuarios u ON l.usuario_id = u.id
                ORDER BY l.created_at DESC
                LIMIT ?";
        
        return \App\Database::fetchAll($sql, [$limit]);
    }

    /**
     * Filtra logs
     */
    public static function filter(array $filters, int $page = 1, int $perPage = 50): array
    {
        $where = ['1=1'];
        $params = [];
        
        if (!empty($filters['tipo'])) {
            $where[] = 'l.tipo = ?';
            $params[] = $filters['tipo'];
        }
        
        if (!empty($filters['categoria'])) {
            $where[] = 'l.categoria = ?';
            $params[] = $filters['categoria'];
        }
        
        if (!empty($filters['usuario_id'])) {
            $where[] = 'l.usuario_id = ?';
            $params[] = $filters['usuario_id'];
        }
        
        if (!empty($filters['data_inicio'])) {
            $where[] = 'DATE(l.created_at) >= ?';
            $params[] = $filters['data_inicio'];
        }
        
        if (!empty($filters['data_fim'])) {
            $where[] = 'DATE(l.created_at) <= ?';
            $params[] = $filters['data_fim'];
        }
        
        $whereStr = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        
        // Total
        $countSql = "SELECT COUNT(*) as total FROM logs l WHERE {$whereStr}";
        $total = \App\Database::fetch($countSql, $params)['total'];
        
        // Dados
        $sql = "SELECT l.*, u.nome as usuario_nome
                FROM logs l
                LEFT JOIN usuarios u ON l.usuario_id = u.id
                WHERE {$whereStr}
                ORDER BY l.created_at DESC
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

    /**
     * Limpa logs antigos
     */
    public static function cleanOld(int $dias = 90): int
    {
        $sql = "DELETE FROM logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        return \App\Database::query($sql, [$dias])->rowCount();
    }
}
