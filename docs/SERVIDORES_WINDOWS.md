# Configuração dos Servidores Windows

Este documento descreve como configurar os servidores Windows para enviar dados de backup para o Backup WebManager.

## Visão Geral

O envio de dados de backup é feito através do script PowerShell `Send-BackupReport.ps1`, que deve ser executado após cada rotina de backup.

## Requisitos

- Windows Server 2012 R2 ou superior
- PowerShell 5.1 ou superior
- Acesso à internet (para conectar à API)
- Certificado SSL válido no servidor do Backup WebManager

## Instalação

### 1. Copiar o Script

Copie o arquivo `Send-BackupReport.ps1` para uma pasta no servidor:

```
C:\Scripts\BackupWebManager\Send-BackupReport.ps1
```

### 2. Criar Diretório de Logs

```powershell
New-Item -ItemType Directory -Path "C:\Logs\BackupWebManager" -Force
```

### 3. Configurar o Script

Edite o script e configure as variáveis:

```powershell
# URL da API do Backup WebManager
$ApiUrl = "https://backup.seudominio.com/api/backup"

# API Key do cliente (obtida no painel do Backup WebManager)
$ApiKey = "SUA_API_KEY_AQUI"

# Nome do servidor (será usado para identificar este servidor)
$NomeServidor = $env:COMPUTERNAME
```

### 4. Obter a API Key

1. Acesse o Backup WebManager
2. Vá em **Clientes**
3. Selecione o cliente correspondente
4. Copie a **API Key** exibida

## Uso

### Execução Básica

```powershell
.\Send-BackupReport.ps1 -Rotina "Nome_da_Rotina" -Status "sucesso"
```

### Parâmetros Disponíveis

| Parâmetro | Obrigatório | Descrição |
|-----------|-------------|-----------|
| `-Rotina` | Sim | Nome da rotina de backup |
| `-Status` | Sim | sucesso, falha, alerta ou executando |
| `-Destino` | Não | Caminho do backup |
| `-MensagemErro` | Não | Mensagem de erro (para falhas) |
| `-DataInicio` | Não | Data/hora de início (padrão: 30min atrás) |
| `-DataFim` | Não | Data/hora de fim (padrão: agora) |
| `-TamanhoBytes` | Não | Tamanho do backup em bytes |
| `-TipoBackup` | Não | Tipo do backup (full, incremental, etc) |

### Exemplos

**Backup com sucesso:**
```powershell
.\Send-BackupReport.ps1 -Rotina "Backup_SQL_Diario" -Status "sucesso" -Destino "D:\Backups\SQL\20240115"
```

**Backup com falha:**
```powershell
.\Send-BackupReport.ps1 -Rotina "Backup_Files" -Status "falha" -MensagemErro "Erro: Disco de destino cheio"
```

**Backup com todas as informações:**
```powershell
.\Send-BackupReport.ps1 `
    -Rotina "Backup_Completo" `
    -Status "sucesso" `
    -Destino "\\NAS\Backups\SRV01\20240115" `
    -DataInicio "2024-01-15 22:00:00" `
    -DataFim "2024-01-15 23:30:00" `
    -TamanhoBytes 10737418240 `
    -TipoBackup "full"
```

## Integração com Rotinas de Backup

### Backup do SQL Server

Adicione ao final do seu script de backup SQL:

```powershell
# ... seu código de backup SQL aqui ...

# Status baseado no resultado
$status = if ($backupSucesso) { "sucesso" } else { "falha" }

# Enviar relatório
& "C:\Scripts\BackupWebManager\Send-BackupReport.ps1" `
    -Rotina "Backup_SQL_$DatabaseName" `
    -Status $status `
    -Destino $caminhoBackup `
    -MensagemErro $mensagemErro
```

### Backup do Windows Server Backup

Crie um script pós-backup:

```powershell
# Obter último backup
$lastBackup = Get-WBJob -Previous 1

# Determinar status
$status = switch ($lastBackup.JobState) {
    "Completed" { "sucesso" }
    "Failed" { "falha" }
    default { "alerta" }
}

# Enviar relatório
& "C:\Scripts\BackupWebManager\Send-BackupReport.ps1" `
    -Rotina "Windows_Server_Backup" `
    -Status $status `
    -DataInicio $lastBackup.StartTime `
    -DataFim $lastBackup.EndTime `
    -MensagemErro $lastBackup.ErrorDescription
```

### Veeam Backup

Configure um script pós-job no Veeam:

```powershell
param($Job)

$status = switch ($Job.LastResult) {
    "Success" { "sucesso" }
    "Warning" { "alerta" }
    "Failed" { "falha" }
    default { "alerta" }
}

& "C:\Scripts\BackupWebManager\Send-BackupReport.ps1" `
    -Rotina "Veeam_$($Job.Name)" `
    -Status $status `
    -DataInicio $Job.LastRun `
    -DataFim (Get-Date) `
    -TipoBackup $Job.BackupTargetType
