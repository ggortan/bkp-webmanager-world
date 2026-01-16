<?php
/**
 * Helpers de Segurança
 */

namespace App\Helpers;

class Security
{
    /**
     * Escapa string para prevenir XSS
     */
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Alias para escape
     */
    public static function e(string $value): string
    {
        return self::escape($value);
    }

    /**
     * Gera token CSRF
     */
    public static function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Valida token CSRF
     */
    public static function validateCsrfToken(?string $token): bool
    {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Gera campo hidden com token CSRF
     */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="_token" value="' . self::generateCsrfToken() . '">';
    }

    /**
     * Gera uma API Key única
     */
    public static function generateApiKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Gera um token seguro
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Hash seguro para senhas
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    /**
     * Verifica senha
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Sanitiza entrada
     */
    public static function sanitize(mixed $value, string $type = 'string'): mixed
    {
        if ($value === null) {
            return null;
        }

        switch ($type) {
            case 'int':
            case 'integer':
                return filter_var($value, FILTER_SANITIZE_NUMBER_INT);
            
            case 'float':
            case 'double':
                return filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            
            case 'email':
                return filter_var($value, FILTER_SANITIZE_EMAIL);
            
            case 'url':
                return filter_var($value, FILTER_SANITIZE_URL);
            
            case 'string':
            default:
                return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
        }
    }

    /**
     * Verifica se IP é válido
     */
    public static function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Obtém IP do cliente
     */
    public static function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (self::isValidIp($ip)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
