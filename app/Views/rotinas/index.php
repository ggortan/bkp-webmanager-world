<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= path('/clientes') ?>">Clientes</a></li>
                <li class="breadcrumb-item"><a href="<?= path('/clientes/' . $cliente['id']) ?>"><?= htmlspecialchars($cliente['nome']) ?></a></li>
                <li class="breadcrumb-item active">Rotinas de Backup</li>
            </ol>
        </nav>
        <h4 class="mt-2 mb-0">Rotinas de Backup</h4>
    </div>
    <?php if (in_array($user['role'] ?? '', ['admin', 'operator'])): ?>
    <a href="<?= path('/clientes/' . $cliente['id'] . '/rotinas/criar') ?>" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Nova Rotina
    </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Nova Arquitetura:</strong> As rotinas de backup agora são independentes e vinculadas diretamente ao cliente. 
            Cada rotina possui uma <strong>Routine Key</strong> única que deve ser configurada no agente ou script do cliente.
        </div>
        
        <?php if (empty($rotinas)): ?>
        <div class="text-center py-5">
            <i class="bi bi-gear text-muted" style="font-size: 4rem;"></i>
            <h5 class="mt-3 text-muted">Nenhuma rotina cadastrada</h5>
            <p class="text-muted">Crie rotinas de backup para este cliente e configure os agentes com as Routine Keys geradas.</p>
            <?php if (in_array($user['role'] ?? '', ['admin', 'operator'])): ?>
            <a href="<?= path('/clientes/' . $cliente['id'] . '/rotinas/criar') ?>" class="btn btn-primary mt-2">
                <i class="bi bi-plus-circle me-1"></i>Criar Primeira Rotina
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>Destino</th>
                        <th>Host/Servidor</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rotinas as $rotina): ?>
                    <?php 
                        $hostInfo = null;
                        if (!empty($rotina['host_info'])) {
                            $hostInfo = is_string($rotina['host_info']) ? json_decode($rotina['host_info'], true) : $rotina['host_info'];
                        }
                    ?>
                    <tr>
                        <td>
                            <a href="<?= path('/clientes/' . $cliente['id'] . '/rotinas/' . $rotina['id']) ?>" class="text-decoration-none">
                                <strong><?= htmlspecialchars($rotina['nome']) ?></strong>
                            </a>
                            <?php if (!empty($rotina['routine_key'])): ?>
                                <br><small class="text-muted font-monospace"><?= substr($rotina['routine_key'], 0, 20) ?>...</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($rotina['tipo']): ?>
                                <span class="badge bg-info"><?= htmlspecialchars($rotina['tipo']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($rotina['destino']): ?>
                                <small><?= htmlspecialchars(substr($rotina['destino'], 0, 40)) ?><?= strlen($rotina['destino']) > 40 ? '...' : '' ?></small>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($hostInfo && !empty($hostInfo['nome'])): ?>
                                <?= htmlspecialchars($hostInfo['nome']) ?>
                            <?php elseif ($rotina['servidor_id']): ?>
                                <small class="text-muted">Servidor vinculado</small>
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
                               class="btn btn-sm btn-outline-primary" title="Ver detalhes">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if (in_array($user['role'] ?? '', ['admin', 'operator'])): ?>
                            <a href="<?= path('/clientes/' . $cliente['id'] . '/rotinas/' . $rotina['id'] . '/editar') ?>" 
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
