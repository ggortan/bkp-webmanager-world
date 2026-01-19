<?php
/**
 * Controller de Autenticação
 */

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\LogService;

class AuthController extends Controller
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * Página de login
     */
    public function login(): void
    {
        if ($this->authService->isAuthenticated()) {
            $this->redirect('/dashboard');
            return;
        }
        
        $this->data['title'] = 'Login';
        $this->view('auth/login', $this->data);
    }

    /**
     * Redireciona para login do Azure AD
     */
    public function redirectToAzure(): void
    {
        $loginUrl = $this->authService->getLoginUrl();
        $this->redirect($loginUrl);
    }

    /**
     * Callback do Azure AD
     */
    public function callback(): void
    {
        try {
            $code = $_GET['code'] ?? null;
            $state = $_GET['state'] ?? null;
            $error = $_GET['error'] ?? null;
            
            if ($error) {
                LogService::warning('auth', 'Erro no callback do Azure', [
                    'error' => $error,
                    'description' => $_GET['error_description'] ?? ''
                ]);
                
                $this->flash('error', 'Erro na autenticação: ' . ($_GET['error_description'] ?? $error));
                $this->redirect('/login');
                return;
            }
            
            if (empty($code)) {
                $this->flash('error', 'Código de autorização não recebido');
                $this->redirect('/login');
                return;
            }
            
            $user = $this->authService->handleCallback($code, $state);
            
            if (!$user) {
                $this->flash('error', 'Não foi possível autenticar o usuário');
                $this->redirect('/login');
                return;
            }
            
            if (!$user['ativo']) {
                $this->authService->logout();
                $this->flash('error', 'Sua conta está desativada. Entre em contato com o administrador.');
                $this->redirect('/login');
                return;
            }
            
            // Redireciona para URL original ou dashboard
            $redirectTo = $_SESSION['redirect_after_login'] ?? '/dashboard';
            unset($_SESSION['redirect_after_login']);
            
            // Sessão já foi gravada no AuthService::login()
            $this->redirect($redirectTo);
            
        } catch (\Exception $e) {
            LogService::error('auth', 'Exceção no callback', [
                'error' => $e->getMessage()
            ]);
            
            $this->flash('error', 'Erro durante a autenticação. Tente novamente.');
            $this->redirect('/login');
        }
    }

    /**
     * Logout
     */
    public function logout(): void
    {
        $this->authService->logout();
        $this->flash('success', 'Você saiu do sistema');
        $this->redirect('/login');
    }
}
