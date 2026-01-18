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
        // Estatísticas gerais
        $stats = ExecucaoBackup::getStats(30);
        
        // Contagem de hosts online
        $sqlHosts = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN online_status = 'online' THEN 1 ELSE 0 END) as online
                     FROM hosts WHERE ativo = 1";
        $hostStats = \App\Database::fetch($sqlHosts);
        $stats['total_hosts'] = $hostStats['total'] ?? 0;
        $stats['hosts_online'] = $hostStats['online'] ?? 0;
        
        // Busca todos os hosts com telemetria
        $sqlAllHosts = "SELECT h.*, c.nome as cliente_nome 
                        FROM hosts h 
                        INNER JOIN clientes c ON h.cliente_id = c.id 
                        WHERE h.ativo = 1 
                        ORDER BY h.online_status DESC, h.last_seen_at DESC";
        $hosts = \App\Database::fetchAll($sqlAllHosts);
        
        // Busca rotinas com falhas recentes (últimos 7 dias)
        $sqlFalhas = "SELECT r.*, 
                             c.nome as cliente_nome, 
                             h.nome as host_nome,
                             (SELECT MAX(e.data_inicio) FROM execucoes_backup e 
                              WHERE e.rotina_id = r.id AND e.status = 'falha') as ultima_falha,
                             (SELECT COUNT(*) FROM execucoes_backup e 
                              WHERE e.rotina_id = r.id 
                              AND e.status = 'falha' 
                              AND e.data_inicio >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as total_falhas
                      FROM rotinas_backup r
                      INNER JOIN clientes c ON r.cliente_id = c.id
                      LEFT JOIN hosts h ON r.host_id = h.id
                      WHERE r.ativa = 1
                      HAVING total_falhas > 0
                      ORDER BY total_falhas DESC, ultima_falha DESC
                      LIMIT 20";
        $rotinasFalha = \App\Database::fetchAll($sqlFalhas);
        
        $this->data['title'] = 'Relatórios';
        $this->data['clientes'] = Cliente::forSelect();
        $this->data['stats'] = $stats;
        $this->data['hosts'] = $hosts;
        $this->data['rotinas_falha'] = $rotinasFalha;
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
