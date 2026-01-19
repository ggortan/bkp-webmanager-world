<#
.SYNOPSIS
    Script de teste para debug da telemetria

.DESCRIPTION
    Testa o envio de telemetria para a API de forma isolada para diagnóstico

.EXAMPLE
    .\Test-Telemetry.ps1 -ConfigPath ".\config\config.json"
#>

param(
    [Parameter(Mandatory = $false)]
    [string]$ConfigPath = ".\config\config.json",
    
    [Parameter(Mandatory = $false)]
    [switch]$Verbose
)

$ErrorActionPreference = "Stop"

Write-Host "=== Teste de Telemetria ===" -ForegroundColor Cyan
Write-Host ""

# 1. Carrega configuração
Write-Host "[1/5] Carregando configuração..." -ForegroundColor Yellow
if (-not (Test-Path $ConfigPath)) {
    Write-Host "ERRO: Arquivo de configuração não encontrado: $ConfigPath" -ForegroundColor Red
    exit 1
}

$config = Get-Content $ConfigPath -Raw | ConvertFrom-Json
Write-Host "  API URL: $($config.api_url)" -ForegroundColor Gray
Write-Host "  Host Name: $($config.host_name)" -ForegroundColor Gray
Write-Host "  API Token: $($config.api_token.Substring(0, 8))..." -ForegroundColor Gray
Write-Host ""

# 2. Testa conectividade básica
Write-Host "[2/5] Testando conectividade..." -ForegroundColor Yellow
$pingUrl = "$($config.api_url.TrimEnd('/'))/ping.php"
try {
    $pingResponse = Invoke-WebRequest -Uri $pingUrl -Method GET -TimeoutSec 10 -UseBasicParsing
    Write-Host "  Ping OK - Status: $($pingResponse.StatusCode)" -ForegroundColor Green
    if ($Verbose) {
        Write-Host "  Response: $($pingResponse.Content)" -ForegroundColor Gray
    }
}
catch {
    Write-Host "  ERRO no ping: $_" -ForegroundColor Red
}
Write-Host ""

# 3. Testa autenticação
Write-Host "[3/5] Testando autenticação..." -ForegroundColor Yellow
$statusUrl = "$($config.api_url.TrimEnd('/'))/api/status"
$headers = @{
    'X-API-Key' = $config.api_token
    'Authorization' = "Bearer $($config.api_token)"
    'Accept' = 'application/json'
}

try {
    $statusResponse = Invoke-WebRequest -Uri $statusUrl -Method GET -Headers $headers -TimeoutSec 10 -UseBasicParsing
    Write-Host "  Auth OK - Status: $($statusResponse.StatusCode)" -ForegroundColor Green
    if ($Verbose) {
        Write-Host "  Response: $($statusResponse.Content)" -ForegroundColor Gray
    }
}
catch {
    $statusCode = $null
    if ($_.Exception.Response) {
        $statusCode = [int]$_.Exception.Response.StatusCode
    }
    Write-Host "  ERRO na autenticação - HTTP $statusCode : $_" -ForegroundColor Red
}
Write-Host ""

# 4. Monta payload de telemetria
Write-Host "[4/5] Montando payload..." -ForegroundColor Yellow

# Dados básicos do sistema
$osInfo = Get-CimInstance -ClassName Win32_OperatingSystem
$computerInfo = Get-CimInstance -ClassName Win32_ComputerSystem
$networkInfo = Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.PrefixOrigin -eq 'Dhcp' -or $_.PrefixOrigin -eq 'Manual' } | Select-Object -First 1

$payload = @{
    host_name = $config.host_name
    hostname = $env:COMPUTERNAME
    ip = $networkInfo.IPAddress
    os = $osInfo.Caption
    metrics = @{
        cpu_percent = 10.5
        memory_percent = 45.2
        memory_total_mb = [math]::Round($osInfo.TotalVisibleMemorySize / 1024, 0)
        memory_used_mb = [math]::Round(($osInfo.TotalVisibleMemorySize - $osInfo.FreePhysicalMemory) / 1024, 0)
        uptime_hours = [math]::Round(((Get-Date) - $osInfo.LastBootUpTime).TotalHours, 2)
    }
}

$jsonBody = $payload | ConvertTo-Json -Depth 10 -Compress

# Garante encoding UTF-8 (PowerShell pode usar UTF-16 por padrão)
$utf8Encoding = [System.Text.Encoding]::UTF8
$bodyBytes = $utf8Encoding.GetBytes($jsonBody)

Write-Host "  Payload criado:" -ForegroundColor Gray
Write-Host "    host_name: $($payload.host_name)" -ForegroundColor Gray
Write-Host "    hostname: $($payload.hostname)" -ForegroundColor Gray
Write-Host "    ip: $($payload.ip)" -ForegroundColor Gray

if ($Verbose) {
    Write-Host ""
    Write-Host "  JSON completo:" -ForegroundColor Gray
    Write-Host $jsonBody -ForegroundColor DarkGray
}
Write-Host ""

# 5. Envia telemetria
Write-Host "[5/5] Enviando telemetria..." -ForegroundColor Yellow
$telemetryUrl = "$($config.api_url.TrimEnd('/'))/api/telemetry"
Write-Host "  URL: $telemetryUrl" -ForegroundColor Gray

$sendHeaders = @{
    'Content-Type' = 'application/json; charset=utf-8'
    'X-API-Key' = $config.api_token
    'Authorization' = "Bearer $($config.api_token)"
    'Accept' = 'application/json'
}

try {
    $response = Invoke-WebRequest -Uri $telemetryUrl -Method POST -Headers $sendHeaders -Body $bodyBytes -ContentType 'application/json; charset=utf-8' -TimeoutSec 30 -UseBasicParsing
    
    Write-Host "  SUCCESS - HTTP $($response.StatusCode)" -ForegroundColor Green
    Write-Host ""
    
    $responseData = $response.Content | ConvertFrom-Json
    Write-Host "  Resposta:" -ForegroundColor Cyan
    Write-Host "    success: $($responseData.success)" -ForegroundColor Green
    Write-Host "    host_id: $($responseData.host_id)" -ForegroundColor Gray
    Write-Host "    status: $($responseData.status)" -ForegroundColor Gray
    Write-Host "    message: $($responseData.message)" -ForegroundColor Gray
}
catch {
    $statusCode = $null
    $responseBody = $null
    
    if ($_.Exception.Response) {
        $statusCode = [int]$_.Exception.Response.StatusCode
        try {
            $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
            $responseBody = $reader.ReadToEnd()
            $reader.Close()
        }
        catch {}
    }
    
    Write-Host "  ERRO - HTTP $statusCode" -ForegroundColor Red
    Write-Host ""
    
    if ($responseBody) {
        Write-Host "  Response Body:" -ForegroundColor Yellow
        Write-Host $responseBody -ForegroundColor DarkGray
        Write-Host ""
        
        try {
            $errorData = $responseBody | ConvertFrom-Json
            if ($errorData.error) {
                Write-Host "  Erro: $($errorData.error)" -ForegroundColor Red
            }
            if ($errorData.received_fields) {
                Write-Host "  Campos recebidos: $($errorData.received_fields -join ', ')" -ForegroundColor Yellow
            }
        }
        catch {}
    }
    
    Write-Host "  Exception: $($_.Exception.Message)" -ForegroundColor Red
    
    Write-Host ""
    Write-Host "  Body que foi enviado:" -ForegroundColor Yellow
    Write-Host $jsonBody -ForegroundColor DarkGray
}

Write-Host ""
Write-Host "=== Fim do Teste ===" -ForegroundColor Cyan
