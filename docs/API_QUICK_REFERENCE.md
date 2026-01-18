# Quick Reference: API de Backup

## Autenticação

Todas as requisições (exceto `/api/status`) requerem autenticação via API Key:

```
Header: Authorization: Bearer {API_KEY}
```

A API Key é obtida na interface web, na página do cliente.

## Endpoints

### GET `/api/status`

Verifica o status da API (não requer autenticação).

**Resposta:**
```json
{
  "success": true,
  "status": "online",
  "version": "1.0.0"
}
```

### GET `/api/me`

Retorna informações do cliente autenticado.

**Resposta:**
```json
{
  "success": true,
  "cliente": {
    "id": 1,
    "identificador": "CLIENTE-01",
    "nome": "Cliente Exemplo",
    "ativo": true
  }
}
```

### GET `/api/rotinas`

Lista todas as rotinas ativas do cliente.

**Resposta:**
```json
{
  "success": true,
  "rotinas": [
    {
      "id": 1,
      "routine_key": "rtk_abc123...",
      "nome": "Backup_SQL",
      "tipo": "full",
      "ativa": true
    }
  ],
  "total": 1
}
```

### POST `/api/backup`

Registra uma execução de backup.

#### Formato Novo (Recomendado)

```json
{
  "routine_key": "rtk_abc123xyz",
  "data_inicio": "2024-01-15 22:00:00",
  "data_fim": "2024-01-15 22:45:00",
  "status": "sucesso",
  "tamanho_bytes": 5368709120,
  "destino": "\\\\NAS\\Backups",
  "tipo_backup": "full",
  "host_info": {
    "nome": "SRV-01",
    "hostname": "srv-01.domain.local",
    "ip": "192.168.1.100",
    "sistema_operacional": "Windows Server 2022"
  },
  "detalhes": {
    "database": "ERP",
    "compression": true
  }
}
```

#### Formato Antigo (Compatibilidade)

```json
{
  "servidor": "SRV-BACKUP-01",
  "rotina": "Backup_Diario",
  "data_inicio": "2024-01-15 22:00:00",
  "data_fim": "2024-01-15 22:45:00",
  "status": "sucesso",
  "tamanho_bytes": 5368709120,
  "destino": "\\\\NAS\\Backups"
}
```

**Resposta de Sucesso:**
```json
{
  "success": true,
  "message": "Execução registrada com sucesso",
  "execucao_id": 123,
  "status": 201
}
```

## Campos Obrigatórios

### Novo Formato
- `routine_key` - Chave única da rotina
- `data_inicio` - Data/hora de início (formato: Y-m-d H:i:s)
- `status` - Status da execução

### Formato Antigo
- `servidor` - Nome do servidor
- `rotina` - Nome da rotina
- `data_inicio` - Data/hora de início
- `status` - Status da execução

## Status Possíveis

- `sucesso` - Backup concluído com sucesso
- `falha` - Backup falhou
- `alerta` - Backup concluído com alertas
- `executando` - Backup em execução

## Exemplos com cURL

### Testar Status

```bash
curl https://backup.example.com/api/status
```

### Listar Rotinas

```bash
curl -H "Authorization: Bearer sua-api-key-aqui" \
     https://backup.example.com/api/rotinas
```

### Registrar Backup (Novo Formato)

```bash
curl -X POST \
  -H "Authorization: Bearer sua-api-key-aqui" \
  -H "Content-Type: application/json" \
  -d '{
    "routine_key": "rtk_abc123xyz",
    "data_inicio": "2024-01-15 22:00:00",
    "data_fim": "2024-01-15 22:45:00",
    "status": "sucesso",
    "tamanho_bytes": 5368709120,
    "destino": "\\\\NAS\\Backups",
    "host_info": {
      "nome": "SRV-01",
      "ip": "192.168.1.100"
    }
  }' \
  https://backup.example.com/api/backup
```

### Registrar Backup (Formato Antigo)

