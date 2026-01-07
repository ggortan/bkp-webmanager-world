<?php
/**
 * Modelo ConfiguracaoEmail
 */

namespace App\Models;

class ConfiguracaoEmail extends Model
{
    protected static string $table = 'configuracoes_email';

    /**
     * Obtém configuração de e-mail de um cliente
     */
    public static function byCliente(?int $clienteId, string $tipo): ?array
    {
        $sql = "SELECT * FROM configuracoes_email WHERE cliente_id ";
        $params = [];
        
        if ($clienteId === null) {
            $sql .= "IS NULL";
        } else {
            $sql .= "= ?";
            $params[] = $clienteId;
        }
        
        $sql .= " AND tipo = ?";
        $params[] = $tipo;
        
        return \App\Database::fetch($sql, $params);
    }

    /**
     * Lista todas configurações ativas
     */
    public static function allAtivas(): array
    {
        $sql = "SELECT ce.*, c.nome as cliente_nome
                FROM configuracoes_email ce
                LEFT JOIN clientes c ON ce.cliente_id = c.id
                WHERE ce.ativo = 1
                ORDER BY c.nome, ce.tipo";
        
        return \App\Database::fetchAll($sql);
    }

    /**
     * Lista configurações para envio automático
     */
    public static function forAutoSend(string $frequencia): array
    {
        $sql = "SELECT ce.*, c.nome as cliente_nome, c.email as cliente_email
                FROM configuracoes_email ce
                LEFT JOIN clientes c ON ce.cliente_id = c.id
                WHERE ce.ativo = 1 AND ce.frequencia = ?";
        
        return \App\Database::fetchAll($sql, [$frequencia]);
    }

    /**
     * Atualiza último envio
     */
    public static function updateUltimoEnvio(int $id): void
    {
        self::update($id, ['ultimo_envio' => date('Y-m-d H:i:s')]);
    }
}
