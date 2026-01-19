<#
.SYNOPSIS
    Agente de Coleta de Dados de Backup - Backup WebManager
    
.DESCRIPTION
    Agente que coleta automaticamente informações de backups do Windows Server Backup
    e Veeam Backup & Replication, enviando os dados para a API central.
    
.NOTES
    Versão: 1.0.0
    Autor: Backup WebManager
    
.EXAMPLE
    .\BackupAgent.ps1 -ConfigPath "C:\BackupAgent\config\config.json"
    
.EXAMPLE
    .\BackupAgent.ps1 -ConfigPath "config.json" -RunOnce
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory = $false)]
    [string]$ConfigPath = "$PSScriptRoot\config\config.json",
    
    [Parameter(Mandatory = $false)]
    [switch]$RunOnce,
    
    [Parameter(Mandatory = $false)]
    [switch]$TestMode,
    
    [Parameter(Mandatory = $false)]
    [switch]$Verbose
)

#Requires -Version 5.1
#Requires -RunAsAdministrator

# ============================================================
# CONFIGURAÇÕES GLOBAIS
# ============================================================

$ErrorActionPreference = "Stop"
$Script:LogPath = "$PSScriptRoot\logs"
$Script:Config = $null
$Script:ModulesPath = "$PSScriptRoot\modules"

# ============================================================
# FUNÇÕES DE LOGGING
# ============================================================

function Write-Log {
    <#
    .SYNOPSIS
        Escreve mensagens no log
    #>
    param(
        [Parameter(Mandatory = $true)]
        [string]$Message,
        
        [Parameter(Mandatory = $false)]
        [ValidateSet('INFO', 'WARNING', 'ERROR', 'DEBUG')]
        [string]$Level = 'INFO'
    )
    
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logMessage = "[$timestamp] [$Level] $Message"
    
    # Cria diretório de logs se não existir
    if (-not (Test-Path $Script:LogPath)) {
        New-Item -ItemType Directory -Path $Script:LogPath -Force | Out-Null
    }
    
    # Define o arquivo de log do dia
    $logFile = Join-Path $Script:LogPath "agent_$(Get-Date -Format 'yyyy-MM-dd').log"
    
    # Escreve no arquivo
    Add-Content -Path $logFile -Value $logMessage -Encoding UTF8
    
    # Escreve no console também
    switch ($Level) {
        'ERROR' { Write-Host $logMessage -ForegroundColor Red }
        'WARNING' { Write-Host $logMessage -ForegroundColor Yellow }
        'DEBUG' { if ($VerbosePreference -eq 'Continue') { Write-Host $logMessage -ForegroundColor Gray } }
        default { Write-Host $logMessage -ForegroundColor White }
    }
}

function Clear-OldLogs {
    <#
    .SYNOPSIS
        Remove logs antigos baseado na configuração
    #>
    param(
        [int]$RetentionDays = 30
    )
    
    try {
        $cutoffDate = (Get-Date).AddDays(-$RetentionDays)
        
        Get-ChildItem -Path $Script:LogPath -Filter "agent_*.log" |
            Where-Object { $_.LastWriteTime -lt $cutoffDate } |
            Remove-Item -Force
        
        Write-Log "Logs antigos removidos (retenção: $RetentionDays dias)" -Level DEBUG
    }
    catch {
        Write-Log "Erro ao limpar logs antigos: $_" -Level WARNING
    }
}

# ============================================================
# FUNÇÕES DE CONFIGURAÇÃO
# ============================================================

function Load-Configuration {
    <#
    .SYNOPSIS
        Carrega o arquivo de configuração
    #>
    param(
        [string]$Path
    )
    
    try {
        if (-not (Test-Path $Path)) {
            throw "Arquivo de configuração não encontrado: $Path"
        }
        
        $configContent = Get-Content -Path $Path -Raw -Encoding UTF8
        $config = $configContent | ConvertFrom-Json
        
        # Valida configurações obrigatórias
        if (-not $config.api.url) {
            throw "URL da API não configurada"
        }
        
        if (-not $config.api.api_key) {
            throw "API Key não configurada"
        }
        
        Write-Log "Configuração carregada com sucesso" -Level INFO
        
        return $config
    }
    catch {
        Write-Log "Erro ao carregar configuração: $_" -Level ERROR
        throw
    }
}

