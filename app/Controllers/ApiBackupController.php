<?php
/**
 * Controller da API de Backup
 * 
 * Endpoint para recebimento de dados de backup dos servidores
 */

namespace App\Controllers;

use App\Services\BackupService;
use App\Services\LogService;
use App\Helpers\Security;

class ApiBackupController extends Controller
{
    private BackupService $backupService;

    public function __construct()
    {
        $this->backupService = new BackupService();
    }

    /**
     * Registra execução de backup
     * POST /api/backup
     */
    public function store(): void
    {
        // Cliente é injetado pelo middleware ApiAuthMiddleware
        $cliente = $_REQUEST['_cliente'] ?? null;
        
        if (!$cliente) {
            $this->json([
                'success' => false,
                'error' => 'Cliente não identificado',
                'status' => 401
            ], 401);
            return;
        }
        
        // Obtém dados do body
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            LogService::api('JSON inválido recebido', [
                'cliente_id' => $cliente['id'],
                'json_error' => json_last_error_msg(),
                'raw' => substr($json, 0, 500)
            ]);
            
            $this->json([
                'success' => false,
                'error' => 'JSON inválido: ' . json_last_error_msg(),
                'status' => 400
            ], 400);
            return;
        }
        
        // Valida dados
        $errors = $this->backupService->validarDadosApi($data);
        
        if (!empty($errors)) {
            LogService::api('Dados inválidos recebidos', [
                'cliente_id' => $cliente['id'],
                'errors' => $errors
            ]);
            
            $this->json([
                'success' => false,
                'error' => 'Dados inválidos',
                'errors' => $errors,
                'status' => 422
            ], 422);
            return;
        }
        
