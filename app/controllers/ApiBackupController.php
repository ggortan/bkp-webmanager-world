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
}
