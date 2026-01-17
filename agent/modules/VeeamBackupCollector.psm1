<#
.SYNOPSIS
    Módulo para coletar informações de backup do Veeam Backup & Replication
    
.DESCRIPTION
    Coleta dados de jobs de backup do Veeam através do PowerShell SnapIn da Veeam
#>

function Connect-VeeamServer {
    <#
    .SYNOPSIS
        Conecta ao servidor Veeam
    #>
    [CmdletBinding()]
    param(
        [string]$Server = "localhost",
        [int]$Port = 9392
    )
    
    try {
        # Verifica se o snap-in está disponível
        if (-not (Get-PSSnapin -Name VeeamPSSnapin -Registered -ErrorAction SilentlyContinue)) {
            Write-Error "Veeam PowerShell Snap-in não está registrado"
            return $false
        }
        
        # Adiciona o snap-in se ainda não estiver carregado
        if (-not (Get-PSSnapin -Name VeeamPSSnapin -ErrorAction SilentlyContinue)) {
            Add-PSSnapin VeeamPSSnapin -ErrorAction Stop
        }
        
        # Conecta ao servidor Veeam
        Connect-VBRServer -Server $Server -Port $Port -ErrorAction Stop
        
        Write-Verbose "Conectado ao servidor Veeam: $Server"
        return $true
    }
    catch {
        Write-Error "Erro ao conectar ao servidor Veeam: $_"
        return $false
    }
}

function Disconnect-VeeamServer {
    <#
    .SYNOPSIS
        Desconecta do servidor Veeam
    #>
    [CmdletBinding()]
    param()
    
    try {
        Disconnect-VBRServer -ErrorAction SilentlyContinue
        Write-Verbose "Desconectado do servidor Veeam"
    }
    catch {
        Write-Verbose "Erro ao desconectar: $_"
    }
}

function Get-VeeamBackupJobs {
    <#
    .SYNOPSIS
        Coleta informações de jobs do Veeam
    #>
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $false)]
        [int]$Hours = 24,
        
        [Parameter(Mandatory = $false)]
        [string]$Server = "localhost",
        
        [Parameter(Mandatory = $false)]
        [int]$Port = 9392
    )
    
    $backupJobs = @()
    
    try {
        # Conecta ao servidor Veeam
        $connected = Connect-VeeamServer -Server $Server -Port $Port
        
        if (-not $connected) {
            Write-Warning "Não foi possível conectar ao servidor Veeam"
            return $backupJobs
        }
        
        # Obtém todos os jobs de backup
        $jobs = Get-VBRJob
        
        $startTime = (Get-Date).AddHours(-$Hours)
        
        foreach ($job in $jobs) {
            try {
                # Obtém a última sessão do job
                $lastSession = Get-VBRBackupSession | 
                    Where-Object { $_.JobId -eq $job.Id } | 
                    Sort-Object EndTime -Descending | 
                    Select-Object -First 1
                
                if ($null -eq $lastSession) {
                    continue
                }
                
                # Filtra por data
                if ($lastSession.EndTime -lt $startTime) {
                    continue
                }
                
                # Determina o status
                $status = switch ($lastSession.Result) {
                    "Success" { "sucesso" }
                    "Warning" { "alerta" }
                    "Failed" { "falha" }
                    default { "desconhecido" }
                }
                
                # Coleta informações de processamento
                $taskSessions = Get-VBRTaskSession -Session $lastSession
                $totalSize = ($taskSessions | Measure-Object -Property ProcessedSize -Sum).Sum
                $totalObjects = $taskSessions.Count
                
                # Informações de storage
                $backupSize = 0
                try {
                    $backup = Get-VBRBackup | Where-Object { $_.JobId -eq $job.Id }
                    if ($backup) {
                        $backupSize = $backup.GetAllStorages() | Measure-Object -Property Stats.BackupSize -Sum | Select-Object -ExpandProperty Sum
                    }
                }
                catch {
                    Write-Verbose "Não foi possível obter tamanho do backup: $_"
                }
                
                # Monta o objeto de job
                $backupJob = @{
                    Source = "Veeam"
                    JobId = $job.Id
                    JobName = $job.Name
                    JobType = $job.JobType
                    StartTime = $lastSession.CreationTime
                    EndTime = $lastSession.EndTime
                    Status = $status
                    Result = $lastSession.Result
                    ProcessedSize = $totalSize
                    BackupSize = $backupSize
                    ObjectsCount = $totalObjects
                    Details = @{
                        SessionId = $lastSession.Id
                        IsFullBackup = $lastSession.IsFullMode
                        IsRetry = $lastSession.IsRetryMode
                        Progress = $lastSession.Progress
                        TargetRepository = $job.GetTargetRepository().Name
                        SourceSize = $lastSession.Info.Progress.TotalSize
                        TransferedSize = $lastSession.Info.Progress.TransferedSize
                        ProcessedObjects = $lastSession.Info.Progress.ProcessedObjects
                        TotalObjects = $lastSession.Info.Progress.TotalObjects
                    }
                }
                
                # Adiciona informação de erro se houver
                if ($status -in @("falha", "alerta")) {
                    $backupJob.ErrorMessage = $lastSession.Info.Reason
                    $backupJob.Details.FailureMessage = $lastSession.GetDetails()
                }
                
                # Adiciona informações de VMs processadas
                $vms = @()
                foreach ($taskSession in $taskSessions) {
                    $vms += @{
                        Name = $taskSession.Name
                        Status = $taskSession.Status
                        ProcessedSize = $taskSession.ProcessedSize
                        Duration = $taskSession.Progress.Duration
                    }
                }
                $backupJob.Details.ProcessedVMs = $vms
                
                $backupJobs += $backupJob
            }
            catch {
                Write-Warning "Erro ao processar job '$($job.Name)': $_"
            }
        }
        
    }
    catch {
        Write-Error "Erro ao coletar dados do Veeam: $_"
    }
    finally {
        # Sempre desconecta ao final
        Disconnect-VeeamServer
    }
    
    return $backupJobs
}

