<#
.SYNOPSIS
    Script de teste para a API do Backup WebManager
    
.DESCRIPTION
    Este script testa os endpoints da API de backup, simulando o envio de dados
    de execução de backup de um servidor Windows.
    
.PARAMETER ApiUrl
    URL base da API (ex: https://dev.gortan.com.br/world/bkpmng)
    
.PARAMETER ApiKey
    Chave de API do cliente para autenticação
    
.PARAMETER TestMode
    Modo de teste: 'all', 'status', 'auth', 'backup'
    
.EXAMPLE
    .\Test-BackupApi.ps1 -ApiUrl "https://dev.gortan.com.br/world/bkpmng" -ApiKey "sua-api-key"
    
.EXAMPLE
    .\Test-BackupApi.ps1 -ApiUrl "https://dev.gortan.com.br/world/bkpmng" -ApiKey "sua-api-key" -TestMode "status"
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$ApiUrl,
    
    [Parameter(Mandatory = $false)]
    [string]$ApiKey = "",
    
    [Parameter(Mandatory = $false)]
    [ValidateSet('all', 'status', 'auth', 'backup', 'multiple')]
    [string]$TestMode = 'all'
)

# Configurações
$ErrorActionPreference = "Stop"

# Remove barra final da URL se existir
$ApiUrl = $ApiUrl.TrimEnd('/')

# Cores para output
function Write-Success { param($Message) Write-Host "✓ $Message" -ForegroundColor Green }
function Write-Fail { param($Message) Write-Host "✗ $Message" -ForegroundColor Red }
function Write-Info { param($Message) Write-Host "→ $Message" -ForegroundColor Cyan }
function Write-Header { param($Message) Write-Host "`n═══════════════════════════════════════════════════════════" -ForegroundColor Yellow; Write-Host " $Message" -ForegroundColor Yellow; Write-Host "═══════════════════════════════════════════════════════════" -ForegroundColor Yellow }

# Função para fazer requisições à API
function Invoke-ApiRequest {
    param(
        [string]$Endpoint,
        [string]$Method = "GET",
        [hashtable]$Body = $null,
        [hashtable]$Headers = @{}
    )
    
    $uri = "$ApiUrl$Endpoint"
    
    $params = @{
        Uri = $uri
        Method = $Method
        ContentType = "application/json; charset=utf-8"
        Headers = $Headers
        UseBasicParsing = $true
    }
    
    if ($Body) {
        $params.Body = ($Body | ConvertTo-Json -Depth 10)
    }
    
    try {
        $response = Invoke-WebRequest @params
        return @{
            Success = $true
            StatusCode = $response.StatusCode
            Content = $response.Content | ConvertFrom-Json
            RawContent = $response.Content
        }
    }
    catch {
        $statusCode = 0
        $content = $null
        
        if ($_.Exception.Response) {
            $statusCode = [int]$_.Exception.Response.StatusCode
            try {
                $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                $content = $reader.ReadToEnd() | ConvertFrom-Json
            }
            catch {
                $content = @{ error = $_.Exception.Message }
            }
        }
        
        return @{
            Success = $false
            StatusCode = $statusCode
            Content = $content
            Error = $_.Exception.Message
        }
    }
}

# ============================================================
# TESTE 1: Status da API (não requer autenticação)
# ============================================================
function Test-ApiStatus {
    Write-Header "TESTE 1: Status da API"
    Write-Info "Endpoint: GET /api/status"
    
    $result = Invoke-ApiRequest -Endpoint "/api/status"
    
    if ($result.Success -and $result.Content.status -eq "online") {
        Write-Success "API está online!"
        Write-Host "   Versão: $($result.Content.version)" -ForegroundColor Gray
        Write-Host "   Timestamp: $($result.Content.timestamp)" -ForegroundColor Gray
        return $true
    }
    else {
        Write-Fail "API não está respondendo corretamente"
        Write-Host "   Erro: $($result.Error)" -ForegroundColor Red
        return $false
    }
}

