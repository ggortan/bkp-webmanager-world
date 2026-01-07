<?php
/**
 * Modelo UsuarioRole
 */

namespace App\Models;

class UsuarioRole extends Model
{
    protected static string $table = 'usuarios_roles';

    /**
     * Encontra role pelo nome
     */
    public static function findByNome(string $nome): ?array
    {
        return self::findBy('nome', $nome);
    }

    /**
     * Retorna todas as roles para select
     */
    public static function forSelect(): array
    {
        $roles = self::all('nome');
        $result = [];
        
        foreach ($roles as $role) {
            $result[$role['id']] = $role['nome'] . ' - ' . $role['descricao'];
        }
        
        return $result;
    }
}
