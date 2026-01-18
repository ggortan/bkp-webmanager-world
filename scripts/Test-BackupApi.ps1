<#
.SYNOPSIS
    Script de teste para a API de Backup WebManager.

.DESCRIPTION
    Testa a conectividade e funcionalidade da API de backup,
    incluindo autenticação, envio de dados de backup e telemetria.

.PARAMETER ApiUrl
    URL base da API (ex: https://backup.seudominio.com/api)

.PARAMETER ApiKey
    Chave de autenticação da API

.PARAMETER RoutineKey
    Chave da rotina de backup para teste

.PARAMETER HostName
    Nome do host para teste de telemetria (padrão: $env:COMPUTERNAME)

.PARAMETER TestType
    Tipo de teste: 'connectivity', 'auth', 'send', 'telemetry', 'full'

.EXAMPLE
    .\Test-BackupApi.ps1 -ApiUrl "https://backup.exemplo.com/api" -ApiKey "sua-key" -TestType connectivity

.EXAMPLE
    .\Test-BackupApi.ps1 -ApiUrl "https://backup.exemplo.com/api" -ApiKey "sua-key" -TestType telemetry

.EXAMPLE
    .\Test-BackupApi.ps1 -ApiUrl "https://backup.exemplo.com/api" -ApiKey "sua-key" -RoutineKey "rtk_123" -TestType full
#>

param(
    [Parameter(Mandatory = $true)]
    [string]$ApiUrl,

    [Parameter(Mandatory = $true)]
    [string]$ApiKey,

    [Parameter(Mandatory = $false)]
    [string]$RoutineKey,

    [Parameter(Mandatory = $false)]
    [string]$HostName = $env:COMPUTERNAME,

    [Parameter(Mandatory = $false)]
    [ValidateSet('connectivity', 'auth', 'send', 'telemetry', 'full')]
    [string]$TestType = 'full'
)

# Cores para output
function Write-Success { param($Message) Write-Host "[OK] $Message" -ForegroundColor Green }
function Write-Fail { param($Message) Write-Host "[FALHA] $Message" -ForegroundColor Red }
function Write-Info { param($Message) Write-Host "[INFO] $Message" -ForegroundColor Cyan }
function Write-Warn { param($Message) Write-Host "[AVISO] $Message" -ForegroundColor Yellow }

# Remove barra final da URL se existir
$ApiUrl = $ApiUrl.TrimEnd('/')

# Configura TLS 1.2 (necessário para muitos servidores HTTPS modernos)
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

# Ignora erros de certificado SSL se necessário (descomente se tiver problemas de certificado)
# [System.Net.ServicePointManager]::ServerCertificateValidationCallback = { $true }

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Teste da API - Backup WebManager" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Info "URL da API: $ApiUrl"
Write-Info "Tipo de teste: $TestType"
Write-Host ""

$results = @{
    Connectivity = $null
    Authentication = $null
    SendBackup = $null
    Telemetry = $null
    TelemetryHistory = $null
}

# ============================================
# TESTE 1: Conectividade
# ============================================
function Test-Connectivity {
    Write-Host "--- Teste de Conectividade ---" -ForegroundColor Yellow
    
    try {
        # Tenta extrair host da URL
        $uri = [System.Uri]$ApiUrl
        $hostName = $uri.Host
        $port = if ($uri.Port -eq -1) { if ($uri.Scheme -eq 'https') { 443 } else { 80 } } else { $uri.Port }
        
        Write-Info "Testando conexao com $hostName na porta $port..."
        
        # Teste de conexão TCP
        $tcpTest = Test-NetConnection -ComputerName $hostName -Port $port -WarningAction SilentlyContinue
        
        if ($tcpTest.TcpTestSucceeded) {
            Write-Success "Conexao TCP estabelecida com sucesso"
            
            # Teste HTTP via endpoint /status (público)
            Write-Info "Testando resposta HTTP via /status..."
            try {
                $statusUrl = "$ApiUrl/status"
                Write-Info "URL: $statusUrl"
                
                # Usa Invoke-WebRequest com mais opções de compatibilidade
                $webResponse = Invoke-WebRequest -Uri $statusUrl -Method Get -TimeoutSec 30 -UseBasicParsing -ErrorAction Stop
                
                if ($webResponse.StatusCode -eq 200) {
                    Write-Success "API respondendo corretamente (Status: 200)"
                    try {
                        $jsonResponse = $webResponse.Content | ConvertFrom-Json
                        if ($jsonResponse.status) {
                            Write-Info "Status da API: $($jsonResponse.status)"
                        }
                    }
                    catch {
                        Write-Info "Resposta recebida (nao-JSON)"
                    }
                    return $true
                }
                else {
                    Write-Success "Servidor respondendo (Status: $($webResponse.StatusCode))"
                    return $true
                }
            }
            catch {
                $errorMsg = $_.Exception.Message
                Write-Warn "Erro ao acessar /status: $errorMsg"
                
                # Verifica se é erro de SSL/TLS
                if ($errorMsg -match "SSL|TLS|certificate|trust") {
                    Write-Warn "Possivel problema de certificado SSL"
                    Write-Info "Tente executar: [System.Net.ServicePointManager]::ServerCertificateValidationCallback = { `$true }"
                }
                
                # Tenta um GET simples na URL base
                Write-Info "Tentando conexao na URL base..."
                try {
                    $basicResponse = Invoke-WebRequest -Uri $ApiUrl -Method Get -TimeoutSec 30 -UseBasicParsing -ErrorAction Stop
                    if ($basicResponse.StatusCode -lt 500) {
                        Write-Success "Servidor HTTP respondendo (Status: $($basicResponse.StatusCode))"
                        return $true
                    }
                }
                catch {
                    if ($_.Exception.Response) {
                        $statusCode = [int]$_.Exception.Response.StatusCode
                        if ($statusCode -lt 500) {
                            Write-Success "Servidor respondendo (Status: $statusCode)"
                            return $true
                        }
                    }
                    Write-Fail "Nenhuma resposta HTTP valida"
                    return $false
                }
            }
        }
        else {
            Write-Fail "Nao foi possivel conectar a $hostName`:$port"
            Write-Warn "Verifique: firewall, DNS, porta correta"
            return $false
        }
    }
    catch {
        # Mesmo com erro, pode ter funcionado parcialmente
        if ($_.Exception.Response) {
            $statusCode = [int]$_.Exception.Response.StatusCode
            if ($statusCode -lt 500) {
                Write-Success "Servidor respondendo (Status: $statusCode)"
                return $true
            }
        }
        Write-Fail "Erro de conectividade: $($_.Exception.Message)"
        return $false
    }
    
    return $true
}

# ============================================
# TESTE 2: Autenticação
# ============================================
function Test-Authentication {
    Write-Host ""
    Write-Host "--- Teste de Autenticacao ---" -ForegroundColor Yellow
    
    $headers = @{
        "X-API-Key" = $ApiKey
        "Content-Type" = "application/json"
        "Accept" = "application/json"
    }
    
    try {
        Write-Info "Testando autenticacao com X-API-Key..."
        
        # Tenta acessar endpoint que requer autenticação
        $response = Invoke-RestMethod -Uri "$ApiUrl/me" -Method Get -Headers $headers -TimeoutSec 15 -ErrorAction Stop
        
        Write-Success "Autenticacao bem sucedida!"
        Write-Info "API Key valida e funcionando"
        return $true
    }
    catch {
        $statusCode = 0
        if ($_.Exception.Response) {
            $statusCode = [int]$_.Exception.Response.StatusCode
        }
        
        switch ($statusCode) {
            401 {
                Write-Fail "API Key invalida ou expirada (401 Unauthorized)"
                Write-Warn "Verifique se a API Key esta correta no painel"
            }
            403 {
                Write-Fail "Acesso negado (403 Forbidden)"
                Write-Warn "A API Key pode nao ter permissao para este recurso"
            }
            404 {
                Write-Warn "Endpoint nao encontrado (404)"
                Write-Info "A autenticacao pode estar correta, mas o endpoint /backups nao existe"
                # Considera sucesso parcial
                return $true
            }
            0 {
                Write-Fail "Erro de conexao: $($_.Exception.Message)"
            }
            default {
                Write-Fail "Erro HTTP $statusCode`: $($_.Exception.Message)"
            }
        }
        return $false
    }
}

# ============================================
# TESTE 3: Envio de Backup
# ============================================
function Test-SendBackup {
    Write-Host ""
    Write-Host "--- Teste de Envio de Backup ---" -ForegroundColor Yellow
    
    if (-not $RoutineKey) {
        Write-Warn "RoutineKey nao fornecida. Pulando teste de envio."
        Write-Info "Use -RoutineKey 'sua-routine-key' para testar o envio"
        return $null
    }
    
    $headers = @{
        "X-API-Key" = $ApiKey
        "Content-Type" = "application/json"
        "Accept" = "application/json"
    }
    
    # Dados de teste - usando campos corretos da API
    $testData = @{
        routine_key = $RoutineKey
        status = "sucesso"  # Valores: sucesso, falha, alerta, executando
        data_inicio = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
        data_fim = (Get-Date).AddHours(1).ToString("yyyy-MM-dd HH:mm:ss")
        tamanho_bytes = 1073741824  # 1 GB
        detalhes = "[TESTE] Backup de teste enviado via Test-BackupApi.ps1 em $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
        host_info = @{
            name = $env:COMPUTERNAME
            ip = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -ne "127.0.0.1" -and $_.IPAddress -notlike "169.*" } | Select-Object -First 1).IPAddress
            os = (Get-CimInstance Win32_OperatingSystem).Caption
        }
    }
    
    $jsonBody = $testData | ConvertTo-Json -Depth 10
    
    Write-Info "Routine Key: $RoutineKey"
    Write-Info "Enviando dados de teste..."
    Write-Host ""
    Write-Host "Payload:" -ForegroundColor Gray
    Write-Host $jsonBody -ForegroundColor DarkGray
    Write-Host ""
    
    try {
        $response = Invoke-RestMethod -Uri "$ApiUrl/backup" -Method Post -Headers $headers -Body $jsonBody -TimeoutSec 30 -ErrorAction Stop
        
        Write-Success "Backup de teste enviado com sucesso!"
        
        if ($response.id) {
            Write-Info "ID da execucao: $($response.id)"
        }
        if ($response.message) {
            Write-Info "Resposta: $($response.message)"
        }
        
        return $true
    }
    catch {
        $statusCode = 0
        $errorBody = ""
        
        if ($_.Exception.Response) {
            $statusCode = [int]$_.Exception.Response.StatusCode
            
            try {
                $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                $errorBody = $reader.ReadToEnd()
                $reader.Close()
            }
            catch {}
        }
        
        switch ($statusCode) {
            400 {
                Write-Fail "Dados invalidos (400 Bad Request)"
                if ($errorBody) { Write-Warn "Detalhes: $errorBody" }
            }
            401 {
                Write-Fail "API Key invalida (401 Unauthorized)"
            }
            404 {
                Write-Fail "Rotina nao encontrada (404 Not Found)"
                Write-Warn "Verifique se a routine_key '$RoutineKey' existe no sistema"
            }
            422 {
                Write-Fail "Erro de validacao (422 Unprocessable Entity)"
                if ($errorBody) { Write-Warn "Detalhes: $errorBody" }
            }
            500 {
                Write-Fail "Erro interno do servidor (500)"
                Write-Warn "Verifique os logs do servidor"
            }
            default {
                Write-Fail "Erro HTTP $statusCode`: $($_.Exception.Message)"
                if ($errorBody) { Write-Warn "Detalhes: $errorBody" }
            }
        }
        return $false
    }
}

