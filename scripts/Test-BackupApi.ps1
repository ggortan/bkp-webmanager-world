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
# TESTE 4: Telemetria
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
    
    # Coleta métricas do sistema
    Write-Info "Coletando metricas do sistema..."
    
    $metrics = @{}
    
    try {
        $cpu = Get-CimInstance -ClassName Win32_Processor | Measure-Object -Property LoadPercentage -Average
        $metrics['cpu_percent'] = [math]::Round($cpu.Average, 2)
    }
    catch {
        $metrics['cpu_percent'] = 0
    }
    
    try {
        $os = Get-CimInstance -ClassName Win32_OperatingSystem
        $memoryUsed = $os.TotalVisibleMemorySize - $os.FreePhysicalMemory
        $metrics['memory_percent'] = [math]::Round(($memoryUsed / $os.TotalVisibleMemorySize) * 100, 2)
    }
    catch {
        $metrics['memory_percent'] = 0
    }
    
    try {
        $sysDrive = Get-CimInstance -ClassName Win32_LogicalDisk -Filter "DeviceID='$($env:SystemDrive)'"
        if ($sysDrive) {
            $diskUsed = $sysDrive.Size - $sysDrive.FreeSpace
            $metrics['disk_percent'] = [math]::Round(($diskUsed / $sysDrive.Size) * 100, 2)
        }
    }
    catch {
        $metrics['disk_percent'] = 0
    }
    
    try {
        $uptime = (Get-Date) - (Get-CimInstance -ClassName Win32_OperatingSystem).LastBootUpTime
        $metrics['uptime_seconds'] = [math]::Round($uptime.TotalSeconds, 0)
    }
    catch {
        $metrics['uptime_seconds'] = 0
    }
    
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
    $osInfo = $null
    try {
        $osInfo = (Get-CimInstance Win32_OperatingSystem).Caption
    }
    catch { }
    
    # Dados de telemetria
    $telemetryData = @{
        host_name = $HostName
        hostname = [System.Net.Dns]::GetHostName()
        ip = $ipAddress
        os = $osInfo
        metrics = $metrics
    }
    
    $jsonBody = $telemetryData | ConvertTo-Json -Depth 5
    
    Write-Info "Host Name: $HostName"
    Write-Host ""
    Write-Host "Payload:" -ForegroundColor Gray
    Write-Host $jsonBody -ForegroundColor DarkGray
    Write-Host ""
    Write-Info "Enviando telemetria..."
    
    try {
        $response = Invoke-RestMethod -Uri "$ApiUrl/telemetry" -Method Post -Headers $headers -Body $jsonBody -TimeoutSec 30 -ErrorAction Stop
        
        if ($response.success) {
            Write-Success "Telemetria enviada com sucesso!"
            Write-Info "Host ID: $($response.host_id)"
            Write-Info "Status: $($response.status)"
            return $true
        }
        else {
            Write-Fail "Resposta inesperada da API"
            Write-Warn "Resposta: $($response | ConvertTo-Json -Compress)"
            return $false
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
                $results.Telemetry = Test-Telemetry
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
                $results.Telemetry = Test-Telemetry
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

Write-Host ""

if ($exitCode -eq 0) {
    Write-Host "Todos os testes passaram!" -ForegroundColor Green
}
else {
    Write-Host "Alguns testes falharam. Verifique os detalhes acima." -ForegroundColor Red
}

Write-Host ""
exit $exitCode
