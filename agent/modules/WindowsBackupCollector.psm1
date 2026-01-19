<#
.SYNOPSIS
    Módulo para coletar informações de backup do Windows Server Backup
    
.DESCRIPTION
    Coleta dados de jobs de backup do Windows Server Backup através de:
    - Event Log (eventos de backup)
    - WMI (Windows Management Instrumentation)
    - PowerShell cmdlets nativos
#>

function Get-WindowsServerBackupJobs {
    <#
    .SYNOPSIS
        Coleta informações de jobs do Windows Server Backup
    #>
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $false)]
        [int]$Hours = 24
    )
    
    $backupJobs = @()
    
    try {
        # Verifica se o módulo Windows Server Backup está disponível
        if (-not (Get-Module -ListAvailable -Name WindowsServerBackup)) {
            Write-Warning "Módulo Windows Server Backup não está instalado"
            return $backupJobs
        }
        
        Import-Module WindowsServerBackup -ErrorAction SilentlyContinue
        
        # Obtém políticas de backup configuradas
        $policy = Get-WBPolicy -Editable -ErrorAction SilentlyContinue
        
        if ($null -eq $policy) {
            Write-Verbose "Nenhuma política de backup configurada"
        }
        
        # Coleta informações do Event Log
        $backupJobs += Get-BackupFromEventLog -Hours $Hours
        
        # Coleta do histórico do Windows Server Backup
        $backupJobs += Get-BackupFromWBSummary
        
    }
    catch {
        Write-Error "Erro ao coletar dados do Windows Server Backup: $_"
    }
    
    return $backupJobs
}

function Get-BackupFromEventLog {
    <#
    .SYNOPSIS
        Coleta dados de backup do Event Log
    #>
    [CmdletBinding()]
    param(
        [int]$Hours = 24
    )
    
    $jobs = @()
    $startTime = (Get-Date).AddHours(-$Hours)
    
    try {
        # Event IDs relevantes do Windows Server Backup
        # 4 = Backup bem-sucedido
        # 5 = Backup falhou
        # 517 = Backup concluído com avisos
        
        $events = Get-WinEvent -FilterHashtable @{
            LogName = 'Microsoft-Windows-Backup'
            StartTime = $startTime
            ID = 4,5,517
        } -ErrorAction SilentlyContinue
        
        foreach ($event in $events) {
            $status = switch ($event.Id) {
                4 { "sucesso" }
                5 { "falha" }
                517 { "alerta" }
                default { "desconhecido" }
            }
            
            $job = @{
                Source = "WindowsServerBackup"
                EventId = $event.Id
                TimeCreated = $event.TimeCreated
                Status = $status
                Message = $event.Message
                Details = @{
                    EventRecordId = $event.RecordId
                    EventLevel = $event.LevelDisplayName
                }
            }
            
            # Tenta extrair informações adicionais da mensagem
            if ($event.Message -match "(\d+(?:\.\d+)?)\s*(GB|MB|KB)") {
                $size = $matches[1]
                $unit = $matches[2]
                
                $sizeBytes = switch ($unit) {
                    "GB" { [long]($size * 1GB) }
                    "MB" { [long]($size * 1MB) }
                    "KB" { [long]($size * 1KB) }
                    default { 0 }
                }
                
                $job.Details.SizeBytes = $sizeBytes
            }
            
            $jobs += $job
        }
    }
    catch {
        Write-Verbose "Erro ao acessar Event Log: $_"
    }
    
    return $jobs
}

function Get-BackupFromWBSummary {
    <#
    .SYNOPSIS
        Coleta dados do histórico do Windows Server Backup
    #>
    [CmdletBinding()]
    param()
    
    $jobs = @()
    
    try {
        # Obtém o sumário dos últimos backups
        $summary = Get-WBSummary -ErrorAction SilentlyContinue
        
        if ($null -eq $summary) {
            return $jobs
        }
        
        # Último backup bem-sucedido
        if ($summary.LastSuccessfulBackupTime) {
            $jobs += @{
                Source = "WindowsServerBackup"
                Type = "LastSuccessful"
                TimeCreated = $summary.LastSuccessfulBackupTime
                Status = "sucesso"
                Details = @{
                    BackupTarget = $summary.LastBackupTarget
                    NextBackupTime = $summary.NextBackupTime
                }
            }
        }
        
        # Último backup (independente do status)
        if ($summary.LastBackupTime -and $summary.LastBackupTime -ne $summary.LastSuccessfulBackupTime) {
            $status = if ($summary.LastBackupResultHR -eq 0) { "sucesso" } else { "falha" }
            
            $jobs += @{
                Source = "WindowsServerBackup"
                Type = "LastBackup"
                TimeCreated = $summary.LastBackupTime
                Status = $status
                Details = @{
                    BackupTarget = $summary.LastBackupTarget
                    ResultHR = $summary.LastBackupResultHR
                    ResultDescription = $summary.LastBackupResultDetailedMessage
                }
            }
        }
        
    }
    catch {
        Write-Verbose "Erro ao obter WBSummary: $_"
    }
    
    return $jobs
}

