<?php
/**
 * View: Detalhes da Execução de Backup
 * 
 * Exibe informações detalhadas similar a um relatório completo
 */

// Helper para formatar duração
function formatDuration($segundos) {
    if (!$segundos) return '-';
    $horas = floor($segundos / 3600);
    $minutos = floor(($segundos % 3600) / 60);
    $segs = $segundos % 60;
    return sprintf('%02d:%02d:%02d', $horas, $minutos, $segs);
}

// Helper para formatar bytes
function formatBytesDetailed($bytes, $precision = 2) {
    if ($bytes === null || $bytes === 0) return '-';
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}

// Helper para status badge
function getStatusBadge($status) {
    $badges = [
        'sucesso' => ['class' => 'bg-success', 'icon' => 'check-circle-fill', 'text' => 'Sucesso'],
        'falha' => ['class' => 'bg-danger', 'icon' => 'x-circle-fill', 'text' => 'Falha'],
        'alerta' => ['class' => 'bg-warning text-dark', 'icon' => 'exclamation-triangle-fill', 'text' => 'Alerta'],
        'executando' => ['class' => 'bg-info', 'icon' => 'arrow-repeat', 'text' => 'Executando']
    ];
    return $badges[$status] ?? $badges['alerta'];
}

$detalhes = $execucao['detalhes_array'] ?? [];
$hostInfo = $detalhes['host_info'] ?? [];
$statusBadge = getStatusBadge($execucao['status']);

// Identifica o tipo de backup
$backupSource = $detalhes['source'] ?? $detalhes['tipo_backup'] ?? 'unknown';
$isVeeam = stripos($backupSource, 'veeam') !== false;
$isWSB = stripos($backupSource, 'windows') !== false || stripos($backupSource, 'wsb') !== false;

// Calcula duração
$duracao = null;
$duracaoSegundos = null;
if ($execucao['data_inicio'] && $execucao['data_fim']) {
    $inicio = new DateTime($execucao['data_inicio']);
    $fim = new DateTime($execucao['data_fim']);
    $diff = $inicio->diff($fim);
    $duracao = $diff->format('%H:%I:%S');
    $duracaoSegundos = $fim->getTimestamp() - $inicio->getTimestamp();
}
?>

