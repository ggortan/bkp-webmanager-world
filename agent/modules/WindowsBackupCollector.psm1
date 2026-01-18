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
    $ipAddress = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.InterfaceAlias -notlike "*Loopback*" } | Select-Object -First 1).IPAddress
    $os = (Get-CimInstance Win32_OperatingSystem).Caption
    
    $rotinaNome = if ($Job.TaskName) { $Job.TaskName } else { $DefaultRotina }
    
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
            sistema_operacional = $os
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
    
    # Adiciona mensagem de erro se status for falha ou alerta
    if ($Job.Status -in @("falha", "alerta")) {
        $mensagem = if ($Job.Message) { $Job.Message } elseif ($Job.Details.ResultDescription) { $Job.Details.ResultDescription } else { "Erro desconhecido" }
        $standardFormat.mensagem_erro = $mensagem
    }
    
    # Adiciona detalhes extras
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
