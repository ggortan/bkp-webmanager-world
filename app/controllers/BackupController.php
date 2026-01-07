<?php
/**
 * Controller de Backups
 */

namespace App\Controllers;

use App\Models\ExecucaoBackup;
use App\Models\Cliente;
use App\Models\Servidor;
use App\Services\AuthService;
use App\Services\BackupService;

class BackupController extends Controller
{
    private AuthService $authService;
    private BackupService $backupService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->backupService = new BackupService();
        $this->data['user'] = $this->authService->getUser();
    }

    /**
     * Lista de execuções de backup
     */
    public function index(): void
    {
        $filters = [
            'cliente_id' => $_GET['cliente_id'] ?? null,
            'servidor_id' => $_GET['servidor_id'] ?? null,
            'status' => $_GET['status'] ?? null,
            'data_inicio' => $_GET['data_inicio'] ?? null,
            'data_fim' => $_GET['data_fim'] ?? null
        ];
        
        $page = (int) ($_GET['page'] ?? 1);
        $result = ExecucaoBackup::filter(array_filter($filters), $page, 20);
        
        $this->data['title'] = 'Histórico de Backups';
        $this->data['execucoes'] = $result['data'];
        $this->data['pagination'] = [
            'total' => $result['total'],
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'total_pages' => $result['total_pages']
        ];
        $this->data['filters'] = $filters;
        $this->data['clientes'] = Cliente::forSelect();
        
        $this->render('backups/index', $this->data);
    }

    /**
     * Exibe detalhes de uma execução
     */
    public function show(int $id): void
    {
        $sql = "SELECT e.*, r.nome as rotina_nome, r.tipo as rotina_tipo,
                       s.nome as servidor_nome, s.hostname,
                       c.nome as cliente_nome, c.identificador as cliente_identificador
                FROM execucoes_backup e
                LEFT JOIN rotinas_backup r ON e.rotina_id = r.id
                LEFT JOIN servidores s ON e.servidor_id = s.id
                LEFT JOIN clientes c ON e.cliente_id = c.id
                WHERE e.id = ?";
        
        $execucao = \App\Database::fetch($sql, [$id]);
        
        if (!$execucao) {
            $this->flash('error', 'Execução não encontrada');
            $this->redirect('/backups');
            return;
        }
        
        // Decodifica detalhes JSON
        if (!empty($execucao['detalhes'])) {
            $execucao['detalhes_array'] = json_decode($execucao['detalhes'], true);
        }
        
        $this->data['title'] = 'Detalhes da Execução';
        $this->data['execucao'] = $execucao;
        
        $this->render('backups/show', $this->data);
    }

    /**
     * Obtém servidores de um cliente (AJAX)
     */
    public function servidoresByCliente(int $clienteId): void
    {
        $servidores = Servidor::ativosByCliente($clienteId);
        
        $this->json([
            'success' => true,
            'servidores' => $servidores
        ]);
    }
}