<style>
.report-section {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    overflow: hidden;
}
.report-header {
    background: linear-gradient(135deg, #212529 0%, #343a40 100%);
    color: #fff;
    padding: 1.25rem 1.5rem;
}
.report-header h5 {
    margin: 0;
    font-weight: 600;
}
.report-header .subtitle {
    opacity: 0.8;
    font-size: 0.875rem;
    margin-top: 0.25rem;
}
.report-body {
    padding: 1.5rem;
}
.info-table {
    width: 100%;
}
.info-table tr {
    border-bottom: 1px solid #f0f0f0;
}
.info-table tr:last-child {
    border-bottom: none;
}
.info-table th {
    font-weight: 500;
    color: #6c757d;
    padding: 0.75rem 1rem 0.75rem 0;
    width: 35%;
    vertical-align: top;
    white-space: nowrap;
}
.info-table td {
    padding: 0.75rem 0;
    word-break: break-word;
}
.status-large {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
}
.status-large i {
    font-size: 1.25rem;
}
.data-table {
    width: 100%;
    font-size: 0.875rem;
}
.data-table thead th {
    background: #f8f9fa;
    padding: 0.75rem;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
}
.data-table tbody td {
    padding: 0.75rem;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: middle;
}
.data-table tbody tr:hover {
    background: #f8f9fa;
}
.metric-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
}
.metric-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #212529;
}
.metric-label {
    font-size: 0.75rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.progress-bar-custom {
    height: 8px;
    border-radius: 4px;
    background: #e9ecef;
    overflow: hidden;
}
.progress-bar-custom .fill {
    height: 100%;
    border-radius: 4px;
}
.veeam-badge {
    background: linear-gradient(135deg, #00b336 0%, #009929 100%);
    color: #fff;
}
.wsb-badge {
    background: linear-gradient(135deg, #0078d4 0%, #005a9e 100%);
    color: #fff;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">
            <i class="bi bi-file-earmark-bar-graph me-2"></i>Relatório de Backup #<?= $execucao['id'] ?>
        </h4>
        <span class="text-muted">Gerado em <?= date('d/m/Y H:i:s') ?></span>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Imprimir
        </button>
        <a href="<?= path('/backups') ?>" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left me-1"></i>Voltar
        </a>
    </div>
</div>

<!-- Cabeçalho Principal do Relatório -->
<div class="report-section">
    <div class="report-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h5>
                    <?php if ($isVeeam): ?>
                        <i class="bi bi-server me-2"></i>Veeam Backup & Replication Report
                    <?php elseif ($isWSB): ?>
                        <i class="bi bi-hdd me-2"></i>Windows Server Backup Report
                    <?php else: ?>
                        <i class="bi bi-archive me-2"></i>Backup Report
                    <?php endif; ?>
                </h5>
                <div class="subtitle">
                    <?= htmlspecialchars($execucao['rotina_nome'] ?? 'Rotina de Backup') ?> - 
                    <?= htmlspecialchars($execucao['cliente_nome'] ?? 'Cliente') ?>
                </div>
            </div>
            <span class="badge <?= $isVeeam ? 'veeam-badge' : 'wsb-badge' ?> px-3 py-2">
                <?= $isVeeam ? 'Veeam' : ($isWSB ? 'Windows Server Backup' : 'Backup') ?>
            </span>
        </div>
    </div>
    
    <div class="report-body">
        <!-- Métricas Principais -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value <?= $execucao['status'] === 'sucesso' ? 'text-success' : ($execucao['status'] === 'falha' ? 'text-danger' : 'text-warning') ?>">
                        <i class="bi bi-<?= $statusBadge['icon'] ?>"></i>
                    </div>
                    <div class="metric-label">Resultado</div>
                    <div class="fw-bold"><?= ucfirst($execucao['status']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value"><?= $duracao ?? '-' ?></div>
                    <div class="metric-label">Duração</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value"><?= formatBytesDetailed($execucao['tamanho_bytes'] ?? null) ?></div>
                    <div class="metric-label">Tamanho</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value"><?= date('d/m/Y', strtotime($execucao['data_inicio'])) ?></div>
                    <div class="metric-label">Data</div>
                </div>
            </div>
        </div>
        
        <!-- Informações do Backup -->
        <div class="row">
            <div class="col-lg-6">
                <h6 class="text-muted mb-3"><i class="bi bi-info-circle me-1"></i>Informações do Backup</h6>
                <table class="info-table">
                    <tr>
                        <th>Data do Relatório:</th>
                        <td><?= date('Y-m-d H:i') ?></td>
                    </tr>
                    <tr>
                        <th>Resultado:</th>
                        <td>
                            <?php if ($execucao['status'] === 'sucesso'): ?>
                                <span class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i>Este backup foi concluído com sucesso!</span>
                            <?php elseif ($execucao['status'] === 'falha'): ?>
                                <span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i>Este backup falhou!</span>
                            <?php elseif ($execucao['status'] === 'alerta'): ?>
                                <span class="text-warning fw-bold"><i class="bi bi-exclamation-triangle me-1"></i>Backup concluído com avisos</span>
                            <?php else: ?>
                                <span class="text-info fw-bold"><i class="bi bi-arrow-repeat me-1"></i>Backup em execução...</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (!empty($execucao['mensagem_erro'])): ?>
                    <tr>
                        <th>Mensagem de Erro:</th>
                        <td class="text-danger"><?= htmlspecialchars($execucao['mensagem_erro']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Destino do Backup:</th>
                        <td><code><?= htmlspecialchars($execucao['destino'] ?? $detalhes['BackupTarget'] ?? $detalhes['TargetRepository'] ?? '-') ?></code></td>
                    </tr>
                    <tr>
                        <th>Início:</th>
                        <td><?= date('d/m/Y H:i:s', strtotime($execucao['data_inicio'])) ?></td>
                    </tr>
                    <tr>
                        <th>Término:</th>
                        <td><?= $execucao['data_fim'] ? date('d/m/Y H:i:s', strtotime($execucao['data_fim'])) : '<span class="text-muted">Em execução...</span>' ?></td>
                    </tr>
                    <tr>
                        <th>Duração:</th>
                        <td><?= $duracao ?? '-' ?></td>
                    </tr>
                    <?php if (!empty($detalhes['NextBackupTime'])): ?>
                    <tr>
                        <th>Próximo Agendamento:</th>
                        <td><?= htmlspecialchars($detalhes['NextBackupTime']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($detalhes['tipo_backup'])): ?>
                    <tr>
                        <th>Tipo de Backup:</th>
                        <td><?= htmlspecialchars($detalhes['tipo_backup']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (isset($detalhes['IsFullBackup'])): ?>
                    <tr>
                        <th>Backup Completo:</th>
                        <td><?= $detalhes['IsFullBackup'] ? '<span class="text-success">Sim</span>' : '<span class="text-muted">Incremental</span>' ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <div class="col-lg-6">
                <h6 class="text-muted mb-3"><i class="bi bi-pc-display me-1"></i>Informações da Máquina</h6>
                <table class="info-table">
                    <tr>
                        <th>Nome da Máquina:</th>
                        <td><strong><?= htmlspecialchars($execucao['host_nome'] ?? $hostInfo['nome'] ?? $hostInfo['hostname'] ?? '-') ?></strong></td>
                    </tr>
                    <?php if (!empty($hostInfo['hostname']) && $hostInfo['hostname'] !== ($hostInfo['nome'] ?? '')): ?>
                    <tr>
                        <th>Hostname:</th>
                        <td><code><?= htmlspecialchars($hostInfo['hostname']) ?></code></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Endereço IP:</th>
                        <td><code><?= htmlspecialchars($hostInfo['ip'] ?? $hostInfo['ip_local'] ?? '-') ?></code></td>
                    </tr>
                    <?php if (!empty($hostInfo['mac'])): ?>
                    <tr>
                        <th>Endereço MAC:</th>
                        <td><code><?= htmlspecialchars($hostInfo['mac']) ?></code></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($hostInfo['ip_externo'])): ?>
                    <tr>
                        <th>IP Externo:</th>
                        <td><code><?= htmlspecialchars($hostInfo['ip_externo']) ?></code></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Sistema Operacional:</th>
                        <td><?= htmlspecialchars($hostInfo['sistema_operacional'] ?? $hostInfo['os'] ?? '-') ?></td>
                    </tr>
                    <?php if (!empty($hostInfo['uptime_hours'])): ?>
                    <tr>
                        <th>Tempo Ligado:</th>
                        <td>
                            <?php 
                            $uptime = $hostInfo['uptime_hours'];
                            $dias = floor($uptime / 24);
                            $horas = $uptime % 24;
                            echo "{$dias} Dias; {$horas} Horas";
                            ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($hostInfo['powershell_version'])): ?>
                    <tr>
                        <th>Versão PowerShell:</th>
                        <td><?= htmlspecialchars($hostInfo['powershell_version']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (isset($hostInfo['is_virtual'])): ?>
                    <tr>
                        <th>Tipo de Máquina:</th>
                        <td><?= $hostInfo['is_virtual'] ? 'Máquina Virtual' : 'Máquina Física' ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Cliente:</th>
                        <td>
                            <a href="<?= path('/clientes/' . $execucao['cliente_id']) ?>" class="text-decoration-none">
                                <?= htmlspecialchars($execucao['cliente_nome'] ?? '-') ?>
                            </a>
                            <br><small class="text-muted"><?= htmlspecialchars($execucao['cliente_identificador'] ?? '') ?></small>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($isVeeam && !empty($detalhes['ProcessedVMs'])): ?>
<!-- Seção Veeam: VMs Processadas -->
<div class="report-section">
    <div class="report-header" style="background: linear-gradient(135deg, #00b336 0%, #009929 100%);">
        <h5><i class="bi bi-hdd-stack me-2"></i>Máquinas Virtuais Processadas</h5>
        <div class="subtitle"><?= count($detalhes['ProcessedVMs']) ?> VM(s) no job</div>
    </div>
    <div class="report-body p-0">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Status</th>
                    <th>Dados Processados</th>
                    <th>Transferido</th>
                    <th>Duração</th>
                    <th>Velocidade Média</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalhes['ProcessedVMs'] as $vm): ?>
                <tr>
                    <td>
                        <i class="bi bi-display me-1 text-muted"></i>
                        <strong><?= htmlspecialchars($vm['Name'] ?? '-') ?></strong>
                        <?php if (!empty($vm['Reason'])): ?>
                        <br><small class="text-danger"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($vm['Reason']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        $vmStatus = strtolower($vm['Status'] ?? 'unknown');
                        $vmStatusClass = $vmStatus === 'success' ? 'success' : ($vmStatus === 'warning' ? 'warning' : ($vmStatus === 'failed' ? 'danger' : 'secondary'));
                        ?>
                        <span class="badge bg-<?= $vmStatusClass ?>">
                            <?= ucfirst($vm['Status'] ?? 'Unknown') ?>
                        </span>
                    </td>
                    <td><?= formatBytesDetailed($vm['ProcessedSize'] ?? 0) ?></td>
                    <td><?= formatBytesDetailed($vm['TransferredSize'] ?? $vm['TransferedSize'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($vm['Duration'] ?? '-') ?></td>
                    <td><?= isset($vm['AvgSpeed']) ? formatBytesDetailed($vm['AvgSpeed']) . '/s' : '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($isVeeam && (!empty($detalhes['Warnings']) || !empty($detalhes['ErrorLogs']) || !empty($detalhes['FailureMessage']))): ?>
<!-- Seção Veeam: Erros e Alertas -->
<div class="report-section">
    <div class="report-header bg-danger">
        <h5><i class="bi bi-exclamation-octagon me-2"></i>Erros e Alertas</h5>
        <div class="subtitle">Detalhes dos problemas encontrados durante o backup</div>
    </div>
    <div class="report-body">
        <?php if (!empty($execucao['mensagem_erro'])): ?>
        <div class="alert alert-danger mb-3">
            <h6 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i>Mensagem de Erro Principal</h6>
            <p class="mb-0"><?= nl2br(htmlspecialchars($execucao['mensagem_erro'])) ?></p>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($detalhes['FailureMessage'])): ?>
        <div class="alert alert-warning mb-3">
            <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Detalhes da Falha</h6>
            <p class="mb-0"><?= nl2br(htmlspecialchars($detalhes['FailureMessage'])) ?></p>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($detalhes['ResultDescription'])): ?>
        <div class="alert alert-secondary mb-3">
            <h6 class="alert-heading"><i class="bi bi-file-text me-2"></i>Descrição do Resultado</h6>
            <p class="mb-0"><?= nl2br(htmlspecialchars($detalhes['ResultDescription'])) ?></p>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($detalhes['Warnings'])): ?>
        <h6 class="mb-3"><i class="bi bi-exclamation-triangle me-2"></i>Objetos com Problemas</h6>
        <table class="data-table mb-4">
            <thead>
                <tr>
                    <th>Objeto</th>
                    <th>Status</th>
                    <th>Motivo</th>
                    <th>Detalhes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalhes['Warnings'] as $warning): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($warning['Object'] ?? '-') ?></strong></td>
                    <td>
                        <?php $warnStatus = strtolower($warning['Status'] ?? 'warning'); ?>
                        <span class="badge bg-<?= $warnStatus === 'failed' ? 'danger' : 'warning' ?>">
                            <?= htmlspecialchars($warning['Status'] ?? 'Warning') ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($warning['Reason'] ?? '-') ?></td>
                    <td><small><?= htmlspecialchars($warning['Details'] ?? '-') ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <?php if (!empty($detalhes['ErrorLogs'])): ?>
        <h6 class="mb-3"><i class="bi bi-journal-x me-2"></i>Logs de Erro da Sessão</h6>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Horário</th>
                        <th>Nível</th>
                        <th>Mensagem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalhes['ErrorLogs'] as $log): ?>
                    <tr>
                        <td class="text-nowrap"><?= htmlspecialchars($log['Time'] ?? '-') ?></td>
                        <td>
                            <?php 
                            $logStatus = $log['Status'] ?? 'EWarning';
                            $logClass = match($logStatus) {
                                'EFailed', 'Error' => 'danger',
                                'EWarning' => 'warning',
                                default => 'secondary'
                            };
                            ?>
                            <span class="badge bg-<?= $logClass ?>"><?= htmlspecialchars($logStatus) ?></span>
                        </td>
                        <td><?= htmlspecialchars($log['Message'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Estatísticas Veeam -->
<?php if ($isVeeam && (isset($detalhes['SourceSize']) || isset($detalhes['TransferedSize']) || isset($detalhes['AvgSpeed']))): ?>
<div class="report-section">
    <div class="report-header" style="background: linear-gradient(135deg, #00b336 0%, #009929 100%);">
        <h5><i class="bi bi-bar-chart me-2"></i>Estatísticas de Processamento</h5>
    </div>
    <div class="report-body">
        <div class="row g-4">
            <?php if (isset($detalhes['SourceSize'])): ?>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value"><?= formatBytesDetailed($detalhes['SourceSize']) ?></div>
                    <div class="metric-label">Tamanho Origem</div>
                </div>
            </div>
            <?php endif; ?>
            <?php if (isset($detalhes['ReadSize'])): ?>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value"><?= formatBytesDetailed($detalhes['ReadSize']) ?></div>
                    <div class="metric-label">Dados Lidos</div>
                </div>
            </div>
            <?php endif; ?>
            <?php if (isset($detalhes['TransferedSize'])): ?>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value"><?= formatBytesDetailed($detalhes['TransferedSize']) ?></div>
                    <div class="metric-label">Dados Transferidos</div>
                </div>
            </div>
            <?php endif; ?>
            <?php if (isset($detalhes['BackupSize'])): ?>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value"><?= formatBytesDetailed($detalhes['BackupSize']) ?></div>
                    <div class="metric-label">Tamanho do Backup</div>
                </div>
            </div>
            <?php endif; ?>
            <?php if (isset($detalhes['ProcessedObjects']) && isset($detalhes['TotalObjects'])): ?>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value"><?= $detalhes['ProcessedObjects'] ?>/<?= $detalhes['TotalObjects'] ?></div>
                    <div class="metric-label">Objetos Processados</div>
                </div>
            </div>
            <?php endif; ?>
            <?php if (isset($detalhes['AvgSpeed'])): ?>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value"><?= formatBytesDetailed($detalhes['AvgSpeed']) ?>/s</div>
                    <div class="metric-label">Velocidade Média</div>
                </div>
            </div>
            <?php endif; ?>
            <?php if (isset($detalhes['Duration'])): ?>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value"><?= htmlspecialchars($detalhes['Duration']) ?></div>
                    <div class="metric-label">Duração Total</div>
                </div>
            </div>
            <?php endif; ?>
            <?php if (isset($detalhes['Progress'])): ?>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value"><?= $detalhes['Progress'] ?>%</div>
                    <div class="metric-label">Progresso</div>
                    <div class="progress-bar-custom mt-2">
                        <div class="fill bg-success" style="width: <?= $detalhes['Progress'] ?>%"></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($detalhes['Bottleneck'])): ?>
        <div class="alert alert-info mt-4 mb-0">
            <i class="bi bi-speedometer2 me-2"></i>
            <strong>Gargalo de Performance:</strong> <?= htmlspecialchars($detalhes['Bottleneck']) ?>
        </div>
        <?php endif; ?>
        
        <div class="mt-4">
            <h6 class="mb-3"><i class="bi bi-info-circle me-2"></i>Informações da Sessão</h6>
            <table class="info-table">
                <?php if (!empty($detalhes['TargetRepository'])): ?>
                <tr>
                    <th>Repositório de Destino:</th>
                    <td>
                        <code><?= htmlspecialchars($detalhes['TargetRepository']) ?></code>
                        <?php if (!empty($detalhes['RepositoryPath'])): ?>
                        <br><small class="text-muted"><?= htmlspecialchars($detalhes['RepositoryPath']) ?></small>
                        <?php endif; ?>
                        <?php if (!empty($detalhes['RepositoryType'])): ?>
                        <span class="badge bg-secondary ms-2"><?= htmlspecialchars($detalhes['RepositoryType']) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($detalhes['SessionId'])): ?>
                <tr>
                    <th>ID da Sessão:</th>
                    <td><code><?= htmlspecialchars($detalhes['SessionId']) ?></code></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($detalhes['SessionName'])): ?>
                <tr>
                    <th>Nome da Sessão:</th>
                    <td><?= htmlspecialchars($detalhes['SessionName']) ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($detalhes['job_id'])): ?>
                <tr>
                    <th>ID do Job:</th>
                    <td><code><?= htmlspecialchars($detalhes['job_id']) ?></code></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($detalhes['job_type'])): ?>
                <tr>
                    <th>Tipo do Job:</th>
                    <td><?= htmlspecialchars($detalhes['job_type']) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>Tipo de Backup:</th>
                    <td>
                        <?php if (!empty($detalhes['IsFullBackup'])): ?>
                        <span class="badge bg-primary">Backup Completo (Full)</span>
                        <?php else: ?>
                        <span class="badge bg-info">Backup Incremental</span>
                        <?php endif; ?>
                        <?php if (!empty($detalhes['IsRetry'])): ?>
                        <span class="badge bg-warning text-dark ms-1">Retry</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if (isset($detalhes['WillBeRetried'])): ?>
                <tr>
                    <th>Será Retentado:</th>
                    <td><?= $detalhes['WillBeRetried'] ? '<span class="badge bg-warning text-dark">Sim</span>' : '<span class="badge bg-success">Não</span>' ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($detalhes['State'])): ?>
                <tr>
                    <th>Estado Final:</th>
                    <td><?= htmlspecialchars($detalhes['State']) ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($detalhes['Result'])): ?>
                <tr>
                    <th>Resultado:</th>
                    <td>
                        <?php 
                        $resultClass = match(strtolower($detalhes['Result'])) {
                            'success' => 'success',
                            'warning' => 'warning',
                            'failed' => 'danger',
                            default => 'secondary'
                        };
                        ?>
                        <span class="badge bg-<?= $resultClass ?>"><?= htmlspecialchars($detalhes['Result']) ?></span>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($isVeeam && !empty($detalhes['Repositories'])): ?>
