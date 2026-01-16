<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-building me-2"></i>Clientes</h4>
    <?php if (in_array($user['role'] ?? '', ['admin', 'operator'])): ?>
    <a href="<?= path('/clientes/criar') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Novo Cliente
    </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Identificador</th>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Relatórios</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clientes)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            Nenhum cliente cadastrado
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($clientes as $cliente): ?>
                        <tr>
                            <td>
                                <code><?= htmlspecialchars($cliente['identificador']) ?></code>
                            </td>
                            <td>
                                <a href="<?= path('/clientes/' . $cliente['id']) ?>" class="text-decoration-none fw-semibold">
                                    <?= htmlspecialchars($cliente['nome']) ?>
                                </a>
                            </td>
                            <td>
                                <?= htmlspecialchars($cliente['email'] ?? '-') ?>
                            </td>
                            <td class="text-center">
                                <?php if ($cliente['ativo']): ?>
                                    <span class="badge bg-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($cliente['relatorios_ativos']): ?>
                                    <i class="bi bi-check-circle text-success"></i>
                                <?php else: ?>
                                    <i class="bi bi-x-circle text-muted"></i>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= path('/clientes/' . $cliente['id']) ?>" class="btn btn-outline-secondary" title="Detalhes">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if (in_array($user['role'] ?? '', ['admin', 'operator'])): ?>
                                    <a href="<?= path('/clientes/' . $cliente['id'] . '/editar') ?>" class="btn btn-outline-secondary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
