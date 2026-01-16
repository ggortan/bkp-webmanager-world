<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">
        <i class="bi bi-hdd me-2"></i>Detalhes da Execução #<?= $execucao['id'] ?>
    </h4>
    <a href="/backups" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Voltar
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Informações da Execução</span>
                <span class="status-badge status-<?= $execucao['status'] ?>">
                    <?= ucfirst($execucao['status']) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Cliente</label>
                        <div class="fw-semibold">
                            <a href="/clientes/<?= $execucao['cliente_id'] ?>" class="text-decoration-none">
                                <?= htmlspecialchars($execucao['cliente_nome'] ?? '-') ?>
                            </a>
                            <br><small class="text-muted"><?= htmlspecialchars($execucao['cliente_identificador'] ?? '') ?></small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Servidor</label>
                        <div class="fw-semibold">
                            <?= htmlspecialchars($execucao['servidor_nome'] ?? '-') ?>
                            <?php if (!empty($execucao['hostname'])): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($execucao['hostname']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Rotina</label>
                        <div class="fw-semibold">
                            <?= htmlspecialchars($execucao['rotina_nome'] ?? '-') ?>
                            <?php if (!empty($execucao['rotina_tipo'])): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($execucao['rotina_tipo']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Tamanho</label>
                        <div class="fw-semibold">
                            <?= \App\Services\BackupService::formatBytes($execucao['tamanho_bytes'] ?? null) ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Início</label>
                        <div class="fw-semibold">
                            <?= date('d/m/Y H:i:s', strtotime($execucao['data_inicio'])) ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Fim</label>
                        <div class="fw-semibold">
                            <?php if ($execucao['data_fim']): ?>
                                <?= date('d/m/Y H:i:s', strtotime($execucao['data_fim'])) ?>
                                <br><small class="text-muted">
                                    Duração: <?php
                                    $inicio = new DateTime($execucao['data_inicio']);
                                    $fim = new DateTime($execucao['data_fim']);
                                    $diff = $inicio->diff($fim);
                                    echo $diff->format('%H:%I:%S');
                                    ?>
                                </small>
                            <?php else: ?>
                                <span class="text-muted">Em execução...</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($execucao['destino'])): ?>
                    <div class="col-12">
                        <label class="form-label text-muted small">Destino</label>
                        <div class="font-monospace bg-light p-2 rounded small">
                            <?= htmlspecialchars($execucao['destino']) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if (!empty($execucao['mensagem_erro'])): ?>
        <div class="card mt-4 border-danger">
            <div class="card-header bg-danger text-white">
                <i class="bi bi-exclamation-triangle me-2"></i>Mensagem de Erro
            </div>
            <div class="card-body">
                <pre class="mb-0 text-danger"><?= htmlspecialchars($execucao['mensagem_erro']) ?></pre>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($execucao['detalhes_array'])): ?>
        <div class="card mt-4">
            <div class="card-header">
                <i class="bi bi-code me-2"></i>Detalhes Adicionais
            </div>
            <div class="card-body">
                <pre class="mb-0"><code><?= htmlspecialchars(json_encode($execucao['detalhes_array'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></code></pre>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clock-history me-2"></i>Timeline
            </div>
            <div class="card-body">
                <div class="d-flex mb-3">
                    <div class="me-3">
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" 
                             style="width:32px;height:32px;">
                            <i class="bi bi-play-fill text-white small"></i>
                        </div>
                    </div>
                    <div>
                        <div class="fw-semibold">Início</div>
                        <small class="text-muted"><?= date('d/m/Y H:i:s', strtotime($execucao['data_inicio'])) ?></small>
                    </div>
                </div>
                
                <?php if ($execucao['data_fim']): ?>
                <div class="d-flex mb-3">
                    <div class="me-3">
                        <div class="bg-<?= $execucao['status'] === 'sucesso' ? 'success' : ($execucao['status'] === 'falha' ? 'danger' : 'warning') ?> rounded-circle d-flex align-items-center justify-content-center" 
                             style="width:32px;height:32px;">
                            <i class="bi bi-<?= $execucao['status'] === 'sucesso' ? 'check' : ($execucao['status'] === 'falha' ? 'x' : 'exclamation') ?>-lg text-white small"></i>
                        </div>
                    </div>
                    <div>
                        <div class="fw-semibold"><?= ucfirst($execucao['status']) ?></div>
                        <small class="text-muted"><?= date('d/m/Y H:i:s', strtotime($execucao['data_fim'])) ?></small>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="d-flex">
                    <div class="me-3">
                        <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" 
                             style="width:32px;height:32px;">
                            <i class="bi bi-database text-white small"></i>
                        </div>
                    </div>
                    <div>
                        <div class="fw-semibold">Registrado</div>
                        <small class="text-muted"><?= date('d/m/Y H:i:s', strtotime($execucao['created_at'])) ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
