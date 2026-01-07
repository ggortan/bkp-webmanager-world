<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-bar-chart-line me-2"></i>Relatório Geral</h4>
        <span class="text-muted">
            Período: <?= date('d/m/Y', strtotime($data_inicio)) ?> a <?= date('d/m/Y', strtotime($data_fim)) ?>
        </span>
    </div>
    <div class="btn-group">
        <a href="/relatorios/exportar-csv?data_inicio=<?= $data_inicio ?>&data_fim=<?= $data_fim ?>" class="btn btn-outline-secondary">
            <i class="bi bi-download me-1"></i>Exportar CSV
        </a>
        <a href="/relatorios" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Voltar
        </a>
    </div>
</div>

<!-- Filtro de Período -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="/relatorios/geral" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Data Início</label>
                <input type="date" class="form-control" name="data_inicio" value="<?= $data_inicio ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Data Fim</label>
                <input type="date" class="form-control" name="data_fim" value="<?= $data_fim ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-1"></i>Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Estatísticas -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card success position-relative">
            <i class="bi bi-check-circle stat-icon"></i>
            <div class="stat-value"><?= number_format($stats['sucesso'] ?? 0) ?></div>
            <div class="stat-label">Sucesso</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card danger position-relative">
            <i class="bi bi-x-circle stat-icon"></i>
            <div class="stat-value"><?= number_format($stats['falha'] ?? 0) ?></div>
            <div class="stat-label">Falhas</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card warning position-relative">
            <i class="bi bi-exclamation-triangle stat-icon"></i>
            <div class="stat-value"><?= number_format($stats['alerta'] ?? 0) ?></div>
            <div class="stat-label">Alertas</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card info position-relative">
            <i class="bi bi-hdd-stack stat-icon"></i>
            <div class="stat-value"><?= number_format($stats['total'] ?? 0) ?></div>
            <div class="stat-label">Total</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Gráfico -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-bar-chart me-2"></i>Backups por Dia
            </div>
            <div class="card-body">
                <canvas id="chartPeriodo" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Status por Cliente -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-building me-2"></i>Por Cliente
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 350px;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="position-sticky top-0 bg-white">
                            <tr>
                                <th>Cliente</th>
                                <th class="text-center">OK</th>
                                <th class="text-center">Erro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats_clientes as $cliente): ?>
                            <tr>
                                <td>
                                    <a href="/relatorios/cliente/<?= $cliente['cliente_id'] ?>" class="text-decoration-none small">
                                        <?= htmlspecialchars($cliente['cliente_nome']) ?>
                                    </a>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success"><?= $cliente['sucesso'] ?? 0 ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-danger"><?= $cliente['falha'] ?? 0 ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabela de Execuções -->
<div class="card mt-4">
    <div class="card-header">
        <i class="bi bi-list-ul me-2"></i>Execuções no Período
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Servidor</th>
                        <th>Rotina</th>
                        <th class="text-center">Status</th>
                        <th>Data/Hora</th>
                        <th>Tamanho</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($execucoes)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Nenhuma execução no período</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach (array_slice($execucoes, 0, 50) as $exec): ?>
                        <tr>
                            <td><?= htmlspecialchars($exec['cliente_nome'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($exec['servidor_nome'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($exec['rotina_nome'] ?? '-') ?></td>
                            <td class="text-center">
                                <span class="status-badge status-<?= $exec['status'] ?>">
                                    <?= ucfirst($exec['status']) ?>
                                </span>
                            </td>
                            <td><small><?= date('d/m/Y H:i', strtotime($exec['data_inicio'])) ?></small></td>
                            <td><?= \App\Services\BackupService::formatBytes($exec['tamanho_bytes'] ?? null) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const statsPeriodo = <?= json_encode($stats_periodo ?? []) ?>;
    
    if (statsPeriodo.length > 0) {
        const labels = statsPeriodo.map(item => {
            const date = new Date(item.data);
            return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
        });
        
        new Chart(document.getElementById('chartPeriodo'), {
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
                scales: {
                    x: { stacked: true },
                    y: { stacked: true, beginAtZero: true }
                }
            }
        });
    }
});
</script>
