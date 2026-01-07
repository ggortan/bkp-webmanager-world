<?php
/**
 * Controller Base
 * 
 * Classe abstrata para todos os controllers
 */

namespace App\Controllers;

class Controller
{
    protected array $data = [];

    /**
     * Renderiza uma view
     */
    protected function view(string $view, array $data = []): void
    {
        $data = array_merge($this->data, $data);
        extract($data);
        
        $viewPath = dirname(__DIR__, 2) . '/app/views/' . str_replace('.', '/', $view) . '.php';
        
        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View não encontrada: {$view}");
        }
        
        require $viewPath;
    }

    /**
     * Renderiza uma view dentro do layout
     */
    protected function render(string $view, array $data = [], string $layout = 'layouts/main'): void
    {
        $data = array_merge($this->data, $data);
        
        // Captura o conteúdo da view
        ob_start();
        extract($data);
        $viewPath = dirname(__DIR__, 2) . '/app/views/' . str_replace('.', '/', $view) . '.php';
        
        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View não encontrada: {$view}");
        }
        
        require $viewPath;
        $content = ob_get_clean();
        
        // Renderiza o layout com o conteúdo
        $data['content'] = $content;
        extract($data);
        
        $layoutPath = dirname(__DIR__, 2) . '/app/views/' . str_replace('.', '/', $layout) . '.php';
        
        if (!file_exists($layoutPath)) {
            throw new \RuntimeException("Layout não encontrado: {$layout}");
        }
        
        require $layoutPath;
    }

    /**
     * Retorna uma resposta JSON
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Redireciona para outra URL
     */
    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    /**
     * Cache para dados de entrada
     */
    private static ?array $inputCache = null;

    /**
     * Retorna dados do POST
     */
    protected function input(string $key = null, mixed $default = null): mixed
    {
        // Usa cache se já foi lido
        if (self::$inputCache === null) {
            self::$inputCache = array_merge($_GET, $_POST);
            
            // Tenta pegar JSON do body
            $json = file_get_contents('php://input');
            if ($json) {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    self::$inputCache = array_merge(self::$inputCache, $decoded);
                }
            }
        }
        
        if ($key === null) {
            return self::$inputCache;
        }
        
        return self::$inputCache[$key] ?? $default;
    }

    /**
     * Valida dados de entrada
     */
    protected function validate(array $data, array $rules): array
    {
        $errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $fieldRules = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            
            foreach ($fieldRules as $rule) {
                $params = [];
                if (strpos($rule, ':') !== false) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }
                
                $error = $this->validateRule($field, $value, $rule, $params);
                if ($error) {
                    $errors[$field][] = $error;
                }
            }
        }
        
        return $errors;
    }

    /**
     * Valida uma regra específica
     */
    private function validateRule(string $field, mixed $value, string $rule, array $params): ?string
    {
        switch ($rule) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    return "O campo {$field} é obrigatório";
                }
                break;
                
            case 'email':
                if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return "O campo {$field} deve ser um e-mail válido";
                }
                break;
                
            case 'min':
                if ($value && strlen($value) < (int)$params[0]) {
                    return "O campo {$field} deve ter no mínimo {$params[0]} caracteres";
                }
                break;
                
            case 'max':
                if ($value && strlen($value) > (int)$params[0]) {
                    return "O campo {$field} deve ter no máximo {$params[0]} caracteres";
                }
                break;
                
            case 'numeric':
                if ($value && !is_numeric($value)) {
                    return "O campo {$field} deve ser numérico";
                }
                break;
                
            case 'in':
                if ($value && !in_array($value, $params)) {
                    return "O campo {$field} deve ser um dos valores: " . implode(', ', $params);
                }
                break;
        }
        
        return null;
    }

    /**
     * Define flash message
     */
    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'][$type] = $message;
    }

    /**
     * Obtém e remove flash message
     */
    protected function getFlash(): array
    {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }
}
