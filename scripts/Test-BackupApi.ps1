<#
.SYNOPSIS
    Script de teste para a API de Backup WebManager.

.DESCRIPTION
    Testa a conectividade e funcionalidade da API de backup,
    incluindo autenticação e envio de dados de teste.

.PARAMETER ApiUrl
    URL base da API (ex: https://backup.seudominio.com/api)

.PARAMETER ApiKey
    Chave de autenticação da API

.PARAMETER RoutineKey
    Chave da rotina de backup para teste

.PARAMETER TestType
    Tipo de teste: 'connectivity', 'auth', 'send', 'full'

.EXAMPLE
    .\Test-BackupApi.ps1 -ApiUrl "https://backup.exemplo.com/api" -ApiKey "sua-key" -TestType connectivity

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
    [ValidateSet('connectivity', 'auth', 'send', 'full')]
    [string]$TestType = 'full'
)

# Cores para output
function Write-Success { param($Message) Write-Host "[OK] $Message" -ForegroundColor Green }
function Write-Fail { param($Message) Write-Host "[FALHA] $Message" -ForegroundColor Red }
function Write-Info { param($Message) Write-Host "[INFO] $Message" -ForegroundColor Cyan }
function Write-Warn { param($Message) Write-Host "[AVISO] $Message" -ForegroundColor Yellow }

# Remove barra final da URL se existir
$ApiUrl = $ApiUrl.TrimEnd('/')

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
            
            # Teste HTTP básico
            Write-Info "Testando resposta HTTP..."
            $response = Invoke-WebRequest -Uri $ApiUrl -Method Head -TimeoutSec 10 -UseBasicParsing -ErrorAction SilentlyContinue
            
            if ($response.StatusCode -lt 500) {
                Write-Success "Servidor HTTP respondendo (Status: $($response.StatusCode))"
                return $true
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
        $response = Invoke-RestMethod -Uri "$ApiUrl/backups" -Method Get -Headers $headers -TimeoutSec 15 -ErrorAction Stop
        
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
    
    # Dados de teste
    $testData = @{
        routine_key = $RoutineKey
        status = "success"
        size_bytes = 1073741824  # 1 GB
        duration_seconds = 3600  # 1 hora
        details = "[TESTE] Backup de teste enviado via Test-BackupApi.ps1 em $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
        executed_at = (Get-Date).ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ")
        host_info = @{
            name = $env:COMPUTERNAME
            ip = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -ne "127.0.0.1" } | Select-Object -First 1).IPAddress
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
        $response = Invoke-RestMethod -Uri "$ApiUrl/backups" -Method Post -Headers $headers -Body $jsonBody -TimeoutSec 30 -ErrorAction Stop
        
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
    'full' {
        $results.Connectivity = Test-Connectivity
        if ($results.Connectivity) {
            $results.Authentication = Test-Authentication
            if ($results.Authentication) {
                $results.SendBackup = Test-SendBackup
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

Write-Host ""

if ($exitCode -eq 0) {
    Write-Host "Todos os testes passaram!" -ForegroundColor Green
}
else {
    Write-Host "Alguns testes falharam. Verifique os detalhes acima." -ForegroundColor Red
}

Write-Host ""
exit $exitCode
