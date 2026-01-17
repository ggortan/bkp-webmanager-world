<#
.SYNOPSIS
    Script de instalação e configuração do Agente de Backup
    
.DESCRIPTION
    Instala e configura o agente de coleta de dados de backup,
    incluindo a criação de tarefa agendada no Windows.
    
.PARAMETER InstallPath
    Caminho onde o agente será instalado
    
.PARAMETER ApiUrl
    URL da API do Backup WebManager
    
.PARAMETER ApiKey
    Chave de API para autenticação
    
.PARAMETER ServerName
    Nome do servidor (identificador)
    
.PARAMETER CheckIntervalMinutes
    Intervalo em minutos para verificação de backups
    
.PARAMETER EnableVeeam
    Habilita coleta de dados do Veeam
    
.PARAMETER VeeamServer
    Servidor Veeam (se diferente de localhost)
    
.EXAMPLE
    .\Install-BackupAgent.ps1 -ApiUrl "https://dev.gortan.com.br/world/bkpmng" -ApiKey "sua-api-key" -ServerName "SRV-PROD-01"
    
.EXAMPLE
    .\Install-BackupAgent.ps1 -ApiUrl "https://api.exemplo.com" -ApiKey "key123" -ServerName "SRV-DB-01" -EnableVeeam
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory = $false)]
    [string]$InstallPath = "C:\BackupAgent",
    
    [Parameter(Mandatory = $true)]
    [string]$ApiUrl,
    
    [Parameter(Mandatory = $true)]
    [string]$ApiKey,
    
    [Parameter(Mandatory = $false)]
    [string]$ServerName = $env:COMPUTERNAME,
    
    [Parameter(Mandatory = $false)]
    [int]$CheckIntervalMinutes = 60,
    
    [Parameter(Mandatory = $false)]
    [switch]$EnableVeeam,
    
    [Parameter(Mandatory = $false)]
    [string]$VeeamServer = "localhost",
    
    [Parameter(Mandatory = $false)]
    [switch]$Uninstall
)

#Requires -Version 5.1
#Requires -RunAsAdministrator

$ErrorActionPreference = "Stop"

# ============================================================
# FUNÇÕES AUXILIARES
# ============================================================

function Write-Status {
    param(
        [string]$Message,
        [string]$Type = "INFO"
    )
    
    $color = switch ($Type) {
        "SUCCESS" { "Green" }
        "ERROR" { "Red" }
        "WARNING" { "Yellow" }
        default { "Cyan" }
    }
    
    $prefix = switch ($Type) {
        "SUCCESS" { "✓" }
        "ERROR" { "✗" }
        "WARNING" { "⚠" }
        default { "→" }
    }
    
    Write-Host "$prefix $Message" -ForegroundColor $color
}

function Test-Prerequisites {
    <#
    .SYNOPSIS
        Verifica pré-requisitos para instalação
    #>
    
    Write-Status "Verificando pré-requisitos..." -Type INFO
    
    # Verifica PowerShell 5.1+
    if ($PSVersionTable.PSVersion.Major -lt 5) {
        Write-Status "PowerShell 5.1 ou superior é necessário" -Type ERROR
        return $false
    }
    
    # Verifica se está executando como administrador
    $isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
    
    if (-not $isAdmin) {
        Write-Status "Este script precisa ser executado como Administrador" -Type ERROR
        return $false
    }
    
    Write-Status "Pré-requisitos verificados" -Type SUCCESS
    return $true
}

# ============================================================
# DESINSTALAÇÃO
# ============================================================

function Uninstall-Agent {
    Write-Status "Iniciando desinstalação do agente..." -Type INFO
    
    # Remove tarefa agendada
    $taskName = "BackupWebManager-Agent"
    if (Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue) {
        Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
        Write-Status "Tarefa agendada removida" -Type SUCCESS
    }
    
    # Remove arquivos
    if (Test-Path $InstallPath) {
        $confirm = Read-Host "Deseja remover os arquivos em '$InstallPath'? (S/N)"
        if ($confirm -eq "S") {
            Remove-Item -Path $InstallPath -Recurse -Force
            Write-Status "Arquivos removidos" -Type SUCCESS
        }
    }
    
    Write-Status "Desinstalação concluída" -Type SUCCESS
}

