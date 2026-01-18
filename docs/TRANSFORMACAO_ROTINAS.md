# Transformação para Sistema Baseado em Rotinas

## Visão Geral

Este documento descreve a transformação da arquitetura do Backup WebManager de um sistema baseado em **servidores** para um sistema baseado em **rotinas independentes**.

## Motivação

### Antes (Sistema Baseado em Servidores)
- Os backups eram vinculados a servidores específicos
- Cada servidor pertencia a um cliente
- As rotinas eram subordinadas aos servidores
- Limitação: não era possível ter múltiplas rotinas independentes para o mesmo host

### Depois (Sistema Baseado em Rotinas)
- As rotinas são entidades independentes vinculadas diretamente aos clientes
- Cada rotina possui uma **Routine Key** única para identificação
- O mesmo host pode ter múltiplas rotinas cadastradas
- Hosts não precisam ser servidores - podem ser estações, VMs, containers, etc.
- Maior flexibilidade e abrangência do sistema

## Mudanças no Banco de Dados

### Tabela `rotinas_backup`

#### Novos Campos
- `cliente_id` (INT, NOT NULL) - Vínculo direto com o cliente
- `routine_key` (VARCHAR(64), UNIQUE, NOT NULL) - Chave única para identificação na API
- `host_info` (JSON) - Informações do host (nome, hostname, IP, SO, etc.)

#### Campos Modificados
- `servidor_id` - Agora é OPCIONAL (NULL permitido)
  - Mantido para compatibilidade com dados existentes
  - Pode ser usado para vincular rotina a um servidor específico, se desejado

#### Índices Adicionados
- `idx_cliente` - Para buscar rotinas por cliente
- `idx_routine_key` - Para buscar rotinas pela chave única

### Tabela `execucoes_backup`

#### Campos Modificados
- `servidor_id` - Agora é OPCIONAL (NULL permitido)
  - Será NULL para rotinas sem servidor vinculado
  - Informações do host são armazenadas em `detalhes->host_info`

### Views Criadas

#### `v_rotinas_completas`
View que combina dados de rotinas, clientes e servidores para facilitar consultas.

#### `v_execucoes_completas`
View que combina dados de execuções com informações completas de rotinas, clientes e servidores.

## Mudanças na API

### Novo Formato de Requisição

#### Usando Routine Key (Recomendado)
```json
{
    "routine_key": "rtk_abc123xyz",
    "data_inicio": "2024-01-15 22:00:00",
    "data_fim": "2024-01-15 22:45:00",
    "status": "sucesso",
    "tamanho_bytes": 5368709120,
    "destino": "\\\\NAS\\Backups\\SQL\\20240115",
    "tipo_backup": "full",
    "host_info": {
        "nome": "SRV-BACKUP-01",
        "hostname": "srv-backup-01.domain.local",
        "ip": "192.168.1.100",
        "sistema_operacional": "Windows Server 2022"
    },
    "detalhes": {
        "database": "ERP_Producao",
        "compression": true
    }
}
```

#### Formato Antigo (Compatibilidade Mantida)
```json
{
    "servidor": "SRV-BACKUP-01",
    "rotina": "Backup_Diario_SQL",
    "data_inicio": "2024-01-15 22:00:00",
    "data_fim": "2024-01-15 22:45:00",
    "status": "sucesso",
    "tamanho_bytes": 5368709120,
    "destino": "\\\\NAS\\Backups\\SQL\\20240115",
    "tipo_backup": "full"
}
```

### Novo Endpoint

#### GET `/api/rotinas`
Retorna todas as rotinas ativas do cliente autenticado.

**Resposta:**
```json
{
    "success": true,
    "rotinas": [
        {
            "id": 1,
            "routine_key": "rtk_abc123xyz",
            "nome": "Backup_SQL_Diario",
            "tipo": "full",
            "destino": "\\\\NAS\\Backups",
            "agendamento": "Diário às 22h",
            "host_info": {...},
            "ativa": true
        }
    ],
    "total": 1
}
```

## Mudanças no Agente PowerShell

### Nova Estrutura de Configuração

O arquivo `config.json` agora suporta múltiplas rotinas com routine_keys:

```json
{
  "api": {
    "url": "https://backup.example.com",
    "api_key": "client-api-key-here"
  },
  "rotinas": [
    {
      "routine_key": "rtk_rotina_sql",
      "nome": "Backup_SQL",
      "collector_type": "windows_server_backup",
      "enabled": true
    },
    {
      "routine_key": "rtk_rotina_veeam",
      "nome": "Backup_Veeam",
      "collector_type": "veeam_backup",
      "enabled": true
    }
  ]
}
```

### Envio de Dados

O agente agora envia:
1. `routine_key` - Identifica a rotina
2. `host_info` - Informações atualizadas do host
3. Dados de backup específicos da rotina

## Interface Web

### Nova Gestão de Rotinas

1. **Menu Clientes > [Cliente] > Rotinas**
   - Lista todas as rotinas do cliente
   - Exibe routine_key de cada rotina
   - Permite criar, editar e excluir rotinas

