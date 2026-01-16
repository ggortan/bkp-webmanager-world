<?php
/**
 * Modelo Configuracao
 */

namespace App\Models;

class Configuracao extends Model
{
    protected static string $table = 'configuracoes';

    /**
     * Obtém valor de uma configuração
     */
    public static function get(string $chave, mixed $default = null): mixed
    {
        $config = self::findBy('chave', $chave);
        
        if (!$config) {
            return $default;
        }
        
        return self::castValue($config['valor'], $config['tipo']);
    }

    /**
     * Define valor de uma configuração
     */
    public static function set(string $chave, mixed $valor, string $tipo = 'string'): void
    {
        $existing = self::findBy('chave', $chave);
        
        if (is_array($valor) || is_object($valor)) {
            $valor = json_encode($valor);
            $tipo = 'json';
        } elseif (is_bool($valor)) {
            $valor = $valor ? 'true' : 'false';
            $tipo = 'boolean';
        }
        
        if ($existing) {
            self::update($existing['id'], ['valor' => $valor, 'tipo' => $tipo]);
        } else {
            self::create(['chave' => $chave, 'valor' => $valor, 'tipo' => $tipo]);
        }
    }

    /**
     * Converte valor para o tipo correto
     */
    private static function castValue(mixed $valor, string $tipo): mixed
    {
        switch ($tipo) {
            case 'integer':
                return (int) $valor;
            case 'boolean':
                return $valor === 'true' || $valor === '1';
            case 'json':
                return json_decode($valor, true);
            default:
                return $valor;
        }
    }

    /**
     * Retorna todas as configurações como array associativo
     */
    public static function getAll(): array
    {
        $configs = self::all('chave');
        $result = [];
        
        foreach ($configs as $config) {
            $result[$config['chave']] = self::castValue($config['valor'], $config['tipo']);
        }
        
        return $result;
    }
}
