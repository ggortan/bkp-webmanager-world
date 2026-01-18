# Sistema de Telemetria de Hosts

Este documento descreve o sistema de telemetria para monitoramento de status online/offline dos hosts.

## Visão Geral

O sistema de telemetria permite:

1. **Monitorar** se um host está online ou offline
2. **Coletar métricas** básicas (CPU, memória, disco)
3. **Alertar** quando hosts ficam offline

## Funcionamento

1. Os agentes enviam periodicamente um "heartbeat" para a API
2. O sistema registra o `last_seen_at` do host
3. Um job verifica periodicamente quais hosts não enviaram heartbeat dentro do threshold
4. Hosts são marcados como offline automaticamente

## Configuração por Host

Cada host pode ter configurações individuais:

| Campo | Descrição | Padrão |
|-------|-----------|--------|
| `telemetry_enabled` | Habilita/desabilita monitoramento | 1 (habilitado) |
| `telemetry_interval_minutes` | Intervalo esperado entre heartbeats | 5 minutos |
| `telemetry_offline_threshold` | Quantos intervalos perdidos para considerar offline | 3 |

Com os valores padrão, um host será considerado offline após **15 minutos** sem enviar telemetria (5 min × 3).

## Endpoint da API

### POST /api/telemetry ou POST /api/heartbeat

Recebe dados de telemetria de um host.

**Headers:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Body:**
```json
{
    "host_name": "SERVER01",
    "hostname": "server01.empresa.local",
    "ip": "192.168.1.100",
    "os": "Windows Server 2022",
    "metrics": {
        "cpu_percent": 45.2,
        "memory_percent": 68.5,
        "disk_percent": 55.0,
        "uptime_seconds": 86400
    }
}
```

**Resposta de sucesso:**
```json
{
    "success": true,
    "message": "Telemetria recebida",
    "host_id": 1,
    "host_name": "SERVER01",
    "status": "online"
}
```

## Script PowerShell

### Uso Básico

```powershell
# Execução única
.\Send-Telemetry.ps1 -ApiUrl "https://backup.empresa.com" -ApiToken "seu-token" -RunOnce

# Loop contínuo (recomendado para serviço)
.\Send-Telemetry.ps1 -ApiUrl "https://backup.empresa.com" -ApiToken "seu-token" -IntervalMinutes 5
```

### Parâmetros

| Parâmetro | Obrigatório | Descrição |
|-----------|-------------|-----------|
| `-ApiUrl` | Sim | URL base da API |
| `-ApiToken` | Sim | Token de autenticação |
| `-HostName` | Não | Nome do host (padrão: `$env:COMPUTERNAME`) |
| `-IntervalMinutes` | Não | Intervalo em minutos (padrão: 5) |
| `-RunOnce` | Não | Executa apenas uma vez |

### Como Tarefa Agendada

Para configurar como tarefa agendada no Windows:

```powershell
$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-ExecutionPolicy Bypass -File C:\Scripts\Send-Telemetry.ps1 -ApiUrl 'https://backup.empresa.com' -ApiToken 'seu-token' -RunOnce"

$trigger = New-ScheduledTaskTrigger -RepetitionInterval (New-TimeSpan -Minutes 5) -Once -At (Get-Date)

$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest

Register-ScheduledTask -TaskName "BackupManager-Telemetry" -Action $action -Trigger $trigger -Principal $principal
```

## Verificação de Hosts Offline

Execute o script PHP periodicamente para verificar hosts que não enviaram telemetria:

```bash
# Execução via cron (a cada 5 minutos)
*/5 * * * * /usr/bin/php /caminho/para/scripts/check-offline-hosts.php

# Execução manual com output verbose
php scripts/check-offline-hosts.php -v
```

## Visualização

### Dashboard

O dashboard principal mostra um resumo dos hosts:
- Total de hosts
- Hosts online
- Hosts offline
- Hosts sem telemetria

### Lista de Hosts

A lista de hosts exibe o status de conexão de cada host com indicadores visuais:
- 🟢 **Online** - Host enviou telemetria recentemente
- 🔴 **Offline** - Host não enviou telemetria dentro do threshold
- ⚫ **Desconhecido** - Host nunca enviou telemetria ou telemetria desabilitada

### Detalhes do Host

A página de detalhes do host mostra:
- Status atual (online/offline)
- Último contato
- Configurações de telemetria
- Métricas coletadas (CPU, memória, disco)

## Integração com Agente de Backup

O agente `BackupAgent.ps1` pode ser configurado para enviar telemetria automaticamente:

```powershell
# No arquivo de configuração do agente (config.json)
{
    "api_url": "https://backup.empresa.com",
    "api_token": "seu-token",
    "telemetry": {
        "enabled": true,
        "interval_minutes": 5
    }
}
```

## Métricas Coletadas

O script de telemetria coleta automaticamente:

| Métrica | Descrição |
|---------|-----------|
| `cpu_percent` | Uso de CPU em porcentagem |
| `memory_percent` | Uso de memória em porcentagem |
| `memory_total_mb` | Total de memória em MB |
| `memory_used_mb` | Memória usada em MB |
| `disk_percent` | Uso do disco do sistema em porcentagem |
| `disk_total_gb` | Tamanho total do disco em GB |
| `disk_used_gb` | Espaço usado em GB |
| `uptime_seconds` | Tempo de atividade em segundos |
| `uptime_days` | Tempo de atividade em dias |
