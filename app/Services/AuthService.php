<?php
/**
 * Serviço de Autenticação
 * 
 * Gerencia autenticação Microsoft Entra (Azure AD)
 */

namespace App\Services;

use App\Models\Usuario;

class AuthService
{
    private array $config;

    public function __construct()
    {
        $this->config = require dirname(__DIR__, 2) . '/config/auth.php';
    }

    /**
     * Gera URL de login do Azure AD
     */
    public function getLoginUrl(): string
    {
        $azure = $this->config['azure'];
        
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;
        
        $params = [
            'client_id' => $azure['client_id'],
            'response_type' => 'code',
            'redirect_uri' => $azure['redirect_uri'],
            'response_mode' => 'query',
            'scope' => implode(' ', $azure['scopes']),
            'state' => $state
        ];
        
        return $azure['authorize_url'] . '?' . http_build_query($params);
    }

    /**
     * Processa callback do Azure AD
     */
    public function handleCallback(string $code, string $state): ?array
    {
        // Valida state
        if (empty($_SESSION['oauth_state']) || $state !== $_SESSION['oauth_state']) {
            throw new \RuntimeException('Estado inválido');
        }
        
        unset($_SESSION['oauth_state']);
        
        // Troca code por token
        $tokens = $this->exchangeCodeForTokens($code);
        
        if (empty($tokens['access_token'])) {
            throw new \RuntimeException('Falha ao obter token de acesso');
        }
        
        // Obtém informações do usuário
        $userInfo = $this->getUserInfo($tokens['access_token']);
        
        if (empty($userInfo['id'])) {
            throw new \RuntimeException('Falha ao obter informações do usuário');
        }
        
        // Cria ou atualiza usuário
        $user = Usuario::createOrUpdateFromSso([
            'azure_id' => $userInfo['id'],
            'nome' => $userInfo['displayName'] ?? $userInfo['name'] ?? 'Usuário',
            'email' => $userInfo['mail'] ?? $userInfo['userPrincipalName'] ?? '',
            'avatar' => null
        ]);
        
        // Inicia sessão
        $this->login($user);
        
        return $user;
    }

    /**
     * Troca código de autorização por tokens
     */
    private function exchangeCodeForTokens(string $code): array
    {
        $azure = $this->config['azure'];
        
        $params = [
            'client_id' => $azure['client_id'],
            'client_secret' => $azure['client_secret'],
            'code' => $code,
            'redirect_uri' => $azure['redirect_uri'],
            'grant_type' => 'authorization_code',
            'scope' => implode(' ', $azure['scopes'])
        ];
        
        $ch = curl_init($azure['token_url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \RuntimeException('Erro ao obter tokens: ' . $response);
        }
        
        return json_decode($response, true) ?? [];
    }

    /**
     * Obtém informações do usuário
     */
    private function getUserInfo(string $accessToken): array
    {
        $azure = $this->config['azure'];
        
        $ch = curl_init($azure['user_info_url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \RuntimeException('Erro ao obter informações do usuário: ' . $response);
        }
        
        return json_decode($response, true) ?? [];
    }

    /**
     * Inicia sessão do usuário
     */
    public function login(array $user): void
    {
        // Limpa dados de sessão anterior (exceto flash messages)
        $flash = $_SESSION['flash'] ?? null;
        
        // Regenera ID da sessão ANTES de definir os dados (segurança e persistência)
        session_regenerate_id(true);
        
        // Restaura flash messages se existiam
        if ($flash) {
            $_SESSION['flash'] = $flash;
        }
        
        // Define os dados do usuário na nova sessão
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user'] = [
            'id' => $user['id'],
            'nome' => $user['nome'],
            'email' => $user['email'],
            'avatar' => $user['avatar'] ?? null,
            'role_id' => $user['role_id'],
            'role' => $this->getRoleName($user['role_id'])
        ];
        
        // Marca a sessão como iniciada agora (evita regeneração imediata no index.php)
        $_SESSION['last_regeneration'] = time();
        $_SESSION['login_time'] = time();
        
        // Força gravação da sessão para garantir persistência
        session_write_close();
        session_start();
        
        // Atualiza último login
        Usuario::updateLastLogin($user['id']);
        
        // Log
        LogService::log('info', 'auth', 'Usuário logado: ' . $user['email'], [
            'usuario_id' => $user['id']
        ], $user['id']);
    }

    /**
     * Encerra sessão do usuário
     */
    public function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        
        if ($userId) {
            LogService::log('info', 'auth', 'Usuário deslogado', [
                'usuario_id' => $userId
            ], $userId);
        }
        
        $_SESSION = [];
        
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        session_destroy();
    }

    /**
     * Verifica se usuário está autenticado
     */
    public function isAuthenticated(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    /**
     * Obtém usuário logado
     */
    public function getUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return $_SESSION['user'] ?? null;
    }

    /**
     * Obtém ID do usuário logado
     */
    public function getUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Verifica se usuário tem permissão
     */
    public function hasRole(string $role): bool
    {
        $user = $this->getUser();
        
        if (!$user) {
            return false;
        }
        
        // Admin tem todas as permissões
        if ($user['role'] === 'admin') {
            return true;
        }
        
        return $user['role'] === $role;
    }

    /**
     * Verifica se é admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Verifica se é operador ou admin
     */
    public function isOperator(): bool
    {
        $user = $this->getUser();
        return $user && in_array($user['role'], ['admin', 'operator']);
    }

    /**
     * Obtém nome da role
     */
    private function getRoleName(int $roleId): string
    {
        $roles = [
            1 => 'admin',
            2 => 'operator',
            3 => 'viewer'
        ];
        
        return $roles[$roleId] ?? 'viewer';
    }
}
