<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= path('/clientes') ?>">Clientes</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($cliente['nome']) ?></li>
            </ol>
        </nav>
        <h4 class="mt-2 mb-0">
            <?= htmlspecialchars($cliente['nome']) ?>
            <code class="ms-2 fs-6"><?= htmlspecialchars($cliente['identificador']) ?></code>
            <?php if ($cliente['ativo']): ?>
                <span class="badge bg-success ms-2">Ativo</span>
            <?php else: ?>
                <span class="badge bg-secondary ms-2">Inativo</span>
            <?php endif; ?>
        </h4>
    </div>
    <div class="btn-group">
        <a href="<?= path('/clientes') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Voltar
        </a>
        <?php if (in_array($user['role'] ?? '', ['admin', 'operator'])): ?>
        <a href="<?= path('/clientes/' . $cliente['id'] . '/editar') ?>" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Editar
        </a>
        <?php endif; ?>
        <a href="<?= path('/relatorios/cliente/' . $cliente['id']) ?>" class="btn btn-outline-info">
            <i class="bi bi-file-earmark-bar-graph me-1"></i>Relatórios
        </a>
    </div>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card success position-relative">
            <i class="bi bi-check-circle stat-icon"></i>
            <div class="stat-value"><?= $cliente['stats_backup']['sucesso'] ?? 0 ?></div>
            <div class="stat-label">Rotinas com Sucesso</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card danger position-relative">
            <i class="bi bi-x-circle stat-icon"></i>
            <div class="stat-value"><?= $cliente['stats_backup']['falha'] ?? 0 ?></div>
            <div class="stat-label">Rotinas com Falha</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card warning position-relative">
            <i class="bi bi-exclamation-triangle stat-icon"></i>
            <div class="stat-value"><?= $cliente['stats_backup']['alerta'] ?? 0 ?></div>
            <div class="stat-label">Rotinas com Alerta</div>
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
    <!-- Coluna Esquerda: Informações e API Key -->
    <div class="col-lg-4">
        <!-- Informações -->
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
    
    <!-- Coluna Direita: Hosts e Rotinas -->
    <div class="col-lg-8">
        <!-- Hosts -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-hdd-network me-2"></i>Hosts</span>
                <div>
                    <a href="<?= path('/clientes/' . $cliente['id'] . '/hosts') ?>" class="btn btn-sm btn-outline-secondary me-1">
                        <i class="bi bi-list me-1"></i>Ver Todos
                    </a>
                    <?php if (in_array($user['role'] ?? '', ['admin', 'operator'])): ?>
                    <a href="<?= path('/clientes/' . $cliente['id'] . '/hosts/criar') ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Novo Host
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Hostname/IP</th>
                                <th class="text-center">Conexão</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($hosts)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    Nenhum host cadastrado.<br>
                                    <small>Cadastre hosts manualmente ou serão criados automaticamente ao receber dados.</small>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach (array_slice($hosts, 0, 5) as $host): ?>
                                <tr>
                                    <td>
                                        <a href="<?= path('/clientes/' . $cliente['id'] . '/hosts/' . $host['id']) ?>" class="text-decoration-none">
                                            <i class="bi bi-hdd-network me-1"></i>
                                            <strong><?= htmlspecialchars($host['nome']) ?></strong>
                                        </a>
                                        <?php if ($host['sistema_operacional']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($host['sistema_operacional']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($host['hostname'] ?? $host['ip'] ?? '-') ?>
                                    </td>
                                    <td class="text-center">
                                        <?php 
                                        $onlineStatus = $host['online_status'] ?? 'unknown';
                                        ?>
                                        <?php if ($onlineStatus === 'online'): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>Online
                                            </span>
                                        <?php elseif ($onlineStatus === 'offline'): ?>
                                            <span class="badge bg-danger">
                                                <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>Offline
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="bi bi-question-circle me-1"></i>?
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($host['ativo']): ?>
                                            <span class="badge bg-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= path('/clientes/' . $cliente['id'] . '/hosts/' . $host['id']) ?>" 
                                           class="btn btn-sm btn-outline-primary" title="Ver detalhes">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if (in_array($user['role'] ?? '', ['admin', 'operator'])): ?>
                                        <a href="<?= path('/clientes/' . $cliente['id'] . '/hosts/' . $host['id'] . '/editar') ?>" 
                                           class="btn btn-sm btn-outline-secondary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (count($hosts) > 5): ?>
                                <tr>
                                    <td colspan="5" class="text-center">
                                        <a href="<?= path('/clientes/' . $cliente['id'] . '/hosts') ?>" class="text-decoration-none">
                                            Ver todos os <?= count($hosts) ?> hosts...
                                        </a>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Rotinas -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-gear me-2"></i>Rotinas de Backup</span>
                <div>
                    <a href="<?= path('/clientes/' . $cliente['id'] . '/rotinas') ?>" class="btn btn-sm btn-outline-secondary me-1">
                        <i class="bi bi-list me-1"></i>Ver Todas
                    </a>
                    <?php if (in_array($user['role'] ?? '', ['admin', 'operator'])): ?>
                    <a href="<?= path('/clientes/' . $cliente['id'] . '/rotinas/criar') ?>" class="btn btn-sm btn-success">
                        <i class="bi bi-plus-circle me-1"></i>Nova Rotina
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Host</th>
                                <th>Tipo</th>
                                <th class="text-center">Última Execução</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rotinas)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    Nenhuma rotina cadastrada.<br>
                                    <small>Crie rotinas de backup para monitorar.</small>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach (array_slice($rotinas, 0, 5) as $rotina): ?>
                                <tr>
                                    <td>
                                        <a href="<?= path('/clientes/' . $cliente['id'] . '/rotinas/' . $rotina['id']) ?>" class="text-decoration-none">
                                            <i class="bi bi-gear me-1"></i>
                                            <strong><?= htmlspecialchars($rotina['nome']) ?></strong>
                                        </a>
                                        <br>
                                        <small class="text-muted font-monospace"><?= htmlspecialchars($rotina['routine_key']) ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($rotina['host_nome'])): ?>
                                            <?= htmlspecialchars($rotina['host_nome']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($rotina['tipo'])): ?>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($rotina['tipo']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (!empty($rotina['ultima_execucao'])): ?>
                                            <?php 
                                            $statusClass = match($rotina['ultimo_status'] ?? '') {
                                                'sucesso' => 'success',
                                                'falha' => 'danger',
                                                'alerta' => 'warning',
                                                'executando' => 'info',
                                                default => 'secondary'
                                            };
                                            $statusIcon = match($rotina['ultimo_status'] ?? '') {
                                                'sucesso' => 'check-circle-fill',
                                                'falha' => 'x-circle-fill',
                                                'alerta' => 'exclamation-triangle-fill',
                                                'executando' => 'arrow-repeat',
                                                default => 'question-circle'
                                            };
                                            ?>
                                            <span class="badge bg-<?= $statusClass ?>">
                                                <i class="bi bi-<?= $statusIcon ?> me-1"></i>
                                                <?= ucfirst($rotina['ultimo_status'] ?? 'Desconhecido') ?>
                                            </span>
                                            <br>
                                            <small class="text-muted"><?= date('d/m H:i', strtotime($rotina['ultima_execucao'])) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Nunca</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($rotina['ativa']): ?>
                                            <span class="badge bg-success">Ativa</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inativa</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (count($rotinas) > 5): ?>
                                <tr>
                                    <td colspan="5" class="text-center">
                                        <a href="<?= path('/clientes/' . $cliente['id'] . '/rotinas') ?>" class="text-decoration-none">
                                            Ver todas as <?= count($rotinas) ?> rotinas...
                                        </a>
                                    </td>
                                </tr>
                                <?php endif; ?>
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
