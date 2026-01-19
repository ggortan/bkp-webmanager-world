<?php
/**
 * Controller de Clientes
 */

namespace App\Controllers;

use App\Models\Cliente;
use App\Models\Host;
use App\Services\AuthService;
use App\Services\LogService;
use App\Helpers\Security;

class ClienteController extends Controller
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->data['user'] = $this->authService->getUser();
    }

    /**
     * Lista de clientes
     */
    public function index(): void
    {
        $clientes = Cliente::all('nome');
        
        $this->data['title'] = 'Clientes';
        $this->data['clientes'] = $clientes;
        $this->data['flash'] = $this->getFlash();
        
        $this->render('clientes/index', $this->data);
    }

    /**
     * Exibe detalhes de um cliente
     */
    public function show(int $id): void
    {
        $cliente = Cliente::findWithStats($id);
        
        if (!$cliente) {
            $this->flash('error', 'Cliente não encontrado');
            $this->redirect('/clientes');
            return;
        }
        
        $hosts = Host::byCliente($id);
        
        // Buscar rotinas com nome do host e última execução
        $sql = "SELECT r.*, h.nome as host_nome,
                       e.data_inicio as ultima_execucao,
                       e.status as ultimo_status
                FROM rotinas_backup r 
                LEFT JOIN hosts h ON r.host_id = h.id 
                LEFT JOIN execucoes_backup e ON e.id = (
                    SELECT MAX(e2.id) FROM execucoes_backup e2 WHERE e2.rotina_id = r.id
                )
                WHERE r.cliente_id = ? 
                ORDER BY r.nome ASC";
        $rotinas = \App\Database::fetchAll($sql, [$id]);
        
        $this->data['title'] = $cliente['nome'];
        $this->data['cliente'] = $cliente;
        $this->data['hosts'] = $hosts;
        $this->data['rotinas'] = $rotinas;
        $this->data['flash'] = $this->getFlash();
        
        $this->render('clientes/show', $this->data);
    }

    /**
     * Formulário de criação
     */
    public function create(): void
    {
        $this->data['title'] = 'Novo Cliente';
        $this->data['cliente'] = null;
        $this->data['errors'] = [];
        
        $this->render('clientes/form', $this->data);
    }

    /**
     * Salva novo cliente
     */
    public function store(): void
    {
        $data = $this->input();
        
        $errors = $this->validate($data, [
            'identificador' => 'required|min:2|max:50',
            'nome' => 'required|min:2|max:200',
            'email' => 'email'
        ]);
        
        // Verifica identificador único
        if (empty($errors) && Cliente::findByIdentificador($data['identificador'])) {
            $errors['identificador'] = ['Este identificador já está em uso'];
        }
        
        if (!empty($errors)) {
            $this->data['title'] = 'Novo Cliente';
            $this->data['cliente'] = $data;
            $this->data['errors'] = $errors;
            $this->render('clientes/form', $this->data);
            return;
        }
        
        $clienteData = [
            'identificador' => Security::sanitize($data['identificador']),
            'nome' => Security::sanitize($data['nome']),
            'email' => Security::sanitize($data['email'] ?? '', 'email'),
            'telefone' => Security::sanitize($data['telefone'] ?? ''),
            'observacoes' => Security::sanitize($data['observacoes'] ?? ''),
            'ativo' => !empty($data['ativo']) ? 1 : 0,
            'relatorios_ativos' => !empty($data['relatorios_ativos']) ? 1 : 0
        ];
        
        $id = Cliente::createWithApiKey($clienteData);
        
        LogService::info('clientes', 'Cliente criado', [
            'cliente_id' => $id,
            'identificador' => $clienteData['identificador']
        ]);
        
        $this->flash('success', 'Cliente criado com sucesso!');
        $this->redirect('/clientes/' . $id);
    }

    /**
     * Formulário de edição
     */
    public function edit(int $id): void
    {
        $cliente = Cliente::find($id);
        
        if (!$cliente) {
            $this->flash('error', 'Cliente não encontrado');
            $this->redirect('/clientes');
            return;
        }
        
        $this->data['title'] = 'Editar Cliente';
        $this->data['cliente'] = $cliente;
        $this->data['errors'] = [];
        
        $this->render('clientes/form', $this->data);
    }

    /**
     * Atualiza cliente
     */
    public function update(int $id): void
    {
        $cliente = Cliente::find($id);
        
        if (!$cliente) {
            $this->flash('error', 'Cliente não encontrado');
            $this->redirect('/clientes');
            return;
        }
        
        $data = $this->input();
        
        $errors = $this->validate($data, [
            'nome' => 'required|min:2|max:200',
            'email' => 'email'
        ]);
        
        if (!empty($errors)) {
            $this->data['title'] = 'Editar Cliente';
            $this->data['cliente'] = array_merge($cliente, $data);
            $this->data['errors'] = $errors;
            $this->render('clientes/form', $this->data);
            return;
        }
        
        $clienteData = [
            'nome' => Security::sanitize($data['nome']),
            'email' => Security::sanitize($data['email'] ?? '', 'email'),
            'telefone' => Security::sanitize($data['telefone'] ?? ''),
            'observacoes' => Security::sanitize($data['observacoes'] ?? ''),
            'ativo' => !empty($data['ativo']) ? 1 : 0,
            'relatorios_ativos' => !empty($data['relatorios_ativos']) ? 1 : 0
        ];
        
        Cliente::update($id, $clienteData);
        
        LogService::info('clientes', 'Cliente atualizado', [
            'cliente_id' => $id
        ]);
        
        $this->flash('success', 'Cliente atualizado com sucesso!');
        $this->redirect('/clientes/' . $id);
    }

    /**
     * Remove cliente
     */
    public function destroy(int $id): void
    {
        $cliente = Cliente::find($id);
        
        if (!$cliente) {
            $this->flash('error', 'Cliente não encontrado');
            $this->redirect('/clientes');
            return;
        }
        
        Cliente::delete($id);
        
        LogService::info('clientes', 'Cliente removido', [
            'cliente_id' => $id,
            'identificador' => $cliente['identificador']
        ]);
        
        $this->flash('success', 'Cliente removido com sucesso!');
        $this->redirect('/clientes');
    }

    /**
     * Regenera API Key
     */
    public function regenerateApiKey(int $id): void
    {
        $cliente = Cliente::find($id);
        
        if (!$cliente) {
            $this->json(['success' => false, 'error' => 'Cliente não encontrado'], 404);
            return;
        }
        
        $newApiKey = Cliente::regenerateApiKey($id);
        
        LogService::info('clientes', 'API Key regenerada', [
            'cliente_id' => $id
        ]);
        
        $this->json([
            'success' => true,
            'api_key' => $newApiKey,
            'message' => 'API Key regenerada com sucesso'
        ]);
    }
}