function Get-TaskSchedulerBackups {
    <#
    .SYNOPSIS
        Coleta informações de tarefas agendadas relacionadas a backup
    #>
    [CmdletBinding()]
    param(
        [int]$Hours = 24
    )
    
    $backupTasks = @()
    
    try {
        # Procura por tarefas com "backup" no nome ou caminho
        $tasks = Get-ScheduledTask | Where-Object { 
            $_.TaskName -like "*backup*" -or 
            $_.TaskPath -like "*backup*" 
        }
        
        foreach ($task in $tasks) {
            $info = Get-ScheduledTaskInfo -TaskName $task.TaskName -TaskPath $task.TaskPath -ErrorAction SilentlyContinue
            
            if ($null -eq $info) { continue }
            
            # Verifica se executou nas últimas X horas
            if ($info.LastRunTime -gt (Get-Date).AddHours(-$Hours)) {
                
                $status = switch ($info.LastTaskResult) {
                    0 { "sucesso" }
                    1 { "falha" }
                    default { "alerta" }
                }
                
                $backupTasks += @{
                    Source = "TaskScheduler"
                    TaskName = $task.TaskName
                    TaskPath = $task.TaskPath
                    LastRunTime = $info.LastRunTime
                    NextRunTime = $info.NextRunTime
                    Status = $status
                    LastResult = $info.LastTaskResult
                    Details = @{
                        State = $task.State
                        Description = $task.Description
                    }
                }
            }
        }
    }
    catch {
        Write-Verbose "Erro ao obter tarefas agendadas: $_"
    }
    
    return $backupTasks
}