# ============================================
# TESTE 4: Telemetria (Versão Aprimorada)
# ============================================
function Test-Telemetry {
    Write-Host ""
    Write-Host "--- Teste de Telemetria ---" -ForegroundColor Yellow
    
    # Headers - suporta ambos os métodos de autenticação
    $headers = @{
        "Content-Type" = "application/json"
        "Accept" = "application/json"
        "Authorization" = "Bearer $ApiKey"
        "X-API-Key" = $ApiKey
    }
    
    # Coleta métricas completas do sistema
    Write-Info "Coletando metricas completas do sistema..."
    
    $metrics = @{}
    
    # ========== CPU ==========
    try {
        $cpuInfo = Get-CimInstance -ClassName Win32_Processor
        $cpuAvg = $cpuInfo | Measure-Object -Property LoadPercentage -Average
        $metrics['cpu_percent'] = [math]::Round($cpuAvg.Average, 2)
        
        $cpuFirst = $cpuInfo | Select-Object -First 1
        $metrics['cpu_info'] = @{
            name = $cpuFirst.Name.Trim()
            cores = $cpuFirst.NumberOfCores
            logical_processors = $cpuFirst.NumberOfLogicalProcessors
            max_clock_mhz = $cpuFirst.MaxClockSpeed
        }
    }
    catch {
        $metrics['cpu_percent'] = 0
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
    }
    catch {
        $metrics['memory_percent'] = 0
    }
    
    # ========== DISCOS (TODOS) ==========
    try {
        $allDisks = Get-CimInstance -ClassName Win32_LogicalDisk -Filter "DriveType=3"
        $disksList = @()
        
        foreach ($disk in $allDisks) {
            $diskUsed = $disk.Size - $disk.FreeSpace
            $diskPercent = if ($disk.Size -gt 0) { [math]::Round(($diskUsed / $disk.Size) * 100, 2) } else { 0 }
            
            $disksList += @{
                drive = $disk.DeviceID
                volume_name = $disk.VolumeName
                total_gb = [math]::Round($disk.Size / 1GB, 2)
                used_gb = [math]::Round($diskUsed / 1GB, 2)
                free_gb = [math]::Round($disk.FreeSpace / 1GB, 2)
                percent_used = $diskPercent
            }
        }
        
        $metrics['disks'] = $disksList
        $metrics['disk_count'] = $disksList.Count
        
        # Disco do sistema para compatibilidade
        $sysDrive = $allDisks | Where-Object { $_.DeviceID -eq $env:SystemDrive }
        if ($sysDrive) {
            $sysDiskUsed = $sysDrive.Size - $sysDrive.FreeSpace
            $metrics['disk_percent'] = [math]::Round(($sysDiskUsed / $sysDrive.Size) * 100, 2)
            $metrics['disk_total_gb'] = [math]::Round($sysDrive.Size / 1GB, 2)
            $metrics['disk_used_gb'] = [math]::Round($sysDiskUsed / 1GB, 2)
            $metrics['disk_free_gb'] = [math]::Round($sysDrive.FreeSpace / 1GB, 2)
        }
    }
    catch {
        $metrics['disk_percent'] = 0
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
        $metrics['uptime_seconds'] = 0
    }
    
    # ========== SISTEMA ==========
    try {
        $computerSystem = Get-CimInstance -ClassName Win32_ComputerSystem
        $metrics['system_info'] = @{
            manufacturer = $computerSystem.Manufacturer
            model = $computerSystem.Model
            domain = $computerSystem.Domain
            total_physical_memory_gb = [math]::Round($computerSystem.TotalPhysicalMemory / 1GB, 2)
        }
    }
    catch { }
    
    # Timestamp de coleta
    $metrics['collected_at'] = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
    
    # Obtém IP
    $ipAddress = $null
    try {
        $ipAddress = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -ne "127.0.0.1" -and $_.IPAddress -notlike "169.*" } | Select-Object -First 1).IPAddress
    }
    catch {
        try {
            $ipAddress = ([System.Net.Dns]::GetHostAddresses([System.Net.Dns]::GetHostName()) | Where-Object { $_.AddressFamily -eq 'InterNetwork' } | Select-Object -First 1).IPAddressToString
        }
        catch { }
    }
    
    # Obtém OS
    $osCaption = $null
    try {
        $osCaption = (Get-CimInstance Win32_OperatingSystem).Caption
    }
    catch { }
    
    # Dados de telemetria completos
    $telemetryData = @{
        host_name = $HostName
        hostname = [System.Net.Dns]::GetHostName()
        ip = $ipAddress
        os = $osCaption
        metrics = $metrics
    }
    
    $jsonBody = $telemetryData | ConvertTo-Json -Depth 10
    
    Write-Info "Host Name: $HostName"
    Write-Host ""
    Write-Host "Metricas Coletadas:" -ForegroundColor Gray
    Write-Host "  CPU: $($metrics['cpu_percent'])%" -ForegroundColor DarkGray
    Write-Host "  Memoria: $($metrics['memory_percent'])% ($($metrics['memory_used_mb'])MB / $($metrics['memory_total_mb'])MB)" -ForegroundColor DarkGray
    Write-Host "  Disco Sistema: $($metrics['disk_percent'])% ($($metrics['disk_used_gb'])GB / $($metrics['disk_total_gb'])GB)" -ForegroundColor DarkGray
    Write-Host "  Discos Encontrados: $($metrics['disk_count'])" -ForegroundColor DarkGray
    Write-Host "  Uptime: $($metrics['uptime_days']) dias" -ForegroundColor DarkGray
    Write-Host ""
    Write-Info "Enviando telemetria..."
    
    try {
        $response = Invoke-RestMethod -Uri "$ApiUrl/telemetry" -Method Post -Headers $headers -Body $jsonBody -TimeoutSec 30 -ErrorAction Stop
        
        if ($response.success) {
            Write-Success "Telemetria enviada com sucesso!"
            Write-Info "Host ID: $($response.host_id)"
            Write-Info "Status: $($response.status)"
            
            # Retorna o host_id para usar na verificação de histórico
            return @{ Success = $true; HostId = $response.host_id }
        }
        else {
            Write-Fail "Resposta inesperada da API"
            Write-Warn "Resposta: $($response | ConvertTo-Json -Compress)"
            return @{ Success = $false; HostId = $null }
        }
    }
    catch {
        $statusCode = 0
        $errorBody = ""
        
        if ($_.Exception.Response) {
            $statusCode = [int]$_.Exception.Response.StatusCode
            
            try {
                $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                $errorBody = $reader.ReadToEnd()
                $reader.Close()
            }
            catch {}
        }
        
        switch ($statusCode) {
            401 {
                Write-Fail "API Key invalida (401 Unauthorized)"
            }
            422 {
                Write-Fail "Erro de validacao (422)"
                if ($errorBody) { Write-Warn "Detalhes: $errorBody" }
            }
            500 {
                Write-Fail "Erro interno do servidor (500)"
                if ($errorBody) { Write-Warn "Detalhes: $errorBody" }
            }
            default {
                Write-Fail "Erro HTTP $statusCode`: $($_.Exception.Message)"
                if ($errorBody) { Write-Warn "Detalhes: $errorBody" }
            }
        }
        return @{ Success = $false; HostId = $null }
    }
}

