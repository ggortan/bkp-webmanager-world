<#
.SYNOPSIS
    Script de telemetria para monitoramento de hosts
    
.DESCRIPTION
    Envia dados de telemetria (heartbeat) para a API do Backup Manager.
    Permite monitorar se o host está online/offline.
    
.PARAMETER ApiUrl
    URL base da API do Backup Manager
    
.PARAMETER ApiToken
    Token de autenticação da API (Bearer token)
    
.PARAMETER HostName
    Nome do host (usado para identificação)
    
.PARAMETER IntervalMinutes
    Intervalo em minutos entre os envios de telemetria (padrão: 5)
    
.PARAMETER RunOnce
    Se definido, executa apenas uma vez ao invés de loop contínuo
    
.EXAMPLE
    .\Send-Telemetry.ps1 -ApiUrl "https://backup.empresa.com" -ApiToken "seu-token" -HostName "SERVER01"
    
.EXAMPLE
    .\Send-Telemetry.ps1 -ApiUrl "https://backup.empresa.com" -ApiToken "seu-token" -HostName "SERVER01" -IntervalMinutes 2 -RunOnce
    
.NOTES
    Versão: 1.0.0
    Requer: PowerShell 5.1+
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$ApiUrl,
    
    [Parameter(Mandatory = $true)]
    [string]$ApiToken,
    
    [Parameter(Mandatory = $false)]
    [string]$HostName = $env:COMPUTERNAME,
    
    [Parameter(Mandatory = $false)]
    [int]$IntervalMinutes = 5,
    
    [switch]$RunOnce
)

# Remove barra final da URL se houver
$ApiUrl = $ApiUrl.TrimEnd('/')

# Função para obter informações do sistema
function Get-SystemMetrics {
    $metrics = @{}
    
    try {
        # CPU
        $cpu = Get-CimInstance -ClassName Win32_Processor | Measure-Object -Property LoadPercentage -Average
        $metrics['cpu_percent'] = [math]::Round($cpu.Average, 2)
    }
    catch {
        Write-Warning "Erro ao obter CPU: $_"
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
        Write-Warning "Erro ao obter memória: $_"
    }
    
    try {
        # Disco (drive do sistema)
        $sysDrive = Get-CimInstance -ClassName Win32_LogicalDisk -Filter "DeviceID='$($env:SystemDrive)'"
        if ($sysDrive) {
            $diskUsed = $sysDrive.Size - $sysDrive.FreeSpace
            $metrics['disk_percent'] = [math]::Round(($diskUsed / $sysDrive.Size) * 100, 2)
            $metrics['disk_total_gb'] = [math]::Round($sysDrive.Size / 1GB, 2)
            $metrics['disk_used_gb'] = [math]::Round($diskUsed / 1GB, 2)
        }
    }
    catch {
        Write-Warning "Erro ao obter disco: $_"
    }
    
    try {
        # Uptime
        $uptime = (Get-Date) - (Get-CimInstance -ClassName Win32_OperatingSystem).LastBootUpTime
        $metrics['uptime_seconds'] = [math]::Round($uptime.TotalSeconds, 0)
        $metrics['uptime_days'] = [math]::Round($uptime.TotalDays, 2)
    }
    catch {
        Write-Warning "Erro ao obter uptime: $_"
    }
    
    return $metrics
}

# Função para obter informações do sistema operacional
function Get-SystemInfo {
    $info = @{
        hostname = [System.Net.Dns]::GetHostName()
        ip = $null
        os = $null
    }
    
    try {
        # IP principal (primeiro IPv4 não-loopback)
        $ipAddress = Get-NetIPAddress -AddressFamily IPv4 | 
            Where-Object { $_.IPAddress -notlike '127.*' -and $_.PrefixOrigin -ne 'WellKnown' } | 
            Select-Object -First 1
        if ($ipAddress) {
            $info['ip'] = $ipAddress.IPAddress
        }
    }
    catch {
        try {
            # Fallback para sistemas mais antigos
            $ipAddress = [System.Net.Dns]::GetHostAddresses([System.Net.Dns]::GetHostName()) | 
                Where-Object { $_.AddressFamily -eq 'InterNetwork' -and $_.IPAddressToString -notlike '127.*' } | 
                Select-Object -First 1
            if ($ipAddress) {
                $info['ip'] = $ipAddress.IPAddressToString
            }
        }
        catch {
            Write-Warning "Erro ao obter IP: $_"
        }
    }
    
    try {
        $os = Get-CimInstance -ClassName Win32_OperatingSystem
        $info['os'] = "$($os.Caption) $($os.Version)"
    }
    catch {
        Write-Warning "Erro ao obter OS: $_"
    }
    
    return $info
}

