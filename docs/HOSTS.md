# Hosts - Documentação

## Visão Geral

Os **Hosts** (anteriormente chamados de "Servidores") são uma camada opcional de organização no Backup WebManager. Eles representam máquinas físicas, virtuais, containers ou qualquer ambiente que execute rotinas de backup.

## Mudança de Nomenclatura

Com a evolução do sistema para suportar rotinas independentes, o termo "Servidor" foi substituído por "Host" para melhor refletir a variedade de ambientes suportados:

- **Antes**: Servidores (tabela `servidores`, coluna `servidor_id`)
- **Depois**: Hosts (tabela `hosts`, coluna `host_id`)

### Migration

A migração do banco de dados é realizada automaticamente através do arquivo:
```
database/migrations/003_rename_servidores_to_hosts.sql
```

Esta migração:
- Renomeia a tabela `servidores` para `hosts`
- Renomeia colunas `servidor_id` para `host_id` em todas as tabelas relacionadas
- Atualiza índices e foreign keys
- Recria views com os novos nomes
- Mantém todos os dados existentes

## Características dos Hosts

### Campos

- **nome**: Nome identificador do host (obrigatório, único por cliente)
- **hostname**: FQDN ou hostname da máquina
- **ip**: Endereço IP
- **sistema_operacional**: Sistema operacional instalado
- **tipo**: Tipo do host (server, workstation, vm, container)
- **observacoes**: Informações adicionais
- **ativo**: Se o host está ativo ou inativo

### Tipos de Host

O campo `tipo` permite categorizar o host:

- **server**: Servidor físico ou dedicado
- **workstation**: Estação de trabalho
- **vm**: Máquina virtual
- **container**: Container Docker/Kubernetes

## Relacionamento com Rotinas

Os hosts têm relacionamento **opcional** com rotinas de backup:

### Rotinas Vinculadas a Host

```php
// Rotina com host específico
$rotina = [
    'cliente_id' => 1,
    'host_id' => 5,  // Vinculada ao host
    'nome' => 'Backup_SQL_Server',
    'routine_key' => 'rtk_abc123...'
];
```

### Rotinas Independentes

```php
// Rotina sem host específico
$rotina = [
    'cliente_id' => 1,
    'host_id' => null,  // Independente
    'nome' => 'Backup_Cloud',
    'routine_key' => 'rtk_xyz789...'
];
```

## CRUD de Hosts

### Listar Hosts de um Cliente

```
GET /clientes/{clienteId}/hosts
```

### Criar Novo Host

```
GET  /clientes/{clienteId}/hosts/criar
POST /clientes/{clienteId}/hosts
```

Validações:
- Nome obrigatório e único por cliente
- IP válido (se fornecido)
- Hostname máximo 255 caracteres

### Ver Detalhes do Host

```
GET /clientes/{clienteId}/hosts/{id}
```

Exibe:
- Informações do host
- Estatísticas de execuções (7 dias)
- Rotinas vinculadas
- Últimas execuções de backup

### Editar Host

```
GET  /clientes/{clienteId}/hosts/{id}/editar
POST /clientes/{clienteId}/hosts/{id}
```

### Deletar Host

```
POST /clientes/{clienteId}/hosts/{id}/delete
```

Regras:
- Não é possível deletar host com rotinas ativas vinculadas
- Host pode ser deletado se todas as rotinas estiverem inativas
- Deletar host remove o vínculo, mas não deleta as rotinas

### Alternar Status

```
POST /clientes/{clienteId}/hosts/{id}/toggle-status
```

Alterna entre ativo/inativo.

## Model: Host

### Métodos Principais

```php
// Listar hosts de um cliente
Host::byCliente(int $clienteId): array

// Listar apenas hosts ativos
Host::ativosByCliente(int $clienteId): array

// Buscar por nome e cliente
Host::findByNomeAndCliente(string $nome, int $clienteId): ?array

// Criar ou encontrar host
Host::findOrCreate(int $clienteId, string $nome, array $extraData): array

// Host com estatísticas
Host::withStats(int $id): ?array

// Verificar se pode deletar
Host::canDelete(int $id): bool

// Alternar status
Host::toggleStatus(int $id): bool
```

