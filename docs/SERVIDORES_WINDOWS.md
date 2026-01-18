# Configuração de Servidores Windows

Este guia explica como configurar servidores Windows para enviar relatórios de backup para o sistema Backup WebManager.

## Pré-requisitos

- Windows Server 2016 ou superior
- PowerShell 5.1 ou superior
- Acesso de administrador no servidor
- Conectividade de rede com o servidor da aplicação
- API Key gerada para o host no painel do WebManager

## Métodos de Envio

### 1. Usando o Agente de Backup (Recomendado)

O agente automatiza a coleta e envio de informações de backup de forma segura.

#### Instalação do Agente

```powershell
# Baixar o instalador
Invoke-WebRequest -Uri "https://seu-servidor/downloads/Install-BackupAgent.ps1" -OutFile "Install-BackupAgent.ps1"

# Executar instalação
.\Install-BackupAgent.ps1 -ApiUrl "https://seu-servidor/api" -RoutineKey "sua-routine-key"
```

#### Configuração Manual do Agente

1. Copie a pasta `agent/` para o servidor Windows
2. Crie o arquivo de configuração `config/config.json`:

```json
{
    "ApiUrl": "https://seu-servidor/api/backup",
    "RoutineKey": "ABC123XYZ",
    "BackupType": "WSB",
    "PollingIntervalMinutes": 60,
    "LogPath": "C:\\BackupAgent\\logs",
    "HostInfo": {
        "name": "SERVER01",
        "ip": "192.168.1.100",
        "os": "Windows Server 2022"
    }
}
```

3. Configure a tarefa agendada:

```powershell
$action = New-ScheduledTaskAction -Execute "PowerShell.exe" `
    -Argument "-ExecutionPolicy Bypass -File C:\BackupAgent\BackupAgent.ps1"

$trigger = New-ScheduledTaskTrigger -Daily -At "08:00AM"

Register-ScheduledTask -TaskName "BackupAgent" `
    -Action $action `
    -Trigger $trigger `
    -RunLevel Highest `
    -User "SYSTEM"
```

### 2. Usando Script Simples

Para envios manuais ou casos específicos:

```powershell
.\Send-BackupReport.ps1 `
    -ApiUrl "https://seu-servidor/api/backup" `
    -RoutineKey "ABC123XYZ" `
    -Status "success" `
    -SizeBytes 1073741824 `
    -Duration 3600 `
    -Details "Backup completo realizado"
