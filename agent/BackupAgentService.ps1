<#
.SYNOPSIS
    Serviço do Agente de Backup - Roda como serviço Windows

.DESCRIPTION
    Este script é executado como serviço Windows e gerencia:
    - Envio de telemetria (heartbeat) periodicamente
    - Execução de rotinas de backup conforme agendamento
    - Monitoramento de backups do Windows Server Backup e Veeam

.NOTES
    Versão: 1.0.0
    Requer: PowerShell 5.1+, Windows Server 2012+
#>

param(
    [Parameter(Mandatory = $false)]
    [string]$ConfigPath = "$PSScriptRoot\config\config.json"
)

# ============================================
# CONFIGURAÇÃO E INICIALIZAÇÃO
# ============================================

$script:ServiceName = "BackupManagerAgent"
$script:LogPath = "$PSScriptRoot\logs"
$script:Running = $true
$script:Config = $null
$script:LastTelemetry = [DateTime]::MinValue
$script:LastBackupCheck = [DateTime]::MinValue

# Cria pasta de logs se não existir
if (-not (Test-Path $script:LogPath)) {
    New-Item -ItemType Directory -Path $script:LogPath -Force | Out-Null
}

# Função de log
function Write-ServiceLog {
    param(
        [string]$Message,
        [ValidateSet('INFO', 'WARN', 'ERROR', 'DEBUG')]
        [string]$Level = 'INFO'
    )
    
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logMessage = "[$timestamp] [$Level] $Message"
    
    # Console (se disponível)
    switch ($Level) {
        'ERROR' { Write-Host $logMessage -ForegroundColor Red }
        'WARN'  { Write-Host $logMessage -ForegroundColor Yellow }
        'DEBUG' { Write-Host $logMessage -ForegroundColor Gray }
        default { Write-Host $logMessage -ForegroundColor White }
    }
    
    # Arquivo de log
    $logFile = Join-Path $script:LogPath "agent-$(Get-Date -Format 'yyyy-MM-dd').log"
    Add-Content -Path $logFile -Value $logMessage -ErrorAction SilentlyContinue
}

# Carrega configuração
function Load-Configuration {
    Write-ServiceLog "Carregando configuração de: $ConfigPath"
    
    if (-not (Test-Path $ConfigPath)) {
        Write-ServiceLog "Arquivo de configuração não encontrado!" -Level ERROR
        return $false
    }
    
    try {
        $script:Config = Get-Content $ConfigPath -Raw | ConvertFrom-Json
        
        # Valida campos obrigatórios
        if (-not $script:Config.api_url -or -not $script:Config.api_token) {
            Write-ServiceLog "Configuração inválida: api_url e api_token são obrigatórios" -Level ERROR
            return $false
        }
        
        # Define valores padrão
        if (-not $script:Config.host_name) {
            $script:Config | Add-Member -NotePropertyName "host_name" -NotePropertyValue $env:COMPUTERNAME -Force
        }
        
        if (-not $script:Config.telemetry) {
            $script:Config | Add-Member -NotePropertyName "telemetry" -NotePropertyValue @{
                enabled = $true
                interval_minutes = 5
            } -Force
        }
        
        if (-not $script:Config.backup) {
            $script:Config | Add-Member -NotePropertyName "backup" -NotePropertyValue @{
                check_interval_minutes = 15
                collectors = @("wsb", "veeam")
            } -Force
        }
        
        Write-ServiceLog "Configuração carregada com sucesso"
        Write-ServiceLog "Host: $($script:Config.host_name)"
        Write-ServiceLog "API URL: $($script:Config.api_url)"
        Write-ServiceLog "Telemetria: $(if ($script:Config.telemetry.enabled) { 'Habilitada' } else { 'Desabilitada' })"
        
        return $true
    }
    catch {
        Write-ServiceLog "Erro ao carregar configuração: $_" -Level ERROR
        return $false
    }
}

# ============================================
# FUNÇÕES DE TELEMETRIA
# ============================================