2. **Criar Nova Rotina**
   - Nome da rotina
   - Tipo de backup (full, incremental, etc.)
   - Destino
   - Agendamento
   - Host Info (opcional)
   - Servidor vinculado (opcional)
   - Uma **Routine Key** é gerada automaticamente

3. **Detalhes da Rotina**
   - Informações completas da rotina
   - Routine Key (copiável)
   - Últimas execuções
   - Host Info atualizado

### Funcionalidades Admin

- **Regenerar Routine Key** - Gera nova chave (invalida a anterior)
- **Vincular/Desvincular Servidor** - Opcional
- **Ativar/Desativar Rotina** - Controle de rotinas ativas

## Migração de Dados Existentes

A migração é **automática** ao executar o script SQL:

1. Todas as rotinas existentes recebem:
   - `cliente_id` - Extraído do servidor vinculado
   - `routine_key` - Gerada automaticamente (UUID)
   - `servidor_id` - Mantido para compatibilidade

2. Nenhuma execução existente é perdida

3. Compatibilidade total mantida:
   - API antiga continua funcionando
   - Servidores existentes continuam válidos
   - Agentes antigos continuam funcionando

## Compatibilidade Retroativa

✅ **API antiga (servidor + rotina)** - Totalmente suportada  
✅ **Agentes antigos** - Continuam funcionando normalmente  
✅ **Dados existentes** - Migrados automaticamente  
✅ **Servidores** - Continuam sendo suportados como opção  

## Benefícios da Nova Arquitetura

1. **Flexibilidade**: Rotinas independentes de servidores
2. **Escalabilidade**: Múltiplas rotinas por host
3. **Simplicidade**: Configuração via routine_key única
4. **Abrangência**: Suporte a qualquer tipo de host
5. **Rastreabilidade**: Cada rotina tem identificador único
6. **Compatibilidade**: Sistema antigo continua funcionando

## Guia de Implementação para Novos Clientes

### Passo 1: Criar Cliente
Acesse **Clientes > Criar Novo** e preencha os dados do cliente.

### Passo 2: Criar Rotinas
1. Acesse o cliente criado
2. Clique em **Rotinas**
3. Clique em **Nova Rotina**
4. Preencha:
   - Nome da rotina
   - Tipo de backup
   - Informações do host (opcional)
   - Servidor vinculado (opcional)
5. Salve - uma **Routine Key** será gerada

### Passo 3: Configurar Agente
1. Copie a **Routine Key** gerada
2. Copie a **API Key** do cliente
3. Configure o agente:

```json
{
  "api": {
    "url": "https://seu-servidor/api",
    "api_key": "CLIENT_API_KEY"
  },
  "rotinas": [
    {
      "routine_key": "ROUTINE_KEY_COPIADA",
      "nome": "Backup_Principal",
      "collector_type": "windows_server_backup",
      "enabled": true
    }
  ]
}
```

### Passo 4: Executar Agente
O agente enviará dados automaticamente usando a routine_key.

## Guia de Migração para Clientes Existentes

### Opção 1: Continuar Usando Formato Antigo
Nenhuma ação necessária - tudo continua funcionando.

### Opção 2: Migrar para Novo Formato
1. Acesse o cliente
2. Vá em **Rotinas**
3. Veja as rotinas já migradas (routine_keys geradas automaticamente)
4. Copie as routine_keys
5. Atualize a configuração do agente para usar routine_keys

## Suporte e Troubleshooting

### Problemas Comuns

**Q: O formato antigo (servidor + rotina) ainda funciona?**  
A: Sim! A compatibilidade é total e permanente.

**Q: Preciso migrar meus agentes existentes?**  
A: Não é obrigatório, mas recomendado para aproveitar os novos recursos.

**Q: O que acontece se eu regenerar uma routine_key?**  
A: A chave antiga é invalidada. Você precisará atualizar o agente com a nova chave.

**Q: Posso ter rotinas sem servidor vinculado?**  
A: Sim! É a nova abordagem recomendada. O servidor é opcional.

**Q: Os dados históricos são preservados?**  
A: Sim! Todos os dados existentes são preservados e migrados automaticamente.

## Changelog

### Versão 2.0.0 - Transformação para Sistema Baseado em Rotinas

**Added:**
- Campo `routine_key` em rotinas
- Campo `cliente_id` direto em rotinas
- Campo `host_info` para informações do host
- Endpoint `/api/rotinas` para listar rotinas do cliente
- Interface de gerenciamento de rotinas
- Views `v_rotinas_completas` e `v_execucoes_completas`
- Suporte a múltiplas rotinas por host
- Documentação completa da transformação

**Changed:**
- `servidor_id` agora é opcional em rotinas
- `servidor_id` agora é opcional em execuções
- API aceita `routine_key` como identificador principal
- Configuração do agente suporta múltiplas rotinas

**Maintained:**
- Compatibilidade total com formato antigo
- Todos os dados existentes preservados
- Agentes antigos continuam funcionando
- Servidores continuam sendo suportados

---

**Última atualização:** Janeiro 2024  
**Versão do Schema:** 002
