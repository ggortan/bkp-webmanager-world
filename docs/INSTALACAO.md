# Guia de Instalação

Este documento descreve o processo completo de instalação do Backup WebManager.

## Requisitos do Sistema

### Servidor Web

- **PHP**: 8.0 ou superior
- **MySQL**: 8.0 ou superior
- **Servidor Web**: Apache 2.4+ com mod_rewrite ou Nginx

### Extensões PHP Obrigatórias

- PDO
- PDO_MySQL
- cURL
- JSON
- mbstring
- openssl

### Para verificar as extensões instaladas:

```bash
php -m
```

## Instalação Passo a Passo

### 1. Clonar o Repositório

```bash
cd /var/www
git clone https://github.com/seu-usuario/bkp-webmanager-world.git
cd bkp-webmanager-world
```

### 2. Instalar Dependências PHP

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Configurar Permissões

```bash
# Linux
chown -R www-data:www-data /var/www/bkp-webmanager-world
chmod -R 755 /var/www/bkp-webmanager-world
chmod -R 775 /var/www/bkp-webmanager-world/logs
```

### 4. Configurar Ambiente

```bash
cp .env.example .env
nano .env
```

Edite o arquivo `.env` com suas configurações:

```env
# Aplicação
APP_NAME="Backup WebManager"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://backup.seudominio.com
APP_KEY=gere-uma-chave-aleatoria-de-32-caracteres

# Banco de Dados
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=backup_webmanager
DB_USERNAME=backup_user
DB_PASSWORD=sua_senha_segura

# Microsoft Entra (Azure AD)
AZURE_CLIENT_ID=seu-client-id-do-azure
AZURE_CLIENT_SECRET=seu-client-secret
AZURE_TENANT_ID=seu-tenant-id
AZURE_REDIRECT_URI=https://backup.seudominio.com/auth/callback

# E-mail SMTP
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_USERNAME=noreply@seudominio.com
MAIL_PASSWORD=sua_senha_email
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@seudominio.com
MAIL_FROM_NAME="Backup WebManager"

# Sessão
SESSION_LIFETIME=120
SESSION_SECURE=true
```

### 5. Criar Banco de Dados

```bash
# Acessar MySQL
mysql -u root -p

# Criar banco e usuário
CREATE DATABASE backup_webmanager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'backup_user'@'localhost' IDENTIFIED BY 'sua_senha_segura';
GRANT ALL PRIVILEGES ON backup_webmanager.* TO 'backup_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Executar migrations
mysql -u backup_user -p backup_webmanager < database/migrations/001_create_tables.sql
```

### 6. Configurar Apache

Crie um virtual host:

```bash
sudo nano /etc/apache2/sites-available/backup-webmanager.conf
```

Conteúdo:

```apache
<VirtualHost *:443>
    ServerName backup.seudominio.com
    DocumentRoot /var/www/bkp-webmanager-world/public
    
    <Directory /var/www/bkp-webmanager-world/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    # SSL (use seus certificados)
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/seu-certificado.crt
    SSLCertificateKeyFile /etc/ssl/private/sua-chave.key
    
    ErrorLog ${APACHE_LOG_DIR}/backup-webmanager-error.log
    CustomLog ${APACHE_LOG_DIR}/backup-webmanager-access.log combined
</VirtualHost>

<VirtualHost *:80>
    ServerName backup.seudominio.com
    Redirect permanent / https://backup.seudominio.com/
</VirtualHost>
```

Ativar o site:

```bash
sudo a2ensite backup-webmanager.conf
sudo a2enmod rewrite ssl
sudo systemctl restart apache2
```

### 7. Configurar Nginx (Alternativa)

```bash
sudo nano /etc/nginx/sites-available/backup-webmanager
```

Conteúdo:

