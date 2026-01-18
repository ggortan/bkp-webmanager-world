# Guia de Migração para Sistema Baseado em Rotinas

## Visão Geral

Este guia fornece instruções passo a passo para migrar do sistema antigo (baseado em servidores) para o novo sistema baseado em rotinas.

## ⚠️ IMPORTANTE: Compatibilidade

**A migração é OPCIONAL.** O sistema antigo continua funcionando perfeitamente. Você pode:

1. Continuar usando o formato antigo (servidor + rotina)
2. Migrar gradualmente para o novo formato
3. Usar ambos os formatos simultaneamente

## Pré-requisitos

1. Backup completo do banco de dados
2. Acesso ao servidor MySQL
3. Acesso à interface administrativa do sistema
4. Acesso aos servidores/hosts onde os agentes estão instalados

## Passo 1: Backup do Banco de Dados

```bash
# Crie um backup completo antes de executar a migração
mysqldump -u root -p backup_webmanager > backup_webmanager_antes_migracao_$(date +%Y%m%d).sql
```

## Passo 2: Executar Migrações do Banco de Dados

### Migração 002: Sistema Baseado em Rotinas

```bash
# Execute o script de migração de rotinas
mysql -u root -p backup_webmanager < database/migrations/002_transform_to_routine_based.sql
```

### Migração 003: Renomeação de Servidores para Hosts

```bash
# Execute o script de migração de nomenclatura
mysql -u root -p backup_webmanager < database/migrations/003_rename_servidores_to_hosts.sql
```

### O que as migrações fazem:

**Migração 002** ✅:
- Adiciona novos campos à tabela `rotinas_backup`:
  - `cliente_id` - Vínculo direto com cliente
  - `routine_key` - Chave única para identificação
  - `host_info` - Informações do host (JSON)
- Torna `servidor_id` opcional em `rotinas_backup` e `execucoes_backup`
- Gera `routine_key` automaticamente para todas as rotinas existentes
- Preenche `cliente_id` com base no servidor vinculado
- Cria views `v_rotinas_completas` e `v_execucoes_completas`

**Migração 003** ✅:
- Renomeia tabela `servidores` para `hosts`
- Renomeia coluna `servidor_id` para `host_id` em todas as tabelas
- Atualiza índices e foreign keys
- Recria views com novos nomes
- Adiciona novos campos: `descricao` e `tipo`
- **Mantém todos os dados existentes intactos**

✅ **Preserva todos os dados existentes - ZERO perda de dados**

## Passo 3: Verificar Migração

### Via Interface Web

1. Acesse **Clientes** no menu
2. Clique em um cliente
3. Clique em **Rotinas**
4. Verifique se as rotinas existentes estão listadas
5. Verifique se cada rotina possui uma `routine_key`

### Via Banco de Dados

```sql
-- Verificar se todos os campos foram adicionados
DESCRIBE rotinas_backup;

-- Verificar se routine_keys foram geradas
SELECT id, nome, routine_key, cliente_id FROM rotinas_backup LIMIT 10;

-- Verificar se cliente_id foi preenchido
SELECT COUNT(*) as total_rotinas, 
       COUNT(cliente_id) as com_cliente_id,
       COUNT(routine_key) as com_routine_key
FROM rotinas_backup;

-- Resultado esperado: total_rotinas = com_cliente_id = com_routine_key
```

## Passo 4: Configurar Rotinas (Opcional - Novos Recursos)

### 4.1. Acessar Gestão de Rotinas

1. Acesse um cliente
2. Clique em **Rotinas**
3. Veja a lista de rotinas existentes (migradas automaticamente)

### 4.2. Criar Nova Rotina Independente

1. Clique em **Nova Rotina**
2. Preencha:
   - Nome da rotina
   - Tipo de backup (full, incremental, etc.)
   - Destino
   - Agendamento (opcional)
   - Informações do host (opcional)
   - **NÃO** vincule a um servidor (deixe vazio)
3. Salve - uma `routine_key` será gerada automaticamente

### 4.3. Copiar Routine Key

1. Abra os detalhes da rotina
2. Copie a `routine_key` exibida
3. Esta chave será usada no agente

## Passo 5: Atualizar Agentes (Opcional)

### Opção A: Continuar Usando Formato Antigo

Nenhuma ação necessária. Seus agentes continuarão funcionando normalmente enviando:
- `servidor` + `rotina`

### Opção B: Migrar para Novo Formato

#### 5.1. Backup da Configuração Atual

```powershell
# Backup do config atual
Copy-Item "C:\BackupAgent\config\config.json" "C:\BackupAgent\config\config.json.backup"
```

#### 5.2. Atualizar Arquivo de Configuração

**ANTES (formato antigo):**
```json
{
  "agent": {
    "server_name": "SRV-BACKUP-01"
  },
  "api": {
    "url": "https://backup.example.com",
    "api_key": "client_api_key_here"
  },
  "collectors": {
    "windows_server_backup": {
      "enabled": true
    }
  }
}
```

