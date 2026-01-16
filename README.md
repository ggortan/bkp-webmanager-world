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
- [Script PowerShell](#script-powershell)
- [Estrutura do Projeto](#estrutura-do-projeto)

## 📖 Sobre

O **Backup WebManager** é uma aplicação web corporativa que centraliza o monitoramento das rotinas de backup executadas em servidores Windows. Substitui o modelo tradicional de envio de relatórios por e-mail, oferecendo:

- Dashboard visual com status dos backups
- Histórico completo de execuções
- Alertas de falhas
- Relatórios automáticos
- API REST para integração com servidores

## ✨ Funcionalidades

### Dashboard
- Visão geral de todos os backups
- Estatísticas de sucesso, falha e alertas
- Gráficos por período
- Status por cliente

### Gestão de Clientes
- Cadastro completo de clientes
- API Key individual por cliente
- Servidores vinculados automaticamente
- Configuração de relatórios

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
cp .env.example .env
```

Edite o arquivo `.env` com suas configurações.

### 3. Crie o banco de dados

```bash
mysql -u root -p < database/migrations/001_create_tables.sql
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

### Arquivo .env

```env
# Aplicação
APP_NAME="Backup WebManager"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://backup.seudominio.com
APP_KEY=sua-chave-secreta-32-chars

# Banco de Dados
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=backup_webmanager
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha

# Microsoft Entra (Azure AD)
AZURE_CLIENT_ID=seu-client-id
AZURE_CLIENT_SECRET=seu-client-secret
AZURE_TENANT_ID=seu-tenant-id
AZURE_REDIRECT_URI=https://backup.seudominio.com/auth/callback

# SMTP (E-mail)
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_USERNAME=noreply@seudominio.com
MAIL_PASSWORD=sua_senha
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@seudominio.com
MAIL_FROM_NAME="Backup WebManager"
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
Authorization: Bearer {API_KEY}
Content-Type: application/json
```

**Body:**
```json
{
    "servidor": "SRV-BACKUP-01",
    "rotina": "Backup_Diario_SQL",
    "data_inicio": "2024-01-15 22:00:00",
    "data_fim": "2024-01-15 22:45:00",
    "status": "sucesso",
    "tamanho_bytes": 5368709120,
    "destino": "\\\\NAS\\Backups\\SQL\\20240115",
    "mensagem_erro": null,
    "tipo_backup": "full",
    "detalhes": {
        "database": "ERP_Producao",
        "compression": true
    }
}
```

**Status possíveis:**
- `sucesso` - Backup concluído com sucesso
- `falha` - Backup falhou
- `alerta` - Backup concluído com alertas
- `executando` - Backup em execução

**Resposta de sucesso (201):**
```json
{
    "success": true,
    "message": "Execução registrada com sucesso",
    "execucao_id": 123,
    "status": 201
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

## 💻 Script PowerShell

O script `scripts/Send-BackupReport.ps1` deve ser executado após cada rotina de backup.

### Configuração

1. Copie o script para o servidor Windows
2. Edite as variáveis de configuração:
   - `$ApiUrl` - URL da API
   - `$ApiKey` - API Key do cliente

### Uso

```powershell
# Backup com sucesso
.\Send-BackupReport.ps1 -Rotina "Backup_Diario" -Status "sucesso" -Destino "D:\Backups\20240115"

# Backup com falha
.\Send-BackupReport.ps1 -Rotina "Backup_SQL" -Status "falha" -MensagemErro "Disco cheio"

# Especificando todas as opções
.\Send-BackupReport.ps1 `
    -Rotina "Backup_Completo" `
    -Status "sucesso" `
    -Destino "\\NAS\Backups" `
    -DataInicio "2024-01-15 22:00:00" `
    -DataFim "2024-01-15 23:30:00" `
    -TamanhoBytes 10737418240 `
    -TipoBackup "full"
```

### Agendador de Tarefas

Configure no Agendador de Tarefas do Windows para executar após cada backup:

1. Abra o Agendador de Tarefas
2. Crie uma nova tarefa
3. Configure o gatilho para executar após o backup
4. Ação: `powershell.exe`
5. Argumentos: `-ExecutionPolicy Bypass -File "C:\Scripts\Send-BackupReport.ps1" -Rotina "Nome_Backup" -Status "sucesso"`

## 📁 Estrutura do Projeto

```
bkp-webmanager-world/
├── app/
│   ├── controllers/      # Controllers da aplicação
│   ├── models/          # Modelos de dados
│   ├── services/        # Serviços (Auth, Email, Backup)
│   ├── middleware/      # Middlewares (Auth, CSRF, API)
│   ├── helpers/         # Funções auxiliares
│   ├── libraries/       # Bibliotecas nativas (JWT, SMTP)
│   └── views/           # Templates HTML
│       ├── layouts/     # Layout principal
│       ├── auth/        # Páginas de autenticação
│       ├── dashboard/   # Dashboard
│       ├── clientes/    # Gestão de clientes
│       ├── usuarios/    # Gestão de usuários
│       ├── backups/     # Histórico de backups
│       ├── relatorios/  # Relatórios
│       └── errors/      # Páginas de erro
├── config/              # Arquivos de configuração
├── database/
│   └── migrations/      # Scripts SQL
├── public/              # Arquivos públicos
│   ├── index.php        # Ponto de entrada
│   ├── .htaccess        # Configuração Apache
│   └── assets/          # CSS, JS, imagens
├── routes/              # Definição de rotas
├── scripts/             # Scripts PowerShell
├── docs/                # Documentação adicional
├── .env.example         # Exemplo de configuração
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