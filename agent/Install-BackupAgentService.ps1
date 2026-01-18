<#
.SYNOPSIS
    Instalador do Agente de Backup como Serviço Windows

.DESCRIPTION
    Este script instala, remove ou gerencia o Agente de Backup como um serviço Windows.
    Usa NSSM (Non-Sucking Service Manager) para gerenciar o serviço.

.PARAMETER Action
    Ação a executar: install, uninstall, start, stop, restart, status

.PARAMETER ServiceName
    Nome do serviço (padrão: BackupManagerAgent)

.PARAMETER ConfigPath
    Caminho para o arquivo de configuração (padrão: .\config\config.json)

.PARAMETER DownloadNssm
    Se especificado, faz download do NSSM automaticamente

.EXAMPLE
    .\Install-BackupAgentService.ps1 -Action install -DownloadNssm

.EXAMPLE
    .\Install-BackupAgentService.ps1 -Action status

.EXAMPLE
    .\Install-BackupAgentService.ps1 -Action uninstall

.NOTES
    Versão: 1.0.0
    Requer: PowerShell 5.1+, Privilégios de Administrador
#>

#Requires -RunAsAdministrator

param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('install', 'uninstall', 'start', 'stop', 'restart', 'status', 'configure')]
    [string]$Action,

    [Parameter(Mandatory = $false)]
    [string]$ServiceName = "BackupManagerAgent",

    [Parameter(Mandatory = $false)]
    [string]$ConfigPath,

    [switch]$DownloadNssm
)

$ErrorActionPreference = "Stop"

# Paths
$ScriptRoot = $PSScriptRoot
$NssmPath = Join-Path $ScriptRoot "nssm.exe"
$ServiceScript = Join-Path $ScriptRoot "BackupAgentService.ps1"
$DefaultConfigPath = Join-Path $ScriptRoot "config\config.json"

if (-not $ConfigPath) {
    $ConfigPath = $DefaultConfigPath
}

# Cores
function Write-Success { param($Message) Write-Host "[OK] $Message" -ForegroundColor Green }
function Write-Fail { param($Message) Write-Host "[ERRO] $Message" -ForegroundColor Red }
function Write-Info { param($Message) Write-Host "[INFO] $Message" -ForegroundColor Cyan }
function Write-Warn { param($Message) Write-Host "[AVISO] $Message" -ForegroundColor Yellow }

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Instalador do Agente de Backup" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# ============================================
# FUNÇÕES AUXILIARES
# ============================================

function Test-NssmInstalled {
    if (Test-Path $NssmPath) {
        return $true
    }
    
    # Verifica no PATH
    $nssmInPath = Get-Command nssm -ErrorAction SilentlyContinue
    if ($nssmInPath) {
        $script:NssmPath = $nssmInPath.Source
        return $true
    }
    
    return $false
}

function Install-Nssm {
    Write-Info "Baixando NSSM..."
    
    $nssmUrl = "https://nssm.cc/release/nssm-2.24.zip"
    $zipPath = Join-Path $env:TEMP "nssm.zip"
    $extractPath = Join-Path $env:TEMP "nssm-extract"
    
    try {
        # Download
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        Invoke-WebRequest -Uri $nssmUrl -OutFile $zipPath -UseBasicParsing
        
        # Extrai
        if (Test-Path $extractPath) {
            Remove-Item $extractPath -Recurse -Force
        }
        Expand-Archive -Path $zipPath -DestinationPath $extractPath -Force
        
        # Copia o executável correto (32 ou 64 bits)
        $arch = if ([Environment]::Is64BitOperatingSystem) { "win64" } else { "win32" }
        $nssmExe = Get-ChildItem -Path $extractPath -Recurse -Filter "nssm.exe" | 
            Where-Object { $_.DirectoryName -like "*$arch*" } | 
            Select-Object -First 1
        
        if ($nssmExe) {
            Copy-Item $nssmExe.FullName -Destination $NssmPath -Force
            Write-Success "NSSM instalado em: $NssmPath"
        }
        else {
            # Fallback - pega qualquer nssm.exe
            $nssmExe = Get-ChildItem -Path $extractPath -Recurse -Filter "nssm.exe" | Select-Object -First 1
            if ($nssmExe) {
                Copy-Item $nssmExe.FullName -Destination $NssmPath -Force
                Write-Success "NSSM instalado em: $NssmPath"
            }
            else {
                throw "Não foi possível encontrar nssm.exe no arquivo baixado"
            }
        }
        
        # Cleanup
        Remove-Item $zipPath -Force -ErrorAction SilentlyContinue
        Remove-Item $extractPath -Recurse -Force -ErrorAction SilentlyContinue
        
        return $true
    }
    catch {
        Write-Fail "Erro ao baixar NSSM: $_"
        Write-Info "Baixe manualmente de: https://nssm.cc/download"
        return $false
    }
}

