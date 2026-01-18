<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= path('/clientes') ?>">Clientes</a></li>
                <li class="breadcrumb-item"><a href="<?= path('/clientes/' . $cliente['id']) ?>"><?= htmlspecialchars($cliente['nome']) ?></a></li>
                <li class="breadcrumb-item active">Hosts</li>
            </ol>
        </nav>
        <h4 class="mt-2 mb-0">Hosts</h4>
    </div>
    <div class="btn-group">
        <a href="<?= path('/clientes/' . $cliente['id']) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Voltar
        </a>
        <?php if (in_array($user['role'] ?? '', ['admin', 'operator'])): ?>
        <a href="<?= path('/clientes/' . $cliente['id'] . '/hosts/criar') ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Novo Host
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

<div class="card">
    <div class="card-body">
        <?php if (empty($hosts)): ?>
        <div class="text-center py-5">
            <i class="bi bi-hdd-network text-muted" style="font-size: 4rem;"></i>
            <h5 class="mt-3 text-muted">Nenhum host cadastrado</h5>
            <p class="text-muted">Cadastre hosts para organizar as rotinas de backup deste cliente.</p>
            <?php if (in_array($user['role'] ?? '', ['admin', 'operator'])): ?>
            <a href="<?= path('/clientes/' . $cliente['id'] . '/hosts/criar') ?>" class="btn btn-primary mt-2">
                <i class="bi bi-plus-circle me-1"></i>Criar Primeiro Host
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Hostname/IP</th>
                        <th>Sistema Operacional</th>
                        <th class="text-center">Rotinas</th>
                        <th class="text-center">Conexão</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hosts as $host): ?>
                    <tr>
                        <td>
                            <a href="<?= path('/clientes/' . $cliente['id'] . '/hosts/' . $host['id']) ?>" class="text-decoration-none">
                                <i class="bi bi-hdd-network me-1"></i>
                                <strong><?= htmlspecialchars($host['nome']) ?></strong>
                            </a>
                            <?php if (!empty($host['tipo'])): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($host['tipo']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($host['hostname'])): ?>
                                <div><?= htmlspecialchars($host['hostname']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($host['ip'])): ?>
                                <small class="text-muted"><?= htmlspecialchars($host['ip']) ?></small>
                            <?php endif; ?>
                            <?php if (empty($host['hostname']) && empty($host['ip'])): ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($host['sistema_operacional'])): ?>
                                <span class="badge bg-secondary"><?= htmlspecialchars($host['sistema_operacional']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($host['total_rotinas'] > 0): ?>
                                <span class="badge bg-info"><?= $host['total_rotinas'] ?></span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php 
                            $onlineStatus = $host['online_status'] ?? 'unknown';
                            $lastSeen = $host['last_seen_at'] ?? null;
                            ?>
                            <?php if ($onlineStatus === 'online'): ?>
                                <span class="badge bg-success" title="Último contato: <?= $lastSeen ? date('d/m/Y H:i', strtotime($lastSeen)) : 'N/A' ?>">
                                    <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>Online
                                </span>
                            <?php elseif ($onlineStatus === 'offline'): ?>
                                <span class="badge bg-danger" title="Último contato: <?= $lastSeen ? date('d/m/Y H:i', strtotime($lastSeen)) : 'N/A' ?>">
                                    <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>Offline
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary" title="Sem telemetria">
                                    <i class="bi bi-question-circle me-1"></i>Desconhecido
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
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