# ============================================================
# TESTE 2: Autenticação com API Key
# ============================================================
function Test-ApiAuth {
    Write-Header "TESTE 2: Autenticação"
    Write-Info "Endpoint: GET /api/me"
    
    if ([string]::IsNullOrEmpty($ApiKey)) {
        Write-Fail "API Key não fornecida. Use o parâmetro -ApiKey"
        return $false
    }
    
    $headers = @{
        "X-API-Key" = $ApiKey
    }
    
    $result = Invoke-ApiRequest -Endpoint "/api/me" -Headers $headers
    
    if ($result.Success -and $result.Content.success) {
        Write-Success "Autenticação bem-sucedida!"
        Write-Host "   Cliente ID: $($result.Content.cliente.id)" -ForegroundColor Gray
        Write-Host "   Identificador: $($result.Content.cliente.identificador)" -ForegroundColor Gray
        Write-Host "   Nome: $($result.Content.cliente.nome)" -ForegroundColor Gray
        Write-Host "   Ativo: $($result.Content.cliente.ativo)" -ForegroundColor Gray
        return $true
    }
    else {
        Write-Fail "Falha na autenticação"
        if ($result.Content.error) {
            Write-Host "   Erro: $($result.Content.error)" -ForegroundColor Red
        }
        Write-Host "   Status Code: $($result.StatusCode)" -ForegroundColor Red
        return $false
    }
}

# ============================================================
# TESTE 3: Envio de dados de backup
# ============================================================
function Test-BackupSubmission {
    param(
        [string]$Status = "sucesso",
        [string]$Servidor = "SRV-TESTE-01",
        [string]$Rotina = "Backup Diário - Teste"
    )
    
    Write-Header "TESTE 3: Envio de Backup ($Status)"
    Write-Info "Endpoint: POST /api/backup"
    
    if ([string]::IsNullOrEmpty($ApiKey)) {
        Write-Fail "API Key não fornecida. Use o parâmetro -ApiKey"
        return $false
    }
    
    $headers = @{
        "X-API-Key" = $ApiKey
    }
    
    # Dados simulados de backup
    $dataInicio = (Get-Date).AddMinutes(-30).ToString("yyyy-MM-dd HH:mm:ss")
    $dataFim = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
    
    $backupData = @{
        servidor = $Servidor
        hostname = "$Servidor.local"
        ip = "192.168.1." + (Get-Random -Minimum 10 -Maximum 250)
        sistema_operacional = "Windows Server 2022"
        rotina = $Rotina
        tipo_backup = "Completo"
        destino = "\\NAS01\Backups\$Servidor"
        data_inicio = $dataInicio
        data_fim = $dataFim
        status = $Status
        tamanho_bytes = (Get-Random -Minimum 1073741824 -Maximum 107374182400)  # 1GB a 100GB
        detalhes = @{
            arquivos_copiados = (Get-Random -Minimum 1000 -Maximum 50000)
            arquivos_ignorados = (Get-Random -Minimum 0 -Maximum 100)
            velocidade_mbps = (Get-Random -Minimum 50 -Maximum 500)
            metodo = "VSS Shadow Copy"
            compressao = "LZ4"
            criptografia = "AES-256"
        }
    }
    
    # Adiciona mensagem de erro se status for falha
    if ($Status -eq "falha") {
        $backupData.mensagem_erro = "Erro simulado: Falha ao conectar com o destino de backup"
        $backupData.detalhes.codigo_erro = "ERR_CONNECTION_REFUSED"
    }
    elseif ($Status -eq "alerta") {
        $backupData.mensagem_erro = "Aviso: Alguns arquivos foram ignorados por estarem em uso"
    }
    
    Write-Info "Dados do backup:"
    Write-Host "   Servidor: $($backupData.servidor)" -ForegroundColor Gray
    Write-Host "   Rotina: $($backupData.rotina)" -ForegroundColor Gray
    Write-Host "   Status: $($backupData.status)" -ForegroundColor Gray
    Write-Host "   Tamanho: $([math]::Round($backupData.tamanho_bytes / 1GB, 2)) GB" -ForegroundColor Gray
    Write-Host "   Início: $($backupData.data_inicio)" -ForegroundColor Gray
    Write-Host "   Fim: $($backupData.data_fim)" -ForegroundColor Gray
    
    $result = Invoke-ApiRequest -Endpoint "/api/backup" -Method "POST" -Body $backupData -Headers $headers
    
    if ($result.Success -and $result.Content.success) {
        Write-Success "Backup registrado com sucesso!"
        Write-Host "   Execução ID: $($result.Content.execucao_id)" -ForegroundColor Gray
        Write-Host "   Mensagem: $($result.Content.message)" -ForegroundColor Gray
        return $true
    }
    else {
        Write-Fail "Falha ao registrar backup"
        if ($result.Content.error) {
            Write-Host "   Erro: $($result.Content.error)" -ForegroundColor Red
        }
        if ($result.Content.errors) {
            Write-Host "   Erros de validação:" -ForegroundColor Red
            $result.Content.errors.PSObject.Properties | ForEach-Object {
                Write-Host "     - $($_.Name): $($_.Value)" -ForegroundColor Red
            }
        }
        Write-Host "   Status Code: $($result.StatusCode)" -ForegroundColor Red
        return $false
    }
}