```

## Configuração no Agendador de Tarefas

Se você não pode modificar seus scripts de backup existentes, crie uma tarefa agendada:

### 1. Abrir Agendador de Tarefas

```
taskschd.msc
```

### 2. Criar Nova Tarefa

1. Clique em **Criar Tarefa**
2. **Geral**:
   - Nome: `Backup WebManager - [Nome da Rotina]`
   - Executar com privilégios mais altos: ✓
   - Configurar para: Windows Server 2016

### 3. Configurar Gatilho

**Opção A - Executar após outro tarefa:**
1. Vá em **Gatilhos** > **Novo**
2. Iniciar a tarefa: **Em um evento**
3. Log: **Microsoft-Windows-TaskScheduler/Operational**
4. Origem: **TaskScheduler**
5. ID do Evento: **102** (tarefa concluída)

**Opção B - Executar em horário fixo:**
1. Vá em **Gatilhos** > **Novo**
2. Configure o horário após a janela de backup

### 4. Configurar Ação

1. Vá em **Ações** > **Novo**
2. Ação: **Iniciar um programa**
3. Programa/script:
   ```
   powershell.exe
   ```
4. Argumentos:
   ```
   -ExecutionPolicy Bypass -File "C:\Scripts\BackupWebManager\Send-BackupReport.ps1" -Rotina "Backup_Diario" -Status "sucesso" -Destino "D:\Backups"
   ```

### 5. Condições e Configurações

- Em **Condições**: desmarque todas
- Em **Configurações**:
  - Permitir que a tarefa seja executada sob demanda: ✓
  - Se a tarefa falhar, reiniciar: ✓

## Logs

Os logs são salvos em:
```
C:\Logs\BackupWebManager\backup_report_YYYY-MM-DD.log
```

### Verificar Logs

```powershell
Get-Content "C:\Logs\BackupWebManager\backup_report_$(Get-Date -Format 'yyyy-MM-dd').log" -Tail 50
```

### Limpar Logs Antigos

Adicione uma tarefa agendada para limpar logs com mais de 30 dias:

```powershell
Get-ChildItem "C:\Logs\BackupWebManager" -Filter "*.log" | 
    Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-30) } | 
    Remove-Item
```

## Tratamento de Falhas

Se o envio falhar, o script:
1. Tentará novamente até 3 vezes
2. Aguardará 10 segundos entre tentativas
3. Salvará o payload em arquivo para reenvio posterior

### Reenviar Relatórios Pendentes

Os arquivos pendentes são salvos em:
```
C:\Logs\BackupWebManager\failed_YYYYMMDD_HHmmss.json
```

Script para reenviar:

```powershell
$ApiUrl = "https://backup.seudominio.com/api/backup"
$ApiKey = "SUA_API_KEY"
$LogPath = "C:\Logs\BackupWebManager"

Get-ChildItem $LogPath -Filter "failed_*.json" | ForEach-Object {
    $payload = Get-Content $_.FullName -Raw
    
    try {
        $headers = @{
            "Authorization" = "Bearer $ApiKey"
            "Content-Type" = "application/json"
        }
        
        $response = Invoke-RestMethod -Uri $ApiUrl -Method Post -Headers $headers -Body $payload
        
        if ($response.success) {
            Remove-Item $_.FullName
            Write-Host "Reenviado: $($_.Name)"
        }
    }
    catch {
        Write-Host "Falha ao reenviar: $($_.Name)"
    }
}
```

## Solução de Problemas

### Erro de Certificado SSL

Se houver erro de certificado, adicione temporariamente (não recomendado em produção):

```powershell
[System.Net.ServicePointManager]::ServerCertificateValidationCallback = { $true }
```

### Erro de Conexão

1. Verifique se o servidor pode acessar a internet
2. Teste a conectividade:
   ```powershell
   Test-NetConnection -ComputerName backup.seudominio.com -Port 443
   ```

### Erro de Autenticação

1. Verifique se a API Key está correta
2. Verifique se o cliente está ativo no sistema
3. Teste a API Key:
   ```powershell
   Invoke-RestMethod -Uri "https://backup.seudominio.com/api/me" `
       -Headers @{ "Authorization" = "Bearer SUA_API_KEY" }
   ```

### Script não Executa

1. Verifique a política de execução:
   ```powershell
   Get-ExecutionPolicy
   Set-ExecutionPolicy RemoteSigned -Scope LocalMachine
   ```

2. Execute manualmente para ver erros:
   ```powershell
   & "C:\Scripts\BackupWebManager\Send-BackupReport.ps1" -Rotina "Teste" -Status "sucesso"
   ```