function Get-SystemMetrics {
    $metrics = @{}
    
    try {
        # CPU
        $cpu = Get-CimInstance -ClassName Win32_Processor | Measure-Object -Property LoadPercentage -Average
        $metrics['cpu_percent'] = [math]::Round($cpu.Average, 2)
    }
    catch {
        Write-ServiceLog "Erro ao obter CPU: $_" -Level DEBUG
    }
    
    try {
        # Memória
        $os = Get-CimInstance -ClassName Win32_OperatingSystem
        $memoryUsed = $os.TotalVisibleMemorySize - $os.FreePhysicalMemory
        $metrics['memory_percent'] = [math]::Round(($memoryUsed / $os.TotalVisibleMemorySize) * 100, 2)
        $metrics['memory_total_mb'] = [math]::Round($os.TotalVisibleMemorySize / 1024, 0)
        $metrics['memory_used_mb'] = [math]::Round($memoryUsed / 1024, 0)
    }
    catch {
        Write-ServiceLog "Erro ao obter memória: $_" -Level DEBUG
    }
    
    try {
        # Disco do sistema
        $sysDrive = Get-CimInstance -ClassName Win32_LogicalDisk -Filter "DeviceID='$($env:SystemDrive)'"
        if ($sysDrive) {
            $diskUsed = $sysDrive.Size - $sysDrive.FreeSpace
            $metrics['disk_percent'] = [math]::Round(($diskUsed / $sysDrive.Size) * 100, 2)
            $metrics['disk_total_gb'] = [math]::Round($sysDrive.Size / 1GB, 2)
            $metrics['disk_used_gb'] = [math]::Round($diskUsed / 1GB, 2)
        }
    }
    catch {
        Write-ServiceLog "Erro ao obter disco: $_" -Level DEBUG
    }
    
    try {
        # Uptime
        $uptime = (Get-Date) - (Get-CimInstance -ClassName Win32_OperatingSystem).LastBootUpTime
        $metrics['uptime_seconds'] = [math]::Round($uptime.TotalSeconds, 0)
        $metrics['uptime_days'] = [math]::Round($uptime.TotalDays, 2)
    }
    catch {
        Write-ServiceLog "Erro ao obter uptime: $_" -Level DEBUG
    }
    
    return $metrics
}

function Get-SystemInfo {
    $info = @{
        hostname = [System.Net.Dns]::GetHostName()
        ip = $null
        os = $null
    }
    
    try {
        $ipAddress = Get-NetIPAddress -AddressFamily IPv4 | 
            Where-Object { $_.IPAddress -notlike '127.*' -and $_.PrefixOrigin -ne 'WellKnown' } | 
            Select-Object -First 1
        if ($ipAddress) {
            $info['ip'] = $ipAddress.IPAddress
        }
    }
    catch {
        try {
            $ipAddress = [System.Net.Dns]::GetHostAddresses([System.Net.Dns]::GetHostName()) | 
                Where-Object { $_.AddressFamily -eq 'InterNetwork' -and $_.IPAddressToString -notlike '127.*' } | 
                Select-Object -First 1
            if ($ipAddress) {
                $info['ip'] = $ipAddress.IPAddressToString
            }
        }
        catch { }
    }
    
    try {
        $os = Get-CimInstance -ClassName Win32_OperatingSystem
        $info['os'] = "$($os.Caption) $($os.Version)"
    }
    catch { }
    
    return $info
}

function Send-Telemetry {
    if (-not $script:Config.telemetry.enabled) {
        return
    }
    
    $intervalMinutes = $script:Config.telemetry.interval_minutes
    if ($intervalMinutes -lt 1) { $intervalMinutes = 5 }
    
    $timeSinceLastTelemetry = (Get-Date) - $script:LastTelemetry
    
    if ($timeSinceLastTelemetry.TotalMinutes -lt $intervalMinutes) {
        return
    }
    
    Write-ServiceLog "Enviando telemetria..." -Level DEBUG
    
    $endpoint = "$($script:Config.api_url.TrimEnd('/'))/api/telemetry"
    $systemInfo = Get-SystemInfo
    $metrics = Get-SystemMetrics
    
    $body = @{
        host_name = $script:Config.host_name
        hostname = $systemInfo.hostname
        ip = $systemInfo.ip
        os = $systemInfo.os
        metrics = $metrics
    } | ConvertTo-Json -Depth 5
    
    $headers = @{
        'Content-Type' = 'application/json'
        'Authorization' = "Bearer $($script:Config.api_token)"
        'Accept' = 'application/json'
    }
    
    try {
        $response = Invoke-RestMethod -Uri $endpoint -Method POST -Headers $headers -Body $body -TimeoutSec 30
        
        if ($response.success) {
            Write-ServiceLog "Telemetria enviada - Status: $($response.status)"
            $script:LastTelemetry = Get-Date
        }
        else {
            Write-ServiceLog "Falha ao enviar telemetria: $($response | ConvertTo-Json -Compress)" -Level WARN
        }
    }
    catch {
        Write-ServiceLog "Erro ao enviar telemetria: $($_.Exception.Message)" -Level ERROR
    }
}

# ============================================
# FUNÇÕES DE BACKUP
# ============================================

