# Backup WebManager – World Informática

Sistema centralizado de monitoramento de backups desenvolvido em PHP puro.

![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)

## 📋 Índice

- [Sobre](#sobre)
- [Funcionalidades](#funcionalidades)
- [Stack Tecnológica](#stack-tecnológica)
- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Configuração](#configuração)
- [Uso da API](#uso-da-api)
- [Agentes de Backup](#agentes-de-backup)
- [Estrutura do Projeto](#estrutura-do-projeto)

## 📖 Sobre

O **Backup WebManager** é uma aplicação web corporativa que centraliza o monitoramento das rotinas de backup executadas em servidores e estações Windows. Substitui o modelo tradicional de envio de relatórios por e-mail, oferecendo:

- Dashboard visual com status dos backups
- Histórico completo de execuções
- Alertas de falhas
- Relatórios automáticos
- API REST para integração

## ✨ Funcionalidades

### Dashboard
- Visão geral de todos os backups
- Estatísticas de sucesso, falha e alertas
- Gráficos por período
- Status por cliente

### Gestão de Clientes
- Cadastro completo de clientes
- API Key individual por cliente
- Hosts organizados por cliente
- Configuração de relatórios

### Gestão de Hosts
- CRUD completo de hosts
- Vinculação opcional de rotinas a hosts
- Informações detalhadas: nome, hostname, IP, SO, tipo
- Estatísticas de execuções por host
- Suporte a: servidores, estações, VMs, containers

### Gestão de Rotinas de Backup
- Rotinas independentes vinculadas diretamente aos clientes
- Routine Key única para cada rotina
- Suporte a múltiplas rotinas por host
- Informações do host armazenadas em JSON
- Gerenciamento completo via interface web

### Histórico de Backups
- Listagem com filtros avançados
- Detalhes de cada execução
- Exportação para CSV

### Relatórios
- Relatório geral do sistema
- Relatório por cliente
- Envio por e-mail
- Exportação em CSV

### Usuários e Permissões
- Autenticação via Microsoft Entra (Azure AD)
- Três níveis de acesso: Admin, Operador, Visualização
- Gestão de usuários

### API REST
- Endpoint seguro para recebimento de dados
- Autenticação via API Key
- Validação completa dos dados
- Formato baseado em Routine Key

## 🛠 Stack Tecnológica

- **Backend**: PHP 8+ (puro, sem frameworks, sem dependências externas)
- **Frontend**: HTML5, Bootstrap 5.3, JavaScript
- **Banco de Dados**: MySQL 8.0
- **Autenticação**: Microsoft Entra (Azure AD) via OAuth 2.0
- **SMTP**: Implementação nativa de SMTP

## 📦 Requisitos

- PHP 8.0 ou superior
- MySQL 8.0 ou superior
- Apache com mod_rewrite ou Nginx
- Extensões PHP: PDO, PDO_MySQL, cURL, JSON, mbstring

## 🚀 Instalação

### 1. Clone o repositório

```bash
git clone https://github.com/seu-usuario/bkp-webmanager-world.git
cd bkp-webmanager-world
```

### 2. Configure o ambiente

```bash
cp config/config.example.php config/config.php
```

Edite o arquivo `config/config.php` com suas configurações.

### 3. Crie o banco de dados

```bash
mysql -u root -p < database/schema.sql
```

### 4. Configure o servidor web

#### Apache

Aponte o DocumentRoot para a pasta `public/`.

```apache
<VirtualHost *:80>
    ServerName backup.seudominio.com
    DocumentRoot /var/www/bkp-webmanager-world/public
    
    <Directory /var/www/bkp-webmanager-world/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Nginx

```nginx
server {
    listen 80;
    server_name backup.seudominio.com;
    root /var/www/bkp-webmanager-world/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## ⚙️ Configuração

### Arquivo config/config.php

```php
return [
    // Aplicação
    'app' => [
        'name' => 'Backup WebManager',
        'env' => 'production',
        'debug' => false,
        'url' => 'https://backup.seudominio.com',
        'key' => 'sua-chave-secreta-32-caracteres',
    ],
    
    // Banco de Dados
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'backup_webmanager',
        'username' => 'seu_usuario',
        'password' => 'sua_senha',
    ],
    
    // Microsoft Entra (Azure AD)
    'azure' => [
        'client_id' => 'seu-client-id',
        'client_secret' => 'seu-client-secret',
        'tenant_id' => 'seu-tenant-id',
        'redirect_uri' => 'https://backup.seudominio.com/auth/callback',
    ],
    
    // SMTP (E-mail)
    'mail' => [
        'host' => 'smtp.office365.com',
        'port' => 587,
        'username' => 'noreply@seudominio.com',
        'password' => 'sua_senha',
        'encryption' => 'tls',
        'from_address' => 'noreply@seudominio.com',
        'from_name' => 'Backup WebManager',
    ],
];
```

### Configuração do Microsoft Entra

1. Acesse [Azure Portal](https://portal.azure.com)
2. Vá em **Azure Active Directory** > **App registrations**
3. Crie um novo registro de aplicativo
4. Configure a URI de redirecionamento: `https://seu-dominio/auth/callback`
5. Copie o Client ID e Tenant ID
6. Crie um Client Secret
7. Configure as permissões: `User.Read`, `openid`, `profile`, `email`

## 🔌 Uso da API

### Endpoint: Registrar Backup

```
POST /api/backup
```

**Headers:**
```
X-API-Key: {API_KEY}
Content-Type: application/json
```

**Body:**
```json
{
    "routine_key": "rtk_abc123xyz456",
    "data_inicio": "2024-01-15 22:00:00",
    "data_fim": "2024-01-15 22:45:00",
    "status": "sucesso",
    "tamanho_bytes": 5368709120,
    "destino": "\\\\NAS\\Backups\\SQL\\20240115",
    "mensagem_erro": null,
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

**Campos obrigatórios:**
- `routine_key` - Chave única da rotina (obtida na interface web)
- `data_inicio` - Data/hora de início
- `status` - Status da execução (`sucesso`, `falha`, `alerta`, `executando`)

**Resposta de sucesso (201):**
```json
{
    "success": true,
    "message": "Execução registrada com sucesso",
    "execucao_id": 123,
    "status": 201
}
```

### Endpoint: Listar Rotinas do Cliente

```
GET /api/rotinas
```

**Headers:**
```
X-API-Key: {API_KEY}
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
            "ativa": true
        }
    ],
    "total": 1
}
```

### Endpoint: Status da API

```
GET /api/status
```

**Resposta:**
```json
{
    "success": true,
    "status": "online",
    "version": "1.0.0",
    "timestamp": "2024-01-15T22:50:00-03:00"
}
```

## 🤖 Agentes de Backup

O sistema inclui agentes PowerShell para coleta automática de dados de backup.

### Localização

```
agent/
├── BackupAgent.ps1              # Agente principal
├── Install-BackupAgent.ps1      # Script de instalação
├── config/
│   └── config.example.json      # Exemplo de configuração
└── modules/
    ├── WindowsBackupCollector.psm1  # Coletor Windows Server Backup
    └── VeeamBackupCollector.psm1    # Coletor Veeam
```

### Instalação do Agente

1. Copie a pasta `agent/` para o servidor Windows
2. Execute como Administrador:

```powershell
.\Install-BackupAgent.ps1 -ApiUrl "https://backup.seudominio.com" -ApiKey "sua-api-key" -ServerName "SRV-PROD-01"
```

### Configuração

Edite o arquivo `config/config.json`:

```json
{
  "agent": {
    "version": "1.0.0",
    "server_name": "SRV-EXEMPLO-01",
    "check_interval_minutes": 60,
    "log_retention_days": 30
  },
  "api": {
    "url": "https://backup.seudominio.com",
    "api_key": "sua-api-key",
    "timeout_seconds": 30,
    "retry_attempts": 3
  },
  "rotinas": [
    {
      "routine_key": "rtk_sua_rotina",
      "nome": "Backup_Windows_Server",
      "collector_type": "windows_server_backup",
      "enabled": true
    }
  ],
  "collectors": {
    "windows_server_backup": {
      "enabled": true,
      "check_event_log": true,
      "event_log_hours": 24
    },
    "veeam_backup": {
      "enabled": false,
      "server": "localhost",
      "port": 9392
    }
  }
}
```

### Tipos de Coletores

- **Windows Server Backup**: Coleta dados do Windows Server Backup nativo
- **Veeam Backup**: Coleta dados do Veeam Backup & Replication
- **Task Scheduler**: Coleta dados de tarefas agendadas de backup

### Execução Manual

```powershell
# Execução única (para testes)
.\BackupAgent.ps1 -RunOnce

# Execução em modo de teste (não envia para API)
.\BackupAgent.ps1 -RunOnce -TestMode

# Execução contínua (modo serviço)
.\BackupAgent.ps1
```

## 📁 Estrutura do Projeto

```
bkp-webmanager-world/
├── app/
│   ├── Controllers/     # Controllers da aplicação
│   ├── Models/          # Modelos de dados
│   ├── Services/        # Serviços (Auth, Email, Backup)
│   ├── Middleware/      # Middlewares (Auth, CSRF, API)
│   ├── Helpers/         # Funções auxiliares
│   ├── Libraries/       # Bibliotecas nativas (JWT, SMTP)
│   └── Views/           # Templates HTML
├── agent/               # Agentes PowerShell para Windows
│   ├── modules/         # Módulos de coleta
│   └── config/          # Configuração do agente
├── config/              # Arquivos de configuração
├── database/
│   └── schema.sql       # Schema do banco de dados
├── public/              # Arquivos públicos (ponto de entrada)
├── routes/              # Definição de rotas
├── scripts/             # Scripts auxiliares PowerShell
├── docs/                # Documentação adicional
└── README.md            # Este arquivo
```

## 🔐 Segurança

- **SQL Injection**: Prevenido com PDO e prepared statements
- **XSS**: Sanitização de entrada e escape de saída
- **CSRF**: Token em todos os formulários
- **Autenticação**: Microsoft Entra (Azure AD) com OAuth 2.0
- **API**: Autenticação via API Key
- **Sessões**: Configurações seguras (httponly, samesite, secure)
- **Headers**: X-Content-Type-Options, X-Frame-Options, X-XSS-Protection

## 📄 Licença

Este projeto é proprietário da World Informática.

## 👥 Suporte

Para suporte, entre em contato com a equipe de TI da World Informática.
