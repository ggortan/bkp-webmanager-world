# Configuração da Coleta Automática de Backups

O agente BackupAgentService.ps1 coleta automaticamente informações de backup do **Windows Server Backup** e do **Veeam Backup & Replication**.

---

## 📋 Pré-requisitos

### Windows Server Backup (WSB)
- **Feature instalada**: `Windows Server Backup`
- **Módulo PowerShell**: `WindowsServerBackup` (vem com a feature)
- **Executar como**: Administrador

### Veeam Backup & Replication
- **Veeam Console** instalado no servidor (ou servidor remoto configurado)
- **Módulo PowerShell**: `Veeam.Backup.PowerShell`
- **Executar como**: Usuário com permissão no Veeam

---

## 🔍 Como Obter os Jobs

### Windows Server Backup

O Windows Server Backup funciona com **um único job ativo**. O agente usa:

```powershell
# Verifica se o módulo está disponível
Import-Module WindowsServerBackup

# Obtém o último job executado
Get-WBJob -Previous 1

# Retorna informações como:
# - JobState: Completed, Failed, Running
# - StartTime: Quando iniciou
# - EndTime: Quando terminou
# - DetailedMessage: Mensagem de detalhes
# - ErrorDescription: Erro (se houver)
```

**Comandos úteis para verificar WSB:**
```powershell
# Ver todos os backups disponíveis
Get-WBBackupSet

# Ver política configurada
Get-WBPolicy

# Ver summary do último backup
Get-WBSummary
```

### Veeam Backup & Replication

O Veeam trabalha com **múltiplos jobs**. Você precisa listar e escolher qual(is) monitorar:

```powershell
# Carrega o módulo Veeam
Add-PSSnapin VeeamPSSnapin -ErrorAction SilentlyContinue
# OU (versões mais novas)
Import-Module Veeam.Backup.PowerShell

# Lista TODOS os jobs configurados
Get-VBRJob | Select-Object Name, JobType, LatestStatus, NextRun

# Exemplo de saída:
# Name                              JobType     LatestStatus  NextRun
# ----                              -------     ------------  -------
# Backup Diário - Servidor Arquivos Backup      Success       18/01/2026 22:00
# Backup VMs Produção               Backup      Warning       18/01/2026 23:00
# Replicação DR                     Replica     Success       19/01/2026 01:00
```

**Comandos úteis para verificar Veeam:**
```powershell
# Detalhes de um job específico
$job = Get-VBRJob -Name "Backup Diário - Servidor Arquivos"

# Última sessão do job
$lastSession = $job.FindLastSession()
$lastSession | Select-Object JobName, CreationTime, EndTime, Result, State

# Histórico de sessões
Get-VBRBackupSession | Where-Object { $_.JobName -eq "Backup Diário" } | 
    Select-Object -First 10 JobName, CreationTime, EndTime, Result

# Ver objetos sendo backupeados
$job.GetObjectsInJob() | Select-Object Name, Type
```

---

## ⚙️ Configuração do Agente

### Passo 1: Criar arquivo de configuração

```powershell
# Navega até a pasta do agente
cd C:\BackupAgent\config

# Copia o exemplo
Copy-Item config.example.json config.json
```

### Passo 2: Obter informações necessárias

1. **API URL**: URL do seu servidor WebManager (ex: `https://backup.empresa.com`)
2. **API Token**: Obtido no painel do cliente em **Configurações > API Key**
3. **Routine Keys**: Obtidas ao criar rotinas no WebManager

### Passo 3: Configurar o config.json

```json
{
  "api_url": "https://backup.empresa.com",
  "api_token": "sua_api_key_aqui_do_painel",
  "host_name": "SRV-FILESERVER-01",

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
      "routine_key": "rtk_abc123def456",
      "source": "wsb",
      "job_name": null
    },
    {
      "routine_key": "rtk_xyz789ghi012",
      "source": "veeam",
      "job_name": "Backup Diário - Servidor Arquivos"
    }
  ]
}
```

---

## 📝 Explicação dos Campos

| Campo | Descrição |
|-------|-----------|
| `api_url` | URL base do WebManager (sem `/api` no final) |
| `api_token` | API Key do cliente obtida no painel |
| `host_name` | Nome que identifica este servidor |
| `telemetry.enabled` | Se `true`, envia métricas (CPU, memória, disco) |
| `telemetry.interval_minutes` | Intervalo de envio de telemetria |
| `backup.check_interval_minutes` | Intervalo de verificação de backups |
| `backup.collectors` | Lista de coletores: `wsb`, `veeam` |
| `routines` | Array de rotinas a monitorar |