```bash
curl -X POST \
  -H "Authorization: Bearer sua-api-key-aqui" \
  -H "Content-Type: application/json" \
  -d '{
    "servidor": "SRV-BACKUP-01",
    "rotina": "Backup_Diario",
    "data_inicio": "2024-01-15 22:00:00",
    "data_fim": "2024-01-15 22:45:00",
    "status": "sucesso",
    "tamanho_bytes": 5368709120
  }' \
  https://backup.example.com/api/backup
```

## Exemplos com PowerShell

### Testar Status

```powershell
Invoke-RestMethod -Uri "https://backup.example.com/api/status" -Method Get
```

### Listar Rotinas

```powershell
$headers = @{
    "Authorization" = "Bearer sua-api-key-aqui"
}

Invoke-RestMethod -Uri "https://backup.example.com/api/rotinas" `
                  -Method Get `
                  -Headers $headers
```

### Registrar Backup (Novo Formato)

```powershell
$headers = @{
    "Authorization" = "Bearer sua-api-key-aqui"
    "Content-Type" = "application/json"
}

$body = @{
    routine_key = "rtk_abc123xyz"
    data_inicio = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
    data_fim = (Get-Date).AddMinutes(45).ToString("yyyy-MM-dd HH:mm:ss")
    status = "sucesso"
    tamanho_bytes = 5368709120
    destino = "\\NAS\Backups"
    host_info = @{
        nome = $env:COMPUTERNAME
        hostname = [System.Net.Dns]::GetHostName()
        ip = (Get-NetIPAddress -AddressFamily IPv4 -InterfaceAlias Ethernet).IPAddress
        sistema_operacional = (Get-WmiObject Win32_OperatingSystem).Caption
    }
} | ConvertTo-Json

Invoke-RestMethod -Uri "https://backup.example.com/api/backup" `
                  -Method Post `
                  -Headers $headers `
                  -Body $body
```

### Registrar Backup (Formato Antigo)

```powershell
$headers = @{
    "Authorization" = "Bearer sua-api-key-aqui"
    "Content-Type" = "application/json"
}

$body = @{
    servidor = $env:COMPUTERNAME
    rotina = "Backup_Diario"
    data_inicio = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
    data_fim = (Get-Date).AddMinutes(45).ToString("yyyy-MM-dd HH:mm:ss")
    status = "sucesso"
    tamanho_bytes = 5368709120
} | ConvertTo-Json

Invoke-RestMethod -Uri "https://backup.example.com/api/backup" `
                  -Method Post `
                  -Headers $headers `
                  -Body $body
```

## Códigos de Resposta HTTP

- `200 OK` - Sucesso (GET)
- `201 Created` - Backup registrado com sucesso
- `400 Bad Request` - Dados inválidos
- `401 Unauthorized` - API Key inválida ou ausente
- `404 Not Found` - Recurso não encontrado
- `422 Unprocessable Entity` - Erros de validação
- `500 Internal Server Error` - Erro no servidor

## Erros Comuns

### API Key inválida

```json
{
  "success": false,
  "error": "API Key inválida",
  "status": 401
}
```

### Routine Key não encontrada

```json
{
  "success": false,
  "error": "Rotina não encontrada com a routine_key fornecida",
  "status": 500
}
```

### Dados inválidos

```json
{
  "success": false,
  "error": "Dados inválidos",
  "errors": {
    "status": "O campo 'status' é obrigatório"
  },
  "status": 422
}
```

## Boas Práticas

1. **Use HTTPS** - Sempre use conexões seguras em produção
2. **Armazene API Keys com Segurança** - Nunca commite em código
3. **Implemente Retry** - Use retry com backoff exponencial
4. **Valide Dados** - Valide antes de enviar para evitar erros
5. **Monitore Logs** - Acompanhe logs de API para detectar problemas
6. **Use Timeout** - Configure timeout adequado (30-60 segundos)
7. **Novo Formato** - Prefira o novo formato com routine_key para novos projetos

## Limites

- Timeout padrão: 30 segundos
- Tamanho máximo do JSON: 10 MB
- Rate limit: 100 requisições/minuto por API Key (configurável)

---

**Documentação completa:** [TRANSFORMACAO_ROTINAS.md](TRANSFORMACAO_ROTINAS.md)  
**Guia de migração:** [GUIA_MIGRACAO.md](GUIA_MIGRACAO.md)
