<?php
/**
 * Debug de requisições API
 * 
 * REMOVA ESTE ARQUIVO EM PRODUÇÃO!
 * 
 * Este arquivo mostra exatamente o que a API está recebendo,
 * útil para debug de problemas de comunicação.
 * 
 * Acesse: https://seusite.com/world/bkpmng/debug-api.php
 */

header('Content-Type: application/json; charset=utf-8');

// Captura todos os dados da requisição
$debug = [
    'timestamp' => date('c'),
    'request' => [
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'],
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
        'content_length' => $_SERVER['CONTENT_LENGTH'] ?? null,
    ],
    'headers' => [],
    'body' => [],
    'php' => [
        'version' => PHP_VERSION,
        'memory_limit' => ini_get('memory_limit'),
        'post_max_size' => ini_get('post_max_size'),
        'input_encoding' => ini_get('default_charset'),
    ]
];

// Captura headers relevantes
$headersList = [
    'HTTP_AUTHORIZATION',
    'HTTP_X_API_KEY',
    'HTTP_ACCEPT',
    'HTTP_CONTENT_TYPE',
    'HTTP_HOST',
    'HTTP_USER_AGENT',
];

foreach ($headersList as $header) {
    if (isset($_SERVER[$header])) {
        $value = $_SERVER[$header];
        // Mascara tokens/chaves
        if (strpos($header, 'AUTH') !== false || strpos($header, 'KEY') !== false) {
            if (strlen($value) > 16) {
                $value = substr($value, 0, 16) . '...';
            }
        }
        $debug['headers'][$header] = $value;
    }
}

// Captura body
$rawInput = file_get_contents('php://input');
$debug['body']['raw_length'] = strlen($rawInput);
$debug['body']['raw_preview'] = substr($rawInput, 0, 500);

// Tenta decodificar JSON
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $debug['body']['json_valid'] = true;
        $debug['body']['json_fields'] = array_keys($decoded);
        $debug['body']['json_data'] = $decoded;
    } else {
        $debug['body']['json_valid'] = false;
        $debug['body']['json_error'] = json_last_error_msg();
        $debug['body']['json_error_code'] = json_last_error();
    }
}

// Se for POST, mostra $_POST também
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $debug['post_data'] = $_POST;
}

// Testa conexão com banco
$debug['database'] = ['tested' => false];
try {
    if (!defined('ROOT_PATH')) {
        define('ROOT_PATH', dirname(__DIR__));
    }
    
    // Carrega configuração do banco
    $dbConfig = require ROOT_PATH . '/config/database.php';
    
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
    
    $debug['database'] = [
        'tested' => true,
        'connected' => true,
        'host' => $dbConfig['host'],
        'database' => $dbConfig['database'],
    ];
    
    // Testa se a tabela hosts existe
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM hosts");
    $debug['database']['hosts_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
} catch (Exception $e) {
    $debug['database'] = [
        'tested' => true,
        'connected' => false,
        'error' => $e->getMessage(),
    ];
}

// Simula processamento de telemetria se enviado via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($decoded)) {
    $debug['telemetry_simulation'] = [];
    
    // Verifica campos obrigatórios
    $hostName = $decoded['host_name'] ?? $decoded['hostname'] ?? $decoded['name'] ?? null;
    
    if (empty($hostName)) {
        $debug['telemetry_simulation']['valid'] = false;
        $debug['telemetry_simulation']['error'] = "Campo 'host_name' não encontrado";
        $debug['telemetry_simulation']['received_fields'] = array_keys($decoded);
    } else {
        $debug['telemetry_simulation']['valid'] = true;
        $debug['telemetry_simulation']['host_name'] = $hostName;
        $debug['telemetry_simulation']['message'] = "Telemetria seria processada para host: $hostName";
    }
    
    // Verifica autenticação
    $apiKey = null;
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/^Bearer\s+(.+)$/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            $apiKey = $matches[1];
        }
    }
    if (empty($apiKey) && isset($_SERVER['HTTP_X_API_KEY'])) {
        $apiKey = $_SERVER['HTTP_X_API_KEY'];
    }
    
    if ($apiKey) {
        $debug['auth_simulation'] = [
            'api_key_found' => true,
            'api_key_preview' => substr($apiKey, 0, 8) . '...',
        ];
        
        // Testa se a key existe no banco
        try {
            if (isset($pdo)) {
                $stmt = $pdo->prepare("SELECT id, nome FROM clientes WHERE api_key = ? AND ativo = 1");
                $stmt->execute([$apiKey]);
                $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($cliente) {
                    $debug['auth_simulation']['cliente_found'] = true;
                    $debug['auth_simulation']['cliente_id'] = $cliente['id'];
                    $debug['auth_simulation']['cliente_nome'] = $cliente['nome'];
                } else {
                    $debug['auth_simulation']['cliente_found'] = false;
                    $debug['auth_simulation']['message'] = 'API Key não encontrada ou cliente inativo';
                }
            }
        } catch (Exception $e) {
            $debug['auth_simulation']['error'] = $e->getMessage();
        }
    } else {
        $debug['auth_simulation'] = [
            'api_key_found' => false,
            'message' => 'Nenhum header de autenticação encontrado',
        ];
    }
}

echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