function Get-WSBBackups {
    $backups = @()
    
    try {
        # Verifica se o módulo está disponível
        if (-not (Get-Command Get-WBJob -ErrorAction SilentlyContinue)) {
            return $backups
        }
        
        # Obtém último job
        $job = Get-WBJob -Previous 1 -ErrorAction SilentlyContinue
        
        if ($job) {
            $backup = @{
                source = 'wsb'
                job_name = 'Windows Server Backup'
                start_time = $job.StartTime
                end_time = $job.EndTime
                status = switch ($job.JobState) {
                    'Completed' { 'sucesso' }
                    'Failed' { 'falha' }
                    'Running' { 'executando' }
                    default { 'alerta' }
                }
                details = $job.DetailedMessage
                error_message = $job.ErrorDescription
            }
            
            # Tenta obter tamanho
            try {
                $summary = Get-WBBackupSet -ErrorAction SilentlyContinue | Select-Object -Last 1
                if ($summary) {
                    $backup['size_bytes'] = $summary.BackupSize
                }
            }
            catch { }
            
            $backups += $backup
        }
    }
    catch {
        Write-ServiceLog "Erro ao coletar WSB: $_" -Level DEBUG
    }
    
    return $backups
}

function Get-VeeamBackups {
    $backups = @()
    
    try {
        # Verifica se Veeam está instalado
        if (-not (Get-Command Get-VBRJob -ErrorAction SilentlyContinue)) {
            # Tenta carregar o módulo
            $veeamPath = "${env:ProgramFiles}\Veeam\Backup and Replication\Console"
            if (Test-Path "$veeamPath\Veeam.Backup.PowerShell\Veeam.Backup.PowerShell.psd1") {
                Import-Module "$veeamPath\Veeam.Backup.PowerShell\Veeam.Backup.PowerShell.psd1" -ErrorAction SilentlyContinue
            }
        }
        
        if (-not (Get-Command Get-VBRJob -ErrorAction SilentlyContinue)) {
            return $backups
        }
        
        # Obtém jobs
        $jobs = Get-VBRJob -ErrorAction SilentlyContinue
        
        foreach ($job in $jobs) {
            $lastSession = $job.FindLastSession()
            
            if ($lastSession) {
                $backup = @{
                    source = 'veeam'
                    job_name = $job.Name
                    start_time = $lastSession.CreationTime
                    end_time = $lastSession.EndTime
                    status = switch ($lastSession.Result) {
                        'Success' { 'sucesso' }
                        'Warning' { 'alerta' }
                        'Failed' { 'falha' }
                        'None' { 'executando' }
                        default { 'alerta' }
                    }
                    details = $lastSession.Description
                }
                
                # Tenta obter tamanho
                try {
                    $taskSessions = $lastSession.GetTaskSessions()
                    $totalSize = ($taskSessions | Measure-Object -Property Progress.TotalSize -Sum).Sum
                    if ($totalSize) {
                        $backup['size_bytes'] = $totalSize
                    }
                }
                catch { }
                
                $backups += $backup
            }
        }
    }
    catch {
        Write-ServiceLog "Erro ao coletar Veeam: $_" -Level DEBUG
    }
    
    return $backups
}

function Send-BackupResult {
    param(
        [hashtable]$Backup,
        [string]$RoutineKey
    )
    
    $endpoint = "$($script:Config.api_url.TrimEnd('/'))/api/backup"
    
    $body = @{
        routine_key = $RoutineKey
        status = $Backup.status
        data_inicio = $Backup.start_time.ToString("yyyy-MM-dd HH:mm:ss")
        data_fim = if ($Backup.end_time) { $Backup.end_time.ToString("yyyy-MM-dd HH:mm:ss") } else { $null }
        tamanho_bytes = $Backup.size_bytes
        detalhes = $Backup.details
        mensagem_erro = $Backup.error_message
        host_info = @{
            name = $script:Config.host_name
            ip = (Get-SystemInfo).ip
            os = (Get-SystemInfo).os
        }
    } | ConvertTo-Json -Depth 5
    
    $headers = @{
        'Content-Type' = 'application/json'
        'Authorization' = "Bearer $($script:Config.api_token)"
        'X-API-Key' = $script:Config.api_token
        'Accept' = 'application/json'
    }
    
    try {
        $response = Invoke-RestMethod -Uri $endpoint -Method POST -Headers $headers -Body $body -TimeoutSec 30
        
        if ($response.success -or $response.id) {
            Write-ServiceLog "Backup enviado - Job: $($Backup.job_name), Status: $($Backup.status)"
            return $true
        }
        else {
            Write-ServiceLog "Falha ao enviar backup: $($response | ConvertTo-Json -Compress)" -Level WARN
            return $false
        }
    }
    catch {
        Write-ServiceLog "Erro ao enviar backup: $($_.Exception.Message)" -Level ERROR
        return $false
    }
}

