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
        
        # Obtém todos os jobs de backup (suporta versões antigas e novas do Veeam)
        $jobs = @()
        try {
            # Primeiro tenta o cmdlet novo (Veeam 12+)
            if (Get-Command Get-VBRComputerBackupJob -ErrorAction SilentlyContinue) {
                $computerJobs = Get-VBRComputerBackupJob -ErrorAction SilentlyContinue
                if ($computerJobs) { $jobs += $computerJobs }
            }
            # Adiciona jobs tradicionais (VMs, etc)
            $standardJobs = Get-VBRJob -ErrorAction SilentlyContinue
            if ($standardJobs) { $jobs += $standardJobs }
        }
        catch {
            $jobs = Get-VBRJob -ErrorAction SilentlyContinue
        }
        
        $startTime = (Get-Date).AddHours(-$Hours)
        
        foreach ($job in $jobs) {
            try {
                $lastSession = $null
                $jobName = $job.Name
                
                # Tenta obter a última sessão - método varia por tipo de job
                try {
                    # Para jobs tradicionais (VM backup)
                    $lastSession = Get-VBRBackupSession -ErrorAction SilentlyContinue | 
                        Where-Object { $_.JobId -eq $job.Id -or $_.JobName -eq $jobName } | 
                        Sort-Object EndTime -Descending | 
                        Select-Object -First 1
                }
                catch { }
                
                # Para Computer Backup Jobs (Veeam 12+), tenta Get-VBRComputerBackupJobSession
                if (-not $lastSession -and (Get-Command Get-VBRComputerBackupJobSession -ErrorAction SilentlyContinue)) {
                    try {
                        $lastSession = Get-VBRComputerBackupJobSession -Name $jobName -ErrorAction SilentlyContinue | 
                            Sort-Object EndTime -Descending | 
                            Select-Object -First 1
                    }
                    catch { }
                }
                
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
                $taskSessions = @()
                $totalSize = 0
                $totalObjects = 0
                
                try {
                    $taskSessions = Get-VBRTaskSession -Session $lastSession -ErrorAction SilentlyContinue
                    if ($taskSessions) {
                        $totalObjects = $taskSessions.Count
                        foreach ($task in $taskSessions) {
                            if ($task.ProcessedSize) {
                                $totalSize += $task.ProcessedSize
                            }
                        }
                    }
                }
                catch {
                    Write-Verbose "Não foi possível obter task sessions: $_"
                }
                
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
                        SessionName = $lastSession.Name
                        IsFullBackup = $lastSession.IsFullMode
                        IsRetry = $lastSession.IsRetryMode
                        Progress = $lastSession.Progress
                        State = $lastSession.State
                        ResultDescription = $lastSession.Description
                        SourceSize = $lastSession.Info.Progress.TotalSize
                        TransferedSize = $lastSession.Info.Progress.TransferedSize
                        ReadSize = $lastSession.Info.Progress.ReadSize
                        ProcessedObjects = $lastSession.Info.Progress.ProcessedObjects
                        TotalObjects = $lastSession.Info.Progress.TotalObjects
                        AvgSpeed = $lastSession.Info.Progress.AvgSpeed
                        Duration = $lastSession.Info.Progress.Duration
                        Bottleneck = $lastSession.Info.Bottleneck
                        WillBeRetried = $lastSession.WillBeRetried
                    }
                }
                
                # Adiciona informações do repositório
                try {
                    $targetRepo = $job.GetTargetRepository()
                    if ($targetRepo) {
                        $backupJob.Details['TargetRepository'] = $targetRepo.Name
                        $backupJob.Details['RepositoryPath'] = $targetRepo.Path
                        $backupJob.Details['RepositoryType'] = $targetRepo.Type
                    }
                } catch { }
                
                # Adiciona informação de erro/alerta detalhada
                if ($status -in @("falha", "alerta")) {
                    # Razão principal
                    $backupJob.ErrorMessage = $lastSession.Info.Reason
                    
                    # Detalhes completos da sessão
                    try {
                        $backupJob.Details['FailureMessage'] = $lastSession.GetDetails()
                    } catch { }
                    
                    # Warnings da sessão
                    try {
                        $sessionWarnings = @()
                        foreach ($taskSession in $taskSessions) {
                            if ($taskSession.Status -ne 'Success') {
                                $sessionWarnings += @{
                                    Object = $taskSession.Name
                                    Status = $taskSession.Status.ToString()
                                    Reason = $taskSession.Info.Reason
                                    Details = $taskSession.Info.Details
                                }
                            }
                        }
                        if ($sessionWarnings.Count -gt 0) {
                            $backupJob.Details['Warnings'] = $sessionWarnings
                        }
                    } catch { }
                    
                    # Log de eventos da sessão
                    try {
                        $sessionLogs = Get-VBRSession -Job $job | 
                            Where-Object { $_.Id -eq $lastSession.Id } |
                            ForEach-Object { $_.Logger.GetLog().UpdatedRecords } |
                            Where-Object { $_.Status -in @('EWarning', 'EFailed', 'Error') } |
                            Select-Object -First 10 |
                            ForEach-Object {
                                @{
                                    Time = $_.Title
                                    Status = $_.Status.ToString()
                                    Message = $_.Description
                                }
                            }
                        if ($sessionLogs) {
                            $backupJob.Details['ErrorLogs'] = @($sessionLogs)
                        }
                    } catch { }
                }
                
                # Adiciona informações de VMs processadas com detalhes de erro
                $vms = @()
                foreach ($taskSession in $taskSessions) {
                    $vmInfo = @{
                        Name = $taskSession.Name
                        Status = $taskSession.Status.ToString()
                        ProcessedSize = $taskSession.ProcessedSize
                        ReadSize = $taskSession.Progress.ReadSize
                        TransferredSize = $taskSession.Progress.TransferedSize
                        Duration = if ($taskSession.Progress.Duration) { $taskSession.Progress.Duration.ToString() } else { $null }
                        StartTime = if ($taskSession.Progress.StartTimeLocal) { $taskSession.Progress.StartTimeLocal.ToString("yyyy-MM-dd HH:mm:ss") } else { $null }
                        StopTime = if ($taskSession.Progress.StopTimeLocal) { $taskSession.Progress.StopTimeLocal.ToString("yyyy-MM-dd HH:mm:ss") } else { $null }
                        AvgSpeed = $taskSession.Progress.AvgSpeed
                    }
                    
                    # Adiciona razão de erro/warning se existir
                    if ($taskSession.Status -ne 'Success') {
                        $vmInfo['Reason'] = $taskSession.Info.Reason
                        $vmInfo['Details'] = $taskSession.Info.Details
                    }
                    
                    $vms += $vmInfo
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
        Converte dados do Veeam para o formato padrão da API (baseado em routine_key)
    #>
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)]
        [hashtable]$Job,
        
        [Parameter(Mandatory = $true)]
        [string]$ServerName,
        
        [Parameter(Mandatory = $false)]
        [string]$RoutineKey = $null
    )
    
    $hostname = [System.Net.Dns]::GetHostName()
    $ipAddress = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.InterfaceAlias -notlike "*Loopback*" -and $_.PrefixOrigin -in @('Dhcp', 'Manual') } | Select-Object -First 1).IPAddress
    $os = (Get-CimInstance Win32_OperatingSystem).Caption
    $osInfo = Get-CimInstance Win32_OperatingSystem
    
    $tipoBackup = if ($Job.Details.IsFullBackup) { "Completo" } else { "Incremental" }
    
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
        rotina_nome = $Job.JobName
        data_inicio = if ($Job.StartTime) { $Job.StartTime.ToString("yyyy-MM-dd HH:mm:ss") } else { (Get-Date).ToString("yyyy-MM-dd HH:mm:ss") }
        data_fim = if ($Job.EndTime) { $Job.EndTime.ToString("yyyy-MM-dd HH:mm:ss") } else { $null }
        status = $Job.Status
        tamanho_bytes = $Job.ProcessedSize
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
            source = "Veeam"
            tipo_backup = $tipoBackup
            job_id = $Job.JobId
            job_type = $Job.JobType
            JobName = $Job.JobName
            Result = $Job.Result
            BackupSize = $Job.BackupSize
            ObjectsCount = $Job.ObjectsCount
            
            # Informações da sessão
            SessionId = $Job.Details.SessionId
            SessionName = $Job.Details.SessionName
            IsFullBackup = $Job.Details.IsFullBackup
            IsRetry = $Job.Details.IsRetry
            State = $Job.Details.State
            ResultDescription = $Job.Details.ResultDescription
            WillBeRetried = $Job.Details.WillBeRetried
            
            # Informações de tamanho e progresso
            SourceSize = $Job.Details.SourceSize
            TransferedSize = $Job.Details.TransferedSize
            ReadSize = $Job.Details.ReadSize
            ProcessedObjects = $Job.Details.ProcessedObjects
            TotalObjects = $Job.Details.TotalObjects
            Progress = $Job.Details.Progress
            AvgSpeed = $Job.Details.AvgSpeed
            Duration = $Job.Details.Duration
            Bottleneck = $Job.Details.Bottleneck
            
            # Repositório
            TargetRepository = $Job.Details.TargetRepository
            RepositoryPath = $Job.Details.RepositoryPath
            RepositoryType = $Job.Details.RepositoryType
        }
    }
    
    # Adiciona destino
    if ($Job.Details.TargetRepository) {
        $standardFormat.destino = $Job.Details.TargetRepository
    }
    
    # Adiciona mensagem de erro e detalhes de falha
    if ($Job.Status -in @("falha", "alerta")) {
        if ($Job.ErrorMessage) {
            $standardFormat.mensagem_erro = $Job.ErrorMessage
        }
        if ($Job.Details.FailureMessage) {
            $standardFormat.detalhes['FailureMessage'] = $Job.Details.FailureMessage
        }
        # Adiciona warnings de objetos com problemas
        if ($Job.Details.Warnings -and $Job.Details.Warnings.Count -gt 0) {
            $standardFormat.detalhes['Warnings'] = $Job.Details.Warnings
        }
        # Adiciona logs de erro da sessão
        if ($Job.Details.ErrorLogs -and $Job.Details.ErrorLogs.Count -gt 0) {
            $standardFormat.detalhes['ErrorLogs'] = $Job.Details.ErrorLogs
        }
    }
    
    # Adiciona lista de VMs processadas com formato detalhado (inclui status individual e razões de erro)
    if ($Job.Details.ProcessedVMs -and $Job.Details.ProcessedVMs.Count -gt 0) {
        $standardFormat.detalhes['ProcessedVMs'] = $Job.Details.ProcessedVMs
    }
    
    # Coleta informações dos repositórios Veeam se disponível
    try {
        $connected = Connect-VeeamServer -ErrorAction SilentlyContinue
        if ($connected) {
            $repositories = Get-VBRBackupRepository -ErrorAction SilentlyContinue
            if ($repositories) {
                $repoInfo = @()
                foreach ($repo in $repositories) {
                    try {
                        $container = $repo.GetContainer()
                        $repoInfo += @{
                            Name = $repo.Name
                            Type = $repo.Type
                            Path = $repo.Path
                            TotalSpace = $container.CachedTotalSpace.InBytes
                            FreeSpace = $container.CachedFreeSpace.InBytes
                            UsedSpace = $container.CachedTotalSpace.InBytes - $container.CachedFreeSpace.InBytes
                        }
                    } catch { }
                }
                if ($repoInfo.Count -gt 0) {
                    $standardFormat.detalhes['Repositories'] = $repoInfo
                }
            }
            Disconnect-VeeamServer -ErrorAction SilentlyContinue
        }
    } catch { }
    
    return $standardFormat
}

# Exporta as funções
Export-ModuleMember -Function @(
    'Get-VeeamBackupJobs',
    'Get-VeeamReplicationJobs',
    'ConvertTo-StandardVeeamFormat'
)
