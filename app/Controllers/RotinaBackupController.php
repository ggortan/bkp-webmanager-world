<?php
/**
 * Controller de Rotinas de Backup
 */

namespace App\Controllers;

use App\Models\Cliente;
use App\Models\RotinaBackup;
use App\Models\Servidor;
use App\Services\AuthService;
use App\Services\LogService;
use App\Helpers\Security;

class RotinaBackupController extends Controller
{
    private AuthService $authService;
    
    // Número padrão de execuções a exibir
    const DEFAULT_EXECUTION_LIMIT = 10;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->data['user'] = $this->authService->getUser();
    }

    /**
     * Lista rotinas de um cliente
     */
    public function index(int $clienteId): void
    {
        $cliente = Cliente::find($clienteId);
        
        if (!$cliente) {
            $this->flash('error', 'Cliente não encontrado');
            $this->redirect('/clientes');
            return;
        }
        
        $rotinas = RotinaBackup::byCliente($clienteId);
        
        $this->data['title'] = 'Rotinas de Backup - ' . $cliente['nome'];
        $this->data['cliente'] = $cliente;
        $this->data['rotinas'] = $rotinas;
        $this->data['flash'] = $this->getFlash();
        
        $this->render('rotinas/index', $this->data);
    }

    /**
     * Formulário de criação
     */
    public function create(int $clienteId): void
    {
        $cliente = Cliente::find($clienteId);
        
        if (!$cliente) {
            $this->flash('error', 'Cliente não encontrado');
            $this->redirect('/clientes');
            return;
        }
        
        // Busca servidores do cliente para opção de vinculação
        $servidores = Servidor::byCliente($clienteId);
        
        $this->data['title'] = 'Nova Rotina de Backup';
        $this->data['cliente'] = $cliente;
        $this->data['servidores'] = $servidores;
        $this->data['rotina'] = null;
        $this->data['errors'] = [];
        
        $this->render('rotinas/form', $this->data);
    }

    /**
     * Salva nova rotina
     */
    public function store(int $clienteId): void
    {
        $cliente = Cliente::find($clienteId);
        
        if (!$cliente) {
            $this->flash('error', 'Cliente não encontrado');
            $this->redirect('/clientes');
            return;
        }
        
        $data = $this->input();
        
        $errors = $this->validate($data, [
            'nome' => 'required|min:2|max:100'
        ]);
        
        // Verifica nome único para o cliente
        if (empty($errors) && RotinaBackup::findByNomeAndCliente($data['nome'], $clienteId)) {
            $errors['nome'] = ['Esta rotina já existe para este cliente'];
        }
        
        if (!empty($errors)) {
            $servidores = Servidor::byCliente($clienteId);
            $this->data['title'] = 'Nova Rotina de Backup';
            $this->data['cliente'] = $cliente;
            $this->data['servidores'] = $servidores;
            $this->data['rotina'] = $data;
            $this->data['errors'] = $errors;
            $this->render('rotinas/form', $this->data);
            return;
        }
        
        // Prepara host_info se fornecido
        $hostInfo = null;
        if (!empty($data['host_nome']) || !empty($data['host_hostname']) || 
            !empty($data['host_ip']) || !empty($data['host_so'])) {
            $hostInfo = [
                'nome' => Security::sanitize($data['host_nome'] ?? ''),
                'hostname' => Security::sanitize($data['host_hostname'] ?? ''),
                'ip' => Security::sanitize($data['host_ip'] ?? ''),
                'sistema_operacional' => Security::sanitize($data['host_so'] ?? '')
            ];
            $hostInfo = array_filter($hostInfo); // Remove valores vazios
        }
        
        $rotinaData = [
            'cliente_id' => $clienteId,
            'servidor_id' => !empty($data['servidor_id']) ? (int)$data['servidor_id'] : null,
            'nome' => Security::sanitize($data['nome']),
            'routine_key' => RotinaBackup::generateRoutineKey(),
            'tipo' => Security::sanitize($data['tipo'] ?? ''),
            'agendamento' => Security::sanitize($data['agendamento'] ?? ''),
            'destino' => Security::sanitize($data['destino'] ?? ''),
            'observacoes' => Security::sanitize($data['observacoes'] ?? ''),
            'host_info' => $hostInfo ? json_encode($hostInfo) : null,
            'ativa' => !empty($data['ativa']) ? 1 : 0
        ];
        
        $id = RotinaBackup::create($rotinaData);
        
        LogService::info('rotinas', 'Rotina de backup criada', [
            'rotina_id' => $id,
            'cliente_id' => $clienteId,
            'nome' => $rotinaData['nome']
        ]);
        
        $this->flash('success', 'Rotina criada com sucesso!');
        $this->redirect('/clientes/' . $clienteId . '/rotinas');
    }

    /**
     * Exibe detalhes da rotina
     */
    public function show(int $clienteId, int $id): void
    {
        $cliente = Cliente::find($clienteId);
        $rotina = RotinaBackup::find($id);
        
        if (!$cliente || !$rotina || $rotina['cliente_id'] != $clienteId) {
            $this->flash('error', 'Rotina não encontrada');
            $this->redirect('/clientes/' . $clienteId . '/rotinas');
            return;
        }
        
        // Busca últimas execuções usando o model
        $execucoes = RotinaBackup::getRecentExecutions($id, self::DEFAULT_EXECUTION_LIMIT);
        
        // Busca servidor se vinculado
        $servidor = null;
        if ($rotina['servidor_id']) {
            $servidor = Servidor::find($rotina['servidor_id']);
        }
        
        $this->data['title'] = 'Rotina: ' . $rotina['nome'];
        $this->data['cliente'] = $cliente;
        $this->data['rotina'] = $rotina;
        $this->data['servidor'] = $servidor;
        $this->data['execucoes'] = $execucoes;
        $this->data['flash'] = $this->getFlash();
        
        $this->render('rotinas/show', $this->data);
    }

    /**
     * Formulário de edição
     */
    public function edit(int $clienteId, int $id): void
    {
        $cliente = Cliente::find($clienteId);
        $rotina = RotinaBackup::find($id);
        
        if (!$cliente || !$rotina || $rotina['cliente_id'] != $clienteId) {
            $this->flash('error', 'Rotina não encontrada');
            $this->redirect('/clientes/' . $clienteId . '/rotinas');
            return;
        }
        
        // Busca servidores do cliente
        $servidores = Servidor::byCliente($clienteId);
        
        $this->data['title'] = 'Editar Rotina';
        $this->data['cliente'] = $cliente;
        $this->data['servidores'] = $servidores;
        $this->data['rotina'] = $rotina;
        $this->data['errors'] = [];
        
        $this->render('rotinas/form', $this->data);
    }

    /**
     * Atualiza rotina
     */
    public function update(int $clienteId, int $id): void
    {
        $cliente = Cliente::find($clienteId);
        $rotina = RotinaBackup::find($id);
        
        if (!$cliente || !$rotina || $rotina['cliente_id'] != $clienteId) {
            $this->flash('error', 'Rotina não encontrada');
            $this->redirect('/clientes/' . $clienteId . '/rotinas');
            return;
        }
        
        $data = $this->input();
        
        $errors = $this->validate($data, [
            'nome' => 'required|min:2|max:100'
        ]);
        
        // Verifica nome único para o cliente (exceto a própria rotina)
        if (empty($errors)) {
            $existente = RotinaBackup::findByNomeAndCliente($data['nome'], $clienteId);
            if ($existente && $existente['id'] != $id) {
                $errors['nome'] = ['Esta rotina já existe para este cliente'];
            }
        }
        
        if (!empty($errors)) {
            $servidores = Servidor::byCliente($clienteId);
            $this->data['title'] = 'Editar Rotina';
            $this->data['cliente'] = $cliente;
            $this->data['servidores'] = $servidores;
            $this->data['rotina'] = array_merge($rotina, $data);
            $this->data['errors'] = $errors;
            $this->render('rotinas/form', $this->data);
            return;
        }
        
        // Prepara host_info se fornecido
        $hostInfo = null;
        if (!empty($data['host_nome']) || !empty($data['host_hostname']) || 
            !empty($data['host_ip']) || !empty($data['host_so'])) {
            $hostInfo = [
                'nome' => Security::sanitize($data['host_nome'] ?? ''),
                'hostname' => Security::sanitize($data['host_hostname'] ?? ''),
                'ip' => Security::sanitize($data['host_ip'] ?? ''),
                'sistema_operacional' => Security::sanitize($data['host_so'] ?? '')
            ];
            $hostInfo = array_filter($hostInfo);
        }
        
        $rotinaData = [
            'servidor_id' => !empty($data['servidor_id']) ? (int)$data['servidor_id'] : null,
            'nome' => Security::sanitize($data['nome']),
            'tipo' => Security::sanitize($data['tipo'] ?? ''),
            'agendamento' => Security::sanitize($data['agendamento'] ?? ''),
            'destino' => Security::sanitize($data['destino'] ?? ''),
            'observacoes' => Security::sanitize($data['observacoes'] ?? ''),
            'host_info' => $hostInfo ? json_encode($hostInfo) : null,
            'ativa' => !empty($data['ativa']) ? 1 : 0
        ];
        
        RotinaBackup::update($id, $rotinaData);
        
        LogService::info('rotinas', 'Rotina de backup atualizada', [
            'rotina_id' => $id,
            'cliente_id' => $clienteId
        ]);
        
        $this->flash('success', 'Rotina atualizada com sucesso!');
        $this->redirect('/clientes/' . $clienteId . '/rotinas/' . $id);
    }

    /**
     * Remove rotina
     */
    public function destroy(int $clienteId, int $id): void
    {
        $cliente = Cliente::find($clienteId);
        $rotina = RotinaBackup::find($id);
        
        if (!$cliente || !$rotina || $rotina['cliente_id'] != $clienteId) {
            $this->flash('error', 'Rotina não encontrada');
            $this->redirect('/clientes/' . $clienteId . '/rotinas');
            return;
        }
        
        RotinaBackup::delete($id);
        
        LogService::info('rotinas', 'Rotina de backup removida', [
            'rotina_id' => $id,
            'cliente_id' => $clienteId,
            'nome' => $rotina['nome']
        ]);
        
        $this->flash('success', 'Rotina removida com sucesso!');
        $this->redirect('/clientes/' . $clienteId . '/rotinas');
    }

    /**
     * Regenera routine_key
     */
    public function regenerateKey(int $clienteId, int $id): void
    {
        $rotina = RotinaBackup::find($id);
        
        if (!$rotina || $rotina['cliente_id'] != $clienteId) {
            $this->json(['success' => false, 'error' => 'Rotina não encontrada'], 404);
            return;
        }
        
        $newKey = RotinaBackup::generateRoutineKey();
        RotinaBackup::update($id, ['routine_key' => $newKey]);
        
        LogService::info('rotinas', 'Routine key regenerada', [
            'rotina_id' => $id,
            'cliente_id' => $clienteId
        ]);
        
        $this->json([
            'success' => true,
            'routine_key' => $newKey,
            'message' => 'Routine key regenerada com sucesso'
        ]);
    }
}
