<?php
/**
 * Controller de Usuários
 */

namespace App\Controllers;

use App\Models\Usuario;
use App\Models\UsuarioRole;
use App\Services\AuthService;
use App\Services\LogService;
use App\Helpers\Security;

class UsuarioController extends Controller
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->data['user'] = $this->authService->getUser();
    }

    /**
     * Lista de usuários
     */
    public function index(): void
    {
        $usuarios = Usuario::allWithRoles();
        
        $this->data['title'] = 'Usuários';
        $this->data['usuarios'] = $usuarios;
        $this->data['flash'] = $this->getFlash();
        
        $this->render('usuarios/index', $this->data);
    }

    /**
     * Exibe detalhes de um usuário
     */
    public function show(int $id): void
    {
        $usuario = Usuario::findWithRole($id);
        
        if (!$usuario) {
            $this->flash('error', 'Usuário não encontrado');
            $this->redirect('/usuarios');
            return;
        }
        
        $this->data['title'] = $usuario['nome'];
        $this->data['usuario'] = $usuario;
        
        $this->render('usuarios/show', $this->data);
    }

    /**
     * Formulário de edição
     */
    public function edit(int $id): void
    {
        $usuario = Usuario::findWithRole($id);
        
        if (!$usuario) {
            $this->flash('error', 'Usuário não encontrado');
            $this->redirect('/usuarios');
            return;
        }
        
        $roles = UsuarioRole::all('nome');
        
        $this->data['title'] = 'Editar Usuário';
        $this->data['usuario'] = $usuario;
        $this->data['roles'] = $roles;
        $this->data['errors'] = [];
        
        $this->render('usuarios/form', $this->data);
    }

    /**
     * Atualiza usuário
     */
    public function update(int $id): void
    {
        $usuario = Usuario::find($id);
        
        if (!$usuario) {
            $this->flash('error', 'Usuário não encontrado');
            $this->redirect('/usuarios');
            return;
        }
        
        $data = $this->input();
        
        // Não permite alterar própria role
        $currentUser = $this->authService->getUser();
        if ($currentUser['id'] === $id && isset($data['role_id'])) {
            unset($data['role_id']);
        }
        
        $usuarioData = [
            'ativo' => !empty($data['ativo']) ? 1 : 0
        ];
        
        if (isset($data['role_id'])) {
            $usuarioData['role_id'] = (int) $data['role_id'];
        }
        
        Usuario::update($id, $usuarioData);
        
        LogService::info('usuarios', 'Usuário atualizado', [
            'usuario_id' => $id,
            'alteracoes' => $usuarioData
        ]);
        
        $this->flash('success', 'Usuário atualizado com sucesso!');
        $this->redirect('/usuarios/' . $id);
    }

    /**
     * Ativa/desativa usuário
     */
    public function toggleStatus(int $id): void
    {
        $usuario = Usuario::find($id);
        
        if (!$usuario) {
            $this->json(['success' => false, 'error' => 'Usuário não encontrado'], 404);
            return;
        }
        
        // Não permite desativar a si mesmo
        $currentUser = $this->authService->getUser();
        if ($currentUser['id'] === $id) {
            $this->json(['success' => false, 'error' => 'Você não pode desativar a si mesmo'], 400);
            return;
        }
        
        $novoStatus = $usuario['ativo'] ? 0 : 1;
        Usuario::update($id, ['ativo' => $novoStatus]);
        
        LogService::info('usuarios', 'Status do usuário alterado', [
            'usuario_id' => $id,
            'novo_status' => $novoStatus ? 'ativo' : 'inativo'
        ]);
        
        $this->json([
            'success' => true,
            'ativo' => $novoStatus,
            'message' => $novoStatus ? 'Usuário ativado' : 'Usuário desativado'
        ]);
    }
}