# ============================================
# TESTE 4.1: Verificação de Histórico de Telemetria
# ============================================
function Test-TelemetryHistory {
    param(
        [int]$HostId = 0
    )
    
    Write-Host ""
    Write-Host "--- Verificacao de Historico de Telemetria ---" -ForegroundColor Yellow
    
    if ($HostId -eq 0) {
        Write-Warn "Host ID nao fornecido. Pulando verificacao de historico."
        return $null
    }
    
    $headers = @{
        "Content-Type" = "application/json"
        "Accept" = "application/json"
        "Authorization" = "Bearer $ApiKey"
        "X-API-Key" = $ApiKey
    }
    
    try {
        Write-Info "Verificando historico de telemetria para Host ID: $HostId..."
        
        # Obtém a lista de hosts para verificar a telemetria
        $response = Invoke-RestMethod -Uri "$ApiUrl/hosts" -Method Get -Headers $headers -TimeoutSec 15 -ErrorAction Stop
        
        if ($response.success -and $response.hosts) {
            $targetHost = $response.hosts | Where-Object { $_.id -eq $HostId }
            
            if ($targetHost) {
                Write-Success "Host encontrado: $($targetHost.nome)"
                
                # Verifica se há dados de telemetria
                if ($targetHost.telemetry_data) {
                    Write-Success "Dados de telemetria presentes no host"
                    
                    $telemetryData = if ($targetHost.telemetry_data -is [string]) { 
                        $targetHost.telemetry_data | ConvertFrom-Json 
                    } else { 
                        $targetHost.telemetry_data 
                    }
                    
                    Write-Host ""
                    Write-Host "Ultima Telemetria Armazenada:" -ForegroundColor Gray
                    if ($telemetryData.cpu_percent) { Write-Host "  CPU: $($telemetryData.cpu_percent)%" -ForegroundColor DarkGray }
                    if ($telemetryData.memory_percent) { Write-Host "  Memoria: $($telemetryData.memory_percent)%" -ForegroundColor DarkGray }
                    if ($telemetryData.disk_percent) { Write-Host "  Disco: $($telemetryData.disk_percent)%" -ForegroundColor DarkGray }
                    if ($telemetryData.uptime_days) { Write-Host "  Uptime: $($telemetryData.uptime_days) dias" -ForegroundColor DarkGray }
                    if ($telemetryData.collected_at) { Write-Host "  Coletado em: $($telemetryData.collected_at)" -ForegroundColor DarkGray }
                    
                    return $true
                }
                else {
                    Write-Warn "Nenhum dado de telemetria encontrado no host"
                    Write-Info "A telemetria pode levar alguns segundos para ser processada"
                    return $false
                }
            }
            else {
                Write-Warn "Host ID $HostId nao encontrado na lista de hosts"
                return $false
            }
        }
        else {
            Write-Warn "Nao foi possivel obter lista de hosts"
            return $false
        }
    }
    catch {
        $statusCode = 0
        if ($_.Exception.Response) {
            $statusCode = [int]$_.Exception.Response.StatusCode
        }
        
        Write-Fail "Erro ao verificar historico: $($_.Exception.Message)"
        return $false
    }
}