### Estatísticas

O método `withStats()` retorna:

- `total_rotinas`: Número de rotinas vinculadas
- `ultima_execucao`: Última execução de backup
- `stats`: Estatísticas dos últimos 7 dias
  - `total`: Total de execuções
  - `sucesso`: Execuções com sucesso
  - `falha`: Execuções com falha

## Formato da API

A API de backup utiliza o formato baseado em `routine_key`:

```json
{
  "routine_key": "rtk_abc123456789...",
  "status": "sucesso",
  "data_inicio": "2026-01-18 22:00:00",
  "data_fim": "2026-01-18 22:15:00",
  "tamanho_bytes": 1048576,
  "destino": "\\NAS\Backups\SQL",
  "host_info": {
    "nome": "SRV-FILESERVER-01",
    "hostname": "fileserver.empresa.local",
    "ip": "192.168.1.100",
    "sistema_operacional": "Windows Server 2022"
  }
}
```

O campo `host_info` é opcional e pode ser atualizado automaticamente pelo agente a cada execução.

## Melhores Práticas

### Quando Usar Hosts

Use hosts quando:
- Precisa organizar rotinas por máquina/ambiente
- Quer visualizar estatísticas por servidor/VM
- Tem múltiplas rotinas executando no mesmo ambiente
- Precisa rastrear informações de infraestrutura (IP, SO, etc)

### Quando Usar Rotinas Independentes

Use rotinas independentes quando:
- A rotina não está associada a uma máquina específica
- É um backup de serviço cloud/SaaS
- A rotina executa em ambientes efêmeros/temporários
- A organização por host não faz sentido

### Nomenclatura

Convenções sugeridas para nomes de hosts:

```
SRV-{FUNÇÃO}-{NÚMERO}     # Ex: SRV-FILESERVER-01
VM-{AMBIENTE}-{FUNÇÃO}    # Ex: VM-PROD-WEB
WS-{DEPTO}-{USUÁRIO}      # Ex: WS-TI-ADMIN
CTR-{APP}-{INSTÂNCIA}     # Ex: CTR-POSTGRES-001
```

## Exemplos de Uso

### Criar Host via API/Service

```php
use App\Models\Host;

$host = Host::findOrCreate(
    clienteId: 1,
    nome: 'SRV-DATABASE-01',
    extraData: [
        'hostname' => 'db01.empresa.local',
        'ip' => '192.168.1.100',
        'sistema_operacional' => 'Windows Server 2022',
        'tipo' => 'server'
    ]
);
```

### Listar Rotinas de um Host

```php
use App\Models\RotinaBackup;

$rotinas = RotinaBackup::byHost($hostId);
$rotinasAtivas = RotinaBackup::ativasByHost($hostId);
```

### Verificar Antes de Deletar

```php
use App\Models\Host;

if (Host::canDelete($hostId)) {
    Host::delete($hostId);
} else {
    // Host tem rotinas ativas vinculadas
    echo "Não é possível deletar este host";
}
```

## Migração de Dados Existentes

Se você tinha dados com a nomenclatura antiga "servidor":

1. Execute a migration 003:
   ```bash
   mysql -u usuario -p backup_webmanager < database/migrations/003_rename_servidores_to_hosts.sql
   ```

2. Todos os dados são preservados:
   - Hosts existentes continuam funcionando
   - Rotinas mantêm seus vínculos
   - Execuções históricas permanecem intactas

3. A API antiga continua funcionando:
   - Requests com `"servidor"` são aceitos
   - Sistema cria/busca hosts automaticamente

## Conclusão

Os Hosts oferecem uma camada flexível de organização no Backup WebManager. Eles são opcionais mas recomendados quando você precisa organizar e rastrear backups por ambiente/máquina específica.

A mudança de "Servidor" para "Host" reflete melhor a variedade de ambientes modernos onde backups são executados, desde servidores físicos tradicionais até containers efêmeros.
