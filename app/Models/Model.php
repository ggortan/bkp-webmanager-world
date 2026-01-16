<?php
/**
 * Modelo Base
 * 
 * Classe abstrata para todos os modelos
 */

namespace App\Models;

use App\Database;

abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    
    /**
     * Encontra um registro pelo ID
     */
    public static function find(int $id): ?array
    {
        $sql = "SELECT * FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?";
        return Database::fetch($sql, [$id]);
    }

    /**
     * Encontra um registro pelo campo especificado
     */
    public static function findBy(string $field, mixed $value): ?array
    {
        $sql = "SELECT * FROM " . static::$table . " WHERE {$field} = ?";
        return Database::fetch($sql, [$value]);
    }

    /**
     * Retorna todos os registros
     */
    public static function all(string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $sql = "SELECT * FROM " . static::$table . " ORDER BY {$orderBy} {$direction}";
        return Database::fetchAll($sql);
    }

    /**
     * Retorna registros com base em condições
     */
    public static function where(array $conditions, string $orderBy = null): array
    {
        $where = [];
        $params = [];
        
        foreach ($conditions as $field => $value) {
            if (is_array($value)) {
                $where[] = "{$field} {$value[0]} ?";
                $params[] = $value[1];
            } else {
                $where[] = "{$field} = ?";
                $params[] = $value;
            }
        }
        
        $sql = "SELECT * FROM " . static::$table . " WHERE " . implode(' AND ', $where);
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        return Database::fetchAll($sql, $params);
    }

    /**
     * Cria um novo registro
     */
    public static function create(array $data): int
    {
        return Database::insert(static::$table, $data);
    }

    /**
     * Atualiza um registro
     */
    public static function update(int $id, array $data): int
    {
        return Database::update(static::$table, $data, static::$primaryKey . ' = ?', [$id]);
    }

    /**
     * Remove um registro
     */
    public static function delete(int $id): int
    {
        return Database::delete(static::$table, static::$primaryKey . ' = ?', [$id]);
    }

    /**
     * Conta registros
     */
    public static function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM " . static::$table;
        $params = [];
        
        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $field => $value) {
                $where[] = "{$field} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $result = Database::fetch($sql, $params);
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Paginação de registros
     */
    public static function paginate(int $page = 1, int $perPage = 15, array $conditions = [], string $orderBy = 'id DESC'): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [];
        
        $sql = "SELECT * FROM " . static::$table;
        
        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $field => $value) {
                $where[] = "{$field} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}";
        
        $data = Database::fetchAll($sql, $params);
        $total = static::count($conditions);
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }
}