<!-- Repositórios Veeam -->
<div class="report-section">
    <div class="report-header" style="background: linear-gradient(135deg, #00b336 0%, #009929 100%);">
        <h5><i class="bi bi-database me-2"></i>Repositórios de Backup</h5>
    </div>
    <div class="report-body p-0">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>Caminho</th>
                    <th>Espaço Total</th>
                    <th>Usado</th>
                    <th>Livre</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalhes['Repositories'] as $repo): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($repo['Name'] ?? '-') ?></strong></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($repo['Type'] ?? '-') ?></span></td>
                    <td><code><?= htmlspecialchars($repo['Path'] ?? '-') ?></code></td>
                    <td><?= formatBytesDetailed($repo['TotalSpace'] ?? 0) ?></td>
                    <td><?= formatBytesDetailed($repo['UsedSpace'] ?? 0) ?></td>
                    <td>
                        <?php 
                        $freeSpace = $repo['FreeSpace'] ?? 0;
                        $totalSpace = $repo['TotalSpace'] ?? 1;
                        $freePercent = ($totalSpace > 0) ? ($freeSpace / $totalSpace * 100) : 0;
                        $freeClass = $freePercent > 50 ? 'success' : ($freePercent > 20 ? 'warning' : 'danger');
                        ?>
                        <span class="text-<?= $freeClass ?>"><?= formatBytesDetailed($freeSpace) ?></span>
                        <small class="text-muted">(<?= number_format($freePercent, 1) ?>%)</small>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($isWSB): ?>