# ============================================================
# INSTALAÇÃO
# ============================================================

function Install-Agent {
    
    Write-Host @"

╔══════════════════════════════════════════════════════════════╗
║     INSTALAÇÃO DO AGENTE DE BACKUP - BACKUP WEBMANAGER      ║
╚══════════════════════════════════════════════════════════════╝

"@ -ForegroundColor Magenta
    
    # Cria estrutura de diretórios
    Write-Status "Criando estrutura de diretórios..." -Type INFO
    
    $directories = @(
        $InstallPath,
        "$InstallPath\config",
        "$InstallPath\modules",
        "$InstallPath\logs"
    )
    
    foreach ($dir in $directories) {
        if (-not (Test-Path $dir)) {
            New-Item -ItemType Directory -Path $dir -Force | Out-Null
        }
    }
    
    Write-Status "Diretórios criados em: $InstallPath" -Type SUCCESS
    
    # Copia arquivos do agente
    Write-Status "Copiando arquivos do agente..." -Type INFO
    
    $sourceDir = $PSScriptRoot
    
    # Copia o script principal
    if (Test-Path "$sourceDir\BackupAgent.ps1") {
        Copy-Item "$sourceDir\BackupAgent.ps1" -Destination $InstallPath -Force
    }
    
    # Copia os módulos
    if (Test-Path "$sourceDir\modules") {
        Copy-Item "$sourceDir\modules\*" -Destination "$InstallPath\modules" -Force -Recurse
    }
    
    Write-Status "Arquivos copiados" -Type SUCCESS
    
    # Cria arquivo de configuração
    Write-Status "Criando arquivo de configuração..." -Type INFO
    
    $config = @{
        agent = @{
            version = "1.0.0"
            server_name = $ServerName
            check_interval_minutes = $CheckIntervalMinutes
            log_level = "INFO"
            log_retention_days = 30
        }
        api = @{
            url = $ApiUrl.TrimEnd('/')
            api_key = $ApiKey
            timeout_seconds = 30
            retry_attempts = 3
            retry_delay_seconds = 5
        }
        collectors = @{
            windows_server_backup = @{
                enabled = $true
                check_event_log = $true
                event_log_hours = 24
            }
            veeam_backup = @{
                enabled = $EnableVeeam.IsPresent
                veeam_ps_snapin = "VeeamPSSnapin"
                server = $VeeamServer
                port = 9392
            }
            custom_scripts = @{
                enabled = $false
                scripts_path = "$InstallPath\custom"
            }
        }
        filters = @{
            ignore_jobs = @()
            only_jobs = @()
            min_size_mb = 0
        }
        notifications = @{
            send_on_failure = $true
            send_on_warning = $true
            send_on_success = $true
        }
    }
    
    $configPath = "$InstallPath\config\config.json"
    $config | ConvertTo-Json -Depth 10 | Set-Content -Path $configPath -Encoding UTF8
    
    Write-Status "Configuração salva em: $configPath" -Type SUCCESS
    
    # Testa o agente
    Write-Status "Testando conexão com a API..." -Type INFO
    
    try {
        $testUrl = "$($ApiUrl.TrimEnd('/'))/api/status"
        $response = Invoke-RestMethod -Uri $testUrl -Method Get -TimeoutSec 10 -UseBasicParsing
        
        if ($response.status -eq "online") {
            Write-Status "API está acessível - Versão: $($response.version)" -Type SUCCESS
        }
        else {
            Write-Status "API retornou status inesperado" -Type WARNING
        }
    }
    catch {
        Write-Status "AVISO: Não foi possível conectar à API: $($_.Exception.Message)" -Type WARNING
        Write-Status "Verifique a URL e a conectividade de rede" -Type WARNING
    }
    
    # Cria tarefa agendada
    Write-Status "Criando tarefa agendada..." -Type INFO
    
    $taskName = "BackupWebManager-Agent"
    
    # Remove tarefa existente se houver
    if (Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue) {
        Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
    }
    
    # Define a ação
    $action = New-ScheduledTaskAction -Execute "PowerShell.exe" -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$InstallPath\BackupAgent.ps1`" -ConfigPath `"$configPath`""
    
    # Define o gatilho (a cada X minutos)
    $trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes $CheckIntervalMinutes) -RepetitionDuration ([TimeSpan]::MaxValue)
    
    # Define configurações
    $settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -RunOnlyIfNetworkAvailable -MultipleInstances IgnoreNew
    
    # Define o principal (executar como SYSTEM)
    $principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
    
    # Registra a tarefa
    Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Description "Agente de coleta de dados de backup para Backup WebManager" | Out-Null
    
    Write-Status "Tarefa agendada criada: $taskName" -Type SUCCESS
    
    # Executa teste inicial
    Write-Status "Executando teste inicial do agente..." -Type INFO
    
    try {
        & "$InstallPath\BackupAgent.ps1" -ConfigPath $configPath -RunOnce -TestMode
        Write-Status "Teste inicial concluído com sucesso" -Type SUCCESS
    }
    catch {
        Write-Status "Erro durante teste inicial: $($_.Exception.Message)" -Type WARNING
        Write-Status "Verifique os logs em: $InstallPath\logs" -Type WARNING
    }
    
    # Resumo da instalação
    Write-Host "`n" -NoNewline
    Write-Host "╔══════════════════════════════════════════════════════════════╗" -ForegroundColor Green
    Write-Host "║              INSTALAÇÃO CONCLUÍDA COM SUCESSO                ║" -ForegroundColor Green
    Write-Host "╚══════════════════════════════════════════════════════════════╝" -ForegroundColor Green
    Write-Host ""
    Write-Host "Configurações:" -ForegroundColor White
    Write-Host "  Caminho de instalação: $InstallPath" -ForegroundColor Gray
    Write-Host "  Servidor: $ServerName" -ForegroundColor Gray
    Write-Host "  URL da API: $ApiUrl" -ForegroundColor Gray
    Write-Host "  Intervalo de verificação: $CheckIntervalMinutes minutos" -ForegroundColor Gray
    Write-Host "  Veeam habilitado: $($EnableVeeam.IsPresent)" -ForegroundColor Gray
    Write-Host ""
    Write-Host "Próximos passos:" -ForegroundColor White
    Write-Host "  1. Verifique o arquivo de configuração: $configPath" -ForegroundColor Gray
    Write-Host "  2. Ajuste filtros e notificações conforme necessário" -ForegroundColor Gray
    Write-Host "  3. Monitore os logs em: $InstallPath\logs" -ForegroundColor Gray
    Write-Host "  4. A tarefa agendada '$taskName' executará automaticamente" -ForegroundColor Gray
    Write-Host ""
    Write-Host "Comandos úteis:" -ForegroundColor White
    Write-Host "  Executar manualmente: & '$InstallPath\BackupAgent.ps1' -RunOnce" -ForegroundColor Gray
    Write-Host "  Testar sem enviar: & '$InstallPath\BackupAgent.ps1' -RunOnce -TestMode" -ForegroundColor Gray
    Write-Host "  Ver tarefa: Get-ScheduledTask -TaskName '$taskName'" -ForegroundColor Gray
    Write-Host "  Desinstalar: & '$PSCommandPath' -Uninstall" -ForegroundColor Gray
    Write-Host ""
}

# ============================================================
# EXECUÇÃO PRINCIPAL
# ============================================================

try {
    if ($Uninstall) {
        Uninstall-Agent
        exit 0
    }
    
    if (-not (Test-Prerequisites)) {
        exit 1
    }
    
    Install-Agent
}
catch {
    Write-Status "Erro durante instalação: $_" -Type ERROR
    Write-Status $_.ScriptStackTrace -Type ERROR
    exit 1
}
