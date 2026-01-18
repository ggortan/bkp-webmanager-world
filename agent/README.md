# Agente de Coleta de Dados de Backup

## 📋 Visão Geral

O Agente de Backup é uma solução client-side para coletar automaticamente informações de execuções de backup de servidores Windows e enviar para a API central do Backup WebManager.

### Características

- ✅ **Coleta automática** de dados de backup do Windows Server Backup
- ✅ **Integração com Veeam** Backup & Replication
- ✅ **Serviço Windows** - Roda como serviço passivo em background
- ✅ **Telemetria** - Monitoramento de status online/offline do host
- ✅ **Sistema de retry** automático em caso de falha
- ✅ **Logs detalhados** com rotação automática
- ✅ **Filtros configuráveis** para jobs e notificações

---

## 🔧 Pré-requisitos

### Sistema Operacional
- Windows Server 2012 R2 ou superior
- Windows 10/11 (para testes)

### Software
- PowerShell 5.1 ou superior
- Permissões de Administrador
- Acesso à rede para comunicação com a API

### Opcional
- Windows Server Backup (Feature do Windows Server)
- Veeam Backup & Replication 9.5 ou superior (se for coletar dados do Veeam)

---

## 📦 Instalação

### Método 1: Como Serviço Windows (Recomendado)

O agente pode ser instalado como um serviço Windows que roda em background, gerenciando tanto a telemetria quanto a coleta de backups.

1. **Baixe os arquivos do agente** para uma pasta (ex: `C:\BackupAgent`)

2. **Edite o arquivo de configuração:**

```powershell
# Copie o exemplo
Copy-Item "config\config.service.example.json" "config\config.json"

# Edite com o bloco de notas
notepad "config\config.json"
```

3. **Instale o serviço** como Administrador:

```powershell
# Instala e baixa o NSSM automaticamente
.\Install-BackupAgentService.ps1 -Action install -DownloadNssm

# Verifique o status
.\Install-BackupAgentService.ps1 -Action status

# Inicie o serviço
.\Install-BackupAgentService.ps1 -Action start
```

4. **Comandos do serviço:**

```powershell
# Parar serviço
.\Install-BackupAgentService.ps1 -Action stop

# Reiniciar serviço
.\Install-BackupAgentService.ps1 -Action restart

# Ver status
.\Install-BackupAgentService.ps1 -Action status

# Editar configuração
.\Install-BackupAgentService.ps1 -Action configure

# Desinstalar
.\Install-BackupAgentService.ps1 -Action uninstall
```

### Método 2: Instalação com Script Assistido

1. **Execute o instalador** como Administrador:

```powershell
# Instalação básica (apenas Windows Server Backup)
.\Install-BackupAgent.ps1 `
    -ApiUrl "https://backup.seudominio.com" `
    -ApiKey "sua-api-key-aqui" `
    -ServerName "SRV-PROD-01" `
    -RoutineKey "rtk_sua_routine_key"

# Instalação com Veeam habilitado
.\Install-BackupAgent.ps1 `
    -ApiUrl "https://backup.seudominio.com" `
    -ApiKey "sua-api-key-aqui" `
    -ServerName "SRV-BACKUP-01" `
    -RoutineKey "rtk_sua_routine_key" `
    -EnableVeeam

# Instalação com configurações customizadas
.\Install-BackupAgent.ps1 `
    -ApiUrl "https://backup.seudominio.com" `
    -ApiKey "sua-api-key-aqui" `
    -ServerName "SRV-DB-01" `
    -RoutineKey "rtk_sua_routine_key" `
    -InstallPath "D:\BackupAgent" `
    -CheckIntervalMinutes 30 `
    -EnableVeeam `
    -VeeamServer "veeam-server.local"
