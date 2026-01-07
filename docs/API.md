# Documentação da API

## Autenticação

Todas as requisições à API devem incluir a API Key no header de autorização.

### Headers obrigatórios

```
Authorization: Bearer {API_KEY}
Content-Type: application/json
```

Alternativamente, a API Key pode ser enviada via:
- Header `X-API-Key`: `{API_KEY}`
- Query string `?api_key={API_KEY}`

## Endpoints

### GET /api/status

Verifica o status da API.

**Autenticação:** Não requerida

**Resposta:**
```json
{
    "success": true,
    "status": "online",
    "version": "1.0.0",
    "timestamp": "2024-01-15T22:50:00-03:00"
}
```

---

### POST /api/backup

Registra uma execução de backup.

**Autenticação:** API Key obrigatória

**Body:**
```json
{
    "servidor": "string (obrigatório)",
    "rotina": "string (obrigatório)",
    "data_inicio": "datetime (obrigatório) - formato: Y-m-d H:i:s",
    "data_fim": "datetime (opcional) - formato: Y-m-d H:i:s",
    "status": "enum (obrigatório) - sucesso|falha|alerta|executando",
    "tamanho_bytes": "integer (opcional)",
    "destino": "string (opcional)",
    "mensagem_erro": "string (opcional)",
    "tipo_backup": "string (opcional) - ex: full, incremental, diferencial",
    "hostname": "string (opcional)",
    "ip": "string (opcional)",
    "sistema_operacional": "string (opcional)",
    "detalhes": "object (opcional) - dados adicionais em JSON"
}
```

**Exemplo de requisição:**
```bash
curl -X POST https://backup.seudominio.com/api/backup \
  -H "Authorization: Bearer SUA_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "servidor": "SRV-SQL-01",
    "rotina": "Backup_Diario_SQL",
    "data_inicio": "2024-01-15 22:00:00",
    "data_fim": "2024-01-15 22:45:00",
    "status": "sucesso",
    "tamanho_bytes": 5368709120,
    "destino": "\\\\NAS\\Backups\\SQL\\20240115",
    "tipo_backup": "full",
    "detalhes": {
        "database": "ERP_Producao",
        "compression": true
    }
}'
```

**Resposta de sucesso (201):**
```json
{
    "success": true,
    "message": "Execução registrada com sucesso",
    "execucao_id": 123,
    "status": 201
}
```

**Resposta de erro - Dados inválidos (422):**
```json
{
    "success": false,
    "error": "Dados inválidos",
    "errors": {
        "servidor": "O campo 'servidor' é obrigatório",
        "status": "Status inválido. Valores aceitos: sucesso, falha, alerta, executando"
    },
    "status": 422
}
```

**Resposta de erro - Não autorizado (401):**
```json
{
    "success": false,
    "error": "API Key inválida",
    "status": 401
}
```

---

### GET /api/me

Retorna informações do cliente autenticado.

**Autenticação:** API Key obrigatória

**Resposta:**
```json
{
    "success": true,
    "cliente": {
        "id": 1,
        "identificador": "cliente-abc",
        "nome": "Cliente ABC Ltda",
        "ativo": true
    }
}
```

## Status de Backup

| Status | Descrição |
|--------|-----------|
| `sucesso` | Backup concluído com sucesso |
| `falha` | Backup falhou |
| `alerta` | Backup concluído com alertas |
| `executando` | Backup em execução |

## Códigos de Resposta HTTP

| Código | Descrição |
|--------|-----------|
| 200 | Sucesso |
| 201 | Criado com sucesso |
| 400 | Requisição inválida |
| 401 | Não autorizado (API Key inválida) |
| 422 | Dados inválidos |
| 500 | Erro interno do servidor |

## Exemplos em Outras Linguagens

### Python

```python
import requests
import json

url = "https://backup.seudominio.com/api/backup"
headers = {
    "Authorization": "Bearer SUA_API_KEY",
    "Content-Type": "application/json"
}
data = {
    "servidor": "SRV-SQL-01",
    "rotina": "Backup_Diario",
    "data_inicio": "2024-01-15 22:00:00",
    "data_fim": "2024-01-15 22:45:00",
    "status": "sucesso",
    "tamanho_bytes": 5368709120
}

response = requests.post(url, headers=headers, json=data)
print(response.json())
```

### Bash (curl)

```bash
#!/bin/bash

API_URL="https://backup.seudominio.com/api/backup"
API_KEY="SUA_API_KEY"

curl -X POST "$API_URL" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "servidor": "'"$HOSTNAME"'",
    "rotina": "Backup_Diario",
    "data_inicio": "'"$(date -d '1 hour ago' '+%Y-%m-%d %H:%M:%S')"'",
    "data_fim": "'"$(date '+%Y-%m-%d %H:%M:%S')"'",
    "status": "sucesso"
}'
```

### C# (.NET)

```csharp
using System;
using System.Net.Http;
using System.Text;
using System.Text.Json;

var client = new HttpClient();
client.DefaultRequestHeaders.Add("Authorization", "Bearer SUA_API_KEY");

var data = new {
    servidor = "SRV-SQL-01",
    rotina = "Backup_Diario",
    data_inicio = DateTime.Now.AddHours(-1).ToString("yyyy-MM-dd HH:mm:ss"),
    data_fim = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"),
    status = "sucesso"
};

var content = new StringContent(
    JsonSerializer.Serialize(data),
    Encoding.UTF8,
    "application/json"
);

var response = await client.PostAsync("https://backup.seudominio.com/api/backup", content);
Console.WriteLine(await response.Content.ReadAsStringAsync());
```
