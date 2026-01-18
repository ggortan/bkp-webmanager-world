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
    
    # ========== CPU ==========
    try {
        $cpuInfo = Get-CimInstance -ClassName Win32_Processor
        $cpuAvg = $cpuInfo | Measure-Object -Property LoadPercentage -Average
        $metrics['cpu_percent'] = [math]::Round($cpuAvg.Average, 2)
        
        # Detalhes do processador
        $cpuFirst = $cpuInfo | Select-Object -First 1
        $metrics['cpu_info'] = @{
            name = $cpuFirst.Name.Trim()
            cores = $cpuFirst.NumberOfCores
            logical_processors = $cpuFirst.NumberOfLogicalProcessors
            max_clock_mhz = $cpuFirst.MaxClockSpeed
            current_clock_mhz = $cpuFirst.CurrentClockSpeed
            architecture = switch ($cpuFirst.Architecture) {
                0 { 'x86' }
                9 { 'x64' }
                5 { 'ARM' }
                12 { 'ARM64' }
                default { 'Unknown' }
            }
        }
    }
    catch {
        Write-ServiceLog "Erro ao obter CPU: $_" -Level DEBUG
    }
    
    # ========== MEMÓRIA ==========
    try {
        $os = Get-CimInstance -ClassName Win32_OperatingSystem
        $memoryTotalKB = $os.TotalVisibleMemorySize
        $memoryFreeKB = $os.FreePhysicalMemory
        $memoryUsedKB = $memoryTotalKB - $memoryFreeKB
        
        $metrics['memory_percent'] = [math]::Round(($memoryUsedKB / $memoryTotalKB) * 100, 2)
        $metrics['memory_total_mb'] = [math]::Round($memoryTotalKB / 1024, 0)
        $metrics['memory_used_mb'] = [math]::Round($memoryUsedKB / 1024, 0)
        $metrics['memory_free_mb'] = [math]::Round($memoryFreeKB / 1024, 0)
        $metrics['memory_total_gb'] = [math]::Round($memoryTotalKB / 1024 / 1024, 2)
        
        # Memória virtual (swap/pagefile)
        $virtualTotal = $os.TotalVirtualMemorySize
        $virtualFree = $os.FreeVirtualMemory
        $virtualUsed = $virtualTotal - $virtualFree
        $metrics['virtual_memory'] = @{
            total_mb = [math]::Round($virtualTotal / 1024, 0)
            used_mb = [math]::Round($virtualUsed / 1024, 0)
            free_mb = [math]::Round($virtualFree / 1024, 0)
            percent = [math]::Round(($virtualUsed / $virtualTotal) * 100, 2)
        }
    }
    catch {
        Write-ServiceLog "Erro ao obter memória: $_" -Level DEBUG
    }
    
    # ========== DISCOS (TODOS) ==========
    try {
        $allDisks = Get-CimInstance -ClassName Win32_LogicalDisk -Filter "DriveType=3"
        $disksList = @()
        $totalSize = 0
        $totalUsed = 0
        
        foreach ($disk in $allDisks) {
            $diskUsed = $disk.Size - $disk.FreeSpace
            $diskPercent = if ($disk.Size -gt 0) { [math]::Round(($diskUsed / $disk.Size) * 100, 2) } else { 0 }
            
            $disksList += @{
                drive = $disk.DeviceID
                volume_name = $disk.VolumeName
                file_system = $disk.FileSystem
                total_gb = [math]::Round($disk.Size / 1GB, 2)
                used_gb = [math]::Round($diskUsed / 1GB, 2)
                free_gb = [math]::Round($disk.FreeSpace / 1GB, 2)
                percent_used = $diskPercent
            }
            
            $totalSize += $disk.Size
            $totalUsed += $diskUsed
        }
        
        $metrics['disks'] = $disksList
        $metrics['disk_count'] = $disksList.Count
        
        # Para compatibilidade, mantém disk_percent e totais do disco do sistema
        $sysDrive = $allDisks | Where-Object { $_.DeviceID -eq $env:SystemDrive }
        if ($sysDrive) {
            $sysDiskUsed = $sysDrive.Size - $sysDrive.FreeSpace
            $metrics['disk_percent'] = [math]::Round(($sysDiskUsed / $sysDrive.Size) * 100, 2)
            $metrics['disk_total_gb'] = [math]::Round($sysDrive.Size / 1GB, 2)
            $metrics['disk_used_gb'] = [math]::Round($sysDiskUsed / 1GB, 2)
            $metrics['disk_free_gb'] = [math]::Round($sysDrive.FreeSpace / 1GB, 2)
        }
        
        # Totais de todos os discos
        $metrics['disk_total_all_gb'] = [math]::Round($totalSize / 1GB, 2)
        $metrics['disk_used_all_gb'] = [math]::Round($totalUsed / 1GB, 2)
    }
    catch {
        Write-ServiceLog "Erro ao obter discos: $_" -Level DEBUG
    }
    
    # ========== REDE ==========
    try {
        $networkAdapters = Get-CimInstance -ClassName Win32_NetworkAdapterConfiguration | 
            Where-Object { $_.IPEnabled -eq $true }
        
        $networkList = @()
        foreach ($adapter in $networkAdapters) {
            $networkList += @{
                description = $adapter.Description
                ip_addresses = @($adapter.IPAddress | Where-Object { $_ })
                mac_address = $adapter.MACAddress
                gateway = @($adapter.DefaultIPGateway | Where-Object { $_ })
                dns_servers = @($adapter.DNSServerSearchOrder | Where-Object { $_ })
                dhcp_enabled = $adapter.DHCPEnabled
            }
        }
        
        $metrics['network_adapters'] = $networkList
        $metrics['network_adapter_count'] = $networkList.Count
    }
    catch {
        Write-ServiceLog "Erro ao obter rede: $_" -Level DEBUG
    }
    
    # ========== UPTIME ==========
    try {
        $osInfo = Get-CimInstance -ClassName Win32_OperatingSystem
        $uptime = (Get-Date) - $osInfo.LastBootUpTime
        $metrics['uptime_seconds'] = [math]::Round($uptime.TotalSeconds, 0)
        $metrics['uptime_days'] = [math]::Round($uptime.TotalDays, 2)
        $metrics['last_boot'] = $osInfo.LastBootUpTime.ToString("yyyy-MM-dd HH:mm:ss")
    }
    catch {
        Write-ServiceLog "Erro ao obter uptime: $_" -Level DEBUG
    }
    
    # ========== SERVIÇOS IMPORTANTES ==========
    try {
        $importantServices = @('wuauserv', 'MSSQLSERVER', 'SQLAgent$*', 'W3SVC', 'IISADMIN', 'WinRM', 'Spooler', 'DNS', 'DHCP')
        $servicesList = @()
        
        foreach ($svcPattern in $importantServices) {
            $services = Get-Service -Name $svcPattern -ErrorAction SilentlyContinue
            foreach ($svc in $services) {
                $servicesList += @{
                    name = $svc.Name
                    display_name = $svc.DisplayName
                    status = $svc.Status.ToString()
                    start_type = $svc.StartType.ToString()
                }
            }
        }
        
        if ($servicesList.Count -gt 0) {
            $metrics['important_services'] = $servicesList
        }
    }
    catch {
        Write-ServiceLog "Erro ao obter serviços: $_" -Level DEBUG
    }
    
    # ========== INFORMAÇÕES DO SISTEMA ==========
    try {
        $computerSystem = Get-CimInstance -ClassName Win32_ComputerSystem
        $bios = Get-CimInstance -ClassName Win32_BIOS
        
        $metrics['system_info'] = @{
            manufacturer = $computerSystem.Manufacturer
            model = $computerSystem.Model
            domain = $computerSystem.Domain
            total_physical_memory_gb = [math]::Round($computerSystem.TotalPhysicalMemory / 1GB, 2)
            number_of_processors = $computerSystem.NumberOfProcessors
            bios_serial = $bios.SerialNumber
            bios_version = $bios.SMBIOSBIOSVersion
        }
    }
    catch {
        Write-ServiceLog "Erro ao obter info sistema: $_" -Level DEBUG
    }
    
    # ========== TIMESTAMP ==========
    $metrics['collected_at'] = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
    
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
    
    # Monta o payload
    $payload = @{
        host_name = $script:Config.host_name
        hostname = $systemInfo.hostname
        ip = $systemInfo.ip
        os = $systemInfo.os
        metrics = $metrics
    }
    
    # Log do payload antes de converter
    Write-ServiceLog "Payload: host_name=$($payload.host_name), hostname=$($payload.hostname)" -Level DEBUG
    
    # Converte para JSON como string simples
    $body = $payload | ConvertTo-Json -Depth 10 -Compress
    
    Write-ServiceLog "JSON Body (preview): $($body.Substring(0, [Math]::Min(200, $body.Length)))..." -Level DEBUG
    
    # Suporta ambos os métodos de autenticação
    $headers = @{
        'Content-Type' = 'application/json'
        'Authorization' = "Bearer $($script:Config.api_token)"
        'X-API-Key' = $script:Config.api_token
        'Accept' = 'application/json'
    }
    
    Write-ServiceLog "Endpoint: $endpoint" -Level DEBUG
    
    try {
        # Usa Invoke-WebRequest para ter mais controle
        $webResponse = Invoke-WebRequest -Uri $endpoint -Method POST -Headers $headers -Body $body -ContentType 'application/json' -TimeoutSec 30 -UseBasicParsing
        
        $response = $webResponse.Content | ConvertFrom-Json
        
        if ($response.success) {
            Write-ServiceLog "Telemetria enviada - Host ID: $($response.host_id), Status: $($response.status)"
            $script:LastTelemetry = Get-Date
        }
        else {
            Write-ServiceLog "Falha ao enviar telemetria: $($response | ConvertTo-Json -Compress)" -Level WARN
        }
    }
    catch {
        $errorDetails = $_.Exception.Message
        $statusCode = $null
        
        # Tenta obter detalhes do erro HTTP
        if ($_.Exception.Response) {
            $statusCode = [int]$_.Exception.Response.StatusCode
            try {
                $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                $responseBody = $reader.ReadToEnd()
                $reader.Close()
                $errorDetails = "HTTP $statusCode - $responseBody"
            }
            catch {
                $errorDetails = "HTTP $statusCode - $errorDetails"
            }
        }
        
        Write-ServiceLog "Erro ao enviar telemetria: $errorDetails" -Level ERROR
        
        # Se for erro 4xx, mostra o body que foi enviado para debug
        if ($statusCode -ge 400 -and $statusCode -lt 500) {
            Write-ServiceLog "Body enviado: $body" -Level DEBUG
        }
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
