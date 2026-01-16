# Guia de Configuração - Backup WebManager

## 🚀 Configuração Rápida

Desde a versão 2.0, a aplicação usa um arquivo centralizado de configuração em vez de variáveis de ambiente.

### Passo 1: Copiar o arquivo de exemplo

```bash
cp config/config.example.php config/config.php
```

### Passo 2: Editar o arquivo de configuração

Abra `config/config.php` e configure:

1. **Aplicação (app)**
   - `url`: URL completa onde a aplicação está hospedada
   - `key`: Chave secreta para criptografia (pode ser qualquer string)
   - `debug`: `false` em produção, `true` para desenvolvimento

2. **Banco de Dados (database)**
   - `host`: Servidor MySQL
   - `database`: Nome do banco
   - `username`: Usuário do banco
   - `password`: Senha do banco

3. **Email (mail)**
   - `host`: Servidor SMTP
   - `username`: Email para autenticação
   - `password`: Senha do email

4. **Azure AD (azure)** - Opcional
   - Configure se vai usar autenticação Microsoft

### Passo 3: Criar o banco de dados

```bash
mysql -u seu_usuario -p seu_banco_de_dados < database/migrations/001_create_tables.sql
```

### Passo 4: Testar a aplicação

```bash
php test-libraries.php
```

## 📁 Estrutura de Configuração

```
config/
├── config.php              ← Seu arquivo de configuração (NÃO commitar no git)
├── config.example.php      ← Modelo para criar config.php
├── app.php                 ← Carrega configuração da app
├── database.php            ← Carrega configuração do banco
├── auth.php                ← Carrega configuração de autenticação
└── mail.php                ← Carrega configuração de email
```

## 🔒 Segurança

- **config.php** está no `.gitignore` - nunca será enviado para o repositório
- Nunca compartilhe suas credenciais
- Use senhas fortes para o banco de dados
- Em produção, configure `debug: false`

## 🌍 Hostagem Compartilhada (Hostgator, etc)

1. Faça upload de todos os arquivos via FTP
2. Copie `config/config.example.php` → `config/config.php` via gerenciador de arquivos
3. Edite `config/config.php` com suas credenciais no cPanel
4. Acesse a aplicação via navegador

## ❓ Dúvidas

- **APP_KEY**: Use uma string qualquer, será usada para criptografia interna
- **SESSION_LIFETIME**: Tempo em minutos que a sessão permanece ativa (padrão: 120 minutos)
- **SECURE**: Deixe `true` se usar HTTPS (recomendado), `false` apenas para desenvolvimento local

## 📝 Variáveis de Ambiente (Descontinuado)

A partir da v2.0, o arquivo `.env` não é mais utilizado. Use `config.php` em seu lugar.

Se estiver atualizando de uma versão anterior:
1. Migre suas configurações de `.env` para `config.php`
2. Delete ou renomeie `.env` (por segurança)
3. Teste a aplicação
