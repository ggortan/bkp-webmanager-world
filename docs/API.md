# Documentação da API

## Autenticação

Todas as requisições à API devem incluir a API Key do cliente.

### Header de autenticação

```
X-API-Key: {API_KEY}
Content-Type: application/json
```

A API Key é gerada automaticamente ao criar um cliente no sistema.

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
    "routine_key": "string (obrigatório) - chave única da rotina",
    "data_inicio": "datetime (obrigatório) - formato: Y-m-d H:i:s",
    "data_fim": "datetime (opcional) - formato: Y-m-d H:i:s",
    "status": "enum (obrigatório) - sucesso|falha|alerta|executando",
    "tamanho_bytes": "integer (opcional)",
    "destino": "string (opcional)",
    "mensagem_erro": "string (opcional)",
    "host_info": {
        "nome": "string (opcional) - nome do host",
        "hostname": "string (opcional) - hostname do sistema",
        "ip": "string (opcional) - endereço IP",
        "sistema_operacional": "string (opcional) - SO do host"
    },
    "detalhes": "object (opcional) - dados adicionais em JSON"
}
```

**Exemplo de requisição:**
```bash
curl -X POST https://backup.seudominio.com/api/backup \
  -H "X-API-Key: SUA_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "routine_key": "rtk_abc123xyz456",
    "data_inicio": "2024-01-15 22:00:00",
    "data_fim": "2024-01-15 22:45:00",
    "status": "sucesso",
    "tamanho_bytes": 5368709120,
    "destino": "\\\\NAS\\Backups\\SQL\\20240115",
    "host_info": {
        "nome": "SRV-SQL-01",
        "hostname": "srv-sql-01.domain.local",
        "ip": "192.168.1.50",
        "sistema_operacional": "Windows Server 2022"
    },
    "detalhes": {
        "database": "ERP_Producao",
        "compression": true,
        "tipo_backup": "full"
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
        "routine_key": "O campo 'routine_key' é obrigatório"
    },
    "status": 422
}
```

**Resposta de erro - Rotina não encontrada (422):**
```json
{
    "success": false,
    "error": "Dados inválidos",
    "errors": {
        "routine_key": "Rotina não encontrada com a routine_key fornecida"
    },
    "status": 422
}
```

**Resposta de erro - Não autorizado (401):**
```json
{
    "success": false,
    "error": "API Key inválida ou ausente",
    "status": 401
}
```

---

### GET /api/rotinas

Retorna todas as rotinas ativas do cliente autenticado.

**Autenticação:** API Key obrigatória

**Exemplo de requisição:**
```bash
curl -X GET https://backup.seudominio.com/api/rotinas \
  -H "X-API-Key: SUA_API_KEY"
```

**Resposta:**
```json
{
    "success": true,
    "rotinas": [
        {
            "id": 1,
            "routine_key": "rtk_abc123xyz456",
            "nome": "Backup_SQL_Diario",
            "tipo": "full",
            "destino": "\\\\NAS\\Backups",
            "agendamento": "Diário às 22h",
            "host_info": {
                "nome": "SRV-SQL-01",
                "hostname": "srv-sql-01.domain.local",
                "ip": "192.168.1.50"
            },
            "ativa": true
        }
    ],
    "total": 1
}
```

---

### GET /api/me

Retorna informações do cliente autenticado.

**Autenticação:** API Key obrigatória

**Exemplo de requisição:**
```bash
curl -X GET https://backup.seudominio.com/api/me \
  -H "X-API-Key: SUA_API_KEY"
```

**Resposta:**
```json
{
    "success": true,
    "cliente": {
        "id": 1,
        "identificador": "CLIENTE001",
        "nome": "Empresa Exemplo Ltda",
        "ativo": true
    }
}
```

---

## Códigos de Status HTTP

| Código | Descrição |
|--------|-----------|
| 200 | Sucesso |
| 201 | Criado com sucesso |
| 400 | Requisição inválida |
| 401 | Não autorizado |
| 403 | Acesso negado |
| 404 | Não encontrado |
| 422 | Dados inválidos |
| 500 | Erro interno do servidor |

## Valores de Status

| Status | Descrição |
|--------|-----------|
| `sucesso` | Backup concluído com sucesso |
| `falha` | Backup falhou |
| `alerta` | Backup concluído com avisos |
| `executando` | Backup em execução |

## Notas

1. A `routine_key` é gerada automaticamente ao criar uma rotina no sistema.
2. A `routine_key` pode ser visualizada e copiada na interface web.
3. O campo `host_info` é opcional mas recomendado para melhor rastreabilidade.
4. O campo `host_info` é armazenado na rotina para uso em execuções futuras.