# ============================================================
# FUNÇÕES DE API
# ============================================================

function Send-BackupDataToAPI {
    <#
    .SYNOPSIS
        Envia dados de backup para a API
    #>
    param(
        [Parameter(Mandatory = $true)]
        [hashtable]$BackupData
    )
    
    $apiUrl = $Script:Config.api.url.TrimEnd('/') + "/api/backup"
    $apiKey = $Script:Config.api.api_key
    $timeout = $Script:Config.api.timeout_seconds
    $maxRetries = $Script:Config.api.retry_attempts
    $retryDelay = $Script:Config.api.retry_delay_seconds
    
    $headers = @{
        "X-API-Key" = $apiKey
        "Content-Type" = "application/json; charset=utf-8"
    }
    
    $jsonBody = $BackupData | ConvertTo-Json -Depth 10 -Compress
    
    # Garante encoding UTF-8 (PowerShell pode usar UTF-16 por padrão)
    $utf8Encoding = [System.Text.Encoding]::UTF8
    $body = $utf8Encoding.GetBytes($jsonBody)
    
    $attempt = 0
    $success = $false
    
    while ($attempt -lt $maxRetries -and -not $success) {
        $attempt++
        
        try {
            Write-Log "Enviando dados para API (tentativa $attempt/$maxRetries)..." -Level DEBUG
            
            $response = Invoke-RestMethod -Uri $apiUrl -Method Post -Headers $headers -Body $body -TimeoutSec $timeout -UseBasicParsing
            
            if ($response.success) {
                Write-Log "Backup enviado com sucesso - ID: $($response.execucao_id)" -Level INFO
                $success = $true
                return $true
            }
            else {
                Write-Log "API retornou erro: $($response.error)" -Level WARNING
            }
        }
        catch {
            Write-Log "Erro ao enviar para API (tentativa $attempt): $($_.Exception.Message)" -Level WARNING
            
            if ($attempt -lt $maxRetries) {
                Write-Log "Aguardando $retryDelay segundos antes de tentar novamente..." -Level DEBUG
                Start-Sleep -Seconds $retryDelay
            }
        }
    }
    
    if (-not $success) {
        Write-Log "Falha ao enviar dados após $maxRetries tentativas" -Level ERROR
        return $false
    }
}

function Test-APIConnection {
    <#
    .SYNOPSIS
        Testa a conexão com a API
    #>
    try {
        $apiUrl = $Script:Config.api.url.TrimEnd('/') + "/api/status"
        
        Write-Log "Testando conexão com a API..." -Level INFO
        
        $response = Invoke-RestMethod -Uri $apiUrl -Method Get -TimeoutSec 10 -UseBasicParsing
        
        if ($response.status -eq "online") {
            Write-Log "API está online - Versão: $($response.version)" -Level INFO
            return $true
        }
        
        return $false
    }
    catch {
        Write-Log "Erro ao testar conexão com API: $_" -Level ERROR
        return $false
    }
}

# ============================================================
# FUNÇÃO PRINCIPAL DE COLETA
# ============================================================

