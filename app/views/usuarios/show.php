<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><?= htmlspecialchars($usuario['nome']) ?></h4>
        <span class="text-muted"><?= htmlspecialchars($usuario['email']) ?></span>
    </div>
    <div class="btn-group">
        <a href="/usuarios/<?= $usuario['id'] ?>/editar" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Editar
        </a>
        <a href="/usuarios" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Voltar
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" 
                     style="width:80px;height:80px;">
                    <span class="text-white fw-bold fs-1"><?= strtoupper(substr($usuario['nome'], 0, 1)) ?></span>
                </div>
                <h5 class="mb-1"><?= htmlspecialchars($usuario['nome']) ?></h5>
                <p class="text-muted mb-3"><?= htmlspecialchars($usuario['email']) ?></p>
                
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
                <span class="badge bg-<?= $roleColors[$role] ?? 'secondary' ?> fs-6">
                    <?= $roleNames[$role] ?? ucfirst($role) ?>
                </span>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle me-2"></i>Informações
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr>
                        <th class="border-0 ps-0" style="width:200px;">Status</th>
                        <td class="border-0">
                            <?php if ($usuario['ativo']): ?>
                                <span class="badge bg-success">Ativo</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inativo</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th class="ps-0">Papel</th>
                        <td>
                            <span class="badge bg-<?= $roleColors[$role] ?? 'secondary' ?>">
                                <?= $roleNames[$role] ?? ucfirst($role) ?>
                            </span>
                            <?php if (!empty($usuario['role_descricao'])): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($usuario['role_descricao']) ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th class="ps-0">Azure ID</th>
                        <td><code><?= htmlspecialchars($usuario['azure_id'] ?? '-') ?></code></td>
                    </tr>
                    <tr>
                        <th class="ps-0">Último Login</th>
                        <td>
                            <?php if ($usuario['ultimo_login']): ?>
                                <?= date('d/m/Y H:i:s', strtotime($usuario['ultimo_login'])) ?>
                            <?php else: ?>
                                <span class="text-muted">Nunca</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th class="ps-0">Criado em</th>
                        <td><?= date('d/m/Y H:i:s', strtotime($usuario['created_at'])) ?></td>
                    </tr>
                    <tr>
                        <th class="ps-0">Última atualização</th>
                        <td><?= date('d/m/Y H:i:s', strtotime($usuario['updated_at'])) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>
