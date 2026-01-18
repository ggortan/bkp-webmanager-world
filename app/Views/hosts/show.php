<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= path('/clientes') ?>">Clientes</a></li>
                <li class="breadcrumb-item"><a href="<?= path('/clientes/' . $cliente['id']) ?>"><?= htmlspecialchars($cliente['nome']) ?></a></li>
                <li class="breadcrumb-item"><a href="<?= path('/clientes/' . $cliente['id'] . '/hosts') ?>">Hosts</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($host['nome']) ?></li>
            </ol>
        </nav>
        <h4 class="mt-2 mb-0">
            <i class="bi bi-hdd-network me-2"></i><?= htmlspecialchars($host['nome']) ?>
            <?php if ($host['ativo']): ?>
                <span class="badge bg-success ms-2">Ativo</span>
            <?php else: ?>
                <span class="badge bg-secondary ms-2">Inativo</span>
            <?php endif; ?>
        </h4>
    </div>
    <div class="btn-group">
        <?php if (in_array($user['role'] ?? '', ['admin', 'operator'])): ?>
        <a href="<?= path('/clientes/' . $cliente['id'] . '/hosts/' . $host['id'] . '/editar') ?>" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Editar
        </a>
        <a href="<?= path('/clientes/' . $cliente['id'] . '/rotinas/criar') ?>?host_id=<?= $host['id'] ?>" class="btn btn-outline-success">
            <i class="bi bi-plus-circle me-1"></i>Nova Rotina
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Estatísticas -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card info">
            <i class="bi bi-gear stat-icon"></i>
            <div class="stat-value"><?= $host['total_rotinas'] ?? 0 ?></div>
            <div class="stat-label">Rotinas Vinculadas</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card success">
            <i class="bi bi-check-circle stat-icon"></i>
            <div class="stat-value"><?= $host['stats']['sucesso'] ?? 0 ?></div>
            <div class="stat-label">Sucesso (7 dias)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card danger">
            <i class="bi bi-x-circle stat-icon"></i>
            <div class="stat-value"><?= $host['stats']['falha'] ?? 0 ?></div>
            <div class="stat-label">Falhas (7 dias)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card warning">
            <i class="bi bi-clock-history stat-icon"></i>
            <div class="stat-value"><?= $host['stats']['total'] ?? 0 ?></div>
            <div class="stat-label">Total (7 dias)</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Informações do Host -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle me-2"></i>Informações do Host
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <?php if (!empty($host['hostname'])): ?>
                    <tr>
                        <th class="border-0 ps-0">Hostname</th>
                        <td class="border-0 text-end"><code><?= htmlspecialchars($host['hostname']) ?></code></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($host['ip'])): ?>
                    <tr>
                        <th class="ps-0">IP</th>
                        <td class="text-end"><code><?= htmlspecialchars($host['ip']) ?></code></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($host['sistema_operacional'])): ?>
                    <tr>
                        <th class="ps-0">Sistema</th>
                        <td class="text-end"><?= htmlspecialchars($host['sistema_operacional']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($host['tipo'])): ?>
                    <tr>
                        <th class="ps-0">Tipo</th>
                        <td class="text-end"><span class="badge bg-secondary"><?= htmlspecialchars($host['tipo']) ?></span></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th class="ps-0">Criado em</th>
                        <td class="text-end"><?= date('d/m/Y H:i', strtotime($host['created_at'])) ?></td>
                    </tr>
                </table>
                
                <?php if (!empty($host['observacoes'])): ?>
                <hr>
                <h6 class="text-muted small mb-2">Observações:</h6>
                <p class="small mb-0"><?= nl2br(htmlspecialchars($host['observacoes'])) ?></p>
                <?php endif; ?>
                
                <?php if ($host['ultima_execucao']): ?>
                <hr>
                <h6 class="text-muted small mb-2">Última Execução:</h6>
                <p class="small mb-0">
                    <span class="badge bg-<?= $host['ultima_execucao']['status'] === 'sucesso' ? 'success' : 'danger' ?>">
                        <?= ucfirst($host['ultima_execucao']['status']) ?>
                    </span>
                    <br>
                    <?= date('d/m/Y H:i', strtotime($host['ultima_execucao']['data_inicio'])) ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (in_array($user['role'] ?? '', ['admin'])): ?>
        <div class="card mt-3">
            <div class="card-header bg-danger text-white">
                <i class="bi bi-exclamation-triangle me-2"></i>Zona de Perigo
            </div>
            <div class="card-body">
                <p class="text-muted small">Deletar este host removerá todos os vínculos com rotinas, mas não deletará as rotinas.</p>
                <form method="POST" action="<?= path('/clientes/' . $cliente['id'] . '/hosts/' . $host['id'] . '/delete') ?>" 
                      onsubmit="return confirm('Tem certeza que deseja deletar este host? Esta ação não pode ser desfeita.');">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <button type="submit" class="btn btn-danger btn-sm w-100">
                        <i class="bi bi-trash me-1"></i>Deletar Host
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Rotinas Vinculadas -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-gear me-2"></i>Rotinas Vinculadas</span>
                <?php if (in_array($user['role'] ?? '', ['admin', 'operator'])): ?>
                <a href="<?= path('/clientes/' . $cliente['id'] . '/rotinas/criar') ?>?host_id=<?= $host['id'] ?>" 
                   class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>Nova Rotina
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($rotinas)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-gear text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-2 mb-0">Nenhuma rotina vinculada a este host</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Tipo</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rotinas as $rotina): ?>
                            <tr>
                                <td>
                                    <a href="<?= path('/clientes/' . $cliente['id'] . '/rotinas/' . $rotina['id']) ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($rotina['nome']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($rotina['tipo']): ?>
                                        <span class="badge bg-info"><?= htmlspecialchars($rotina['tipo']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($rotina['ativa']): ?>
                                        <span class="badge bg-success">Ativa</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inativa</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= path('/clientes/' . $cliente['id'] . '/rotinas/' . $rotina['id']) ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Últimas Execuções -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clock-history me-2"></i>Últimas Execuções
            </div>
            <div class="card-body">
                <?php if (empty($execucoes)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-clock-history text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-2 mb-0">Nenhuma execução registrada</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>Rotina</th>
                                <th>Data/Hora</th>
                                <th>Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($execucoes as $exec): ?>
                            <tr>
                                <td><?= htmlspecialchars($exec['rotina_nome']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($exec['data_inicio'])) ?></td>
                                <td>
                                    <?php
                                    $statusClass = match($exec['status']) {
                                        'sucesso' => 'success',
                                        'falha' => 'danger',
                                        'alerta' => 'warning',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($exec['status']) ?></span>
                                </td>
                                <td class="text-end">
                                    <a href="<?= path('/backups/' . $exec['id']) ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
