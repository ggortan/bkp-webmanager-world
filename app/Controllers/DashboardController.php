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
        $dashboardData = $this->backupService->getDashboardData();
        
        // Adiciona resumo de status dos hosts
        $hostStatusSummary = \App\Models\Host::statusSummary();
        
        $this->data['title'] = 'Dashboard';
        $this->data['stats'] = $dashboardData['stats'];
        $this->data['stats_periodo'] = $dashboardData['stats_periodo'];
        $this->data['stats_clientes'] = $dashboardData['stats_clientes'];
        $this->data['execucoes_recentes'] = $dashboardData['execucoes_recentes'];
        $this->data['total_clientes'] = $dashboardData['total_clientes'];
        $this->data['total_servidores'] = $dashboardData['total_servidores'];
        $this->data['host_status'] = $hostStatusSummary;
        
        $this->render('dashboard/index', $this->data);
    }
}
