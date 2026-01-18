<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= path('/clientes') ?>">Clientes</a></li>
                <li class="breadcrumb-item"><a href="<?= path('/clientes/' . $cliente['id']) ?>"><?= htmlspecialchars($cliente['nome']) ?></a></li>
                <li class="breadcrumb-item"><a href="<?= path('/clientes/' . $cliente['id'] . '/rotinas') ?>">Rotinas</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($rotina['nome']) ?></li>
            </ol>
        </nav>
        <h4 class="mt-2 mb-1"><?= htmlspecialchars($rotina['nome']) ?></h4>
        <?php if ($rotina['ativa']): ?>
            <span class="badge bg-success">Ativa</span>
        <?php else: ?>
            <span class="badge bg-secondary">Inativa</span>
        <?php endif; ?>
    </div>
    <?php if (in_array($user['role'] ?? '', ['admin', 'operator'])): ?>
    <div class="btn-group">
        <a href="<?= path('/clientes/' . $cliente['id'] . '/rotinas/' . $rotina['id'] . '/editar') ?>" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Editar
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <!-- Informações da Rotina -->
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-info-circle me-2"></i>Informações da Rotina
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr>
                        <th class="border-0 ps-0">Tipo</th>
                        <td class="border-0 text-end">
                            <?php if ($rotina['tipo']): ?>
                                <span class="badge bg-info"><?= htmlspecialchars($rotina['tipo']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th class="ps-0">Agendamento</th>
                        <td class="text-end"><?= htmlspecialchars($rotina['agendamento'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th class="ps-0">Destino</th>
                        <td class="text-end">
                            <?php if ($rotina['destino']): ?>
                                <small><?= htmlspecialchars($rotina['destino']) ?></small>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($host): ?>
                    <tr>
                        <th class="ps-0">Host Vinculado</th>
                        <td class="text-end">
                            <?= htmlspecialchars($host['nome']) ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th class="ps-0">Criada em</th>
                        <td class="text-end"><?= date('d/m/Y H:i', strtotime($rotina['created_at'])) ?></td>
                    </tr>
                    <tr>
                        <th class="ps-0">Atualizada em</th>
                        <td class="text-end"><?= date('d/m/Y H:i', strtotime($rotina['updated_at'])) ?></td>
                    </tr>
                </table>
                
                <?php if (!empty($rotina['observacoes'])): ?>
                <hr>
                <p class="small text-muted mb-0"><?= nl2br(htmlspecialchars($rotina['observacoes'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Routine Key -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-key me-2"></i>Routine Key
            </div>
            <div class="card-body">
                <div class="input-group">
                    <input type="password" class="form-control font-monospace" id="routineKey" 
                           value="<?= htmlspecialchars($rotina['routine_key']) ?>" readonly>
                    <button class="btn btn-outline-secondary" type="button" onclick="toggleRoutineKey()">
                        <i class="bi bi-eye" id="routineKeyIcon"></i>
                    </button>
                    <button class="btn btn-outline-secondary" type="button" onclick="copyRoutineKey()">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
                <small class="text-muted d-block mt-2">
                    Use esta chave para identificar a rotina ao enviar dados pela API.
                </small>
                
                <?php if (($user['role'] ?? '') === 'admin'): ?>
                <button class="btn btn-sm btn-outline-danger mt-3" onclick="regenerateRoutineKey()">
                    <i class="bi bi-arrow-repeat me-1"></i>Regenerar Routine Key
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Host Info -->
        <?php 
            $hostInfo = null;
            if (!empty($rotina['host_info'])) {
                $hostInfo = is_string($rotina['host_info']) ? json_decode($rotina['host_info'], true) : $rotina['host_info'];
            }
        ?>
        <?php if ($hostInfo && !empty(array_filter($hostInfo))): ?>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-pc-display me-2"></i>Informações do Host
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <?php if (!empty($hostInfo['nome'])): ?>
                    <tr>
                        <th class="border-0 ps-0">Nome</th>
                        <td class="border-0 text-end"><?= htmlspecialchars($hostInfo['nome']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($hostInfo['hostname'])): ?>
                    <tr>
                        <th class="ps-0">Hostname</th>
                        <td class="text-end"><?= htmlspecialchars($hostInfo['hostname']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($hostInfo['ip'])): ?>
                    <tr>
                        <th class="ps-0">IP</th>
                        <td class="text-end"><?= htmlspecialchars($hostInfo['ip']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($hostInfo['sistema_operacional'])): ?>
                    <tr>
                        <th class="ps-0">Sistema Operacional</th>
                        <td class="text-end"><?= htmlspecialchars($hostInfo['sistema_operacional']) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Últimas Execuções -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clock-history me-2"></i>Últimas Execuções
            </div>
            <div class="card-body p-0">
                <?php if (empty($execucoes)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3 mb-0">Nenhuma execução registrada ainda</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th>Duração</th>
                                <th>Tamanho</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($execucoes as $exec): ?>
                            <?php
                                $duracao = '';
                                if ($exec['data_fim']) {
                                    $inicio = new DateTime($exec['data_inicio']);
                                    $fim = new DateTime($exec['data_fim']);
                                    $diff = $inicio->diff($fim);
                                    
                                    if ($diff->h > 0) {
                                        $duracao = $diff->h . 'h ' . $diff->i . 'm';
                                    } else {
                                        $duracao = $diff->i . 'm ' . $diff->s . 's';
                                    }
                                }
                            ?>
                            <tr>
                                <td>
                                    <a href="<?= path('/backups/' . $exec['id']) ?>" class="text-decoration-none">
                                        <?= date('d/m/Y H:i', strtotime($exec['data_inicio'])) ?>
                                    </a>
                                </td>
                                <td><?= $duracao ?: '-' ?></td>
                                <td><?= format_bytes($exec['tamanho_bytes']) ?></td>
                                <td class="text-center">
                                    <?php
                                        $badges = [
                                            'sucesso' => 'bg-success',
                                            'falha' => 'bg-danger',
                                            'alerta' => 'bg-warning text-dark',
                                            'executando' => 'bg-info'
                                        ];
                                        $badgeClass = $badges[$exec['status']] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= ucfirst($exec['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <a href="<?= path('/backups?rotina_id=' . $rotina['id']) ?>" class="text-decoration-none">
                        Ver todas as execuções <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function toggleRoutineKey() {
    const input = document.getElementById('routineKey');
    const icon = document.getElementById('routineKeyIcon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}

function copyRoutineKey() {
    const input = document.getElementById('routineKey');
    navigator.clipboard.writeText(input.value).then(() => {
        showToast('Routine Key copiada para a área de transferência');
    });
}

function regenerateRoutineKey() {
    if (!confirm('Tem certeza que deseja regenerar a Routine Key?\n\nIsso invalidará a chave atual e o agente precisará ser reconfigurado.')) {
        return;
    }
    
    fetch('<?= path('/clientes/' . $cliente['id'] . '/rotinas/' . $rotina['id'] . '/regenerar-key') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('routineKey').value = data.routine_key;
            showToast('Routine Key regenerada com sucesso');
        } else {
            showToast(data.error || 'Erro ao regenerar Routine Key', 'error');
        }
    })
    .catch(() => {
        showToast('Erro ao regenerar Routine Key', 'error');
    });
}
</script>
