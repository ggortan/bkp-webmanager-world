<?php
/**
 * Classe de Conexão com Banco de Dados
 * 
 * Implementa padrão Singleton para conexão PDO
 */

namespace App;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    /**
     * Obtém a instância da conexão PDO
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::connect();
        }
        return self::$instance;
    }

    /**
     * Configura as credenciais do banco
     */
    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Estabelece a conexão com o banco de dados
     */
    private static function connect(): void
    {
        if (empty(self::$config)) {
            throw new RuntimeException('Configuração do banco de dados não definida');
        }

        $config = self::$config;
        
        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $config['driver'] ?? 'mysql',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset'] ?? 'utf8mb4'
        );

        try {
            self::$instance = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options'] ?? [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            throw new RuntimeException('Erro ao conectar ao banco de dados: ' . $e->getMessage());
        }
    }

    /**
     * Executa uma query com prepared statements
     */
    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Obtém um único registro
     */
    public static function fetch(string $sql, array $params = []): ?array
    {
        $result = self::query($sql, $params)->fetch();
        return $result ?: null;
    }

    /**
     * Obtém todos os registros
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Insere um registro e retorna o ID
     */
    public static function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        self::query($sql, array_values($data));
        
        return (int) self::getInstance()->lastInsertId();
    }

    /**
     * Atualiza registros
     */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(' = ?, ', array_keys($data)) . ' = ?';
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";
        
        $params = array_merge(array_values($data), $whereParams);
        return self::query($sql, $params)->rowCount();
    }

    /**
     * Executa uma query (alias para query, usado para INSERT/UPDATE/DELETE com SQL puro)
     */
    public static function execute(string $sql, array $params = []): \PDOStatement
    {
        return self::query($sql, $params);
    }

    /**
     * Remove registros
     */
    public static function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return self::query($sql, $params)->rowCount();
    }

    /**
     * Inicia uma transação
     */
    public static function beginTransaction(): bool
    {
        return self::getInstance()->beginTransaction();
    }

    /**
     * Confirma uma transação
     */
    public static function commit(): bool
    {
        return self::getInstance()->commit();
    }

    /**
     * Reverte uma transação
     */
    public static function rollBack(): bool
    {
        return self::getInstance()->rollBack();
    }

    /**
     * Fecha a conexão
     */
    public static function close(): void
    {
        self::$instance = null;
    }
}
