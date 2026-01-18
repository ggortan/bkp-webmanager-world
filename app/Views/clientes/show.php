<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><?= htmlspecialchars($cliente['nome']) ?></h4>
        <span class="text-muted">
            <code><?= htmlspecialchars($cliente['identificador']) ?></code>
            <?php if ($cliente['ativo']): ?>
                <span class="badge bg-success ms-2">Ativo</span>
            <?php else: ?>
                <span class="badge bg-secondary ms-2">Inativo</span>
            <?php endif; ?>
        </span>
    </div>
    <div class="btn-group">
        <?php if (in_array($user['role'] ?? '', ['admin', 'operator'])): ?>
        <a href="<?= path('/clientes/' . $cliente['id'] . '/editar') ?>" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Editar
        </a>
        <a href="<?= path('/clientes/' . $cliente['id'] . '/rotinas') ?>" class="btn btn-outline-success">
            <i class="bi bi-gear me-1"></i>Rotinas
        </a>
        <?php endif; ?>
        <a href="<?= path('/relatorios/cliente/' . $cliente['id']) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-bar-graph me-1"></i>Relatórios
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Stats -->
    <div class="col-md-3">
        <div class="stat-card success position-relative">
            <i class="bi bi-check-circle stat-icon"></i>
            <div class="stat-value"><?= $cliente['stats_backup']['sucesso'] ?? 0 ?></div>
            <div class="stat-label">Sucesso (30 dias)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card danger position-relative">
            <i class="bi bi-x-circle stat-icon"></i>
            <div class="stat-value"><?= $cliente['stats_backup']['falha'] ?? 0 ?></div>
            <div class="stat-label">Falhas (30 dias)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card warning position-relative">
            <i class="bi bi-exclamation-triangle stat-icon"></i>
            <div class="stat-value"><?= $cliente['stats_backup']['alerta'] ?? 0 ?></div>
            <div class="stat-label">Alertas (30 dias)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card info position-relative">
            <i class="bi bi-hdd-network stat-icon"></i>
            <div class="stat-value"><?= $cliente['total_hosts'] ?? 0 ?></div>
            <div class="stat-label">Hosts</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Informações -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle me-2"></i>Informações
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr>
                        <th class="border-0 ps-0">E-mail</th>
                        <td class="border-0 text-end"><?= htmlspecialchars($cliente['email'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th class="ps-0">Telefone</th>
                        <td class="text-end"><?= htmlspecialchars($cliente['telefone'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th class="ps-0">Relatórios</th>
                        <td class="text-end">
                            <?php if ($cliente['relatorios_ativos']): ?>
                                <span class="badge bg-success">Ativos</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Desativados</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th class="ps-0">Criado em</th>
                        <td class="text-end"><?= date('d/m/Y H:i', strtotime($cliente['created_at'])) ?></td>
                    </tr>
                </table>
                
                <?php if (!empty($cliente['observacoes'])): ?>
                <hr>
                <p class="small text-muted mb-0"><?= nl2br(htmlspecialchars($cliente['observacoes'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- API Key -->
        <?php if (in_array($user['role'] ?? '', ['admin', 'operator'])): ?>
        <div class="card mt-4">
            <div class="card-header">
                <i class="bi bi-key me-2"></i>API Key
            </div>
            <div class="card-body">
                <div class="input-group">
                    <input type="password" class="form-control font-monospace" id="apiKey" 
                           value="<?= htmlspecialchars($cliente['api_key']) ?>" readonly>
                    <button class="btn btn-outline-secondary" type="button" onclick="toggleApiKey()">
                        <i class="bi bi-eye" id="apiKeyIcon"></i>
                    </button>
                    <button class="btn btn-outline-secondary" type="button" onclick="copyApiKey()">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
                <small class="text-muted d-block mt-2">
                    Use esta chave para autenticar as requisições da API de backup.
                </small>
                
                <?php if (($user['role'] ?? '') === 'admin'): ?>
                <button class="btn btn-sm btn-outline-danger mt-3" onclick="regenerateApiKey()">
                    <i class="bi bi-arrow-repeat me-1"></i>Regenerar API Key
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Hosts -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-hdd-network me-2"></i>Hosts</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Hostname/IP</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($hosts)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">
                                    Nenhum servidor registrado.<br>
                                    <small>Os servidores serão criados automaticamente ao receber dados de backup.</small>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($hosts as $host): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($host['nome']) ?></strong>
                                        <?php if ($host['sistema_operacional']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($host['sistema_operacional']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($host['hostname'] ?? $host['ip'] ?? '-') ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($host['ativo']): ?>
                                            <span class="badge bg-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inativo</span>
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
</div>

<script>
function toggleApiKey() {
    const input = document.getElementById('apiKey');
    const icon = document.getElementById('apiKeyIcon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}

function copyApiKey() {
    const input = document.getElementById('apiKey');
    navigator.clipboard.writeText(input.value).then(() => {
        showToast('API Key copiada para a área de transferência');
    });
}

function regenerateApiKey() {
    if (!confirm('Tem certeza que deseja regenerar a API Key?\n\nIsso invalidará a chave atual e os servidores precisarão ser reconfigurados.')) {
        return;
    }
    
    fetch('<?= path('/clientes/' . $cliente['id'] . '/regenerar-api-key') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('apiKey').value = data.api_key;
            showToast('API Key regenerada com sucesso');
        } else {
            showToast(data.error || 'Erro ao regenerar API Key', 'error');
        }
    })
    .catch(() => {
        showToast('Erro ao regenerar API Key', 'error');
    });
}
</script>
