<?php
/**
 * Carregador de variáveis de ambiente
 * 
 * Este arquivo carrega as variáveis do arquivo .env
 */

class Env
{
    private static array $variables = [];
    private static bool $loaded = false;

    /**
     * Carrega as variáveis de ambiente do arquivo .env
     */
    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        $envFile = rtrim($path, '/') . '/.env';
        
        if (!file_exists($envFile)) {
            // Se o arquivo .env não existe, usa as variáveis de ambiente do servidor
            // ou os valores padrão das configurações
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Ignora comentários
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Processa a linha
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                
                // Remove aspas
                $value = trim($value, '"\'');
                
                self::$variables[$name] = $value;
                $_ENV[$name] = $value;
                putenv("{$name}={$value}");
            }
        }

        self::$loaded = true;
    }

    /**
     * Obtém uma variável de ambiente
     */
    public static function get(string $key, $default = null): mixed
    {
        return self::$variables[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }

    /**
     * Verifica se uma variável existe
     */
    public static function has(string $key): bool
    {
        return isset(self::$variables[$key]) || isset($_ENV[$key]) || getenv($key) !== false;
    }
}