```nginx
server {
    listen 80;
    server_name backup.seudominio.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name backup.seudominio.com;
    root /var/www/bkp-webmanager-world/public;
    index index.php;

    ssl_certificate /etc/ssl/certs/seu-certificado.crt;
    ssl_certificate_key /etc/ssl/private/sua-chave.key;

    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options SAMEORIGIN;
    add_header X-XSS-Protection "1; mode=block";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    access_log /var/log/nginx/backup-webmanager-access.log;
    error_log /var/log/nginx/backup-webmanager-error.log;
}
```

Ativar o site:

```bash
sudo ln -s /etc/nginx/sites-available/backup-webmanager /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

## Configuração do Microsoft Entra (Azure AD)

### 1. Criar Registro de Aplicativo

1. Acesse [Azure Portal](https://portal.azure.com)
2. Vá para **Azure Active Directory** > **App registrations**
3. Clique em **New registration**
4. Preencha:
   - **Name**: Backup WebManager
   - **Supported account types**: Accounts in this organizational directory only
   - **Redirect URI**: Web - `https://backup.seudominio.com/auth/callback`
5. Clique em **Register**

### 2. Configurar Autenticação

1. No registro do aplicativo, vá para **Authentication**
2. Em **Redirect URIs**, verifique se a URI está correta
3. Em **Implicit grant and hybrid flows**, marque:
   - Access tokens
   - ID tokens
4. Clique em **Save**

### 3. Criar Client Secret

1. Vá para **Certificates & secrets**
2. Clique em **New client secret**
3. Descrição: "Backup WebManager Production"
4. Validade: escolha conforme política de segurança
5. Clique em **Add**
6. **Copie o valor imediatamente** (não será exibido novamente)

### 4. Configurar Permissões de API

1. Vá para **API permissions**
2. Clique em **Add a permission**
3. Selecione **Microsoft Graph**
4. Selecione **Delegated permissions**
5. Adicione:
   - `openid`
   - `profile`
   - `email`
   - `User.Read`
6. Clique em **Grant admin consent**

### 5. Copiar IDs

Na página **Overview** do aplicativo, copie:
- **Application (client) ID** → `AZURE_CLIENT_ID`
- **Directory (tenant) ID** → `AZURE_TENANT_ID`

## Primeiro Acesso

1. Acesse `https://backup.seudominio.com`
2. Clique em "Entrar com Microsoft"
3. Faça login com uma conta da organização
4. O primeiro usuário será criado automaticamente com papel de "Visualização"
5. Acesse o banco de dados para promover o primeiro admin:

```sql
UPDATE usuarios SET role_id = 1 WHERE email = 'seu.email@seudominio.com';
```

## Verificação da Instalação

### Checklist

- [ ] Aplicação acessível via HTTPS
- [ ] Login com Microsoft funcionando
- [ ] Dashboard carregando corretamente
- [ ] API respondendo em `/api/status`
- [ ] Permissões de diretório corretas

### Testar API

```bash
curl -X GET https://backup.seudominio.com/api/status
```

Resposta esperada:
```json
{
    "success": true,
    "status": "online",
    "version": "1.0.0"
}
```

## Solução de Problemas

### Erro 500 - Internal Server Error

1. Verifique logs de erro:
   ```bash
   tail -f /var/log/apache2/backup-webmanager-error.log
   ```
2. Verifique se o arquivo `.env` existe e está configurado
3. Verifique permissões de diretório

### Erro de conexão com banco de dados

1. Verifique credenciais no `.env`
2. Verifique se o serviço MySQL está rodando
3. Teste conexão manual:
   ```bash
   mysql -u backup_user -p backup_webmanager
   ```

### Erro de autenticação Microsoft

1. Verifique se todas as configurações do Azure estão corretas
2. Confirme que a URI de redirecionamento está exatamente igual
3. Verifique se as permissões de API foram concedidas

### Páginas não carregando (erro 404)

1. Verifique se mod_rewrite está ativo (Apache)
2. Verifique configuração de try_files (Nginx)
3. Confirme que o DocumentRoot aponta para `/public`