```

### Método 3: Instalação Manual

1. **Crie a estrutura de diretórios:**

```powershell
New-Item -ItemType Directory -Path "C:\BackupAgent" -Force
New-Item -ItemType Directory -Path "C:\BackupAgent\config" -Force
New-Item -ItemType Directory -Path "C:\BackupAgent\modules" -Force
New-Item -ItemType Directory -Path "C:\BackupAgent\logs" -Force
```

2. **Copie os arquivos:**
   - `BackupAgentService.ps1` → `C:\BackupAgent\`
   - `Install-BackupAgentService.ps1` → `C:\BackupAgent\`
   - `modules\*.psm1` → `C:\BackupAgent\modules\`
   - `config\config.service.example.json` → `C:\BackupAgent\config\config.json`

3. **Configure o arquivo** `config.json` (veja seção [Configuração](#configuração))

4. **Instale o serviço** (veja Método 1)

---

## ⚙️ Configuração do Serviço

Edite o arquivo `C:\BackupAgent\config\config.json`:

```json
{
  "api_url": "https://backup.seudominio.com",
  "api_token": "COLE_AQUI_A_API_KEY_DO_CLIENTE",
  "host_name": "SRV-EXEMPLO-01",
  
  "telemetry": {
    "enabled": true,
    "interval_minutes": 5
  },
  
  "backup": {
    "check_interval_minutes": 15,
    "collectors": ["wsb", "veeam"]
  },
  
  "routines": [
    {
      "routine_key": "rtk_SUA_ROUTINE_KEY",
      "source": "wsb",
      "job_name": ""
    }
  ]
}
```

### Parâmetros

| Parâmetro | Descrição |
|-----------|-----------|
| `api_url` | URL base da API do Backup Manager |
| `api_token` | Token de autenticação (API Key do cliente) |
| `host_name` | Nome identificador deste host |
| `telemetry.enabled` | Habilita envio de telemetria (heartbeat) |
| `telemetry.interval_minutes` | Intervalo entre envios de telemetria |
| `backup.check_interval_minutes` | Intervalo de verificação de backups |
| `backup.collectors` | Coletores habilitados: `wsb`, `veeam` |
| `routines` | Lista de rotinas de backup vinculadas |

---

## 📡 Telemetria

O serviço envia automaticamente dados de telemetria para monitorar se o host está online:

- **CPU** - Uso percentual
- **Memória** - Uso percentual e total
- **Disco** - Uso percentual do disco do sistema
- **Uptime** - Tempo desde última reinicialização

O host é marcado como **offline** quando não envia telemetria por um período configurável no servidor.

---

## ⚙️ Configuração Legada

Edite o arquivo `C:\BackupAgent\config\config.json`:

```json
{
  "agent": {
    "version": "1.0.0",
    "server_name": "SRV-PROD-01",
    "check_interval_minutes": 60,
    "log_level": "INFO",
    "log_retention_days": 30
  },
  "api": {
    "url": "https://backup.seudominio.com/api",
    "api_key": "sua-api-key-aqui",
    "timeout_seconds": 30,
    "retry_attempts": 3,
    "retry_delay_seconds": 5
  },
  "rotinas": [
    {
      "routine_key": "rtk_sua_routine_key_aqui",
      "nome": "Backup_Diario_WSB",
      "collector_type": "windows_server_backup",
      "enabled": true
    },
    {
      "routine_key": "rtk_outra_routine_key",
      "nome": "Backup_Veeam_Producao",
      "collector_type": "veeam_backup",
      "enabled": false
    }
  ],
  "collectors": {
    "windows_server_backup": {
      "enabled": true,
      "check_event_log": true,
      "event_log_hours": 24
    },
    "veeam_backup": {
      "enabled": false,
      "veeam_ps_snapin": "VeeamPSSnapin",
      "server": "localhost",
      "port": 9392
    }
  },
  "filters": {
    "ignore_jobs": [],
    "only_jobs": [],
    "min_size_mb": 0
  },
  "notifications": {
    "send_on_failure": true,
    "send_on_warning": true,
    "send_on_success": true
  }
}
```

### Parâmetros Importantes

| Parâmetro | Descrição | Valor Padrão |
|-----------|-----------|--------------|
| `server_name` | Nome identificador do servidor | Nome do computador |
| `check_interval_minutes` | Intervalo de verificação | 60 minutos |
| `api_key` | Chave de autenticação da API | *obrigatório* |
| `rotinas[].routine_key` | Chave única da rotina no sistema | *obrigatório* |
| `rotinas[].collector_type` | Tipo de coletor (windows_server_backup, veeam_backup) | *obrigatório* |
| `ignore_jobs` | Jobs que serão ignorados | [] |
| `only_jobs` | Processar apenas estes jobs | [] |
| `min_size_mb` | Tamanho mínimo do backup (MB) | 0 |

---

## 🚀 Uso

### Executar Manualmente

```powershell
# Execução única
C:\BackupAgent\BackupAgent.ps1 -RunOnce

