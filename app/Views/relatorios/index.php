<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-file-earmark-bar-graph me-2"></i>Relatórios</h4>
</div>

<div class="row g-4">
    <!-- Relatório Geral -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body text-center py-5">
                <i class="bi bi-bar-chart-line text-primary" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Relatório Geral</h5>
                <p class="text-muted">
                    Visualize um resumo de todas as execuções de backup do sistema.
                </p>
                <a href="/relatorios/geral" class="btn btn-primary">
                    <i class="bi bi-eye me-1"></i>Visualizar
                </a>
            </div>
        </div>
    </div>
    
    <!-- Relatório por Cliente -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body text-center py-5">
                <i class="bi bi-building text-success" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Relatório por Cliente</h5>
                <p class="text-muted">
                    Selecione um cliente para ver o relatório detalhado de backups.
                </p>
                <div class="d-flex gap-2 justify-content-center">
                    <select class="form-select" style="max-width: 250px;" id="selectCliente">
                        <option value="">Selecione um cliente</option>
                        <?php foreach ($clientes as $id => $nome): ?>
                        <option value="<?= $id ?>"><?= htmlspecialchars($nome) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-success" onclick="irParaRelatorioCliente()">
                        <i class="bi bi-eye me-1"></i>Ver
                    </button>
                </div>
            </div>
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
        <form action="/relatorios/exportar-csv" method="GET" class="row g-3">
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
</script>