        try {
            $result = $this->backupService->registrarExecucao($data, $cliente);
            
            $this->json([
                'success' => true,
                'message' => $result['message'],
                'execucao_id' => $result['execucao_id'],
                'status' => 201
            ], 201);
            
        } catch (\Exception $e) {
            LogService::error('api', 'Erro ao processar backup', [
                'cliente_id' => $cliente['id'],
                'error' => $e->getMessage()
            ]);
            
            $this->json([
                'success' => false,
                'error' => 'Erro interno ao processar backup',
                'status' => 500
            ], 500);
        }
    }

    /**
     * Verifica status da API
     * GET /api/status
     */
    public function status(): void
    {
        // Resposta mínima - sem conexão com banco para diagnóstico
        $response = [
            'success' => true,
            'status' => 'online',
            'version' => '1.0.0',
            'timestamp' => date('c'),
            'php_version' => PHP_VERSION
        ];
        
        // Tenta verificar banco de dados
        try {
            $pdo = \App\Database::getInstance();
            $stmt = $pdo->query('SELECT 1');
            $response['database'] = 'connected';
        } catch (\Exception $e) {
            $response['database'] = 'error';
            $response['database_error'] = $e->getMessage();
        }
        
        $this->json($response);
    }

    /**
     * Retorna informações do cliente autenticado
     * GET /api/me
     */
    public function me(): void
    {
        $cliente = $_REQUEST['_cliente'] ?? null;
        
        if (!$cliente) {
            $this->json([
                'success' => false,
                'error' => 'Cliente não identificado',
                'status' => 401
            ], 401);
            return;
        }
        
        $this->json([
            'success' => true,
            'cliente' => [
                'id' => $cliente['id'],
                'identificador' => $cliente['identificador'],
                'nome' => $cliente['nome'],
                'ativo' => (bool) $cliente['ativo']
            ]
        ]);
    }

    /**
     * Retorna rotinas do cliente autenticado
     * GET /api/rotinas
     */
    public function rotinas(): void
    {
        $cliente = $_REQUEST['_cliente'] ?? null;
        
        if (!$cliente) {
            $this->json([
                'success' => false,
                'error' => 'Cliente não identificado',
                'status' => 401
            ], 401);
            return;
        }
        
        $rotinas = \App\Models\RotinaBackup::ativasByCliente($cliente['id']);
        
        // Formata rotinas para a resposta
        $result = array_map(function($rotina) {
            $hostInfo = null;
            if (!empty($rotina['host_info'])) {
                $hostInfo = is_string($rotina['host_info']) ? 
                    json_decode($rotina['host_info'], true) : $rotina['host_info'];
            }
            
            return [
                'id' => $rotina['id'],
                'routine_key' => $rotina['routine_key'],
                'nome' => $rotina['nome'],
                'tipo' => $rotina['tipo'],
                'destino' => $rotina['destino'],
                'agendamento' => $rotina['agendamento'],
                'host_info' => $hostInfo,
                'ativa' => (bool) $rotina['ativa']
            ];
        }, $rotinas);
        
        $this->json([
            'success' => true,
            'rotinas' => $result,
            'total' => count($result)
        ]);
    }

    /**
     * Recebe telemetria (heartbeat) do host
     * POST /api/telemetry ou POST /api/heartbeat
     */
    public function telemetry(): void
    {
        $cliente = $_REQUEST['_cliente'] ?? null;
        
        if (!$cliente) {
            $this->json([
                'success' => false,
                'error' => 'Cliente não identificado',
                'status' => 401
            ], 401);
            return;
        }
        
        // Obtém dados do body
        $json = file_get_contents('php://input');
        
        // Log para debug
        LogService::log('debug', 'api', 'Telemetria recebida (raw)', [
            'cliente_id' => $cliente['id'],
            'content_length' => strlen($json),
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
            'json_preview' => substr($json, 0, 200)
        ]);
        
        // Verifica se o body está vazio
        if (empty($json)) {
            LogService::log('error', 'api', 'Body vazio na telemetria', [
                'cliente_id' => $cliente['id'],
                'method' => $_SERVER['REQUEST_METHOD'],
                'content_length_header' => $_SERVER['CONTENT_LENGTH'] ?? 'not set'
            ]);
            
            $this->json([
                'success' => false,
                'error' => 'Body da requisição está vazio',
                'status' => 400
            ], 400);
            return;
        }
        
        // Tenta decodificar JSON
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            LogService::log('error', 'api', 'JSON inválido na telemetria', [
                'cliente_id' => $cliente['id'],
                'json_error' => json_last_error_msg(),
                'raw_preview' => substr($json, 0, 500)
            ]);
            
            $this->json([
                'success' => false,
                'error' => 'JSON inválido: ' . json_last_error_msg(),
                'status' => 400
            ], 400);
            return;
        }
        
        // Aceita múltiplos nomes de campo para host_name
        $hostName = $data['host_name'] ?? $data['hostname'] ?? $data['name'] ?? null;
        
        // Valida campos obrigatórios
        if (empty($hostName)) {
            $this->json([
                'success' => false,
                'error' => "O campo 'host_name' é obrigatório",
                'received_fields' => array_keys($data),
                'status' => 422
            ], 422);
            return;
        }
        
        // Usa host_name encontrado
        $data['host_name'] = $hostName;
        
        try {
            $hostNome = $data['host_name'];
            
            // Busca ou cria o host
            $host = \App\Models\Host::findByNomeAndCliente($hostNome, $cliente['id']);
            
            if (!$host) {
                // Cria host automaticamente
                $newHostData = [
                    'cliente_id' => $cliente['id'],
                    'nome' => $hostNome,
                    'hostname' => $data['hostname'] ?? $hostNome,
                    'ip' => $data['ip'] ?? null,
                    'sistema_operacional' => $data['os'] ?? $data['sistema_operacional'] ?? null,
                    'tipo' => $data['tipo'] ?? 'server',
                    'ativo' => 1,
                    'online_status' => 'online',
                    'last_seen_at' => date('Y-m-d H:i:s'),
                    'telemetry_enabled' => 1,
                    'observacoes' => 'Criado automaticamente via telemetria'
                ];
                
                // Adiciona dados de telemetria se fornecidos
                if (!empty($data['metrics'])) {
                    $newHostData['telemetry_data'] = json_encode($data['metrics']);
                }
                
                $hostId = \App\Models\Host::create($newHostData);
                $host = \App\Models\Host::find($hostId);
                
                LogService::api('Host criado via telemetria', [
                    'cliente_id' => $cliente['id'],
                    'host_id' => $hostId,
                    'host_nome' => $hostNome
                ]);
            } else {
                // Atualiza host existente
                $updateData = [
                    'last_seen_at' => date('Y-m-d H:i:s'),
                    'online_status' => 'online'
                ];
                
                // Atualiza IP se mudou
                if (!empty($data['ip']) && $data['ip'] !== $host['ip']) {
                    $updateData['ip'] = $data['ip'];
                }
                
                // Atualiza hostname se mudou
                if (!empty($data['hostname']) && $data['hostname'] !== $host['hostname']) {
                    $updateData['hostname'] = $data['hostname'];
                }
                
                // Atualiza SO se fornecido
                if (!empty($data['os']) || !empty($data['sistema_operacional'])) {
                    $so = $data['os'] ?? $data['sistema_operacional'];
                    if ($so !== $host['sistema_operacional']) {
                        $updateData['sistema_operacional'] = $so;
                    }
                }
                
                // Atualiza métricas de telemetria
                if (!empty($data['metrics'])) {
                    $updateData['telemetry_data'] = json_encode($data['metrics']);
                }
                
                \App\Models\Host::update($host['id'], $updateData);
            }
            
            // Salva histórico de telemetria se métricas foram fornecidas
            if (!empty($data['metrics'])) {
                $metrics = $data['metrics'];
                
                $historicoData = [
                    'host_id' => $host['id'],
                    'cliente_id' => $cliente['id'],
                    'cpu_percent' => $metrics['cpu_percent'] ?? 0,
                    'memory_percent' => $metrics['memory_percent'] ?? 0,
                    'disk_percent' => $metrics['disk_percent'] ?? 0,
                    'uptime_seconds' => $metrics['uptime_seconds'] ?? null,
                    'data_completa' => json_encode($metrics)
                ];
                
                $sql = "INSERT INTO telemetria_historico 
                        (host_id, cliente_id, cpu_percent, memory_percent, disk_percent, uptime_seconds, data_completa) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                
                \App\Database::execute($sql, [
                    $historicoData['host_id'],
                    $historicoData['cliente_id'],
                    $historicoData['cpu_percent'],
                    $historicoData['memory_percent'],
                    $historicoData['disk_percent'],
                    $historicoData['uptime_seconds'],
                    $historicoData['data_completa']
                ]);
                
                // Aplica retenção de dados (se configurado)
                $this->aplicarRetencaoTelemetria($host['id']);
            }
            
            // Auto-vincula rotinas sem host a este host (baseado no host_info)
            $this->autoVincularRotinas($host, $cliente['id']);
            
            $this->json([
                'success' => true,
                'message' => 'Telemetria recebida',
                'host_id' => $host['id'],
                'host_name' => $host['nome'],
                'status' => 'online'
            ]);
            
        } catch (\Exception $e) {
            LogService::error('api', 'Erro ao processar telemetria', [
                'cliente_id' => $cliente['id'],
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            $this->json([
                'success' => false,
                'error' => 'Erro ao processar telemetria',
                'status' => 500
            ], 500);
        }
    }
    
    /**
     * Aplica retenção de telemetria para um host
     */
    private function aplicarRetencaoTelemetria(int $hostId): void
    {
        try {
            $diasRetencao = (int) (\App\Models\Configuracao::get('dias_retencao_telemetria') ?? 0);
            
            // Se retenção é 0, não deleta nada (manter sempre)
            if ($diasRetencao <= 0) {
                return;
            }
            
            $dataLimite = date('Y-m-d H:i:s', strtotime("-{$diasRetencao} days"));
            
            $sql = "DELETE FROM telemetria_historico WHERE host_id = ? AND created_at < ?";
            \App\Database::execute($sql, [$hostId, $dataLimite]);
            
        } catch (\Exception $e) {
            LogService::warning('api', 'Falha ao aplicar retenção de telemetria', [
                'host_id' => $hostId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Auto-vincula rotinas sem host ao host baseado no host_info
     */
    private function autoVincularRotinas(array $host, int $clienteId): void
    {
        try {
            // Busca rotinas do cliente que não têm host vinculado mas têm host_info
            $sql = "SELECT * FROM rotinas_backup 
                    WHERE cliente_id = ? 
                    AND host_id IS NULL 
                    AND host_info IS NOT NULL 
                    AND host_info != ''";
            
            $rotinasSemHost = \App\Database::fetchAll($sql, [$clienteId]);
            
            foreach ($rotinasSemHost as $rotina) {
                $hostInfo = json_decode($rotina['host_info'], true);
                
                if (!$hostInfo) {
                    continue;
                }
                
                // Verifica se o nome do host no host_info corresponde ao host atual
                $hostNome = $hostInfo['name'] ?? $hostInfo['nome'] ?? null;
                
                if ($hostNome && strtolower($hostNome) === strtolower($host['nome'])) {
                    // Vincula a rotina ao host
                    \App\Models\RotinaBackup::update($rotina['id'], ['host_id' => $host['id']]);
                    
                    LogService::api('Rotina auto-vinculada ao host via telemetria', [
                        'rotina_id' => $rotina['id'],
                        'rotina_nome' => $rotina['nome'],
                        'host_id' => $host['id'],
                        'host_nome' => $host['nome']
                    ]);
                }
            }
        } catch (\Exception $e) {
            LogService::warning('api', 'Falha ao auto-vincular rotinas', [
                'host_id' => $host['id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Retorna hosts do cliente autenticado
     * GET /api/hosts
     */
    public function hosts(): void
    {
        $cliente = $_REQUEST['_cliente'] ?? null;
        
        if (!$cliente) {
            $this->json([
                'success' => false,
                'error' => 'Cliente não identificado',
                'status' => 401
            ], 401);
            return;
        }
        
        $hosts = \App\Models\Host::ativosByCliente($cliente['id']);
        
        // Formata hosts para a resposta
        $result = array_map(function($host) {
            return [
                'id' => $host['id'],
                'nome' => $host['nome'],
                'hostname' => $host['hostname'],
                'ip' => $host['ip'],
                'sistema_operacional' => $host['sistema_operacional'],
                'tipo' => $host['tipo'],
                'online_status' => $host['online_status'] ?? 'unknown',
                'last_seen_at' => $host['last_seen_at'],
                'telemetry_enabled' => (bool) ($host['telemetry_enabled'] ?? true)
            ];
        }, $hosts);
        
        $this->json([
            'success' => true,
            'hosts' => $result,
            'total' => count($result)
        ]);
    }
}
