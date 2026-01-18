<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= path('/clientes') ?>">Clientes</a></li>
                <li class="breadcrumb-item"><a href="<?= path('/clientes/' . $cliente['id']) ?>"><?= htmlspecialchars($cliente['nome']) ?></a></li>
                <li class="breadcrumb-item"><a href="<?= path('/clientes/' . $cliente['id'] . '/rotinas') ?>">Rotinas</a></li>
                <li class="breadcrumb-item active"><?= $rotina ? 'Editar' : 'Nova Rotina' ?></li>
            </ol>
        </nav>
        <h4 class="mt-2 mb-0"><?= $rotina ? 'Editar Rotina' : 'Nova Rotina de Backup' ?></h4>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="<?= $rotina ? path('/clientes/' . $cliente['id'] . '/rotinas/' . $rotina['id']) : path('/clientes/' . $cliente['id'] . '/rotinas') ?>">
                    <?= csrf_field() ?>
                    
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome da Rotina *</label>
                        <input type="text" 
                               class="form-control <?= isset($errors['nome']) ? 'is-invalid' : '' ?>" 
                               id="nome" 
                               name="nome" 
                               value="<?= htmlspecialchars($rotina['nome'] ?? '') ?>"
                               placeholder="Ex: Backup_Diario_SQL"
                               required>
                        <?php if (isset($errors['nome'])): ?>
                            <div class="invalid-feedback"><?= $errors['nome'][0] ?></div>
                        <?php endif; ?>
                        <small class="form-text text-muted">Nome identificador da rotina de backup</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="tipo" class="form-label">Tipo de Backup</label>
                            <select class="form-select" id="tipo" name="tipo">
                                <option value="">Selecione...</option>
                                <option value="full" <?= ($rotina['tipo'] ?? '') === 'full' ? 'selected' : '' ?>>Full</option>
                                <option value="incremental" <?= ($rotina['tipo'] ?? '') === 'incremental' ? 'selected' : '' ?>>Incremental</option>
                                <option value="differential" <?= ($rotina['tipo'] ?? '') === 'differential' ? 'selected' : '' ?>>Differential</option>
                                <option value="snapshot" <?= ($rotina['tipo'] ?? '') === 'snapshot' ? 'selected' : '' ?>>Snapshot</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="agendamento" class="form-label">Agendamento</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="agendamento" 
                                   name="agendamento" 
                                   value="<?= htmlspecialchars($rotina['agendamento'] ?? '') ?>"
                                   placeholder="Ex: Diário às 22h">
                            <small class="form-text text-muted">Descrição do agendamento</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="destino" class="form-label">Destino do Backup</label>
                        <input type="text" 
                               class="form-control" 
                               id="destino" 
                               name="destino" 
                               value="<?= htmlspecialchars($rotina['destino'] ?? '') ?>"
                               placeholder="Ex: \\NAS\Backups\SQL">
                        <small class="form-text text-muted">Caminho de destino dos arquivos de backup</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="host_id" class="form-label">Host (Opcional)</label>
                        <select class="form-select" id="host_id" name="host_id">
                            <option value="">Nenhum (rotina independente)</option>
                            <?php foreach ($hosts as $host): ?>
                                <option value="<?= $host['id'] ?>" 
                                        <?= ($rotina['host_id'] ?? '') == $host['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($host['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Deixe vazio se a rotina não está vinculada a um host específico</small>
                    </div>
                    
                    <hr class="my-4">
                    <h5 class="mb-3">Informações do Host (Opcional)</h5>
                    <p class="text-muted small">
                        Configure as informações do host que executará a rotina. Estas informações podem ser atualizadas automaticamente pelo agente.
                    </p>
                    
                    <?php 
                        $hostInfo = null;
                        if (!empty($rotina['host_info'])) {
                            $hostInfo = is_string($rotina['host_info']) ? json_decode($rotina['host_info'], true) : $rotina['host_info'];
                        }
                    ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="host_nome" class="form-label">Nome do Host</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="host_nome" 
                                   name="host_nome" 
                                   value="<?= htmlspecialchars($hostInfo['nome'] ?? '') ?>"
                                   placeholder="Ex: SRV-BACKUP-01">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="host_hostname" class="form-label">Hostname</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="host_hostname" 
                                   name="host_hostname" 
                                   value="<?= htmlspecialchars($hostInfo['hostname'] ?? '') ?>"
                                   placeholder="Ex: srv-backup-01.domain.local">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="host_ip" class="form-label">Endereço IP</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="host_ip" 
                                   name="host_ip" 
                                   value="<?= htmlspecialchars($hostInfo['ip'] ?? '') ?>"
                                   placeholder="Ex: 192.168.1.100">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="host_so" class="form-label">Sistema Operacional</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="host_so" 
                                   name="host_so" 
                                   value="<?= htmlspecialchars($hostInfo['sistema_operacional'] ?? '') ?>"
                                   placeholder="Ex: Windows Server 2022">
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="mb-3">
                        <label for="observacoes" class="form-label">Observações</label>
                        <textarea class="form-control" 
                                  id="observacoes" 
                                  name="observacoes" 
                                  rows="3"><?= htmlspecialchars($rotina['observacoes'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input type="checkbox" 
                               class="form-check-input" 
                               id="ativa" 
                               name="ativa" 
                               value="1"
                               <?= ($rotina['ativa'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ativa">
                            Rotina ativa
                        </label>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i>Salvar
                        </button>
                        <a href="<?= path('/clientes/' . $cliente['id'] . '/rotinas') ?>" class="btn btn-outline-secondary">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle me-2"></i>Informações
            </div>
            <div class="card-body">
                <h6>Routine Key</h6>
                <p class="small text-muted">
                    <?php if ($rotina && !empty($rotina['routine_key'])): ?>
                        Cada rotina possui uma <strong>Routine Key</strong> única que deve ser configurada no agente do cliente.
                        <br><br>
                        <code class="d-block p-2 bg-light rounded"><?= htmlspecialchars($rotina['routine_key']) ?></code>
                    <?php else: ?>
                        Uma <strong>Routine Key</strong> única será gerada automaticamente ao salvar a rotina. 
                        Esta chave deve ser configurada no agente ou script do cliente.
                    <?php endif; ?>
                </p>
                
                <hr>
                
                <h6>Nova Arquitetura</h6>
                <p class="small text-muted">
                    As rotinas agora são independentes de hostes. Você pode:
                </p>
                <ul class="small text-muted">
                    <li>Criar rotinas sem vincular a um host</li>
                    <li>Configurar múltiplas rotinas para o mesmo host</li>
                    <li>Enviar dados de qualquer host usando a Routine Key</li>
                </ul>
            </div>
        </div>
    </div>
</div>