function Get-ServiceStatus {
    $service = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
    
    if (-not $service) {
        return @{
            Installed = $false
            Status = "Não instalado"
            StartType = $null
        }
    }
    
    return @{
        Installed = $true
        Status = $service.Status.ToString()
        StartType = $service.StartType.ToString()
        DisplayName = $service.DisplayName
    }
}

# ============================================
# AÇÕES
# ============================================

function Install-Service {
    Write-Info "Instalando serviço: $ServiceName"
    
    # Verifica se já existe
    $status = Get-ServiceStatus
    if ($status.Installed) {
        Write-Warn "Serviço já instalado. Status: $($status.Status)"
        Write-Info "Use '-Action uninstall' primeiro para reinstalar"
        return
    }
    
    # Verifica NSSM
    if (-not (Test-NssmInstalled)) {
        if ($DownloadNssm) {
            if (-not (Install-Nssm)) {
                Write-Fail "Não foi possível instalar o NSSM"
                return
            }
        }
        else {
            Write-Fail "NSSM não encontrado"
            Write-Info "Use o parâmetro -DownloadNssm para baixar automaticamente"
            Write-Info "Ou baixe manualmente de: https://nssm.cc/download"
            return
        }
    }
    
    # Verifica script do serviço
    if (-not (Test-Path $ServiceScript)) {
        Write-Fail "Script do serviço não encontrado: $ServiceScript"
        return
    }
    
    # Verifica/cria configuração
    if (-not (Test-Path $ConfigPath)) {
        Write-Warn "Arquivo de configuração não encontrado: $ConfigPath"
        
        $configDir = Split-Path $ConfigPath -Parent
        if (-not (Test-Path $configDir)) {
            New-Item -ItemType Directory -Path $configDir -Force | Out-Null
        }
        
        # Cria configuração de exemplo
        $exampleConfig = @{
            api_url = "https://backup.seudominio.com"
            api_token = "SEU_TOKEN_AQUI"
            host_name = $env:COMPUTERNAME
            telemetry = @{
                enabled = $true
                interval_minutes = 5
            }
            backup = @{
                check_interval_minutes = 15
                collectors = @("wsb", "veeam")
            }
            routines = @(
                @{
                    routine_key = "rtk_EXEMPLO"
                    source = "wsb"
                    job_name = ""
                }
            )
        }
        
        $exampleConfig | ConvertTo-Json -Depth 5 | Set-Content $ConfigPath -Force
        
        Write-Warn "Arquivo de configuração criado: $ConfigPath"
        Write-Warn "EDITE O ARQUIVO antes de iniciar o serviço!"
    }
    
    # Instala serviço via NSSM
    Write-Info "Instalando via NSSM..."
    
    $powershellPath = (Get-Command powershell).Source
    $arguments = "-ExecutionPolicy Bypass -NoProfile -File `"$ServiceScript`" -ConfigPath `"$ConfigPath`""
    
    # Instala
    & $NssmPath install $ServiceName $powershellPath $arguments
    
    if ($LASTEXITCODE -ne 0) {
        Write-Fail "Erro ao instalar serviço"
        return
    }
    
    # Configura serviço
    & $NssmPath set $ServiceName DisplayName "Backup Manager Agent"
    & $NssmPath set $ServiceName Description "Agente de monitoramento de backup - Envia telemetria e status de backups"
    & $NssmPath set $ServiceName Start SERVICE_AUTO_START
    & $NssmPath set $ServiceName AppStdout "$ScriptRoot\logs\service-stdout.log"
    & $NssmPath set $ServiceName AppStderr "$ScriptRoot\logs\service-stderr.log"
    & $NssmPath set $ServiceName AppRotateFiles 1
    & $NssmPath set $ServiceName AppRotateBytes 10485760  # 10 MB
    & $NssmPath set $ServiceName AppDirectory $ScriptRoot
    
    # Cria pasta de logs
    $logsPath = Join-Path $ScriptRoot "logs"
    if (-not (Test-Path $logsPath)) {
        New-Item -ItemType Directory -Path $logsPath -Force | Out-Null
    }
    
    Write-Success "Serviço instalado com sucesso!"
    Write-Info ""
    Write-Info "Próximos passos:"
    Write-Info "  1. Edite o arquivo de configuração: $ConfigPath"
    Write-Info "  2. Inicie o serviço: .\Install-BackupAgentService.ps1 -Action start"
    Write-Info "  3. Verifique o status: .\Install-BackupAgentService.ps1 -Action status"
    Write-Info ""
    Write-Info "Logs em: $logsPath"
}

