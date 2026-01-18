<#
.SYNOPSIS
    Script para envio de resultados de backup para o Backup WebManager.

.DESCRIPTION
    Este script envia informações de execução de backup para a API do Backup WebManager.
    Usa o formato baseado em routine_key para identificação da rotina.

.NOTES
    Backup WebManager - World Informática
    Versão: 2.0.0
    
    CONFIGURAÇÃO:
    1. Preencha as variáveis de configuração abaixo
    2. Configure no Agendador de Tarefas para executar após cada backup
    3. Pode ser chamado diretamente com parâmetros

.EXAMPLE
    .\Send-BackupReport.ps1 -RoutineKey "rtk_abc123xyz" -Status "sucesso" -Destino "\\Server\Backups"
    
.EXAMPLE
    .\Send-BackupReport.ps1 -RoutineKey "rtk_abc123xyz" -Status "falha" -MensagemErro "Disco cheio"
#>

param (
    [Parameter(Mandatory=$true)]
    [string]$RoutineKey,
    
    [Parameter(Mandatory=$true)]
    [ValidateSet("sucesso", "falha", "alerta", "executando")]
    [string]$Status,
    
    [Parameter(Mandatory=$false)]
    [string]$Destino = "",
    
    [Parameter(Mandatory=$false)]
    [string]$MensagemErro = "",
    
    [Parameter(Mandatory=$false)]
    [DateTime]$DataInicio = (Get-Date).AddMinutes(-30),
    
    [Parameter(Mandatory=$false)]
    [DateTime]$DataFim = (Get-Date),
    
    [Parameter(Mandatory=$false)]
    [long]$TamanhoBytes = 0,
    
    [Parameter(Mandatory=$false)]
    [string]$TipoBackup = "full"
)

# ============================================
# CONFIGURAÇÃO - PREENCHA ESTAS VARIÁVEIS
# ============================================

# URL da API do Backup WebManager
$ApiUrl = "https://seu-servidor.com/api/backup"

# API Key do cliente (obtida no painel do Backup WebManager)
$ApiKey = "SUA_API_KEY_AQUI"

# Caminho para o arquivo de log local
$LogPath = "C:\Logs\BackupWebManager"

# ============================================
# NÃO MODIFIQUE ABAIXO DESTA LINHA
# ============================================

# Função para escrever log
function Write-Log {
    param (
        [string]$Message,
        [string]$Level = "INFO"
    )
    
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logMessage = "[$timestamp] [$Level] $Message"
    
    # Cria diretório de log se não existir
    if (!(Test-Path $LogPath)) {
        New-Item -ItemType Directory -Path $LogPath -Force | Out-Null
    }
    
    $logFile = Join-Path $LogPath "backup_report_$(Get-Date -Format 'yyyy-MM-dd').log"
    Add-Content -Path $logFile -Value $logMessage
    
    # Escreve no console também
    switch ($Level) {
        "ERROR" { Write-Host $logMessage -ForegroundColor Red }
        "WARNING" { Write-Host $logMessage -ForegroundColor Yellow }
        "SUCCESS" { Write-Host $logMessage -ForegroundColor Green }
        default { Write-Host $logMessage }
    }
}

# Função para calcular tamanho do backup automaticamente
function Get-BackupSize {
    param (
        [string]$Path
    )
    
    if ([string]::IsNullOrEmpty($Path) -or !(Test-Path $Path -ErrorAction SilentlyContinue)) {
        return 0
    }
    
    try {
        $size = (Get-ChildItem $Path -Recurse -ErrorAction SilentlyContinue | Measure-Object -Property Length -Sum).Sum
        return [long]$size
    }
    catch {
        Write-Log "Não foi possível calcular tamanho do backup: $_" "WARNING"
        return 0
    }
}

