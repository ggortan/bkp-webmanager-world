<?php
/**
 * Teste direto do endpoint de telemetria
 * 
 * Este arquivo simula exatamente o que o endpoint /api/telemetry faz
 * Use para diagnosticar o erro 400
 * 
 * REMOVA EM PRODUÇÃO!
 */

header('Content-Type: application/json; charset=utf-8');

$result = [
    'timestamp' => date('c'),
    'step' => 'init',
    'success' => false,
];

try {
    // Step 1: Verifica método
    $result['step'] = 'check_method';
    $result['method'] = $_SERVER['REQUEST_METHOD'];
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $result['error'] = 'Método deve ser POST';
        echo json_encode($result, JSON_PRETTY_PRINT);
        exit;
    }
    
    // Step 2: Lê o body
    $result['step'] = 'read_body';
    $rawInput = file_get_contents('php://input');
    $result['body_length'] = strlen($rawInput);
    $result['body_preview'] = substr($rawInput, 0, 300);
    
    if (empty($rawInput)) {
        $result['error'] = 'Body está vazio';
        $result['content_type'] = $_SERVER['CONTENT_TYPE'] ?? 'not set';
        $result['content_length'] = $_SERVER['CONTENT_LENGTH'] ?? 'not set';
        echo json_encode($result, JSON_PRETTY_PRINT);
        exit;
    }
    
    // Step 3: Decodifica JSON
    $result['step'] = 'decode_json';
    $data = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $result['error'] = 'JSON inválido: ' . json_last_error_msg();
        $result['json_error_code'] = json_last_error();
        echo json_encode($result, JSON_PRETTY_PRINT);
        exit;
    }
    
    $result['json_valid'] = true;
    $result['json_fields'] = array_keys($data);
    
    // Step 4: Verifica campos obrigatórios
    $result['step'] = 'validate_fields';
    $hostName = $data['host_name'] ?? $data['hostname'] ?? $data['name'] ?? null;
    
    if (empty($hostName)) {
        $result['error'] = "Campo 'host_name' não encontrado";
        $result['received_fields'] = array_keys($data);
        echo json_encode($result, JSON_PRETTY_PRINT);
        exit;
    }
    
    $result['host_name'] = $hostName;
    
    // Step 5: Verifica autenticação
    $result['step'] = 'check_auth';
    
    $apiKey = null;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        $apiKey = $matches[1];
        $result['auth_method'] = 'Bearer';
    }
    
    if (empty($apiKey) && !empty($_SERVER['HTTP_X_API_KEY'])) {
        $apiKey = $_SERVER['HTTP_X_API_KEY'];
        $result['auth_method'] = 'X-API-Key';
    }
    
    if (empty($apiKey)) {
        $result['error'] = 'API Key não fornecida';
        echo json_encode($result, JSON_PRETTY_PRINT);
        exit;
    }
    
    $result['api_key_preview'] = substr($apiKey, 0, 8) . '...';
    
    // Step 6: Conecta ao banco
    $result['step'] = 'connect_db';
    
    if (!defined('ROOT_PATH')) {
        define('ROOT_PATH', dirname(__DIR__));
    }
    
    $dbConfig = require ROOT_PATH . '/config/database.php';
    
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $result['db_connected'] = true;
    
    // Step 7: Valida API Key
    $result['step'] = 'validate_api_key';
    
    $stmt = $pdo->prepare("SELECT id, nome, ativo FROM clientes WHERE api_key = ?");
    $stmt->execute([$apiKey]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        $result['error'] = 'API Key inválida';
        echo json_encode($result, JSON_PRETTY_PRINT);
        exit;
    }
    
    if (!$cliente['ativo']) {
        $result['error'] = 'Cliente inativo';
        echo json_encode($result, JSON_PRETTY_PRINT);
        exit;
    }
    
    $result['cliente_id'] = $cliente['id'];
    $result['cliente_nome'] = $cliente['nome'];
    
    // Step 8: Busca host
    $result['step'] = 'find_host';
    
    $stmt = $pdo->prepare("SELECT id, nome FROM hosts WHERE nome = ? AND cliente_id = ?");
    $stmt->execute([$hostName, $cliente['id']]);
    $host = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($host) {
        $result['host_found'] = true;
        $result['host_id'] = $host['id'];
        
        // Step 9: Atualiza host
        $result['step'] = 'update_host';
        
        $metricsJson = isset($data['metrics']) ? json_encode($data['metrics']) : null;
        
        $stmt = $pdo->prepare("UPDATE hosts SET 
            last_seen_at = NOW(), 
            online_status = 'online',
            ip = COALESCE(?, ip),
            telemetry_data = COALESCE(?, telemetry_data)
            WHERE id = ?");
        $stmt->execute([
            $data['ip'] ?? null,
            $metricsJson,
            $host['id']
        ]);
        
        $result['host_updated'] = true;
        
        // Step 10: Salva histórico
        if (!empty($data['metrics'])) {
            $result['step'] = 'save_history';
            
            $metrics = $data['metrics'];
            
            $stmt = $pdo->prepare("INSERT INTO telemetria_historico 
                (host_id, cliente_id, cpu_percent, memory_percent, disk_percent, uptime_seconds, data_completa) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $host['id'],
                $cliente['id'],
                $metrics['cpu_percent'] ?? 0,
                $metrics['memory_percent'] ?? 0,
                $metrics['disk_percent'] ?? 0,
                $metrics['uptime_seconds'] ?? null,
                json_encode($metrics)
            ]);
            
            $result['history_saved'] = true;
        }
    } else {
        $result['host_found'] = false;
        $result['message'] = 'Host não encontrado - seria criado automaticamente no endpoint real';
    }
    
    // Sucesso!
    $result['step'] = 'complete';
    $result['success'] = true;
    $result['message'] = 'Telemetria processada com sucesso';
    
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
    $result['error_file'] = $e->getFile();
    $result['error_line'] = $e->getLine();
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