function Invoke-BackupCollection {
    <#
    .SYNOPSIS
        Executa a coleta de dados de backup
    #>
    
    Write-Log "=== Iniciando coleta de dados de backup ===" -Level INFO
    
    $collectedData = @()
    $serverName = $Script:Config.agent.server_name
    $checkHours = if ($Script:Config.agent.check_interval_minutes -gt 60) { 
        [int]($Script:Config.agent.check_interval_minutes / 60) + 1
    } else { 
        24 
    }
    
    # ============================================================
    # COLETA: Windows Server Backup
    # ============================================================
    
    if ($Script:Config.collectors.windows_server_backup.enabled) {
        Write-Log "Coletando dados do Windows Server Backup..." -Level INFO
        
        try {
            Import-Module "$Script:ModulesPath\WindowsBackupCollector.psm1" -Force
            
            # Busca a routine_key configurada para Windows Server Backup
            $wsbRoutine = $Script:Config.rotinas | Where-Object { $_.collector_type -eq "windows_server_backup" -and $_.enabled }
            
            if (-not $wsbRoutine) {
                Write-Log "Nenhuma rotina habilitada para Windows Server Backup" -Level WARNING
            }
            else {
                # Coleta de jobs do WSB
                $wsbJobs = Get-WindowsServerBackupJobs -Hours $checkHours
                
                foreach ($job in $wsbJobs) {
                    $standardData = ConvertTo-StandardBackupFormat -Job $job -ServerName $serverName -RoutineKey $wsbRoutine.routine_key
                    $collectedData += $standardData
                }
                
                # Coleta de tarefas agendadas
                if ($Script:Config.collectors.windows_server_backup.check_event_log) {
                    $taskJobs = Get-TaskSchedulerBackups -Hours $checkHours
                    
                    foreach ($job in $taskJobs) {
                        $standardData = ConvertTo-StandardBackupFormat -Job $job -ServerName $serverName -RoutineKey $wsbRoutine.routine_key
                        $collectedData += $standardData
                    }
                }
                
                Write-Log "Windows Server Backup: $($wsbJobs.Count) jobs coletados" -Level INFO
            }
        }
        catch {
            Write-Log "Erro ao coletar dados do Windows Server Backup: $_" -Level ERROR
        }
    }
    
    # ============================================================
    # COLETA: Veeam Backup
    # ============================================================
    
    if ($Script:Config.collectors.veeam_backup.enabled) {
        Write-Log "Coletando dados do Veeam Backup..." -Level INFO
        
        try {
            Import-Module "$Script:ModulesPath\VeeamBackupCollector.psm1" -Force
            
            # Busca a routine_key configurada para Veeam
            $veeamRoutine = $Script:Config.rotinas | Where-Object { $_.collector_type -eq "veeam_backup" -and $_.enabled }
            
            if (-not $veeamRoutine) {
                Write-Log "Nenhuma rotina habilitada para Veeam Backup" -Level WARNING
            }
            else {
                $veeamServer = $Script:Config.collectors.veeam_backup.server
                $veeamPort = $Script:Config.collectors.veeam_backup.port
                
                # Coleta jobs de backup
                $veeamJobs = Get-VeeamBackupJobs -Hours $checkHours -Server $veeamServer -Port $veeamPort
                
                foreach ($job in $veeamJobs) {
                    $standardData = ConvertTo-StandardVeeamFormat -Job $job -ServerName $serverName -RoutineKey $veeamRoutine.routine_key
                    $collectedData += $standardData
                }
                
                # Coleta jobs de replicação
                $replicationJobs = Get-VeeamReplicationJobs -Hours $checkHours -Server $veeamServer -Port $veeamPort
                
                foreach ($job in $replicationJobs) {
                    $standardData = ConvertTo-StandardVeeamFormat -Job $job -ServerName $serverName -RoutineKey $veeamRoutine.routine_key
                    $collectedData += $standardData
                }
                
                Write-Log "Veeam Backup: $($veeamJobs.Count + $replicationJobs.Count) jobs coletados" -Level INFO
            }
        }
        catch {
            Write-Log "Erro ao coletar dados do Veeam: $_" -Level ERROR
        }
    }
    
    # ============================================================
    # FILTROS
    # ============================================================
    
    if ($Script:Config.filters.only_jobs.Count -gt 0) {
        $collectedData = $collectedData | Where-Object { 
            $_.rotina_nome -in $Script:Config.filters.only_jobs 
        }
    }
    
    if ($Script:Config.filters.ignore_jobs.Count -gt 0) {
        $collectedData = $collectedData | Where-Object { 
            $_.rotina_nome -notin $Script:Config.filters.ignore_jobs 
        }
    }
    
    if ($Script:Config.filters.min_size_mb -gt 0) {
        $minBytes = $Script:Config.filters.min_size_mb * 1MB
        $collectedData = $collectedData | Where-Object { 
            $_.tamanho_bytes -ge $minBytes 
        }
    }
    
    Write-Log "Total de backups coletados após filtros: $($collectedData.Count)" -Level INFO
    
    # ============================================================
    # ENVIO PARA API
    # ============================================================
    
    if ($collectedData.Count -eq 0) {
        Write-Log "Nenhum dado de backup para enviar" -Level INFO
        return
    }
    
    $successCount = 0
    $failCount = 0
    
    foreach ($data in $collectedData) {
        # Aplica filtro de notificações
        $shouldSend = $false
        
        if ($data.status -eq "falha" -and $Script:Config.notifications.send_on_failure) {
            $shouldSend = $true
        }
        elseif ($data.status -eq "alerta" -and $Script:Config.notifications.send_on_warning) {
            $shouldSend = $true
        }
        elseif ($data.status -eq "sucesso" -and $Script:Config.notifications.send_on_success) {
            $shouldSend = $true
        }
        
        if (-not $shouldSend) {
            Write-Log "Backup '$($data.rotina_nome)' ($($data.status)) ignorado por configuração de notificações" -Level DEBUG
            continue
        }
        
        if ($TestMode) {
            Write-Log "MODO TESTE - Dados que seriam enviados:" -Level INFO
            Write-Log ($data | ConvertTo-Json -Depth 5) -Level DEBUG
            $successCount++
        }
        else {
            $result = Send-BackupDataToAPI -BackupData $data
            
            if ($result) {
                $successCount++
            }
            else {
                $failCount++
            }
        }
    }
    
    Write-Log "=== Coleta finalizada: $successCount enviados, $failCount falhas ===" -Level INFO
}

