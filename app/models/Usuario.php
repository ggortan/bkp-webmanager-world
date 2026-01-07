<?php
/**
 * Modelo Usuario
 */

namespace App\Models;

class Usuario extends Model
{
    protected static string $table = 'usuarios';

    /**
     * Encontra usuário pelo Azure ID
     */
    public static function findByAzureId(string $azureId): ?array
    {
        return self::findBy('azure_id', $azureId);
    }

    /**
     * Encontra usuário pelo e-mail
     */
    public static function findByEmail(string $email): ?array
    {
        return self::findBy('email', $email);
    }

    /**
     * Retorna usuário com informações de role
     */
    public static function findWithRole(int $id): ?array
    {
        $sql = "SELECT u.*, r.nome as role_nome, r.descricao as role_descricao, r.permissoes as role_permissoes
                FROM usuarios u
                LEFT JOIN usuarios_roles r ON u.role_id = r.id
                WHERE u.id = ?";
        
        return \App\Database::fetch($sql, [$id]);
    }

    /**
     * Lista todos os usuários com suas roles
     */
    public static function allWithRoles(string $orderBy = 'nome', string $direction = 'ASC'): array
    {
        $sql = "SELECT u.*, r.nome as role_nome, r.descricao as role_descricao
                FROM usuarios u
                LEFT JOIN usuarios_roles r ON u.role_id = r.id
                ORDER BY u.{$orderBy} {$direction}";
        
        return \App\Database::fetchAll($sql);
    }

    /**
     * Atualiza último login
     */
    public static function updateLastLogin(int $id): void
    {
        \App\Database::update('usuarios', ['ultimo_login' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
    }

    /**
     * Cria ou atualiza usuário via SSO
     */
    public static function createOrUpdateFromSso(array $userData): array
    {
        $existing = self::findByAzureId($userData['azure_id']);
        
        $data = [
            'azure_id' => $userData['azure_id'],
            'nome' => $userData['nome'],
            'email' => $userData['email'],
            'avatar' => $userData['avatar'] ?? null,
            'ultimo_login' => date('Y-m-d H:i:s')
        ];
        
        if ($existing) {
            self::update($existing['id'], $data);
            return self::find($existing['id']);
        }
        
        // Novo usuário - role padrão viewer (3)
        $data['role_id'] = 3;
        $id = self::create($data);
        
        return self::find($id);
    }
}
