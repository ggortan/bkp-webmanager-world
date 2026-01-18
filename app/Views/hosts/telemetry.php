<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= path('/clientes') ?>">Clientes</a></li>
                <li class="breadcrumb-item"><a href="<?= path('/clientes/' . $cliente['id']) ?>"><?= htmlspecialchars($cliente['nome']) ?></a></li>
                <li class="breadcrumb-item"><a href="<?= path('/clientes/' . $cliente['id'] . '/hosts') ?>">Hosts</a></li>
                <li class="breadcrumb-item"><a href="<?= path('/clientes/' . $cliente['id'] . '/hosts/' . $host['id']) ?>"><?= htmlspecialchars($host['nome']) ?></a></li>
                <li class="breadcrumb-item active">Telemetria</li>
            </ol>
        </nav>
        <h4 class="mt-2 mb-0">
            <i class="bi bi-broadcast me-2"></i>Telemetria - <?= htmlspecialchars($host['nome']) ?>
        </h4>
    </div>
    <a href="<?= path('/clientes/' . $cliente['id'] . '/hosts/' . $host['id']) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Voltar
    </a>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Status Atual -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <?php 
                $onlineStatus = $host['online_status'] ?? 'unknown';
                $lastSeen = $host['last_seen_at'] ?? null;
                ?>
                <?php if ($onlineStatus === 'online'): ?>
                    <span class="badge bg-success fs-4 px-4 py-2 mb-2">
                        <i class="bi bi-circle-fill me-2" style="font-size: 0.7rem; animation: blink 1s infinite;"></i>ONLINE
                    </span>
                <?php elseif ($onlineStatus === 'offline'): ?>
                    <span class="badge bg-danger fs-4 px-4 py-2 mb-2">
                        <i class="bi bi-circle-fill me-2" style="font-size: 0.7rem;"></i>OFFLINE
                    </span>
                <?php else: ?>
                    <span class="badge bg-secondary fs-4 px-4 py-2 mb-2">
                        <i class="bi bi-question-circle me-2"></i>DESCONHECIDO
                    </span>
                <?php endif; ?>
                <p class="text-muted small mb-0">
                    <?php if ($lastSeen): ?>
                        Último contato: <?= date('d/m/Y H:i:s', strtotime($lastSeen)) ?>
                    <?php else: ?>
                        Nunca conectou
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card info">
            <i class="bi bi-cpu stat-icon"></i>
            <div class="stat-value">
                <?php 
                $telemetry = $host['telemetry_data'] ? json_decode($host['telemetry_data'], true) : null;
                echo $telemetry ? ($telemetry['cpu_percent'] ?? 0) . '%' : '-';
                ?>
            </div>
            <div class="stat-label">CPU Atual</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card warning">
            <i class="bi bi-memory stat-icon"></i>
            <div class="stat-value">
                <?= $telemetry ? ($telemetry['memory_percent'] ?? 0) . '%' : '-' ?>
            </div>
            <div class="stat-label">Memória Atual</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card <?= ($telemetry['disk_percent'] ?? 0) > 90 ? 'danger' : 'success' ?>">
            <i class="bi bi-hdd stat-icon"></i>
            <div class="stat-value">
                <?= $telemetry ? ($telemetry['disk_percent'] ?? 0) . '%' : '-' ?>
            </div>
            <div class="stat-label">Disco Atual</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Dados da Última Coleta -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-info-circle me-2"></i>Dados da Última Coleta
            </div>
            <div class="card-body">
                <?php if ($telemetry): ?>
                <table class="table table-sm mb-0">
                    <tr>
                        <th class="border-0 ps-0">CPU</th>
                        <td class="border-0 text-end">
                            <div class="progress" style="width: 80px; height: 10px; display: inline-block;">
                                <div class="progress-bar <?= ($telemetry['cpu_percent'] ?? 0) > 80 ? 'bg-danger' : 'bg-info' ?>" 
                                     style="width: <?= $telemetry['cpu_percent'] ?? 0 ?>%"></div>
                            </div>
                            <span class="ms-2"><?= $telemetry['cpu_percent'] ?? 0 ?>%</span>
                        </td>
                    </tr>
                    <tr>
                        <th class="ps-0">Memória</th>
                        <td class="text-end">
                            <div class="progress" style="width: 80px; height: 10px; display: inline-block;">
                                <div class="progress-bar <?= ($telemetry['memory_percent'] ?? 0) > 80 ? 'bg-danger' : 'bg-warning' ?>" 
                                     style="width: <?= $telemetry['memory_percent'] ?? 0 ?>%"></div>
                            </div>
                            <span class="ms-2"><?= $telemetry['memory_percent'] ?? 0 ?>%</span>
                        </td>
                    </tr>
                    <tr>
                        <th class="ps-0">Disco</th>
                        <td class="text-end">
                            <div class="progress" style="width: 80px; height: 10px; display: inline-block;">
                                <div class="progress-bar <?= ($telemetry['disk_percent'] ?? 0) > 90 ? 'bg-danger' : 'bg-success' ?>" 
                                     style="width: <?= $telemetry['disk_percent'] ?? 0 ?>%"></div>
                            </div>
                            <span class="ms-2"><?= $telemetry['disk_percent'] ?? 0 ?>%</span>
                        </td>
                    </tr>
                    <?php if (isset($telemetry['uptime_seconds'])): ?>
                    <tr>
                        <th class="ps-0">Uptime</th>
                        <td class="text-end">
                            <?php 
                            $seconds = $telemetry['uptime_seconds'];
                            $days = floor($seconds / 86400);
                            $hours = floor(($seconds % 86400) / 3600);
                            $minutes = floor(($seconds % 3600) / 60);
                            echo "{$days}d {$hours}h {$minutes}m";
                            ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
                <?php else: ?>
                <p class="text-muted mb-0 text-center">Nenhum dado de telemetria disponível.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Configuração de Telemetria -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-gear me-2"></i>Configuração
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr>
                        <th class="border-0 ps-0">Status</th>
                        <td class="border-0 text-end">
                            <?php if ($host['telemetry_enabled']): ?>
                                <span class="badge bg-success">Habilitada</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Desabilitada</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th class="ps-0">Intervalo</th>
                        <td class="text-end"><?= $host['telemetry_interval_minutes'] ?? 5 ?> minutos</td>
                    </tr>
                    <tr>
                        <th class="ps-0">Offline após</th>
                        <td class="text-end"><?= $host['telemetry_offline_threshold'] ?? 3 ?> falhas</td>
                    </tr>
                    <tr>
                        <th class="ps-0">Retenção</th>
                        <td class="text-end">
                            <?php if ($dias_retencao > 0): ?>
                                <?= $dias_retencao ?> dias
                            <?php else: ?>
                                <span class="text-muted">Manter sempre</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <a href="<?= path('/clientes/' . $cliente['id'] . '/hosts/' . $host['id'] . '/editar') ?>" 
                   class="btn btn-sm btn-outline-primary mt-3 w-100">
                    <i class="bi bi-pencil me-1"></i>Editar Configurações
                </a>
            </div>
        </div>
        
        <!-- Estatísticas -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-bar-chart me-2"></i>Estatísticas (Histórico)
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr>
                        <th class="border-0 ps-0">Registros</th>
                        <td class="border-0 text-end"><?= $stats['total_registros'] ?></td>
                    </tr>
                    <tr>
                        <th class="ps-0">CPU Média</th>
                        <td class="text-end"><?= $stats['media_cpu'] ?>%</td>
                    </tr>
                    <tr>
                        <th class="ps-0">CPU Máx.</th>
                        <td class="text-end"><?= $stats['max_cpu'] ?>%</td>
                    </tr>
                    <tr>
                        <th class="ps-0">Memória Média</th>
                        <td class="text-end"><?= $stats['media_memoria'] ?>%</td>
                    </tr>
                    <tr>
                        <th class="ps-0">Memória Máx.</th>
                        <td class="text-end"><?= $stats['max_memoria'] ?>%</td>
                    </tr>
                    <tr>
                        <th class="ps-0">Disco Média</th>
                        <td class="text-end"><?= $stats['media_disco'] ?>%</td>
                    </tr>
                    <tr>
                        <th class="ps-0">Disco Máx.</th>
                        <td class="text-end"><?= $stats['max_disco'] ?>%</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Histórico de Telemetrias -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-2"></i>Histórico de Telemetrias</span>
                <small class="text-muted">Últimos 100 registros</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th class="text-center">CPU</th>
                                <th class="text-center">Memória</th>
                                <th class="text-center">Disco</th>
                                <th>Uptime</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($historico)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    Nenhum histórico de telemetria disponível.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($historico as $registro): ?>
                                <tr>
                                    <td>
                                        <small><?= date('d/m/Y H:i:s', strtotime($registro['created_at'])) ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= $registro['cpu_percent'] > 80 ? 'bg-danger' : 'bg-info' ?>">
                                            <?= $registro['cpu_percent'] ?>%
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= $registro['memory_percent'] > 80 ? 'bg-danger' : 'bg-warning' ?>">
                                            <?= $registro['memory_percent'] ?>%
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= $registro['disk_percent'] > 90 ? 'bg-danger' : 'bg-success' ?>">
                                            <?= $registro['disk_percent'] ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($registro['uptime_seconds']) {
                                            $s = $registro['uptime_seconds'];
                                            $d = floor($s / 86400);
                                            $h = floor(($s % 86400) / 3600);
                                            $m = floor(($s % 3600) / 60);
                                            echo "{$d}d {$h}h {$m}m";
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0.3; }
}
</style>