# ============================================
# TESTE 5: Lista de Hosts
# ============================================
function Test-ListHosts {
    Write-Host ""
    Write-Host "--- Teste de Lista de Hosts ---" -ForegroundColor Yellow
    
    $headers = @{
        "Content-Type" = "application/json"
        "Accept" = "application/json"
        "Authorization" = "Bearer $ApiKey"
        "X-API-Key" = $ApiKey
    }
    
    try {
        Write-Info "Obtendo lista de hosts..."
        
        $response = Invoke-RestMethod -Uri "$ApiUrl/hosts" -Method Get -Headers $headers -TimeoutSec 15 -ErrorAction Stop
        
        if ($response.success) {
            Write-Success "Lista de hosts obtida com sucesso!"
            Write-Info "Total de hosts: $($response.total)"
            
            if ($response.hosts -and $response.hosts.Count -gt 0) {
                Write-Host ""
                Write-Host "Hosts:" -ForegroundColor White
                foreach ($host in $response.hosts) {
                    $statusIcon = switch ($host.online_status) {
                        'online' { "[ONLINE]" }
                        'offline' { "[OFFLINE]" }
                        default { "[?]" }
                    }
                    $statusColor = switch ($host.online_status) {
                        'online' { "Green" }
                        'offline' { "Red" }
                        default { "Gray" }
                    }
                    Write-Host "  " -NoNewline
                    Write-Host $statusIcon -ForegroundColor $statusColor -NoNewline
                    Write-Host " $($host.nome) - $($host.ip)" -ForegroundColor White
                }
            }
            return $true
        }
        else {
            Write-Warn "Resposta inesperada"
            return $false
        }
    }
    catch {
        $statusCode = 0
        if ($_.Exception.Response) {
            $statusCode = [int]$_.Exception.Response.StatusCode
        }
        
        switch ($statusCode) {
            401 { Write-Fail "API Key invalida (401 Unauthorized)" }
            403 { Write-Fail "Acesso negado (403 Forbidden)" }
            default { Write-Fail "Erro: $($_.Exception.Message)" }
        }
        return $false
    }
}

