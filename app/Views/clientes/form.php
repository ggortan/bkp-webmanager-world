<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">
        <i class="bi bi-building me-2"></i>
        <?= $cliente ? 'Editar Cliente' : 'Novo Cliente' ?>
    </h4>
    <a href="<?= $cliente ? '/clientes/' . $cliente['id'] : '/clientes' ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Voltar
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="<?= $cliente ? '/clientes/' . $cliente['id'] : '/clientes' ?>">
                    <?= \App\Helpers\Security::csrfField() ?>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="identificador" class="form-label">Identificador <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?= isset($errors['identificador']) ? 'is-invalid' : '' ?>" 
                                   id="identificador" name="identificador" 
                                   value="<?= htmlspecialchars($cliente['identificador'] ?? '') ?>"
                                   <?= $cliente ? 'readonly' : 'required' ?>
                                   pattern="[a-zA-Z0-9_-]+" title="Apenas letras, números, hífen e underscore">
                            <?php if (isset($errors['identificador'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['identificador'][0]) ?></div>
                            <?php else: ?>
                                <small class="text-muted">Identificador único (não pode ser alterado)</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-8">
                            <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?= isset($errors['nome']) ? 'is-invalid' : '' ?>" 
                                   id="nome" name="nome" 
                                   value="<?= htmlspecialchars($cliente['nome'] ?? '') ?>" required>
                            <?php if (isset($errors['nome'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['nome'][0]) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">E-mail</label>
                            <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" 
                                   id="email" name="email" 
                                   value="<?= htmlspecialchars($cliente['email'] ?? '') ?>">
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['email'][0]) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="telefone" class="form-label">Telefone</label>
                            <input type="text" class="form-control" id="telefone" name="telefone" 
                                   value="<?= htmlspecialchars($cliente['telefone'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="observacoes" class="form-label">Observações</label>
                        <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?= htmlspecialchars($cliente['observacoes'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="ativo" name="ativo" value="1"
                                       <?= ($cliente['ativo'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="ativo">Cliente ativo</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="relatorios_ativos" name="relatorios_ativos" value="1"
                                       <?= ($cliente['relatorios_ativos'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="relatorios_ativos">Receber relatórios por e-mail</label>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between">
                        <a href="<?= $cliente ? '/clientes/' . $cliente['id'] : '/clientes' ?>" class="btn btn-outline-secondary">
                            Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>
                            <?= $cliente ? 'Salvar Alterações' : 'Criar Cliente' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle me-2"></i>Ajuda
            </div>
            <div class="card-body">
                <h6>Identificador</h6>
                <p class="small text-muted">
                    Um código único para identificar o cliente. Será usado na API para envio de dados de backup.
                    Exemplo: <code>cliente-abc</code>, <code>empresa_xyz</code>
                </p>
                
                <h6>API Key</h6>
                <p class="small text-muted mb-0">
                    <?php if ($cliente): ?>
                        A API Key foi gerada automaticamente. Você pode visualizá-la na página de detalhes do cliente.
                    <?php else: ?>
                        Uma API Key será gerada automaticamente ao criar o cliente. Use-a para autenticar as requisições de backup.
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</div>