function Check-Backups {
    $intervalMinutes = $script:Config.backup.check_interval_minutes
    if ($intervalMinutes -lt 5) { $intervalMinutes = 15 }
    
    $timeSinceLastCheck = (Get-Date) - $script:LastBackupCheck
    
    if ($timeSinceLastCheck.TotalMinutes -lt $intervalMinutes) {
        return
    }
    
    Write-ServiceLog "Verificando backups..." -Level DEBUG
    
    $collectors = $script:Config.backup.collectors
    $routines = $script:Config.routines
    
    if (-not $routines -or $routines.Count -eq 0) {
        Write-ServiceLog "Nenhuma rotina configurada" -Level DEBUG
        $script:LastBackupCheck = Get-Date
        return
    }
    
    # Coleta backups
    $allBackups = @()
    
    if ($collectors -contains 'wsb') {
        $wsbBackups = Get-WSBBackups
        $allBackups += $wsbBackups
    }
    
    if ($collectors -contains 'veeam') {
        $veeamBackups = Get-VeeamBackups
        $allBackups += $veeamBackups
    }
    
    # Envia backups para as rotinas correspondentes
    foreach ($routine in $routines) {
        $routineKey = $routine.routine_key
        $source = $routine.source
        $jobName = $routine.job_name
        
        $matchingBackups = $allBackups | Where-Object {
            ($_.source -eq $source) -and
            (-not $jobName -or $_.job_name -like "*$jobName*")
        }
        
        foreach ($backup in $matchingBackups) {
            # Verifica se já enviamos este backup (baseado no horário)
            $backupKey = "$routineKey-$($backup.start_time.ToString('yyyyMMddHHmmss'))"
            $sentBackupsFile = Join-Path $script:LogPath "sent-backups.json"
            
            $sentBackups = @{}
            if (Test-Path $sentBackupsFile) {
                try {
                    $sentBackups = Get-Content $sentBackupsFile -Raw | ConvertFrom-Json -AsHashtable
                }
                catch {
                    $sentBackups = @{}
                }
            }
            
            if ($sentBackups.ContainsKey($backupKey)) {
                continue
            }
            
            # Envia backup
            $result = Send-BackupResult -Backup $backup -RoutineKey $routineKey
            
            if ($result) {
                $sentBackups[$backupKey] = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
                
                # Mantém apenas últimos 1000 registros
                if ($sentBackups.Count -gt 1000) {
                    $keysToRemove = $sentBackups.Keys | Sort-Object | Select-Object -First 500
                    foreach ($key in $keysToRemove) {
                        $sentBackups.Remove($key)
                    }
                }
                
                $sentBackups | ConvertTo-Json | Set-Content $sentBackupsFile -Force
            }
        }
    }
    
    $script:LastBackupCheck = Get-Date
}

# ============================================
# LOOP PRINCIPAL DO SERVIÇO
# ============================================

function Start-ServiceLoop {
    Write-ServiceLog "Iniciando loop do serviço..."
    
    # Loop principal
    while ($script:Running) {
        try {
            # Envia telemetria
            Send-Telemetry
            
            # Verifica backups
            Check-Backups
        }
        catch {
            Write-ServiceLog "Erro no loop principal: $_" -Level ERROR
        }
        
        # Aguarda 30 segundos antes de próxima iteração
        Start-Sleep -Seconds 30
    }
    
    Write-ServiceLog "Loop do serviço encerrado"
}

function Stop-Service {
    Write-ServiceLog "Recebido sinal para parar o serviço..."
    $script:Running = $false
}

# ============================================
# PONTO DE ENTRADA
# ============================================

Write-ServiceLog "========================================"
Write-ServiceLog "  Backup Manager Agent Service"
Write-ServiceLog "========================================"
Write-ServiceLog "Iniciando serviço..."

# Carrega configuração
if (-not (Load-Configuration)) {
    Write-ServiceLog "Falha ao carregar configuração. Encerrando." -Level ERROR
    exit 1
}

# Registra handler para parada
Register-EngineEvent -SourceIdentifier PowerShell.Exiting -Action { Stop-Service } -ErrorAction SilentlyContinue

# Inicia loop
try {
    Start-ServiceLoop
}
catch {
    Write-ServiceLog "Erro fatal: $_" -Level ERROR
    exit 1
}

Write-ServiceLog "Serviço encerrado"
exit 0
