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
        $this->json([
            'success' => true,
            'status' => 'online',
            'version' => '1.0.0',
            'timestamp' => date('c')
        ]);
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
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->json([
                'success' => false,
                'error' => 'JSON inválido',
                'status' => 400
            ], 400);
            return;
        }
        
        // Valida campos obrigatórios
        if (empty($data['host_name'])) {
            $this->json([
                'success' => false,
                'error' => "O campo 'host_name' é obrigatório",
                'status' => 422
            ], 422);
            return;
        }
        
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
