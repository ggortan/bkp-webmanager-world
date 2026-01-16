<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-hdd-stack me-2"></i>Histórico de Backups</h4>
    <a href="<?= path('/relatorios/exportar-csv?' . http_build_query($filters)) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-download me-1"></i>Exportar CSV
    </a>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="<?= path('/backups') ?>" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Cliente</label>
                <select class="form-select" name="cliente_id" id="filtroCliente">
                    <option value="">Todos</option>
                    <?php foreach ($clientes as $id => $nome): ?>
                    <option value="<?= $id ?>" <?= ($filters['cliente_id'] ?? '') == $id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($nome) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">Todos</option>
                    <option value="sucesso" <?= ($filters['status'] ?? '') === 'sucesso' ? 'selected' : '' ?>>Sucesso</option>
                    <option value="falha" <?= ($filters['status'] ?? '') === 'falha' ? 'selected' : '' ?>>Falha</option>
                    <option value="alerta" <?= ($filters['status'] ?? '') === 'alerta' ? 'selected' : '' ?>>Alerta</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Data Início</label>
                <input type="date" class="form-control" name="data_inicio" value="<?= htmlspecialchars($filters['data_inicio'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Data Fim</label>
                <input type="date" class="form-control" name="data_fim" value="<?= htmlspecialchars($filters['data_fim'] ?? '') ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search me-1"></i>Filtrar
                </button>
                <a href="<?= path('/backups') ?>" class="btn btn-outline-secondary">Limpar</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabela -->
<div class="card">
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
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($execucoes)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            Nenhuma execução encontrada
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($execucoes as $exec): ?>
                        <tr>
                            <td>
                                <a href="<?= path('/clientes/' . $exec['cliente_id']) ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($exec['cliente_nome'] ?? '-') ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($exec['servidor_nome'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($exec['rotina_nome'] ?? '-') ?></td>
                            <td class="text-center">
                                <span class="status-badge status-<?= $exec['status'] ?>">
                                    <?= ucfirst($exec['status']) ?>
                                </span>
                            </td>
                            <td>
                                <small>
                                    <?= date('d/m/Y H:i:s', strtotime($exec['data_inicio'])) ?>
                                </small>
                            </td>
                            <td>
                                <?= \App\Services\BackupService::formatBytes($exec['tamanho_bytes'] ?? null) ?>
                            </td>
                            <td class="text-center">
                                <a href="<?= path('/backups/' . $exec['id']) ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer">
        <nav>
            <ul class="pagination pagination-sm mb-0 justify-content-center">
                <?php if ($pagination['page'] > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $pagination['page'] - 1])) ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php for ($i = max(1, $pagination['page'] - 2); $i <= min($pagination['total_pages'], $pagination['page'] + 2); $i++): ?>
                <li class="page-item <?= $i === $pagination['page'] ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $pagination['page'] + 1])) ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <p class="text-center text-muted small mb-0 mt-2">
            Mostrando <?= count($execucoes) ?> de <?= $pagination['total'] ?> registros
        </p>
    </div>
    <?php endif; ?>
</div>
