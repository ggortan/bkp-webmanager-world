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

<!-- Configurações de Telemetria -->
<div class="card mb-3">
    <div class="card-header">
        <i class="bi bi-broadcast me-2"></i>Configurações de Telemetria
    </div>
    <div class="card-body">
        <div class="mb-3">
            <div class="form-check">
                <input type="checkbox" 
                       class="form-check-input" 
                       id="telemetry_enabled" 
                       name="telemetry_enabled" 
                       value="1"
                       <?= ($host['telemetry_enabled'] ?? 0) ? 'checked' : '' ?>>
                <label class="form-check-label" for="telemetry_enabled">
                    Habilitar monitoramento de telemetria
                </label>
            </div>
            <small class="form-text text-muted">
                Quando habilitado, o sistema monitorará se o host está online através dos heartbeats enviados pelo agente.
            </small>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="telemetry_interval_minutes" class="form-label">Intervalo de telemetria (minutos)</label>
                    <input type="number" 
                           class="form-control" 
                           id="telemetry_interval_minutes" 
                           name="telemetry_interval_minutes" 
                           value="<?= htmlspecialchars($host['telemetry_interval_minutes'] ?? '5') ?>"
                           min="1"
                           max="60">
                    <small class="form-text text-muted">Frequência esperada do envio de telemetria pelo agente.</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="telemetry_offline_threshold" class="form-label">Threshold offline (falhas)</label>
                    <input type="number" 
                           class="form-control" 
                           id="telemetry_offline_threshold" 
                           name="telemetry_offline_threshold" 
                           value="<?= htmlspecialchars($host['telemetry_offline_threshold'] ?? '3') ?>"
                           min="1"
                           max="20">
                    <small class="form-text text-muted">
                        Número de intervalos sem resposta para considerar o host offline.
                    </small>
                </div>
            </div>
        </div>
        
        <?php 
        $interval = (int)($host['telemetry_interval_minutes'] ?? 5);
        $threshold = (int)($host['telemetry_offline_threshold'] ?? 3);
        $offlineAfter = $interval * $threshold;
        ?>
        <div class="alert alert-info mb-0">
            <i class="bi bi-info-circle me-2"></i>
            Com a configuração atual, o host será considerado <strong>offline</strong> após <strong><?= $offlineAfter ?> minutos</strong> sem enviar telemetria.
        </div>
    </div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-circle me-1"></i>Salvar
    </button>
    <a href="<?= path('/clientes/' . $cliente['id'] . '/hosts') ?>" class="btn btn-secondary">
        <i class="bi bi-x-circle me-1"></i>Cancelar
    </a>
</div>