# Modo teste (não envia para API)
C:\BackupAgent\BackupAgent.ps1 -RunOnce -TestMode

# Modo verbose (mais detalhes)
C:\BackupAgent\BackupAgent.ps1 -RunOnce -Verbose

# Executar em loop contínuo
C:\BackupAgent\BackupAgent.ps1
```

### Verificar Status da Tarefa Agendada

```powershell
# Ver informações da tarefa
Get-ScheduledTask -TaskName "BackupWebManager-Agent"

# Ver histórico de execução
Get-ScheduledTaskInfo -TaskName "BackupWebManager-Agent"

# Executar tarefa manualmente
Start-ScheduledTask -TaskName "BackupWebManager-Agent"
```

### Visualizar Logs

```powershell
# Ver log do dia atual
Get-Content "C:\BackupAgent\logs\agent_$(Get-Date -Format 'yyyy-MM-dd').log" -Tail 50

# Acompanhar em tempo real
Get-Content "C:\BackupAgent\logs\agent_$(Get-Date -Format 'yyyy-MM-dd').log" -Wait -Tail 20
```

---

## 🔨 Compilação em Executável

Para compilar o agente em um executável (.exe), você pode usar o **PS2EXE**.

### Instalação do PS2EXE

```powershell
Install-Module -Name ps2exe -Scope CurrentUser -Force
```

### Compilar o Agente

```powershell
# Compilação básica
Invoke-ps2exe `
    -inputFile "C:\BackupAgent\BackupAgent.ps1" `
    -outputFile "C:\BackupAgent\BackupAgent.exe" `
    -noConsole:$false `
    -requireAdmin `
    -title "Backup WebManager Agent" `
    -description "Agente de coleta de dados de backup" `
    -company "Sua Empresa" `
    -version "1.0.0.0"

# Compilação com ícone customizado
Invoke-ps2exe `
    -inputFile "C:\BackupAgent\BackupAgent.ps1" `
    -outputFile "C:\BackupAgent\BackupAgent.exe" `
    -iconFile "C:\BackupAgent\icon.ico" `
    -noConsole:$false `
    -requireAdmin `
    -title "Backup WebManager Agent" `
    -version "1.0.0.0"
```

### Usar o Executável

Após compilar, você pode executar:

```powershell
# Executar diretamente
C:\BackupAgent\BackupAgent.exe -RunOnce

# Atualizar tarefa agendada para usar o .exe
$action = New-ScheduledTaskAction -Execute "C:\BackupAgent\BackupAgent.exe" -Argument "-ConfigPath C:\BackupAgent\config\config.json"

Set-ScheduledTask -TaskName "BackupWebManager-Agent" -Action $action
```

**⚠️ IMPORTANTE:** Ao compilar, os módulos (.psm1) ainda precisam estar na pasta `modules/` pois são carregados dinamicamente.

---

## 📊 Agendamento

### Criar Tarefa Manualmente

```powershell
$action = New-ScheduledTaskAction `
    -Execute "PowerShell.exe" `
    -Argument "-NoProfile -ExecutionPolicy Bypass -File C:\BackupAgent\BackupAgent.ps1 -ConfigPath C:\BackupAgent\config\config.json"

$trigger = New-ScheduledTaskTrigger `
    -Once `
    -At (Get-Date) `
    -RepetitionInterval (New-TimeSpan -Minutes 60) `
    -RepetitionDuration ([TimeSpan]::MaxValue)

$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -RunOnlyIfNetworkAvailable

$principal = New-ScheduledTaskPrincipal `
    -UserId "SYSTEM" `
    -LogonType ServiceAccount `
    -RunLevel Highest