```

Parâmetros do script:

| Parâmetro | Obrigatório | Descrição |
|-----------|-------------|-----------|
| `-ApiUrl` | Sim | URL da API do WebManager |
| `-RoutineKey` | Sim | Chave única da rotina de backup |
| `-Status` | Sim | Status: success, warning, error |
| `-SizeBytes` | Não | Tamanho do backup em bytes |
| `-Duration` | Não | Duração em segundos |
| `-Details` | Não | Detalhes adicionais |
| `-HostName` | Não | Nome do host (padrão: hostname atual) |
| `-HostIP` | Não | IP do host |

## Configurações por Tipo de Backup

### Windows Server Backup (WSB)

Configure o `BackupType` como `"WSB"` no config.json:

```json
{
    "ApiUrl": "https://seu-servidor/api/backup",
    "RoutineKey": "ROTINA-WSB-001",
    "BackupType": "WSB",
    "PollingIntervalMinutes": 60
}
```

O módulo `WindowsBackupCollector.psm1` coleta automaticamente:
- Último backup realizado
- Status de sucesso/falha
- Tamanho do backup
- Duração da operação

### Veeam Backup & Replication

Configure o `BackupType` como `"Veeam"`:

```json
{
    "ApiUrl": "https://seu-servidor/api/backup",
    "RoutineKey": "ROTINA-VEEAM-001",
    "BackupType": "Veeam",
    "VeeamJobName": "Daily Backup",
    "PollingIntervalMinutes": 60
}
```

O módulo `VeeamBackupCollector.psm1` requer:
- Veeam Backup & Replication instalado
- PowerShell module do Veeam disponível

## Formato da API

O agente envia dados no seguinte formato:

```json
{
    "routine_key": "ABC123XYZ",
    "status": "success",
    "size_bytes": 1073741824,
    "duration_seconds": 3600,
    "details": "Backup concluído com sucesso",
    "executed_at": "2025-01-15T03:00:00Z",
    "host_info": {
        "name": "SERVER01",
        "ip": "192.168.1.100",
        "os": "Windows Server 2022"
    }
}
```

Campos obrigatórios:
- `routine_key`: Chave da rotina cadastrada no WebManager
- `status`: success, warning, error
- `executed_at`: Data/hora da execução

Campos opcionais:
- `size_bytes`: Tamanho em bytes
- `duration_seconds`: Duração em segundos
- `details`: Mensagem ou log resumido
- `host_info`: Objeto com informações do host (name, ip, os)

## Autenticação

A API usa autenticação via header:

```
X-API-Key: sua-api-key-aqui
```

A API Key é obtida ao cadastrar o host no WebManager:
1. Acesse **Hosts** no painel
2. Clique em **Novo Host**
3. Preencha os dados e salve
4. Copie a API Key gerada

## Troubleshooting

### Erro de Conexão

Verifique:
- Conectividade de rede com o servidor
- Firewall liberando a porta (80/443)
- URL da API correta

```powershell
# Testar conectividade
Test-NetConnection -ComputerName "seu-servidor" -Port 443
```

### Erro de Autenticação (401)

- Verifique se a API Key está correta
- Confirme se a API Key está ativa no painel
- Verifique se o header X-API-Key está sendo enviado

### Rotina não encontrada (404)

- Confirme se a `routine_key` existe no sistema
- Verifique se a rotina está ativa
- Confirme a grafia exata da chave

### Logs do Agente

Os logs são salvos em `C:\BackupAgent\logs\` por padrão:

```powershell
# Visualizar logs recentes
Get-Content "C:\BackupAgent\logs\backup-agent.log" -Tail 50
```

## Segurança

### Boas Práticas

1. **HTTPS**: Sempre use HTTPS para a API
2. **API Key**: Mantenha a chave segura e não versione em Git
3. **Permissões**: Execute o agente com usuário de serviço dedicado
4. **Logs**: Configure rotação de logs para evitar acúmulo

### Armazenamento Seguro de Credenciais

```powershell
# Criar credencial segura (Windows Credential Manager)
$apiKey = ConvertTo-SecureString "sua-api-key" -AsPlainText -Force
$apiKey | ConvertFrom-SecureString | Set-Content "C:\BackupAgent\config\apikey.txt"

# Ler credencial no script
$secureKey = Get-Content "C:\BackupAgent\config\apikey.txt" | ConvertTo-SecureString
$apiKeyPlain = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
    [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureKey)
)
```

## Integração com Task Scheduler

### Exemplo Completo de Tarefa

```powershell
# Criar tarefa que executa a cada hora
$action = New-ScheduledTaskAction -Execute "PowerShell.exe" `
    -Argument "-ExecutionPolicy Bypass -NoProfile -File C:\BackupAgent\BackupAgent.ps1" `
    -WorkingDirectory "C:\BackupAgent"

$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) `
    -RepetitionInterval (New-TimeSpan -Hours 1) `
    -RepetitionDuration (New-TimeSpan -Days 365)

$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -RestartCount 3 `
    -RestartInterval (New-TimeSpan -Minutes 5)

$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest

Register-ScheduledTask -TaskName "BackupAgent" `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Principal $principal `
    -Description "Agente de monitoramento de backup"
```

### Verificar Status da Tarefa

```powershell
Get-ScheduledTask -TaskName "BackupAgent" | Get-ScheduledTaskInfo
```

## Suporte

Em caso de dúvidas ou problemas:

1. Consulte os logs do agente
2. Verifique a documentação da API em `/docs/API.md`
3. Confirme as configurações de rede e firewall
