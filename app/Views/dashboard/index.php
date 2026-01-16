<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Dashboard</h4>
    <span class="text-muted">Últimos 30 dias</span>
</div>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card success position-relative">
            <i class="bi bi-check-circle stat-icon"></i>
            <div class="stat-value"><?= number_format($stats['sucesso'] ?? 0) ?></div>
            <div class="stat-label">Backups com Sucesso</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card danger position-relative">
            <i class="bi bi-x-circle stat-icon"></i>
            <div class="stat-value"><?= number_format($stats['falha'] ?? 0) ?></div>
            <div class="stat-label">Backups com Falha</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card warning position-relative">
            <i class="bi bi-exclamation-triangle stat-icon"></i>
            <div class="stat-value"><?= number_format($stats['alerta'] ?? 0) ?></div>
            <div class="stat-label">Backups com Alerta</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card info position-relative">
            <i class="bi bi-building stat-icon"></i>
            <div class="stat-value"><?= number_format($total_clientes ?? 0) ?></div>
            <div class="stat-label">Clientes Ativos</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Gráfico de Backups -->
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bar-chart me-2"></i>Backups por Dia</span>
            </div>
            <div class="card-body">
                <canvas id="chartBackups" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Status por Cliente -->
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-pie-chart me-2"></i>Status Geral
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="chartStatus" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Tabelas -->
<div class="row g-4 mt-2">
    <!-- Status por Cliente -->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-building me-2"></i>Status por Cliente</span>
                <a href="/clientes" class="btn btn-sm btn-outline-primary">Ver todos</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th class="text-center">Sucesso</th>
                                <th class="text-center">Falha</th>
                                <th class="text-center">Última Exec.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($stats_clientes)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Nenhum cliente cadastrado</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach (array_slice($stats_clientes, 0, 5) as $cliente): ?>
                                <tr>
                                    <td>
                                        <a href="/clientes/<?= $cliente['cliente_id'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($cliente['cliente_nome']) ?>
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success"><?= $cliente['sucesso'] ?? 0 ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger"><?= $cliente['falha'] ?? 0 ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($cliente['ultima_execucao']): ?>
                                            <small><?= date('d/m H:i', strtotime($cliente['ultima_execucao'])) ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Últimas Execuções -->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-2"></i>Últimas Execuções</span>
                <a href="/backups" class="btn btn-sm btn-outline-primary">Ver todas</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Rotina</th>
                                <th>Status</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($execucoes_recentes)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Nenhuma execução registrada</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($execucoes_recentes as $exec): ?>
                                <tr>
                                    <td>
                                        <small><?= htmlspecialchars($exec['cliente_nome'] ?? '-') ?></small>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($exec['rotina_nome'] ?? '-') ?></small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $exec['status'] ?>">
                                            <?= ucfirst($exec['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?= date('d/m H:i', strtotime($exec['data_inicio'])) ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dados do PHP
    const statsPeriodo = <?= json_encode($stats_periodo ?? []) ?>;
    const stats = <?= json_encode($stats ?? ['sucesso' => 0, 'falha' => 0, 'alerta' => 0]) ?>;
    
    // Gráfico de barras - Backups por dia
    if (statsPeriodo.length > 0) {
        const labels = statsPeriodo.map(item => {
            const date = new Date(item.data);
            return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
        });
        
        new Chart(document.getElementById('chartBackups'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Sucesso',
                        data: statsPeriodo.map(item => item.sucesso),
                        backgroundColor: '#198754'
                    },
                    {
                        label: 'Falha',
                        data: statsPeriodo.map(item => item.falha),
                        backgroundColor: '#dc3545'
                    },
                    {
                        label: 'Alerta',
                        data: statsPeriodo.map(item => item.alerta),
                        backgroundColor: '#ffc107'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    // Gráfico de pizza - Status geral
    const total = parseInt(stats.sucesso) + parseInt(stats.falha) + parseInt(stats.alerta);
    if (total > 0) {
        new Chart(document.getElementById('chartStatus'), {
            type: 'doughnut',
            data: {
                labels: ['Sucesso', 'Falha', 'Alerta'],
                datasets: [{
                    data: [stats.sucesso, stats.falha, stats.alerta],
                    backgroundColor: ['#198754', '#dc3545', '#ffc107']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
});
</script>