# ============================================================
# TESTE 4: Múltiplos backups (simula vários servidores)
# ============================================================
function Test-MultipleBackups {
    Write-Header "TESTE 4: Múltiplos Backups"
    Write-Info "Simulando backups de vários servidores..."
    
    $servidores = @(
        @{ Nome = "SRV-DC01"; Rotina = "Backup Active Directory"; Status = "sucesso" },
        @{ Nome = "SRV-SQL01"; Rotina = "Backup SQL Server - Full"; Status = "sucesso" },
        @{ Nome = "SRV-FILE01"; Rotina = "Backup Arquivos Compartilhados"; Status = "alerta" },
        @{ Nome = "SRV-WEB01"; Rotina = "Backup IIS e Aplicações"; Status = "sucesso" },
        @{ Nome = "SRV-MAIL01"; Rotina = "Backup Exchange"; Status = "falha" }
    )
    
    $successCount = 0
    $failCount = 0
    
    foreach ($srv in $servidores) {
        Write-Host "`n--- Servidor: $($srv.Nome) ---" -ForegroundColor Cyan
        
        $result = Test-BackupSubmission -Status $srv.Status -Servidor $srv.Nome -Rotina $srv.Rotina
        
        if ($result) {
            $successCount++
        }
        else {
            $failCount++
        }
        
        # Pequena pausa entre requisições
        Start-Sleep -Milliseconds 500
    }
    
    Write-Host "`n--- Resumo ---" -ForegroundColor Yellow
    Write-Host "   Sucesso: $successCount" -ForegroundColor Green
    Write-Host "   Falha: $failCount" -ForegroundColor Red
    
    return ($failCount -eq 0)
}

# ============================================================
# EXECUÇÃO PRINCIPAL
# ============================================================

Write-Host @"

╔══════════════════════════════════════════════════════════════╗
║           BACKUP WEBMANAGER - TESTE DE API                  ║
║                                                              ║
║  Este script testa os endpoints da API de backup            ║
╚══════════════════════════════════════════════════════════════╝

"@ -ForegroundColor Magenta

Write-Host "Configurações:" -ForegroundColor White
Write-Host "  URL da API: $ApiUrl" -ForegroundColor Gray
Write-Host "  API Key: $(if ($ApiKey) { $ApiKey.Substring(0, [Math]::Min(8, $ApiKey.Length)) + '...' } else { '(não fornecida)' })" -ForegroundColor Gray
Write-Host "  Modo de teste: $TestMode" -ForegroundColor Gray

$allPassed = $true

switch ($TestMode) {
    'status' {
        $allPassed = Test-ApiStatus
    }
    'auth' {
        $allPassed = Test-ApiAuth
    }
    'backup' {
        $allPassed = Test-BackupSubmission
    }
    'multiple' {
        $allPassed = Test-MultipleBackups
    }
    'all' {
        $test1 = Test-ApiStatus
        
        if ($ApiKey) {
            $test2 = Test-ApiAuth
            $test3 = Test-BackupSubmission -Status "sucesso"
            $test4 = Test-BackupSubmission -Status "falha" -Servidor "SRV-FALHA-01" -Rotina "Backup com Erro"
            $test5 = Test-BackupSubmission -Status "alerta" -Servidor "SRV-ALERTA-01" -Rotina "Backup com Aviso"
            
            $allPassed = $test1 -and $test2 -and $test3 -and $test4 -and $test5
        }
        else {
            Write-Host "`n⚠ API Key não fornecida. Apenas o teste de status foi executado." -ForegroundColor Yellow
            Write-Host "  Para executar todos os testes, use: -ApiKey 'sua-api-key'" -ForegroundColor Yellow
            $allPassed = $test1
        }
    }
}

# Resumo final
Write-Host "`n" -NoNewline
Write-Header "RESULTADO FINAL"

if ($allPassed) {
    Write-Success "Todos os testes passaram!"
    exit 0
}
else {
    Write-Fail "Alguns testes falharam. Verifique os erros acima."
    exit 1
}