**DEPOIS (novo formato):**
```json
{
  "agent": {
    "server_name": "SRV-BACKUP-01"
  },
  "api": {
    "url": "https://backup.example.com",
    "api_key": "client_api_key_here"
  },
  "rotinas": [
    {
      "routine_key": "rtk_cole_a_chave_aqui",
      "nome": "Backup_Windows_Server",
      "collector_type": "windows_server_backup",
      "enabled": true
    }
  ],
  "collectors": {
    "windows_server_backup": {
      "enabled": true
    }
  }
}
```

#### 5.3. Obter Routine Keys

Para cada rotina existente no servidor:

1. Acesse a interface web
2. Vá em **Clientes > [Seu Cliente] > Rotinas**
3. Localize a rotina migrada automaticamente
4. Copie a `routine_key`
5. Cole no campo `routine_key` da configuração do agente

#### 5.4. Testar Configuração

```powershell
# Testar conexão com a API
.\BackupAgent.ps1 -TestMode -Verbose

# Executar uma coleta de teste
.\BackupAgent.ps1 -RunOnce -Verbose
```

## Passo 6: Validação Final

### 6.1. Verificar Envios na Interface

1. Acesse **Backups** ou **Dashboard**
2. Verifique se as execuções aparecem corretamente
3. Verifique se estão vinculadas às rotinas corretas

### 6.2. Verificar Logs do Agente

```powershell
# Ver logs recentes
Get-Content "C:\BackupAgent\logs\agent_$(Get-Date -Format 'yyyy-MM-dd').log" -Tail 50
```

Procure por mensagens como:
```
[INFO] Backup enviado com sucesso - ID: 123
```

### 6.3. Verificar no Banco de Dados

```sql
-- Ver últimas execuções registradas
SELECT e.id, e.data_inicio, e.status, 
       r.nome as rotina_nome, r.routine_key,
       c.nome as cliente_nome
FROM execucoes_backup e
JOIN rotinas_backup r ON e.rotina_id = r.id
JOIN clientes c ON e.cliente_id = c.id
ORDER BY e.created_at DESC
LIMIT 10;
```

## Cenários de Uso

### Cenário 1: Manter Sistema Antigo

**Ação:** Nenhuma  
**Resultado:** Tudo continua funcionando como antes

### Cenário 2: Migração Gradual

**Ação:** 
1. Manter agentes antigos funcionando
2. Criar novas rotinas com routine_keys
3. Migrar agentes um por um

**Benefício:** Transição suave, sem interrupções

### Cenário 3: Migração Completa

**Ação:**
1. Executar migração do banco
2. Atualizar todos os agentes com routine_keys
3. Aproveitar novos recursos (múltiplas rotinas por host)

**Benefício:** Máxima flexibilidade

### Cenário 4: Sistema Híbrido

**Ação:**
1. Alguns agentes usam formato antigo
2. Alguns agentes usam formato novo

**Resultado:** Ambos funcionam simultaneamente

## Rollback (se necessário)

Se algo der errado, você pode reverter:

### 1. Restaurar Backup do Banco

```bash
# Parar aplicação (se possível)
# Restaurar backup
mysql -u root -p backup_webmanager < backup_webmanager_antes_migracao_YYYYMMDD.sql
```

### 2. Restaurar Configuração do Agente

```powershell
# Restaurar backup da configuração
Copy-Item "C:\BackupAgent\config\config.json.backup" "C:\BackupAgent\config\config.json"
```

## Troubleshooting

### Problema: Routine key não aceita na API

**Causa:** Rotina não encontrada ou routine_key incorreta

**Solução:**
1. Verifique se a routine_key está correta
2. Verifique se a rotina pertence ao cliente correto
3. Verifique os logs: `SELECT * FROM logs WHERE categoria = 'api' ORDER BY created_at DESC LIMIT 10`

### Problema: Agente não envia dados

**Causa:** Configuração incorreta ou problemas de rede

**Solução:**
1. Teste conexão: `.\BackupAgent.ps1 -TestMode`
2. Verifique logs do agente
3. Verifique API Key e URL
4. Teste com formato antigo primeiro

### Problema: Execuções não aparecem na rotina correta

**Causa:** Mapeamento incorreto de rotina

**Solução:**
1. Verifique se a routine_key está correta no agente
2. Consulte: `SELECT * FROM rotinas_backup WHERE routine_key = 'sua_routine_key'`
3. Verifique se a rotina está ativa

## FAQ

**P: Preciso atualizar meus agentes imediatamente?**  
R: Não. O formato antigo continua funcionando indefinidamente.

**P: Posso criar novas rotinas sem vincular a servidores?**  
R: Sim! Esta é a nova abordagem recomendada.

**P: O que acontece com meus dados existentes?**  
R: Todos os dados são preservados e migrados automaticamente.

**P: Posso ter múltiplas rotinas para o mesmo servidor?**  
R: Sim! Este é um dos principais benefícios da nova arquitetura.

**P: E se eu quiser voltar ao sistema antigo?**  
R: Você pode usar o formato antigo a qualquer momento, mesmo após a migração.

**P: A migração afeta o desempenho?**  
R: Não. O sistema foi otimizado para ambos os formatos.

## Suporte

Para dúvidas ou problemas durante a migração:

1. Consulte a [documentação completa](TRANSFORMACAO_ROTINAS.md)
2. Verifique os logs do sistema
3. Entre em contato com a equipe de suporte

---

**Última atualização:** 18/01/2026  
**Versão do Guia:** 1.0