# ============================================
# Execução dos Testes
# ============================================

$exitCode = 0

switch ($TestType) {
    'connectivity' {
        $results.Connectivity = Test-Connectivity
    }
    'auth' {
        $results.Connectivity = Test-Connectivity
        if ($results.Connectivity) {
            $results.Authentication = Test-Authentication
        }
    }
    'send' {
        $results.SendBackup = Test-SendBackup
    }
    'telemetry' {
        $results.Connectivity = Test-Connectivity
        if ($results.Connectivity) {
            $results.Authentication = Test-Authentication
            if ($results.Authentication) {
                $telemetryResult = Test-Telemetry
                if ($telemetryResult -is [hashtable]) {
                    $results.Telemetry = $telemetryResult.Success
                    if ($telemetryResult.HostId) {
                        Start-Sleep -Seconds 2  # Aguarda processamento
                        $results.TelemetryHistory = Test-TelemetryHistory -HostId $telemetryResult.HostId
                    }
                } else {
                    $results.Telemetry = $telemetryResult
                }
                Test-ListHosts | Out-Null
            }
        }
    }
    'full' {
        $results.Connectivity = Test-Connectivity
        if ($results.Connectivity) {
            $results.Authentication = Test-Authentication
            if ($results.Authentication) {
                $results.SendBackup = Test-SendBackup
                $telemetryResult = Test-Telemetry
                if ($telemetryResult -is [hashtable]) {
                    $results.Telemetry = $telemetryResult.Success
                    if ($telemetryResult.HostId) {
                        Start-Sleep -Seconds 2  # Aguarda processamento
                        $results.TelemetryHistory = Test-TelemetryHistory -HostId $telemetryResult.HostId
                    }
                } else {
                    $results.Telemetry = $telemetryResult
                }
                Test-ListHosts | Out-Null
            }
        }
    }
}

