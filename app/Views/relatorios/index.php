<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-file-earmark-bar-graph me-2"></i>Relatórios</h4>
    <a href="<?= path('/dashboard') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Voltar
    </a>
</div>

<!-- Cards de Relatórios Principais -->
<div class="row g-4 mb-4">
    <!-- Relatório Geral -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center py-4">
                <i class="bi bi-bar-chart-line text-primary" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Relatório Geral</h5>
                <p class="text-muted small">
                    Resumo de todas as execuções de backup do sistema.
                </p>
                <a href="<?= path('/relatorios/geral') ?>" class="btn btn-primary">
                    <i class="bi bi-eye me-1"></i>Visualizar
                </a>
            </div>
        </div>
    </div>
    
    <!-- Relatório por Cliente -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center py-4">
                <i class="bi bi-building text-success" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Relatório por Cliente</h5>
                <p class="text-muted small">
                    Relatório detalhado de backups de um cliente.
                </p>
                <div class="d-flex gap-2 justify-content-center">
                    <select class="form-select form-select-sm" style="max-width: 180px;" id="selectCliente">
                        <option value="">Selecione</option>
                        <?php foreach ($clientes as $id => $nome): ?>
                        <option value="<?= $id ?>"><?= htmlspecialchars($nome) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-success btn-sm" onclick="irParaRelatorioCliente()">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Relatório de Hosts -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center py-4">
                <i class="bi bi-hdd-network text-info" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Status dos Hosts</h5>
                <p class="text-muted small">
                    Visão geral de todos os hosts online/offline.
                </p>
                <a href="#hostsSection" class="btn btn-info" onclick="scrollToSection('hostsSection')">
                    <i class="bi bi-eye me-1"></i>Ver Abaixo
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Estatísticas Rápidas -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card success">
            <i class="bi bi-check-circle stat-icon"></i>
            <div class="stat-value"><?= $stats['sucesso'] ?? 0 ?></div>
            <div class="stat-label">Sucesso (30 dias)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card danger">
            <i class="bi bi-x-circle stat-icon"></i>
            <div class="stat-value"><?= $stats['falha'] ?? 0 ?></div>
            <div class="stat-label">Falhas (30 dias)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card warning">
            <i class="bi bi-exclamation-triangle stat-icon"></i>
            <div class="stat-value"><?= $stats['alerta'] ?? 0 ?></div>
            <div class="stat-label">Alertas (30 dias)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card info">
            <i class="bi bi-hdd-network stat-icon"></i>
            <div class="stat-value"><?= $stats['hosts_online'] ?? 0 ?> / <?= $stats['total_hosts'] ?? 0 ?></div>
            <div class="stat-label">Hosts Online</div>
        </div>
    </div>
</div>

<!-- Status dos Hosts -->
<div class="card mb-4" id="hostsSection">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-hdd-network me-2"></i>Status dos Hosts</span>
        <span class="badge bg-secondary"><?= count($hosts ?? []) ?> hosts</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Host</th>
                        <th>Cliente</th>
                        <th class="text-center">Status</th>
                        <th>Último Contato</th>
                        <th class="text-center">CPU</th>
                        <th class="text-center">Mem</th>
                        <th class="text-center">Disco</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($hosts)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Nenhum host cadastrado</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($hosts as $host): ?>
                        <?php $metrics = $host['telemetry_data'] ? json_decode($host['telemetry_data'], true) : []; ?>
                        <tr>
                            <td>
                                <a href="<?= path('/clientes/' . $host['cliente_id'] . '/hosts/' . $host['id']) ?>" class="text-decoration-none">
                                    <i class="bi bi-hdd-network me-1"></i><?= htmlspecialchars($host['nome']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($host['cliente_nome'] ?? '') ?></td>
                            <td class="text-center">
                                <?php if ($host['online_status'] === 'online'): ?>
                                    <span class="badge bg-success"><i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>Online</span>
                                <?php elseif ($host['online_status'] === 'offline'): ?>
                                    <span class="badge bg-danger"><i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>Offline</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">?</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($host['last_seen_at']): ?>
                                    <small><?= date('d/m H:i', strtotime($host['last_seen_at'])) ?></small>
                                <?php else: ?>
                                    <small class="text-muted">Nunca</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($metrics['cpu_percent'])): ?>
                                    <span class="badge <?= $metrics['cpu_percent'] > 80 ? 'bg-danger' : 'bg-info' ?>">
                                        <?= number_format($metrics['cpu_percent'], 0) ?>%
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($metrics['memory_percent'])): ?>
                                    <span class="badge <?= $metrics['memory_percent'] > 80 ? 'bg-danger' : 'bg-warning' ?>">
                                        <?= number_format($metrics['memory_percent'], 0) ?>%
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($metrics['disk_percent'])): ?>
                                    <span class="badge <?= $metrics['disk_percent'] > 90 ? 'bg-danger' : 'bg-success' ?>">
                                        <?= number_format($metrics['disk_percent'], 0) ?>%
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
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

