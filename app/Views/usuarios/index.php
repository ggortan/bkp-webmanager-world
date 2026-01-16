<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-people me-2"></i>Usuários</h4>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>E-mail</th>
                        <th>Papel</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Último Login</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usuarios)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            Nenhum usuário cadastrado
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width:36px;height:36px;">
                                        <span class="text-white fw-bold"><?= strtoupper(substr($usuario['nome'], 0, 1)) ?></span>
                                    </div>
                                    <div>
                                        <a href="<?= path('/usuarios/' . $usuario['id']) ?>" class="text-decoration-none fw-semibold">
                                            <?= htmlspecialchars($usuario['nome']) ?>
                                        </a>
                                        <?php if ($usuario['id'] === $user['id']): ?>
                                            <span class="badge bg-info ms-1">Você</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($usuario['email']) ?></td>
                            <td>
                                <?php
                                $roleColors = [
                                    'admin' => 'danger',
                                    'operator' => 'primary',
                                    'viewer' => 'secondary'
                                ];
                                $roleNames = [
                                    'admin' => 'Administrador',
                                    'operator' => 'Operador',
                                    'viewer' => 'Visualização'
                                ];
                                $role = $usuario['role_nome'] ?? 'viewer';
                                ?>
                                <span class="badge bg-<?= $roleColors[$role] ?? 'secondary' ?>">
                                    <?= $roleNames[$role] ?? ucfirst($role) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($usuario['ativo']): ?>
                                    <span class="badge bg-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($usuario['ultimo_login']): ?>
                                    <small><?= date('d/m/Y H:i', strtotime($usuario['ultimo_login'])) ?></small>
                                <?php else: ?>
                                    <small class="text-muted">Nunca</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= path('/usuarios/' . $usuario['id']) ?>" class="btn btn-outline-secondary" title="Detalhes">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= path('/usuarios/' . $usuario['id'] . '/editar') ?>" class="btn btn-outline-secondary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
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

<div class="card mt-4">
    <div class="card-header">
        <i class="bi bi-info-circle me-2"></i>Informações
    </div>
    <div class="card-body">
        <p class="text-muted mb-0">
            Os usuários são criados automaticamente ao fazer login com a conta Microsoft da organização.
            Novos usuários recebem o papel de "Visualização" por padrão.
        </p>
    </div>
</div>