### Campos de cada Rotina:

| Campo | Descrição |
|-------|-----------|
| `routine_key` | Chave única da rotina (obtida no WebManager) |
| `source` | Tipo: `wsb` (Windows Server Backup) ou `veeam` |
| `job_name` | Nome do job (apenas Veeam). Use `null` para WSB ou para pegar todos |

---

## 🚀 Iniciando o Agente

### Modo Debug (para testes)
```powershell
# Executa diretamente no PowerShell
cd C:\BackupAgent
.\BackupAgentService.ps1
```

### Como Serviço Windows (produção)
```powershell
# Instala como serviço usando NSSM (Non-Sucking Service Manager)
# Baixe em: https://nssm.cc/download

nssm install BackupManagerAgent "C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe"
nssm set BackupManagerAgent AppParameters "-ExecutionPolicy Bypass -File C:\BackupAgent\BackupAgentService.ps1"
nssm set BackupManagerAgent DisplayName "Backup Manager Agent"
nssm set BackupManagerAgent Description "Agente de monitoramento de backup"
nssm set BackupManagerAgent Start SERVICE_AUTO_START

# Inicia o serviço
Start-Service BackupManagerAgent
```

---

## 🔄 Fluxo de Funcionamento

1. **Telemetria** (a cada X minutos):
   - Coleta CPU, memória, discos, uptime
   - Envia para `/api/telemetry`
   - Host é criado/atualizado automaticamente

2. **Verificação de Backups** (a cada Y minutos):
   - Consulta WSB: `Get-WBJob -Previous 1`
   - Consulta Veeam: `Get-VBRJob` + `FindLastSession()`
   - Compara com rotinas configuradas
   - Envia resultados para `/api/backup`
   - Evita duplicatas (controla por timestamp)

3. **Mapeamento de Status**:
   - WSB: `Completed` → `sucesso`, `Failed` → `falha`, `Running` → `executando`
   - Veeam: `Success` → `sucesso`, `Warning` → `alerta`, `Failed` → `falha`

---

## 📊 Logs

Os logs ficam em `C:\BackupAgent\logs\`:
- `agent-YYYY-MM-DD.log` - Log diário
- `sent-backups.json` - Controle de backups já enviados

```powershell
# Ver logs em tempo real
Get-Content "C:\BackupAgent\logs\agent-$(Get-Date -Format 'yyyy-MM-dd').log" -Wait -Tail 50
```

---

## 🛠️ Troubleshooting

### WSB não detectado
```powershell
# Verifica se a feature está instalada
Get-WindowsFeature -Name Windows-Server-Backup

# Instala se necessário
Install-WindowsFeature -Name Windows-Server-Backup -IncludeManagementTools

# Verifica se o módulo está disponível
Get-Module -ListAvailable WindowsServerBackup
```

### Veeam não detectado
```powershell
# Verifica se o snapin está disponível
Get-PSSnapin -Registered | Where-Object { $_.Name -like "*Veeam*" }

# Carrega manualmente
Add-PSSnapin VeeamPSSnapin

# OU para versões novas
Import-Module "C:\Program Files\Veeam\Backup and Replication\Console\Veeam.Backup.PowerShell\Veeam.Backup.PowerShell.psd1"
```

### Nenhum backup sendo enviado
1. Verifique se `routine_key` está correta
2. Verifique se `source` corresponde ao tipo de backup
3. Para Veeam, verifique se `job_name` está exato (ou use `null` para todos)
4. Veja os logs para mensagens de erro

---

## 📌 Exemplo Completo

Servidor com Windows Server Backup + 2 jobs Veeam:

```json
{
  "api_url": "https://backup.minhaempresa.com.br",
  "api_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "host_name": "SRV-DC01",

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
      "routine_key": "rtk_wsb_dc01_systemstate",
      "source": "wsb",
      "job_name": null
    },
    {
      "routine_key": "rtk_veeam_vms_producao",
      "source": "veeam",
      "job_name": "VMs Produção - Diário"
    },
    {
      "routine_key": "rtk_veeam_fileserver",
      "source": "veeam", 
      "job_name": "FileServer - Incremental"
    }
  ]
}
```