# ============================================================
# LOOP PRINCIPAL
# ============================================================

function Start-AgentLoop {
    <#
    .SYNOPSIS
        Inicia o loop de execução do agente
    #>
    
    Write-Log "Agente iniciado em modo contínuo" -Level INFO
    Write-Log "Intervalo de verificação: $($Script:Config.agent.check_interval_minutes) minutos" -Level INFO
    
    while ($true) {
        try {
            Invoke-BackupCollection
            Clear-OldLogs -RetentionDays $Script:Config.agent.log_retention_days
        }
        catch {
            Write-Log "Erro durante a execução: $_" -Level ERROR
        }
        
        $sleepSeconds = $Script:Config.agent.check_interval_minutes * 60
        Write-Log "Próxima execução em $($Script:Config.agent.check_interval_minutes) minutos..." -Level INFO
        Start-Sleep -Seconds $sleepSeconds
    }
}

# ============================================================
# PONTO DE ENTRADA
# ============================================================

try {
    Write-Host @"

╔══════════════════════════════════════════════════════════════╗
║        BACKUP WEBMANAGER - AGENTE DE COLETA v1.0.0          ║
╚══════════════════════════════════════════════════════════════╝

"@ -ForegroundColor Cyan
    
    # Carrega configuração
    $Script:Config = Load-Configuration -Path $ConfigPath
    
    # Testa conexão com API
    $apiOk = Test-APIConnection
    
    if (-not $apiOk) {
        Write-Log "AVISO: API não está acessível, mas o agente continuará tentando" -Level WARNING
    }
    
    # Executa coleta
    if ($RunOnce -or $TestMode) {
        Invoke-BackupCollection
        Write-Log "Execução única finalizada" -Level INFO
    }
    else {
        Start-AgentLoop
    }
}
catch {
    Write-Log "Erro fatal: $_" -Level ERROR
    Write-Log $_.ScriptStackTrace -Level ERROR
    exit 1
}