Register-ScheduledTask `
    -TaskName "BackupWebManager-Agent" `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Principal $principal `
    -Description "Agente de coleta de backup"
```

---

## 🗑️ Desinstalação

```powershell
# Usando o instalador
.\Install-BackupAgent.ps1 -Uninstall

# Manualmente
Unregister-ScheduledTask -TaskName "BackupWebManager-Agent" -Confirm:$false
Remove-Item -Path "C:\BackupAgent" -Recurse -Force
```

---

## 🔍 Troubleshooting

### Problema: Erro "Módulo Windows Server Backup não está instalado"

**Solução:** Instale o Windows Server Backup:

```powershell
Install-WindowsFeature -Name Windows-Server-Backup
```

### Problema: Erro ao conectar ao Veeam

**Soluções:**
1. Verifique se o Veeam PowerShell Snap-in está instalado:
   ```powershell
   Get-PSSnapin -Registered | Where-Object { $_.Name -like "*Veeam*" }
   ```

2. Instale o Veeam Console se necessário

3. Verifique conectividade com o servidor Veeam:
   ```powershell
   Test-NetConnection -ComputerName "veeam-server" -Port 9392
   ```

### Problema: Dados não estão sendo enviados para a API

**Verificações:**
1. Teste a conexão com a API:
   ```powershell
   Invoke-RestMethod -Uri "https://backup.seudominio.com/api/status"
   ```

2. Verifique a API Key no arquivo de configuração

3. Revise os logs em `C:\BackupAgent\logs\`

4. Execute em modo teste:
   ```powershell
   C:\BackupAgent\BackupAgent.ps1 -RunOnce -TestMode -Verbose
   ```

### Problema: Tarefa agendada não executa

**Soluções:**
1. Verifique se a tarefa está habilitada:
   ```powershell
   Get-ScheduledTask -TaskName "BackupWebManager-Agent" | Select State
   ```

2. Veja o último resultado:
   ```powershell
   Get-ScheduledTaskInfo -TaskName "BackupWebManager-Agent"
   ```

3. Execute manualmente para verificar erros:
   ```powershell
   Start-ScheduledTask -TaskName "BackupWebManager-Agent"
   ```

---

## 📁 Estrutura de Arquivos

```
C:\BackupAgent\
├── BackupAgent.ps1                 # Script principal
├── Install-BackupAgent.ps1         # Script de instalação
├── config\
│   ├── config.json                 # Configuração ativa
│   └── config.example.json         # Exemplo de configuração
├── modules\
│   ├── WindowsBackupCollector.psm1 # Módulo Windows Server Backup
│   └── VeeamBackupCollector.psm1   # Módulo Veeam
└── logs\
    └── agent_2026-01-17.log        # Logs por data
```

---

## 🔐 Segurança

### Proteção da API Key

A API Key é armazenada em texto simples no arquivo de configuração. Para aumentar a segurança:

1. **Permissões NTFS:** Restrinja acesso ao arquivo `config.json`:
   ```powershell
   icacls "C:\BackupAgent\config\config.json" /grant "SYSTEM:(F)" /inheritance:r
   ```

2. **Criptografia:** Use DPAPI para criptografar a API Key (implementação futura)

3. **Rotation:** Rotacione a API Key periodicamente no painel web

### Execução como SYSTEM

O agente é executado como conta SYSTEM para ter acesso aos eventos de backup e Veeam.

---

## 🆘 Suporte

Para problemas ou dúvidas:

1. Verifique os logs em `C:\BackupAgent\logs\`
2. Execute em modo verbose: `-Verbose`
3. Consulte a documentação da API em `/docs/API.md`
4. Consulte o guia de configuração de servidores Windows em `/docs/SERVIDORES_WINDOWS.md`

---

## 📝 Changelog

### v1.0.0 (2026-01-17)
- ✨ Versão inicial
- ✅ Suporte a Windows Server Backup
- ✅ Suporte a Veeam Backup & Replication
- ✅ Sistema de logs e retry
- ✅ Instalador automático
- ✅ Documentação completa
