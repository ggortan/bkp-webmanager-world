<?php
/**
 * Router - Sistema de Roteamento
 */

namespace App;

class Router
{
    private static array $routes = [];
    private static array $middlewares = [];
    private static string $prefix = '';
    private static array $groupMiddlewares = [];

    /**
     * Registra uma rota GET
     */
    public static function get(string $path, array|callable $handler, array $middlewares = []): void
    {
        self::addRoute('GET', $path, $handler, $middlewares);
    }

    /**
     * Registra uma rota POST
     */
    public static function post(string $path, array|callable $handler, array $middlewares = []): void
    {
        self::addRoute('POST', $path, $handler, $middlewares);
    }

    /**
     * Registra uma rota PUT
     */
    public static function put(string $path, array|callable $handler, array $middlewares = []): void
    {
        self::addRoute('PUT', $path, $handler, $middlewares);
    }

    /**
     * Registra uma rota DELETE
     */
    public static function delete(string $path, array|callable $handler, array $middlewares = []): void
    {
        self::addRoute('DELETE', $path, $handler, $middlewares);
    }

    /**
     * Agrupa rotas com prefixo e middlewares
     */
    public static function group(array $options, callable $callback): void
    {
        $previousPrefix = self::$prefix;
        $previousMiddlewares = self::$groupMiddlewares;

        self::$prefix .= $options['prefix'] ?? '';
        self::$groupMiddlewares = array_merge(self::$groupMiddlewares, $options['middleware'] ?? []);

        $callback();

        self::$prefix = $previousPrefix;
        self::$groupMiddlewares = $previousMiddlewares;
    }

    /**
     * Adiciona uma rota
     */
    private static function addRoute(string $method, string $path, array|callable $handler, array $middlewares): void
    {
        $fullPath = self::$prefix . $path;
        $allMiddlewares = array_merge(self::$groupMiddlewares, $middlewares);

        // Converte parâmetros de rota para regex
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $fullPath);
        $pattern = '#^' . $pattern . '$#';

        self::$routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'pattern' => $pattern,
            'handler' => $handler,
            'middlewares' => $allMiddlewares
        ];
    }

    /**
     * Registra um middleware global
     */
    public static function middleware(string $name, callable|array $handler): void
    {
        self::$middlewares[$name] = $handler;
    }

    /**
     * Despacha a requisição
     */
    public static function dispatch(): void
    {
        global $config;
        
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Remove o prefixo base da aplicação (ex: /world/bkpmng)
        if (isset($config) && isset($config['app']['url'])) {
            $appUrl = $config['app']['url'];
            $basePath = parse_url($appUrl, PHP_URL_PATH);
            
            // Se a URI começa com o basePath, remove
            if ($basePath && $basePath !== '/' && strpos($uri, $basePath) === 0) {
                $uri = substr($uri, strlen($basePath));
            }
        }
        
        // Remove trailing slash
        $uri = rtrim($uri, '/') ?: '/';

        foreach (self::$routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                // Extrai parâmetros nomeados
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Executa middlewares
                foreach ($route['middlewares'] as $middlewareName) {
                    if (isset(self::$middlewares[$middlewareName])) {
                        $result = call_user_func(self::$middlewares[$middlewareName]);
                        if ($result === false) {
                            return;
                        }
                    }
                }

                // Executa o handler
                if (is_callable($route['handler'])) {
                    call_user_func_array($route['handler'], $params);
                } elseif (is_array($route['handler'])) {
                    [$class, $method] = $route['handler'];
                    $controller = new $class();
                    call_user_func_array([$controller, $method], $params);
                }

                return;
            }
        }

        // Rota não encontrada
        http_response_code(404);
        if (self::isApiRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Rota não encontrada', 'status' => 404]);
        } else {
            require dirname(__DIR__) . '/app/Views/errors/404.php';
        }
    }

    /**
     * Verifica se é uma requisição de API
     */
    private static function isApiRequest(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($uri, '/api/') === 0;
    }

    /**
     * Obtém o caminho base da aplicação
     */
    public static function basePath(): string
    {
        global $config;
        
        if (isset($config) && isset($config['app']['url'])) {
            $basePath = parse_url($config['app']['url'], PHP_URL_PATH);
            return $basePath ? rtrim($basePath, '/') : '';
        }
        
        return '';
    }

    /**
     * Gera URL para uma rota (caminho relativo com base path)
     */
    public static function path(string $path): string
    {
        return self::basePath() . $path;
    }

    /**
     * Gera URL completa para uma rota
     */
    public static function url(string $path): string
    {
        global $config;
        
        $baseUrl = isset($config) && isset($config['app']['url']) 
            ? rtrim($config['app']['url'], '/') 
            : 'http://localhost';
        
        return $baseUrl . $path;
    }
}
