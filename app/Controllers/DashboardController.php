<?php
/**
 * Controller do Dashboard
 */

namespace App\Controllers;

use App\Services\BackupService;
use App\Services\AuthService;

class DashboardController extends Controller
{
    private BackupService $backupService;
    private AuthService $authService;

    public function __construct()
    {
        $this->backupService = new BackupService();
        $this->authService = new AuthService();
        $this->data['user'] = $this->authService->getUser();
    }

    /**
     * Página principal do dashboard
     */
    public function index(): void
    {
        // Obtém o período da query string (null = últimas execuções, 7 = 7 dias, 30 = 30 dias)
        $periodo = isset($_GET['periodo']) ? (int)$_GET['periodo'] : null;
        
        // Valida o período
        if ($periodo !== null && !in_array($periodo, [7, 30])) {
            $periodo = null; // Volta para o padrão (últimas execuções)
        }
        
        $dashboardData = $this->backupService->getDashboardData($periodo);
        
        // Adiciona resumo de status dos hosts
        $hostStatusSummary = \App\Models\Host::statusSummary();
        
        $this->data['title'] = 'Dashboard';
        $this->data['stats'] = $dashboardData['stats'];
        $this->data['stats_periodo'] = $dashboardData['stats_periodo'];
        $this->data['stats_clientes'] = $dashboardData['stats_clientes'];
        $this->data['execucoes_recentes'] = $dashboardData['execucoes_recentes'];
        $this->data['total_clientes'] = $dashboardData['total_clientes'];
        $this->data['total_servidores'] = $dashboardData['total_servidores'] ?? $dashboardData['total_hosts'] ?? 0;
        $this->data['host_status'] = $hostStatusSummary;
        $this->data['periodo_atual'] = $dashboardData['periodo'];
        
        $this->render('dashboard/index', $this->data);
    }
}