<!-- Rotinas com Problemas -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-exclamation-triangle me-2"></i>Rotinas com Problemas Recentes</span>
        <span class="badge bg-warning"><?= count($rotinas_falha ?? []) ?> rotinas</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Rotina</th>
                        <th>Cliente</th>
                        <th>Host</th>
                        <th>Última Falha</th>
                        <th class="text-center">Falhas (7d)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rotinas_falha)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            <i class="bi bi-check-circle text-success me-2"></i>Nenhuma rotina com falhas recentes
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($rotinas_falha as $rotina): ?>
                        <tr>
                            <td>
                                <a href="<?= path('/clientes/' . $rotina['cliente_id'] . '/rotinas/' . $rotina['id']) ?>" class="text-decoration-none">
                                    <i class="bi bi-gear me-1"></i><?= htmlspecialchars($rotina['nome']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($rotina['cliente_nome'] ?? '') ?></td>
                            <td><?= htmlspecialchars($rotina['host_nome'] ?? '-') ?></td>
                            <td>
                                <?php if ($rotina['ultima_falha']): ?>
                                    <small><?= date('d/m/Y H:i', strtotime($rotina['ultima_falha'])) ?></small>
                                <?php else: ?>
                                    <small class="text-muted">-</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-danger"><?= $rotina['total_falhas'] ?? 0 ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Envio por E-mail -->
<?php if (in_array($user['role'] ?? '', ['admin', 'operator'])): ?>
<div class="card mt-4">
    <div class="card-header">
        <i class="bi bi-envelope me-2"></i>Enviar Relatório por E-mail
    </div>
    <div class="card-body">
        <form id="formEnviarEmail">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tipo de Relatório</label>
                    <select class="form-select" name="tipo" id="tipoRelatorio">
                        <option value="geral">Relatório Geral</option>
                        <option value="cliente">Por Cliente</option>
                    </select>
                </div>
                <div class="col-md-3" id="divClienteEmail" style="display: none;">
                    <label class="form-label">Cliente</label>
                    <select class="form-select" name="cliente_id" id="clienteEmail">
                        <option value="">Selecione</option>
                        <?php foreach ($clientes as $id => $nome): ?>
                        <option value="<?= $id ?>"><?= htmlspecialchars($nome) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Destinatários (separados por vírgula)</label>
                    <input type="text" class="form-control" name="emails" id="emailsDestino" 
                           placeholder="email1@exemplo.com, email2@exemplo.com">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-send me-1"></i>Enviar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Exportação -->
<div class="card mt-4">
    <div class="card-header">
        <i class="bi bi-download me-2"></i>Exportar Dados
    </div>
    <div class="card-body">
        <form action="<?= path('/relatorios/exportar-csv') ?>" method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Cliente</label>
                <select class="form-select" name="cliente_id">
                    <option value="">Todos</option>
                    <?php foreach ($clientes as $id => $nome): ?>
                    <option value="<?= $id ?>"><?= htmlspecialchars($nome) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">Todos</option>
                    <option value="sucesso">Sucesso</option>
                    <option value="falha">Falha</option>
                    <option value="alerta">Alerta</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Data Início</label>
                <input type="date" class="form-control" name="data_inicio" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Data Fim</label>
                <input type="date" class="form-control" name="data_fim" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>Exportar CSV
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function irParaRelatorioCliente() {
    const clienteId = document.getElementById('selectCliente').value;
    if (clienteId) {
        window.location.href = '/relatorios/cliente/' + clienteId;
    } else {
        showToast('Selecione um cliente', 'warning');
    }
}

document.getElementById('tipoRelatorio')?.addEventListener('change', function() {
    document.getElementById('divClienteEmail').style.display = this.value === 'cliente' ? 'block' : 'none';
});

document.getElementById('formEnviarEmail')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const tipo = document.getElementById('tipoRelatorio').value;
    const clienteId = document.getElementById('clienteEmail')?.value;
    const emails = document.getElementById('emailsDestino').value;
    
    if (tipo === 'cliente' && !clienteId) {
        showToast('Selecione um cliente', 'warning');
        return;
    }
    
    if (!emails) {
        showToast('Informe pelo menos um e-mail', 'warning');
        return;
    }
    
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enviando...';
    
    fetch('/relatorios/enviar-email', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ tipo, cliente_id: clienteId, emails })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Relatório enviado com sucesso!', 'success');
            document.getElementById('emailsDestino').value = '';
        } else {
            showToast(data.error || 'Erro ao enviar relatório', 'error');
        }
    })
    .catch(() => {
        showToast('Erro ao enviar relatório', 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-1"></i>Enviar';
    });
});

function scrollToSection(id) {
    const element = document.getElementById(id);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}
</script>