# Função para obter informações do sistema
function Get-SystemInfo {
    try {
        $os = Get-CimInstance Win32_OperatingSystem
        $ip = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.InterfaceAlias -notlike "*Loopback*" } | Select-Object -First 1).IPAddress
        
        return @{
            nome = $env:COMPUTERNAME
            hostname = [System.Net.Dns]::GetHostName()
            ip = $ip
            sistema_operacional = "$($os.Caption) $($os.Version)"
        }
    }
    catch {
        return @{
            nome = $env:COMPUTERNAME
            hostname = $env:COMPUTERNAME
            ip = ""
            sistema_operacional = "Windows"
        }
    }
}

# Função principal para enviar relatório
function Send-BackupReport {
    Write-Log "Iniciando envio de relatório de backup..."
    Write-Log "RoutineKey: $RoutineKey | Status: $Status"
    
    # Calcula tamanho se não foi fornecido e o destino existe
    if ($TamanhoBytes -eq 0 -and ![string]::IsNullOrEmpty($Destino)) {
        $TamanhoBytes = Get-BackupSize -Path $Destino
        if ($TamanhoBytes -gt 0) {
            Write-Log "Tamanho calculado: $([math]::Round($TamanhoBytes / 1MB, 2)) MB"
        }
    }
    
    # Obtém informações do sistema
    $hostInfo = Get-SystemInfo
    
    # Monta o payload no novo formato
    $payload = @{
        routine_key = $RoutineKey
        data_inicio = $DataInicio.ToString("yyyy-MM-dd HH:mm:ss")
        data_fim = $DataFim.ToString("yyyy-MM-dd HH:mm:ss")
        status = $Status
        destino = $Destino
        tamanho_bytes = $TamanhoBytes
        mensagem_erro = $MensagemErro
        host_info = $hostInfo
        detalhes = @{
            tipo_backup = $TipoBackup
            script_version = "2.0.0"
            execution_date = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
        }
    } | ConvertTo-Json -Depth 5
    
    Write-Log "Payload montado" "DEBUG"
    
    # Configura os headers
    $headers = @{
        "Content-Type" = "application/json; charset=utf-8"
        "Accept" = "application/json"
        "X-API-Key" = $ApiKey
    }
    
    # Tentativas de envio
    $maxRetries = 3
    $retryCount = 0
    $success = $false
    
    while (!$success -and $retryCount -lt $maxRetries) {
        try {
            $retryCount++
            Write-Log "Tentativa $retryCount de $maxRetries..."
            
            # Configura TLS 1.2 (requerido para comunicação segura)
            [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
            
            $response = Invoke-RestMethod -Uri $ApiUrl -Method Post -Headers $headers -Body $payload -TimeoutSec 30
            
            if ($response.success -eq $true) {
                Write-Log "Relatório enviado com sucesso! ID: $($response.execucao_id)" "SUCCESS"
                $success = $true
            }
            else {
                throw "API retornou erro: $($response.error)"
            }
        }
        catch {
            $errorMessage = $_.Exception.Message
            Write-Log "Erro ao enviar relatório: $errorMessage" "ERROR"
            
            if ($retryCount -lt $maxRetries) {
                Write-Log "Aguardando 10 segundos antes de tentar novamente..."
                Start-Sleep -Seconds 10
            }
        }
    }
    
    if (!$success) {
        Write-Log "FALHA: Não foi possível enviar o relatório após $maxRetries tentativas" "ERROR"
        
        # Salva o payload localmente para reenvio posterior
        $failedFile = Join-Path $LogPath "failed_$(Get-Date -Format 'yyyyMMdd_HHmmss').json"
        $payload | Out-File -FilePath $failedFile -Encoding UTF8
        Write-Log "Payload salvo em: $failedFile" "WARNING"
        
        return $false
    }
    
    return $true
}

# Executa o envio
try {
    $result = Send-BackupReport
    
    if ($result) {
        Write-Log "Script finalizado com sucesso"
        exit 0
    }
    else {
        Write-Log "Script finalizado com falha"
        exit 1
    }
}
catch {
    Write-Log "Erro fatal: $_" "ERROR"
    exit 1
}