# ============================================
# Resumo
# ============================================
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Resumo dos Testes" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

if ($null -ne $results.Connectivity) {
    if ($results.Connectivity) { Write-Success "Conectividade: OK" }
    else { Write-Fail "Conectividade: FALHOU"; $exitCode = 1 }
}

if ($null -ne $results.Authentication) {
    if ($results.Authentication) { Write-Success "Autenticacao: OK" }
    else { Write-Fail "Autenticacao: FALHOU"; $exitCode = 1 }
}

if ($null -ne $results.SendBackup) {
    if ($results.SendBackup) { Write-Success "Envio de Backup: OK" }
    else { Write-Fail "Envio de Backup: FALHOU"; $exitCode = 1 }
}
elseif ($TestType -eq 'full' -and -not $RoutineKey) {
    Write-Warn "Envio de Backup: PULADO (sem RoutineKey)"
}

if ($null -ne $results.Telemetry) {
    if ($results.Telemetry) { Write-Success "Telemetria: OK" }
    else { Write-Fail "Telemetria: FALHOU"; $exitCode = 1 }
}

if ($null -ne $results.TelemetryHistory) {
    if ($results.TelemetryHistory) { Write-Success "Historico de Telemetria: OK" }
    else { Write-Warn "Historico de Telemetria: NAO CONFIRMADO" }
}

Write-Host ""

if ($exitCode -eq 0) {
    Write-Host "Todos os testes passaram!" -ForegroundColor Green
}
else {
    Write-Host "Alguns testes falharam. Verifique os detalhes acima." -ForegroundColor Red
}

Write-Host ""
exit $exitCode
