<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">
        <i class="bi bi-pencil me-2"></i>Editar Usuário
    </h4>
    <a href="/usuarios/<?= $usuario['id'] ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Voltar
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="/usuarios/<?= $usuario['id'] ?>">
                    <?= \App\Helpers\Security::csrfField() ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($usuario['nome']) ?>" disabled>
                        <small class="text-muted">O nome é sincronizado com a conta Microsoft</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">E-mail</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($usuario['email']) ?>" disabled>
                        <small class="text-muted">O e-mail é sincronizado com a conta Microsoft</small>
                    </div>
                    
                    <?php if ($usuario['id'] !== $user['id']): ?>
                    <div class="mb-3">
                        <label for="role_id" class="form-label">Papel</label>
                        <select class="form-select" id="role_id" name="role_id">
                            <?php foreach ($roles as $role): ?>
                            <option value="<?= $role['id'] ?>" <?= $usuario['role_id'] == $role['id'] ? 'selected' : '' ?>>
                                <?= ucfirst($role['nome']) ?> - <?= $role['descricao'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="ativo" name="ativo" value="1"
                                   <?= $usuario['ativo'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativo">Usuário ativo</label>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Você não pode alterar seu próprio papel ou status.
                    </div>
                    <?php endif; ?>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between">
                        <a href="/usuarios/<?= $usuario['id'] ?>" class="btn btn-outline-secondary">
                            Cancelar
                        </a>
                        <?php if ($usuario['id'] !== $user['id']): ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Salvar Alterações
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-shield me-2"></i>Papéis e Permissões
            </div>
            <div class="card-body">
                <h6 class="text-danger">Administrador</h6>
                <p class="small text-muted">
                    Acesso total ao sistema. Pode gerenciar usuários, clientes, configurações e todos os dados.
                </p>
                
                <h6 class="text-primary">Operador</h6>
                <p class="small text-muted">
                    Pode gerenciar clientes, backups e relatórios. Não pode gerenciar usuários ou configurações do sistema.
                </p>
                
                <h6 class="text-secondary">Visualização</h6>
                <p class="small text-muted mb-0">
                    Acesso somente leitura. Pode visualizar dashboard, clientes e histórico de backups.
                </p>
            </div>
        </div>
    </div>
</div>