function Uninstall-Service {
    Write-Info "Removendo serviço: $ServiceName"
    
    $status = Get-ServiceStatus
    if (-not $status.Installed) {
        Write-Warn "Serviço não está instalado"
        return
    }
    
    # Para o serviço se estiver rodando
    if ($status.Status -eq "Running") {
        Write-Info "Parando serviço..."
        Stop-Service -Name $ServiceName -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2
    }
    
    # Remove via NSSM ou sc.exe
    if (Test-NssmInstalled) {
        & $NssmPath remove $ServiceName confirm
    }
    else {
        & sc.exe delete $ServiceName
    }
    
    if ($LASTEXITCODE -eq 0) {
        Write-Success "Serviço removido com sucesso!"
    }
    else {
        Write-Fail "Erro ao remover serviço"
    }
}

function Start-ServiceAction {
    $status = Get-ServiceStatus
    
    if (-not $status.Installed) {
        Write-Fail "Serviço não está instalado"
        return
    }
    
    if ($status.Status -eq "Running") {
        Write-Warn "Serviço já está em execução"
        return
    }
    
    Write-Info "Iniciando serviço..."
    Start-Service -Name $ServiceName
    Start-Sleep -Seconds 2
    
    $newStatus = Get-ServiceStatus
    if ($newStatus.Status -eq "Running") {
        Write-Success "Serviço iniciado com sucesso!"
    }
    else {
        Write-Fail "Falha ao iniciar serviço. Status: $($newStatus.Status)"
        Write-Info "Verifique os logs em: $ScriptRoot\logs"
    }
}

function Stop-ServiceAction {
    $status = Get-ServiceStatus
    
    if (-not $status.Installed) {
        Write-Fail "Serviço não está instalado"
        return
    }
    
    if ($status.Status -ne "Running") {
        Write-Warn "Serviço não está em execução. Status: $($status.Status)"
        return
    }
    
    Write-Info "Parando serviço..."
    Stop-Service -Name $ServiceName -Force
    Start-Sleep -Seconds 2
    
    $newStatus = Get-ServiceStatus
    if ($newStatus.Status -eq "Stopped") {
        Write-Success "Serviço parado com sucesso!"
    }
    else {
        Write-Fail "Falha ao parar serviço. Status: $($newStatus.Status)"
    }
}

function Restart-ServiceAction {
    $status = Get-ServiceStatus
    
    if (-not $status.Installed) {
        Write-Fail "Serviço não está instalado"
        return
    }
    
    Write-Info "Reiniciando serviço..."
    
    if ($status.Status -eq "Running") {
        Stop-Service -Name $ServiceName -Force
        Start-Sleep -Seconds 2
    }
    
    Start-Service -Name $ServiceName
    Start-Sleep -Seconds 2
    
    $newStatus = Get-ServiceStatus
    if ($newStatus.Status -eq "Running") {
        Write-Success "Serviço reiniciado com sucesso!"
    }
    else {
        Write-Fail "Falha ao reiniciar serviço. Status: $($newStatus.Status)"
    }
}