function Get-VeeamReplicationJobs {
    <#
    .SYNOPSIS
        Coleta informações de jobs de replicação do Veeam
    #>
    [CmdletBinding()]
    param(
        [int]$Hours = 24,
        [string]$Server = "localhost",
        [int]$Port = 9392
    )
    
    $replicationJobs = @()
    
    try {
        $connected = Connect-VeeamServer -Server $Server -Port $Port
        
        if (-not $connected) {
            return $replicationJobs
        }
        
        $jobs = Get-VBRJob | Where-Object { $_.JobType -eq "Replica" }
        $startTime = (Get-Date).AddHours(-$Hours)
        
        foreach ($job in $jobs) {
            $lastSession = Get-VBRBackupSession | 
                Where-Object { $_.JobId -eq $job.Id } | 
                Sort-Object EndTime -Descending | 
                Select-Object -First 1
            
            if ($null -eq $lastSession -or $lastSession.EndTime -lt $startTime) {
                continue
            }
            
            $status = switch ($lastSession.Result) {
                "Success" { "sucesso" }
                "Warning" { "alerta" }
                "Failed" { "falha" }
                default { "desconhecido" }
            }
            
            $replicationJobs += @{
                Source = "VeeamReplication"
                JobId = $job.Id
                JobName = $job.Name
                JobType = "Replication"
                StartTime = $lastSession.CreationTime
                EndTime = $lastSession.EndTime
                Status = $status
                Result = $lastSession.Result
                Details = @{
                    TargetHost = $job.GetTargetHost().Name
                    SessionId = $lastSession.Id
                }
            }
        }
    }
    catch {
        Write-Error "Erro ao coletar replicações Veeam: $_"
    }
    finally {
        Disconnect-VeeamServer
    }
    
    return $replicationJobs
}

function ConvertTo-StandardVeeamFormat {
    <#
    .SYNOPSIS
        Converte dados do Veeam para o formato padrão da API
    #>
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)]
        [hashtable]$Job,
        
        [Parameter(Mandatory = $true)]
        [string]$ServerName
    )
    
    $hostname = [System.Net.Dns]::GetHostName()
    $ipAddress = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.InterfaceAlias -notlike "*Loopback*" } | Select-Object -First 1).IPAddress
    $os = (Get-CimInstance Win32_OperatingSystem).Caption
    
    $tipoBackup = if ($Job.Details.IsFullBackup) { "Completo" } else { "Incremental" }
    
    $standardFormat = @{
        servidor = $ServerName
        hostname = $hostname
        ip = $ipAddress
        sistema_operacional = $os
        rotina = $Job.JobName
        tipo_backup = $tipoBackup
        data_inicio = $Job.StartTime
        data_fim = $Job.EndTime
        status = $Job.Status
        tamanho_bytes = $Job.ProcessedSize
        detalhes = @{
            source = $Job.Source
            job_id = $Job.JobId
            job_type = $Job.JobType
            backup_size_bytes = $Job.BackupSize
            objects_count = $Job.ObjectsCount
            is_full = $Job.Details.IsFullBackup
            repository = $Job.Details.TargetRepository
            processed_objects = $Job.Details.ProcessedObjects
            total_objects = $Job.Details.TotalObjects
        }
    }
    
    # Adiciona destino
    if ($Job.Details.TargetRepository) {
        $standardFormat.destino = $Job.Details.TargetRepository
    }
    
    # Adiciona mensagem de erro
    if ($Job.Status -in @("falha", "alerta") -and $Job.ErrorMessage) {
        $standardFormat.mensagem_erro = $Job.ErrorMessage
    }
    
    # Adiciona lista de VMs processadas
    if ($Job.Details.ProcessedVMs) {
        $standardFormat.detalhes.vms = $Job.Details.ProcessedVMs
    }
    
    return $standardFormat
}

# Exporta as funções
Export-ModuleMember -Function @(
    'Get-VeeamBackupJobs',
    'Get-VeeamReplicationJobs',
    'ConvertTo-StandardVeeamFormat'
)
