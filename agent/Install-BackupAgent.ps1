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
    [switch]$Uninstall,
    
    [Parameter(Mandatory = $false)]
    [ValidateSet('Service', 'Task', 'Auto')]
    [string]$InstallMode = 'Auto'
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
    
    # Remove serviço Windows
    $serviceName = "BackupManagerAgent"
    $service = Get-Service -Name $serviceName -ErrorAction SilentlyContinue
    if ($service) {
        Write-Status "Parando e removendo serviço Windows..." -Type INFO
        Stop-Service -Name $serviceName -Force -ErrorAction SilentlyContinue
        
        $nssmExe = "$InstallPath\nssm.exe"
        if (Test-Path $nssmExe) {
            & $nssmExe remove $serviceName confirm 2>$null
        }
        else {
            sc.exe delete $serviceName 2>$null
        }
        
        Write-Status "Serviço removido" -Type SUCCESS
    }
    
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

function Get-NSSM {
    <#
    .SYNOPSIS
        Baixa e extrai o NSSM de fontes alternativas
    #>
    param(
        [string]$DestinationPath
    )
    
    $nssmExe = "$DestinationPath\nssm.exe"
    
    # Se já existe, usa o existente
    if (Test-Path $nssmExe) {
        Write-Status "NSSM já existe em: $nssmExe" -Type SUCCESS
        return $nssmExe
    }
    
    # Tenta fontes alternativas
    $sources = @(
        @{
            Name = "GitHub Release"
            Url = "https://github.com/kirillkovalenko/nssm/releases/download/v2.24.101/nssm-2.24.101.zip"
        },
        @{
            Name = "NSSM.cc"
            Url = "https://nssm.cc/release/nssm-2.24.zip"
        },
        @{
            Name = "Archive.org"
            Url = "https://web.archive.org/web/2024/https://nssm.cc/release/nssm-2.24.zip"
        }
    )
    
    $tempZip = "$env:TEMP\nssm.zip"
    $tempDir = "$env:TEMP\nssm_extract"
    $downloaded = $false
    
    foreach ($source in $sources) {
        Write-Status "Tentando baixar NSSM de: $($source.Name)..." -Type INFO
        
        try {
            [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
            Invoke-WebRequest -Uri $source.Url -OutFile $tempZip -TimeoutSec 30 -UseBasicParsing -ErrorAction Stop
            $downloaded = $true
            Write-Status "Download concluído de: $($source.Name)" -Type SUCCESS
            break
        }
        catch {
            Write-Status "Falha ao baixar de $($source.Name): $($_.Exception.Message)" -Type WARNING
        }
    }
    
    if (-not $downloaded) {
        Write-Status "Não foi possível baixar NSSM automaticamente" -Type ERROR
        Write-Host ""
        Write-Host "=== INSTALAÇÃO MANUAL DO NSSM ===" -ForegroundColor Yellow
        Write-Host "1. Baixe manualmente de: https://github.com/kirillkovalenko/nssm/releases" -ForegroundColor White
        Write-Host "2. Extraia o arquivo zip" -ForegroundColor White
        Write-Host "3. Copie nssm.exe (da pasta win64) para: $DestinationPath" -ForegroundColor White
        Write-Host "4. Execute este instalador novamente" -ForegroundColor White
        Write-Host ""
        return $null
    }
    
    # Extrai o zip
    Write-Status "Extraindo NSSM..." -Type INFO
    
    if (Test-Path $tempDir) {
        Remove-Item $tempDir -Recurse -Force
    }
    
    Expand-Archive -Path $tempZip -DestinationPath $tempDir -Force
    
    # Encontra o nssm.exe (64-bit preferencialmente)
    $nssmFile = Get-ChildItem -Path $tempDir -Recurse -Filter "nssm.exe" | 
                Where-Object { $_.DirectoryName -like "*win64*" -or $_.DirectoryName -like "*64*" } | 
                Select-Object -First 1
    
    if (-not $nssmFile) {
        $nssmFile = Get-ChildItem -Path $tempDir -Recurse -Filter "nssm.exe" | Select-Object -First 1
    }
    
    if ($nssmFile) {
        Copy-Item $nssmFile.FullName -Destination $nssmExe -Force
        Write-Status "NSSM instalado em: $nssmExe" -Type SUCCESS
    }
    else {
        Write-Status "Arquivo nssm.exe não encontrado no zip" -Type ERROR
        return $null
    }
    
    # Limpa arquivos temporários
    Remove-Item $tempZip -Force -ErrorAction SilentlyContinue
    Remove-Item $tempDir -Recurse -Force -ErrorAction SilentlyContinue
    
    return $nssmExe
}

function Install-AsService {
    <#
    .SYNOPSIS
        Instala o agente como serviço Windows usando NSSM
    #>
    param(
        [string]$ServiceName = "BackupManagerAgent",
        [string]$AgentPath,
        [string]$ConfigPath
    )
    
    Write-Status "Instalando como serviço Windows..." -Type INFO
    
    # Obtém NSSM
    $nssmExe = Get-NSSM -DestinationPath $AgentPath
    
    if (-not $nssmExe) {
        Write-Status "Usando Tarefa Agendada como alternativa..." -Type WARNING
        return $false
    }
    
    # Remove serviço existente se houver
    $existingService = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
    if ($existingService) {
        Write-Status "Removendo serviço existente..." -Type INFO
        & $nssmExe stop $ServiceName 2>$null
        & $nssmExe remove $ServiceName confirm 2>$null
        Start-Sleep -Seconds 2
    }
    
    # Instala o serviço
    $scriptPath = "$AgentPath\BackupAgentService.ps1"
    $powershellPath = "$env:SystemRoot\System32\WindowsPowerShell\v1.0\powershell.exe"
    
    & $nssmExe install $ServiceName $powershellPath
    & $nssmExe set $ServiceName AppParameters "-ExecutionPolicy Bypass -NoProfile -File `"$scriptPath`" -ConfigPath `"$ConfigPath`""
    & $nssmExe set $ServiceName DisplayName "Backup Manager Agent"
    & $nssmExe set $ServiceName Description "Agente de monitoramento de backup - World Informatica"
    & $nssmExe set $ServiceName Start SERVICE_AUTO_START
    & $nssmExe set $ServiceName AppDirectory $AgentPath
    & $nssmExe set $ServiceName AppStdout "$AgentPath\logs\service-stdout.log"
    & $nssmExe set $ServiceName AppStderr "$AgentPath\logs\service-stderr.log"
    & $nssmExe set $ServiceName AppRotateFiles 1
    & $nssmExe set $ServiceName AppRotateBytes 10485760
    
    # Inicia o serviço
    Write-Status "Iniciando serviço..." -Type INFO
    Start-Service -Name $ServiceName -ErrorAction SilentlyContinue
    
    Start-Sleep -Seconds 3
    
    $service = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
    if ($service -and $service.Status -eq 'Running') {
        Write-Status "Serviço instalado e rodando: $ServiceName" -Type SUCCESS
        return $true
    }
    else {
        Write-Status "Serviço instalado mas não iniciou automaticamente" -Type WARNING
        Write-Status "Verifique os logs em: $AgentPath\logs" -Type INFO
        return $true
    }
}

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
    
    # Copia o script principal (execução manual)
    if (Test-Path "$sourceDir\BackupAgent.ps1") {
        Copy-Item "$sourceDir\BackupAgent.ps1" -Destination $InstallPath -Force
    }
    
    # Copia o script de serviço
    if (Test-Path "$sourceDir\BackupAgentService.ps1") {
        Copy-Item "$sourceDir\BackupAgentService.ps1" -Destination $InstallPath -Force
    }
    
    # Copia os módulos
    if (Test-Path "$sourceDir\modules") {
        Copy-Item "$sourceDir\modules\*" -Destination "$InstallPath\modules" -Force -Recurse
    }
    
    Write-Status "Arquivos copiados" -Type SUCCESS
    
    # Cria arquivo de configuração
    Write-Status "Criando arquivo de configuração..." -Type INFO
    
    # Formato compatível com BackupAgentService.ps1
    $config = @{
        # Campos na raiz para BackupAgentService.ps1
        api_url = $ApiUrl.TrimEnd('/')
        api_token = $ApiKey
        host_name = $ServerName
        
        # Configuração de telemetria
        telemetry = @{
            enabled = $true
            interval_minutes = 5
        }
        
        # Configuração de backup
        backup = @{
            check_interval_minutes = $CheckIntervalMinutes
            collectors = @("wsb", "veeam")
        }
        
        # Rotinas (devem ser preenchidas manualmente com as routine_keys do WebManager)
        routines = @()
        
        # Configurações adicionais (para BackupAgent.ps1 legado)
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
    
    # Cria tarefa agendada ou serviço
    $installedAs = ""
    
    if ($InstallMode -eq 'Service' -or $InstallMode -eq 'Auto') {
        $serviceInstalled = Install-AsService -ServiceName "BackupManagerAgent" -AgentPath $InstallPath -ConfigPath $configPath
        
        if ($serviceInstalled) {
            $installedAs = "Serviço Windows"
        }
        elseif ($InstallMode -eq 'Auto') {
            Write-Status "Fallback para Tarefa Agendada..." -Type INFO
            $InstallMode = 'Task'
        }
        else {
            Write-Status "Falha ao instalar como serviço" -Type ERROR
            return
        }
    }
    
    if ($InstallMode -eq 'Task') {
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
        $installedAs = "Tarefa Agendada"
    }
    
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
    Write-Host "  Instalado como: $installedAs" -ForegroundColor Gray
    Write-Host ""
    Write-Host "Próximos passos:" -ForegroundColor White
    Write-Host "  1. Verifique o arquivo de configuração: $configPath" -ForegroundColor Gray
    Write-Host "  2. Ajuste filtros e notificações conforme necessário" -ForegroundColor Gray
    Write-Host "  3. Monitore os logs em: $InstallPath\logs" -ForegroundColor Gray
    if ($installedAs -eq "Serviço Windows") {
        Write-Host "  4. O serviço 'BackupManagerAgent' está rodando automaticamente" -ForegroundColor Gray
    }
    else {
        Write-Host "  4. A tarefa agendada 'BackupWebManager-Agent' executará automaticamente" -ForegroundColor Gray
    }
    Write-Host ""
    Write-Host "Comandos úteis:" -ForegroundColor White
    Write-Host "  Executar manualmente: & '$InstallPath\BackupAgent.ps1' -RunOnce" -ForegroundColor Gray
    Write-Host "  Testar sem enviar: & '$InstallPath\BackupAgent.ps1' -RunOnce -TestMode" -ForegroundColor Gray
    if ($installedAs -eq "Serviço Windows") {
        Write-Host "  Ver status: Get-Service BackupManagerAgent" -ForegroundColor Gray
        Write-Host "  Reiniciar: Restart-Service BackupManagerAgent" -ForegroundColor Gray
        Write-Host "  Ver logs: Get-Content '$InstallPath\logs\agent-$(Get-Date -Format 'yyyy-MM-dd').log' -Tail 50" -ForegroundColor Gray
    }
    else {
        Write-Host "  Ver tarefa: Get-ScheduledTask -TaskName 'BackupWebManager-Agent'" -ForegroundColor Gray
    }
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