function Show-Status {
    $status = Get-ServiceStatus
    
    Write-Host ""
    Write-Host "Status do Serviço: $ServiceName" -ForegroundColor White
    Write-Host "================================" -ForegroundColor White
    
    if (-not $status.Installed) {
        Write-Warn "Serviço não instalado"
    }
    else {
        $statusColor = switch ($status.Status) {
            "Running" { "Green" }
            "Stopped" { "Red" }
            default { "Yellow" }
        }
        
        Write-Host "  Status:      " -NoNewline
        Write-Host $status.Status -ForegroundColor $statusColor
        Write-Host "  Tipo Início: $($status.StartType)"
        Write-Host "  Nome:        $($status.DisplayName)"
    }
    
    # Mostra configuração
    Write-Host ""
    Write-Host "Configuração:" -ForegroundColor White
    Write-Host "  Arquivo: $ConfigPath"
    
    if (Test-Path $ConfigPath) {
        Write-Host "  Status:  " -NoNewline
        Write-Host "OK" -ForegroundColor Green
        
        try {
            $config = Get-Content $ConfigPath -Raw | ConvertFrom-Json
            Write-Host ""
            Write-Host "  API URL:    $($config.api_url)"
            Write-Host "  Host:       $($config.host_name)"
            Write-Host "  Telemetria: $(if ($config.telemetry.enabled) { 'Habilitada' } else { 'Desabilitada' })"
            
            if ($config.routines) {
                Write-Host "  Rotinas:    $($config.routines.Count)"
            }
        }
        catch {
            Write-Warn "  Erro ao ler configuração"
        }
    }
    else {
        Write-Host "  Status:  " -NoNewline
        Write-Warn "Não encontrado"
    }
    
    # Mostra logs recentes
    $logFile = Join-Path $ScriptRoot "logs\agent-$(Get-Date -Format 'yyyy-MM-dd').log"
    if (Test-Path $logFile) {
        Write-Host ""
        Write-Host "Últimas entradas do log:" -ForegroundColor White
        Get-Content $logFile -Tail 5 | ForEach-Object { Write-Host "  $_" -ForegroundColor Gray }
    }
    
    Write-Host ""
}

function Show-Configure {
    Write-Info "Abrindo configuração: $ConfigPath"
    
    if (-not (Test-Path $ConfigPath)) {
        Write-Warn "Arquivo não existe. Criando..."
        
        $configDir = Split-Path $ConfigPath -Parent
        if (-not (Test-Path $configDir)) {
            New-Item -ItemType Directory -Path $configDir -Force | Out-Null
        }
        
        $exampleConfig = @{
            api_url = "https://backup.seudominio.com"
            api_token = "SEU_TOKEN_AQUI"
            host_name = $env:COMPUTERNAME
            telemetry = @{
                enabled = $true
                interval_minutes = 5
            }
            backup = @{
                check_interval_minutes = 15
                collectors = @("wsb", "veeam")
            }
            routines = @(
                @{
                    routine_key = "rtk_EXEMPLO"
                    source = "wsb"
                    job_name = ""
                }
            )
        }
        
        $exampleConfig | ConvertTo-Json -Depth 5 | Set-Content $ConfigPath -Force
    }
    
    # Abre no editor padrão
    Start-Process notepad.exe -ArgumentList $ConfigPath
}

# ============================================
# EXECUÇÃO
# ============================================

switch ($Action) {
    'install'   { Install-Service }
    'uninstall' { Uninstall-Service }
    'start'     { Start-ServiceAction }
    'stop'      { Stop-ServiceAction }
    'restart'   { Restart-ServiceAction }
    'status'    { Show-Status }
    'configure' { Show-Configure }
}

Write-Host ""