<!-- Seção WSB: Itens do Backup -->
<?php if (!empty($detalhes['backup_items']) || !empty($detalhes['itens_backup'])): ?>
<?php $backupItems = $detalhes['backup_items'] ?? $detalhes['itens_backup'] ?? []; ?>
<div class="report-section">
    <div class="report-header" style="background: linear-gradient(135deg, #0078d4 0%, #005a9e 100%);">
        <h5><i class="bi bi-folder me-2"></i>Itens no Backup</h5>
        <div class="subtitle"><?= count($backupItems) ?> item(ns) incluído(s)</div>
    </div>
    <div class="report-body p-0">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>Dados Transferidos</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($backupItems as $item): ?>
                <tr>
                    <td>
                        <?php 
                        $itemType = strtolower($item['type'] ?? $item['tipo'] ?? 'volume');
                        $icon = $itemType === 'volume' ? 'hdd' : ($itemType === 'systemstate' ? 'gear' : 'folder');
                        ?>
                        <i class="bi bi-<?= $icon ?> me-1 text-muted"></i>
                        <strong><?= htmlspecialchars($item['name'] ?? $item['nome'] ?? '-') ?></strong>
                    </td>
                    <td><?= htmlspecialchars(ucfirst($item['type'] ?? $item['tipo'] ?? 'Volume')) ?></td>
                    <td>
                        <?php 
                        $itemStatus = strtolower($item['status'] ?? 'completed');
                        $statusClass = strpos($itemStatus, 'success') !== false || strpos($itemStatus, 'completed') !== false ? 'success' : 'warning';
                        ?>
                        <span class="badge bg-<?= $statusClass ?>">
                            <?= htmlspecialchars($item['status'] ?? 'Concluído') ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($item['data_transferred'] ?? $item['dados_transferidos'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Seção WSB: Uso de Mídia -->
<?php if (!empty($detalhes['media_usage']) || !empty($detalhes['uso_midia'])): ?>
<?php $mediaUsage = $detalhes['media_usage'] ?? $detalhes['uso_midia'] ?? []; ?>
<div class="report-section">
    <div class="report-header" style="background: linear-gradient(135deg, #0078d4 0%, #005a9e 100%);">
        <h5><i class="bi bi-device-hdd me-2"></i>Uso de Mídia</h5>
    </div>
    <div class="report-body p-0">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Label</th>
                    <th>Volume</th>
                    <th>Capacidade Total</th>
                    <th>Usado</th>
                    <th>Livre</th>
                    <th>% Livre</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mediaUsage as $media): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($media['label'] ?? $media['Label'] ?? '-') ?></strong></td>
                    <td><code><?= htmlspecialchars($media['volume'] ?? $media['Volume'] ?? '-') ?></code></td>
                    <td><?= htmlspecialchars($media['total_capacity'] ?? $media['TotalCapacity'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($media['used_space'] ?? $media['UsedSpace'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($media['free_space'] ?? $media['FreeSpace'] ?? '-') ?></td>
                    <td>
                        <?php 
                        $percentFree = floatval($media['percent_free'] ?? $media['PercentFree'] ?? 0);
                        $barClass = $percentFree > 50 ? 'success' : ($percentFree > 20 ? 'warning' : 'danger');
                        ?>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress-bar-custom flex-grow-1" style="height: 6px;">
                                <div class="fill bg-<?= $barClass ?>" style="width: <?= $percentFree ?>%"></div>
                            </div>
                            <span class="small"><?= number_format($percentFree, 1) ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Seção WSB: VSS Writers -->
<?php if (!empty($detalhes['vss_writers'])): ?>
<div class="report-section">
    <div class="report-header" style="background: linear-gradient(135deg, #0078d4 0%, #005a9e 100%);">
        <h5><i class="bi bi-journal-code me-2"></i>VSS Writers</h5>
    </div>
    <div class="report-body p-0">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome do Writer</th>
                    <th>Estado</th>
                    <th>Último Erro</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalhes['vss_writers'] as $writer): ?>
                <tr>
                    <td><?= htmlspecialchars($writer['name'] ?? $writer['Name'] ?? '-') ?></td>
                    <td>
                        <?php 
                        $writerState = strtolower($writer['state'] ?? $writer['State'] ?? 'stable');
                        $stateClass = $writerState === 'stable' ? 'success' : 'warning';
                        ?>
                        <span class="badge bg-<?= $stateClass ?>"><?= htmlspecialchars($writer['state'] ?? $writer['State'] ?? 'Stable') ?></span>
                    </td>
                    <td><?= htmlspecialchars($writer['last_error'] ?? $writer['LastError'] ?? 'No error') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Detalhes Técnicos (JSON) -->
<?php if (!empty($detalhes)): ?>
<?php 
// Verifica se os dados são completos ou básicos
$hasDetailedData = isset($detalhes['source']) || isset($detalhes['ProcessedVMs']) || isset($detalhes['backup_items']) || isset($detalhes['tipo_backup']);
$onlyHostInfo = !$hasDetailedData && isset($detalhes['host_info']) && count($detalhes) <= 2;
?>
<div class="report-section">
    <div class="report-header bg-secondary">
        <div class="d-flex justify-content-between align-items-center">
            <h5><i class="bi bi-code-slash me-2"></i>Dados Técnicos Completos</h5>
            <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="collapse" data-bs-target="#technicalDetails">
                <i class="bi bi-chevron-down"></i> Expandir
            </button>
        </div>
    </div>
    <div class="collapse" id="technicalDetails">
        <div class="report-body">
            <?php if ($onlyHostInfo): ?>
            <div class="alert alert-info mb-3">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Dados básicos:</strong> Esta execução foi registrada antes da atualização do agente de coleta. 
                Para obter dados técnicos detalhados (VMs processadas, erros, warnings, repositórios, etc.), 
                atualize o agente no servidor e execute uma nova coleta.
            </div>
            <?php endif; ?>
            <pre class="mb-0 bg-dark text-light p-3 rounded" style="max-height: 400px; overflow: auto;"><code><?= htmlspecialchars(json_encode($detalhes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></pre>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Timeline -->
<div class="report-section">
    <div class="report-header bg-secondary">
        <h5><i class="bi bi-clock-history me-2"></i>Timeline da Execução</h5>
    </div>
    <div class="report-body">
        <div class="d-flex">
            <div class="d-flex flex-column align-items-center me-4">
                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                    <i class="bi bi-play-fill text-white"></i>
                </div>
                <div class="bg-secondary" style="width:2px;flex:1;min-height:40px;"></div>
            </div>
            <div class="pb-4">
                <div class="fw-bold">Início do Backup</div>
                <div class="text-muted"><?= date('d/m/Y H:i:s', strtotime($execucao['data_inicio'])) ?></div>
            </div>
        </div>
        
        <?php if ($execucao['data_fim']): ?>
        <div class="d-flex">
            <div class="d-flex flex-column align-items-center me-4">
                <div class="bg-<?= $execucao['status'] === 'sucesso' ? 'success' : ($execucao['status'] === 'falha' ? 'danger' : 'warning') ?> rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                    <i class="bi bi-<?= $statusBadge['icon'] ?> text-white"></i>
                </div>
                <div class="bg-secondary" style="width:2px;flex:1;min-height:40px;"></div>
            </div>
            <div class="pb-4">
                <div class="fw-bold"><?= ucfirst($execucao['status']) ?></div>
                <div class="text-muted"><?= date('d/m/Y H:i:s', strtotime($execucao['data_fim'])) ?></div>
                <small class="text-muted">Duração: <?= $duracao ?></small>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="d-flex">
            <div class="d-flex flex-column align-items-center me-4">
                <div class="bg-dark rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                    <i class="bi bi-database text-white"></i>
                </div>
            </div>
            <div>
                <div class="fw-bold">Registrado no Sistema</div>
                <div class="text-muted"><?= date('d/m/Y H:i:s', strtotime($execucao['created_at'])) ?></div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .btn, button, .no-print { display: none !important; }
    .sidebar, .navbar { display: none !important; }
    .main-content { margin: 0 !important; padding: 0 !important; }
    .report-section { break-inside: avoid; }
    .collapse { display: block !important; }
}
</style>