function ConvertTo-StandardBackupFormat {
    <#
    .SYNOPSIS
        Converte dados coletados para o formato padrão da API (baseado em routine_key)
    #>
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)]
        [hashtable]$Job,
        
        [Parameter(Mandatory = $true)]
        [string]$ServerName,
        
        [Parameter(Mandatory = $false)]
        [string]$RoutineKey = $null,
        
        [Parameter(Mandatory = $false)]
        [string]$DefaultRotina = "Windows Server Backup"
    )
    
    $hostname = [System.Net.Dns]::GetHostName()
    $ipAddress = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.InterfaceAlias -notlike "*Loopback*" -and $_.PrefixOrigin -in @('Dhcp', 'Manual') } | Select-Object -First 1).IPAddress
    $os = (Get-CimInstance Win32_OperatingSystem).Caption
    $osInfo = Get-CimInstance Win32_OperatingSystem
    
    $rotinaNome = if ($Job.TaskName) { $Job.TaskName } else { $DefaultRotina }
    
    # Coleta informações adicionais do sistema
    $macAddress = $null
    $externalIp = $null
    $uptimeHours = $null
    $psVersion = $null
    $isVirtual = $null
    
    try {
        # MAC Address
        $macAddress = (Get-NetAdapter | Where-Object { $_.Status -eq 'Up' -and $_.Name -notlike '*Loopback*' } | Select-Object -First 1).MacAddress
        
        # Uptime
        $uptimeHours = [math]::Round(((Get-Date) - $osInfo.LastBootUpTime).TotalHours, 2)
        
        # PowerShell Version
        $psVersion = $PSVersionTable.PSVersion.ToString()
        
        # Virtual ou Physical
        $computerSystem = Get-CimInstance Win32_ComputerSystem
        $isVirtual = $computerSystem.Model -match 'Virtual|VMware|VirtualBox|Hyper-V|KVM|Xen|QEMU'
        
        # IP Externo (timeout curto para não atrasar)
        try {
            $externalIp = (Invoke-WebRequest -Uri 'https://api.ipify.org' -TimeoutSec 5 -UseBasicParsing).Content
        } catch { }
    } catch { }
    
    # Formato padrão da API (usando routine_key)
    $standardFormat = @{
        routine_key = $RoutineKey
        rotina_nome = $rotinaNome
        data_inicio = if ($Job.TimeCreated) { $Job.TimeCreated.ToString("yyyy-MM-dd HH:mm:ss") } else { (Get-Date).ToString("yyyy-MM-dd HH:mm:ss") }
        status = $Job.Status
        host_info = @{
            nome = $ServerName
            hostname = $hostname
            ip = $ipAddress
            mac = $macAddress
            ip_externo = $externalIp
            sistema_operacional = $os
            uptime_hours = $uptimeHours
            powershell_version = $psVersion
            is_virtual = $isVirtual
        }
        detalhes = @{
            source = $Job.Source
            tipo_backup = "Completo"
        }
    }
    
    # Adiciona data de fim se disponível
    if ($Job.EndTime) {
        $standardFormat.data_fim = $Job.EndTime.ToString("yyyy-MM-dd HH:mm:ss")
    }
    elseif ($Job.TimeCreated) {
        $standardFormat.data_fim = $Job.TimeCreated.ToString("yyyy-MM-dd HH:mm:ss")
    }
    
    # Adiciona tamanho se disponível
    if ($Job.Details.SizeBytes) {
        $standardFormat.tamanho_bytes = $Job.Details.SizeBytes
    }
    
    # Adiciona destino se disponível
    if ($Job.Details.BackupTarget) {
        $standardFormat.destino = $Job.Details.BackupTarget
    }
    
    # Coleta informações detalhadas do Windows Server Backup
    try {
        $wbSummary = Get-WBSummary -ErrorAction SilentlyContinue
        if ($wbSummary) {
            $standardFormat.detalhes['BackupTarget'] = $wbSummary.LastBackupTarget
            $standardFormat.detalhes['NextBackupTime'] = if ($wbSummary.NextBackupTime) { $wbSummary.NextBackupTime.ToString("yyyy-MM-dd HH:mm") } else { $null }
            $standardFormat.detalhes['NumberOfVersions'] = $wbSummary.NumberOfVersions
            
            # Obtém informações do último backup
            $lastBackup = Get-WBBackupSet -ErrorAction SilentlyContinue | Sort-Object BackupTime -Descending | Select-Object -First 1
            if ($lastBackup) {
                $standardFormat.detalhes['tipo_backup'] = if ($lastBackup.BackupTarget -match 'Disk') { 'Disco' } else { 'Rede' }
                
                # Itens do backup
                $backupItems = @()
                foreach ($volume in $lastBackup.Volume) {
                    $backupItems += @{
                        name = $volume.MountPath
                        type = 'Volume'
                        status = 'Completed Successfully'
                    }
                }
                
                if ($lastBackup.SystemState) {
                    $backupItems += @{ name = 'SystemState'; type = 'SystemState'; status = 'Completed Successfully' }
                }
                if ($lastBackup.BareMetalRecovery) {
                    $backupItems += @{ name = 'BareMetalRecovery'; type = 'BareMetalRecovery'; status = 'Completed Successfully' }
                }
                
                if ($backupItems.Count -gt 0) {
                    $standardFormat.detalhes['backup_items'] = $backupItems
                }
            }
        }
    } catch { }
    
    # Coleta uso de mídia/discos
    try {
        $mediaUsage = @()
        $volumes = Get-Volume | Where-Object { $_.DriveLetter -or $_.FileSystemLabel }
        foreach ($vol in $volumes) {
            if ($vol.Size -gt 0) {
                $percentFree = [math]::Round(($vol.SizeRemaining / $vol.Size) * 100, 2)
                $mediaUsage += @{
                    Label = if ($vol.FileSystemLabel) { $vol.FileSystemLabel } else { '' }
                    Volume = if ($vol.DriveLetter) { "$($vol.DriveLetter):\" } else { $vol.Path }
                    TotalCapacity = "{0:N1} GB" -f ($vol.Size / 1GB)
                    UsedSpace = "{0:N1} GB" -f (($vol.Size - $vol.SizeRemaining) / 1GB)
                    FreeSpace = "{0:N1} GB" -f ($vol.SizeRemaining / 1GB)
                    PercentFree = $percentFree
                }
            }
        }
        if ($mediaUsage.Count -gt 0) {
            $standardFormat.detalhes['media_usage'] = $mediaUsage
        }
    } catch { }
    
    # Coleta VSS Writers
    try {
        $vssOutput = vssadmin list writers 2>$null
        if ($vssOutput) {
            $vssWriters = @()
            $currentWriter = @{}
            foreach ($line in $vssOutput) {
                if ($line -match "Writer name:\s*'([^']+)'") {
                    if ($currentWriter.Count -gt 0) { $vssWriters += $currentWriter }
                    $currentWriter = @{ name = $matches[1]; state = 'Stable'; last_error = 'No error' }
                }
                elseif ($line -match "State:\s*\[(\d+)\]\s*(.+)") {
                    $currentWriter['state'] = $matches[2].Trim()
                }
                elseif ($line -match "Last error:\s*(.+)") {
                    $currentWriter['last_error'] = $matches[1].Trim()
                }
            }
            if ($currentWriter.Count -gt 0) { $vssWriters += $currentWriter }
            
            if ($vssWriters.Count -gt 0) {
                $standardFormat.detalhes['vss_writers'] = $vssWriters
            }
        }
    } catch { }
    
    # Adiciona mensagem de erro se status for falha ou alerta
    if ($Job.Status -in @("falha", "alerta")) {
        $mensagem = if ($Job.Message) { $Job.Message } elseif ($Job.Details.ResultDescription) { $Job.Details.ResultDescription } else { "Erro desconhecido" }
        $standardFormat.mensagem_erro = $mensagem
    }
    
    # Adiciona detalhes extras do job
    foreach ($key in $Job.Details.Keys) {
        if ($key -notin @("SizeBytes", "BackupTarget", "ResultDescription")) {
            $standardFormat.detalhes[$key] = $Job.Details[$key]
        }
    }
    
    return $standardFormat
}

# Exporta as funções
Export-ModuleMember -Function @(
    'Get-WindowsServerBackupJobs',
    'Get-TaskSchedulerBackups',
    'ConvertTo-StandardBackupFormat'
)
