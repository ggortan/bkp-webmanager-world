<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label for="nome" class="form-label">Nome do Host <span class="text-danger">*</span></label>
            <input type="text" 
                   class="form-control <?= !empty($errors['nome']) ? 'is-invalid' : '' ?>" 
                   id="nome" 
                   name="nome" 
                   value="<?= htmlspecialchars($host['nome'] ?? '') ?>" 
                   required
                   maxlength="100">
            <?php if (!empty($errors['nome'])): ?>
                <div class="invalid-feedback"><?= $errors['nome'][0] ?></div>
            <?php endif; ?>
            <small class="form-text text-muted">Nome identificador do host (ex: SRV-FILESERVER-01)</small>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="mb-3">
            <label for="hostname" class="form-label">Hostname</label>
            <input type="text" 
                   class="form-control <?= !empty($errors['hostname']) ? 'is-invalid' : '' ?>" 
                   id="hostname" 
                   name="hostname" 
                   value="<?= htmlspecialchars($host['hostname'] ?? '') ?>"
                   maxlength="255">
            <?php if (!empty($errors['hostname'])): ?>
                <div class="invalid-feedback"><?= $errors['hostname'][0] ?></div>
            <?php endif; ?>
            <small class="form-text text-muted">FQDN ou hostname da máquina</small>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label for="ip" class="form-label">Endereço IP</label>
            <input type="text" 
                   class="form-control <?= !empty($errors['ip']) ? 'is-invalid' : '' ?>" 
                   id="ip" 
                   name="ip" 
                   value="<?= htmlspecialchars($host['ip'] ?? '') ?>"
                   placeholder="192.168.1.100">
            <?php if (!empty($errors['ip'])): ?>
                <div class="invalid-feedback"><?= $errors['ip'][0] ?></div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="mb-3">
            <label for="sistema_operacional" class="form-label">Sistema Operacional</label>
            <input type="text" 
                   class="form-control <?= !empty($errors['sistema_operacional']) ? 'is-invalid' : '' ?>" 
                   id="sistema_operacional" 
                   name="sistema_operacional" 
                   value="<?= htmlspecialchars($host['sistema_operacional'] ?? '') ?>"
                   placeholder="Windows Server 2022"
                   maxlength="100">
            <?php if (!empty($errors['sistema_operacional'])): ?>
                <div class="invalid-feedback"><?= $errors['sistema_operacional'][0] ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="mb-3">
    <label for="tipo" class="form-label">Tipo</label>
    <select class="form-select" id="tipo" name="tipo">
        <option value="">Selecione...</option>
        <option value="server" <?= ($host['tipo'] ?? '') === 'server' ? 'selected' : '' ?>>Servidor</option>
        <option value="workstation" <?= ($host['tipo'] ?? '') === 'workstation' ? 'selected' : '' ?>>Workstation</option>
        <option value="vm" <?= ($host['tipo'] ?? '') === 'vm' ? 'selected' : '' ?>>Máquina Virtual</option>
        <option value="container" <?= ($host['tipo'] ?? '') === 'container' ? 'selected' : '' ?>>Container</option>
    </select>
    <small class="form-text text-muted">Tipo de host (opcional)</small>
</div>

<div class="mb-3">
    <label for="observacoes" class="form-label">Observações</label>
    <textarea class="form-control" 
              id="observacoes" 
              name="observacoes" 
              rows="3"><?= htmlspecialchars($host['observacoes'] ?? '') ?></textarea>
    <small class="form-text text-muted">Informações adicionais sobre este host</small>
</div>

<div class="mb-3">
    <div class="form-check">
        <input type="checkbox" 
               class="form-check-input" 
               id="ativo" 
               name="ativo" 
               value="1"
               <?= empty($host) || ($host['ativo'] ?? 1) ? 'checked' : '' ?>>
        <label class="form-check-label" for="ativo">
            Host ativo
        </label>
    </div>
    <small class="form-text text-muted">Hosts inativos não aparecem nos seletores de rotinas</small>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-circle me-1"></i>Salvar
    </button>
    <a href="<?= path('/clientes/' . $cliente['id'] . '/hosts') ?>" class="btn btn-secondary">
        <i class="bi bi-x-circle me-1"></i>Cancelar
    </a>
</div>
