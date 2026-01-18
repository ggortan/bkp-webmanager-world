<?php
/**
 * Funções Helper Globais
 */

if (!function_exists('url')) {
    /**
     * Gera URL completa para uma rota
     */
    function url(string $path = ''): string
    {
        return \App\Router::url($path);
    }
}

if (!function_exists('path')) {
    /**
     * Gera caminho relativo com base path da aplicação
     */
    function path(string $route = ''): string
    {
        return \App\Router::path($route);
    }
}

if (!function_exists('asset')) {
    /**
     * Gera URL para assets estáticos
     */
    function asset(string $path): string
    {
        return \App\Router::path('/' . ltrim($path, '/'));
    }
}

if (!function_exists('config')) {
    /**
     * Obtém valor de configuração
     */
    function config(string $key, mixed $default = null): mixed
    {
        global $config;
        
        $keys = explode('.', $key);
        $value = $config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Gera campo hidden com token CSRF
     */
    function csrf_field(): string
    {
        return \App\Helpers\Security::csrfField();
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Obtém token CSRF
     */
    function csrf_token(): string
    {
        return \App\Helpers\Security::generateCsrfToken();
    }
}

if (!function_exists('old')) {
    /**
     * Obtém valor antigo de input (para repopular formulários)
     */
    function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['old_input'][$key] ?? $default;
    }
}

if (!function_exists('e')) {
    /**
     * Escapa string para HTML
     */
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('format_bytes')) {
    /**
     * Formata tamanho em bytes para exibição
     */
    function format_bytes(?int $bytes): string
    {
        if ($bytes === null || $bytes === 0) {
            return '-';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
