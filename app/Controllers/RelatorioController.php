<?php
/**
 * Controller de Relatórios
 */

namespace App\Controllers;

use App\Models\ExecucaoBackup;
use App\Models\Cliente;
use App\Models\ConfiguracaoEmail;
use App\Services\AuthService;
use App\Services\EmailService;
use App\Services\LogService;
use App\Services\BackupService;

class RelatorioController extends Controller
{
    private AuthService $authService;
    private EmailService $emailService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->emailService = new EmailService();
        $this->data['user'] = $this->authService->getUser();
    }

    /**
     * Página de relatórios
     */
    public function index(): void
    {
        $this->data['title'] = 'Relatórios';
        $this->data['clientes'] = Cliente::forSelect();
        $this->data['flash'] = $this->getFlash();
        
        $this->render('relatorios/index', $this->data);
    }

    /**
     * Gera relatório geral
     */
    public function geral(): void
    {
        $dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-7 days'));
        $dataFim = $_GET['data_fim'] ?? date('Y-m-d');
        
        $stats = ExecucaoBackup::getStats(30);
        $statsPeriodo = ExecucaoBackup::getStatsByPeriod(7);
        $statsClientes = ExecucaoBackup::getStatsByCliente(30);
        $execucoes = ExecucaoBackup::filter([
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim
        ], 1, 100);
        
        $this->data['title'] = 'Relatório Geral';
        $this->data['data_inicio'] = $dataInicio;
        $this->data['data_fim'] = $dataFim;
        $this->data['stats'] = $stats;
        $this->data['stats_periodo'] = $statsPeriodo;
        $this->data['stats_clientes'] = $statsClientes;
        $this->data['execucoes'] = $execucoes['data'];
        
        $this->render('relatorios/geral', $this->data);
    }

    /**
     * Gera relatório por cliente
     */
    public function cliente(int $clienteId): void
    {
        $cliente = Cliente::findWithStats($clienteId);
        
        if (!$cliente) {
            $this->flash('error', 'Cliente não encontrado');
            $this->redirect('/relatorios');
            return;
        }
        
        $dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
        $dataFim = $_GET['data_fim'] ?? date('Y-m-d');
        
        $execucoes = ExecucaoBackup::filter([
            'cliente_id' => $clienteId,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim
        ], 1, 100);
        
        $this->data['title'] = 'Relatório - ' . $cliente['nome'];
        $this->data['cliente'] = $cliente;
        $this->data['data_inicio'] = $dataInicio;
        $this->data['data_fim'] = $dataFim;
        $this->data['execucoes'] = $execucoes['data'];
        
        $this->render('relatorios/cliente', $this->data);
    }

    /**
     * Envia relatório por e-mail
     */
    public function enviarEmail(): void
    {
        $data = $this->input();
        
        $tipo = $data['tipo'] ?? 'geral';
        $clienteId = $data['cliente_id'] ?? null;
        $emails = array_filter(array_map('trim', explode(',', $data['emails'] ?? '')));
        
        if (empty($emails)) {
            $this->json(['success' => false, 'error' => 'Informe pelo menos um e-mail'], 400);
            return;
        }
        
        // Monta dados do relatório
        $reportData = [
            'stats' => ExecucaoBackup::getStats(30),
            'execucoes' => ExecucaoBackup::getRecent(20)
        ];
        
        if ($clienteId) {
            $reportData['execucoes'] = ExecucaoBackup::byCliente($clienteId, 20);
        }
        
        $success = $this->emailService->sendBackupReport($reportData, $emails);
        
        if ($success) {
            LogService::info('relatorios', 'Relatório enviado por e-mail', [
                'tipo' => $tipo,
                'cliente_id' => $clienteId,
                'emails' => $emails
            ]);
            
            $this->json(['success' => true, 'message' => 'Relatório enviado com sucesso']);
        } else {
            $this->json(['success' => false, 'error' => 'Falha ao enviar e-mail'], 500);
        }
    }

    /**
     * Exporta relatório para CSV
     */
    public function exportarCsv(): void
    {
        $filters = [
            'cliente_id' => $_GET['cliente_id'] ?? null,
            'status' => $_GET['status'] ?? null,
            'data_inicio' => $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days')),
            'data_fim' => $_GET['data_fim'] ?? date('Y-m-d')
        ];
        
        $execucoes = ExecucaoBackup::filter(array_filter($filters), 1, 10000);
        
        $filename = 'relatorio_backups_' . date('Y-m-d_His') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        // Cabeçalho
        fputcsv($output, [
            'ID',
            'Cliente',
            'Host',
            'Rotina',
            'Status',
            'Data Início',
            'Data Fim',
            'Tamanho (bytes)',
            'Destino',
            'Mensagem de Erro'
        ], ';');
        
        // Dados
        foreach ($execucoes['data'] as $exec) {
            fputcsv($output, [
                $exec['id'],
                $exec['cliente_nome'] ?? '',
                $exec['host_nome'] ?? '',
                $exec['rotina_nome'] ?? '',
                $exec['status'],
                $exec['data_inicio'],
                $exec['data_fim'] ?? '',
                $exec['tamanho_bytes'] ?? '',
                $exec['destino'] ?? '',
                $exec['mensagem_erro'] ?? ''
            ], ';');
        }
        
        fclose($output);
        exit;
    }
}
