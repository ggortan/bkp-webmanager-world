# Bibliotecas Nativas do Backup WebManager

Este projeto não utiliza Composer ou dependências externas. Todas as funcionalidades são implementadas com bibliotecas nativas em PHP.

## 📦 Bibliotecas Implementadas

### 1. JWT (JSON Web Token)
**Arquivo:** `app/libraries/Jwt.php`

Implementação simplificada de JWT para autenticação via API Key.

#### Uso Básico:

```php
use App\Libraries\Jwt;

// Define a chave secreta
Jwt::setSecretKey('sua-chave-secreta-32-caracteres');

// Codifica um JWT
$token = Jwt::encode([
    'sub' => 123,
    'email' => 'usuario@example.com'
]);

// Decodifica um JWT
try {
    $payload = Jwt::decode($token);
    echo $payload->sub;  // 123
} catch (Exception $e) {
    echo 'Token inválido: ' . $e->getMessage();
}
```

#### Algoritmos Suportados:
- HS256 (padrão)
- HS384
- HS512

### 2. SMTP Nativo
**Arquivo:** `app/libraries/Smtp.php`

Implementação nativa de SMTP para envio de e-mails sem dependências.

#### Uso Básico:

```php
use App\Libraries\Smtp;

$smtp = new Smtp(
    'smtp.office365.com',
    587,
    'usuario@example.com',
    'senha',
    'tls'
);

$smtp->connect();

$smtp->send(
    'from@example.com',
    ['to@example.com'],
    'Assunto',
    '<h1>Olá!</h1><p>Este é um e-mail HTML</p>',
    ['Reply-To' => 'noreply@example.com']
);

$smtp->disconnect();
```

#### Encriptação Suportada:
- `tls` - TLS (padrão)
- `ssl` - SSL

## 🔧 Configuração

### Arquivo .env

```env
# Chave da aplicação (necessária para JWT)
APP_KEY=sua-chave-secreta-32-caracteres

# Configurações SMTP
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_USERNAME=seu-email@dominio.com
MAIL_PASSWORD=sua-senha
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=seu-email@dominio.com
MAIL_FROM_NAME="Backup WebManager"
```

## 🔐 Segurança

### JWT
- Usa HMAC para assinatura
- Validação automática de expiração
- Proteção contra timing attacks com `hash_equals()`

### SMTP
- Suporta TLS/SSL
- Autenticação integrada
- Tratamento de erros

## 📝 Migração de Composer

Se você estava usando as versões anteriores com Composer, aqui está o mapeamento:

| Composer | Nativo |
|----------|--------|
| `firebase/php-jwt` | `App\Libraries\Jwt` |
| `vlucas/phpdotenv` | `config/env.php` (já existia) |
| `phpmailer/phpmailer` | `App\Libraries\Smtp` |

## ⚠️ Limitações

- SMTP nativo: Apenas conexões básicas (sem suporte avançado de anexos por stream)
- JWT: Apenas HMAC (sem suporte a chaves assimétricas)

Para funcionalidades avançadas, considere usar as versões com Composer novamente.

## 🆘 Troubleshooting

### Erro de conexão SMTP
```
Erro ao conectar ao SMTP: Connection refused
```
- Verifique o host e porta
- Confirme se o servidor SMTP está disponível
- Valide credenciais

### Token JWT Expirado
```
RuntimeException: Token expirado
```
- O token passou do tempo de expiração
- Gere um novo token

### Erro de Autenticação SMTP
```
Erro SMTP (535): Authentication Failed
```
- Verifique usuário e senha
- Verifique se a encriptação (TLS/SSL) está correta