# Função para enviar telemetria
function Send-TelemetryData {
    param(
        [string]$Url,
        [string]$Token,
        [string]$Host,
        [hashtable]$Metrics,
        [hashtable]$SystemInfo
    )
    
    $endpoint = "$Url/api/telemetry"
    
    $body = @{
        host_name = $Host
        hostname = $SystemInfo.hostname
        ip = $SystemInfo.ip
        os = $SystemInfo.os
        metrics = $Metrics
    }
    
    $jsonBody = $body | ConvertTo-Json -Depth 5
    
    # Garante encoding UTF-8 (PowerShell pode usar UTF-16 por padrão)
    $utf8Encoding = [System.Text.Encoding]::UTF8
    $bodyBytes = $utf8Encoding.GetBytes($jsonBody)
    
    $headers = @{
        'Content-Type' = 'application/json; charset=utf-8'
        'Authorization' = "Bearer $Token"
        'Accept' = 'application/json'
    }
    
    try {
        $response = Invoke-RestMethod -Uri $endpoint -Method POST -Headers $headers -Body $bodyBytes -ContentType 'application/json; charset=utf-8' -TimeoutSec 30
        
        if ($response.success) {
            Write-Host "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] Telemetria enviada com sucesso - Host: $($response.host_name), Status: $($response.status)" -ForegroundColor Green
            return $true
        }
        else {
            Write-Warning "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] Resposta da API: $($response | ConvertTo-Json -Compress)"
            return $false
        }
    }
    catch {
        $errorMessage = $_.Exception.Message
        
        if ($_.Exception.Response) {
            try {
                $reader = [System.IO.StreamReader]::new($_.Exception.Response.GetResponseStream())
                $errorBody = $reader.ReadToEnd()
                $reader.Close()
                Write-Error "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] Erro ao enviar telemetria: $errorMessage`nResposta: $errorBody"
            }
            catch {
                Write-Error "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] Erro ao enviar telemetria: $errorMessage"
            }
        }
        else {
            Write-Error "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] Erro ao enviar telemetria: $errorMessage"
        }
        
        return $false
    }
}

# ============================================
# MAIN
# ============================================

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  BACKUP MANAGER - TELEMETRIA          " -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Host: $HostName" -ForegroundColor White
Write-Host "API: $ApiUrl" -ForegroundColor White
Write-Host "Intervalo: $IntervalMinutes minutos" -ForegroundColor White
Write-Host "Modo: $(if ($RunOnce) { 'Execução única' } else { 'Loop contínuo' })" -ForegroundColor White
Write-Host ""

# Obtém informações do sistema (apenas uma vez)
$systemInfo = Get-SystemInfo

Write-Host "Hostname: $($systemInfo.hostname)" -ForegroundColor Gray
Write-Host "IP: $($systemInfo.ip)" -ForegroundColor Gray
Write-Host "OS: $($systemInfo.os)" -ForegroundColor Gray
Write-Host ""

if ($RunOnce) {
    # Execução única
    $metrics = Get-SystemMetrics
    $result = Send-TelemetryData -Url $ApiUrl -Token $ApiToken -Host $HostName -Metrics $metrics -SystemInfo $systemInfo
    
    if ($result) {
        exit 0
    }
    else {
        exit 1
    }
}
else {
    # Loop contínuo
    Write-Host "Iniciando loop de telemetria. Pressione Ctrl+C para parar." -ForegroundColor Yellow
    Write-Host ""
    
    $consecutiveFailures = 0
    $maxFailures = 10
    
    while ($true) {
        $metrics = Get-SystemMetrics
        $result = Send-TelemetryData -Url $ApiUrl -Token $ApiToken -Host $HostName -Metrics $metrics -SystemInfo $systemInfo
        
        if ($result) {
            $consecutiveFailures = 0
        }
        else {
            $consecutiveFailures++
            
            if ($consecutiveFailures -ge $maxFailures) {
                Write-Error "Muitas falhas consecutivas ($consecutiveFailures). Encerrando..."
                exit 1
            }
            
            Write-Warning "Falha ao enviar telemetria. Falhas consecutivas: $consecutiveFailures / $maxFailures"
        }
        
        # Aguarda o intervalo
        Start-Sleep -Seconds ($IntervalMinutes * 60)
    }
}
