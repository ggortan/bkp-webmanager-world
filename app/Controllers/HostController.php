<?php
/**
 * Controller de Hosts
 */

namespace App\Controllers;

use App\Models\Host;
use App\Models\Cliente;
use App\Models\RotinaBackup;
use App\Services\AuthService;
use App\Services\LogService;
use App\Helpers\Security;

class HostController extends Controller
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->data['user'] = $this->authService->getUser();
    }

    /**
     * Lista hosts de um cliente
     * GET /clientes/{clienteId}/hosts
     */
    public function index(int $clienteId): void
    {
        $cliente = Cliente::find($clienteId);
        
        if (!$cliente) {
            $this->flash('error', 'Cliente não encontrado');
            $this->redirect('/clientes');
            return;
        }
        
        $hosts = Host::byCliente($clienteId);
        
        // Adicionar contagem de rotinas para cada host
        foreach ($hosts as &$host) {
            $sql = "SELECT COUNT(*) as total FROM rotinas_backup WHERE host_id = ?";
            $result = \App\Database::fetch($sql, [$host['id']]);
            $host['total_rotinas'] = $result['total'] ?? 0;
        }
        
        $this->data['title'] = 'Hosts - ' . $cliente['nome'];
        $this->data['cliente'] = $cliente;
        $this->data['hosts'] = $hosts;
        $this->data['flash'] = $this->getFlash();
        
        $this->render('hosts/index', $this->data);
    }

    /**
     * Formulário de criação de host
     * GET /clientes/{clienteId}/hosts/create
     */
    public function create(int $clienteId): void
    {
        $cliente = Cliente::find($clienteId);
        
        if (!$cliente) {
            $this->flash('error', 'Cliente não encontrado');
            $this->redirect('/clientes');
            return;
        }
        
        $this->data['title'] = 'Novo Host - ' . $cliente['nome'];
        $this->data['cliente'] = $cliente;
        $this->data['host'] = null;
        $this->data['errors'] = [];
        
        $this->render('hosts/create', $this->data);
    }

    /**
     * Salva novo host
     * POST /clientes/{clienteId}/hosts
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
        
        // Verifica nome único por cliente
        if (empty($errors) && Host::findByNomeAndCliente($data['nome'], $clienteId)) {
            $errors['nome'] = ['Já existe um host com este nome para este cliente'];
        }
        
        // Valida IP se fornecido
        if (!empty($data['ip']) && !filter_var($data['ip'], FILTER_VALIDATE_IP)) {
            $errors['ip'] = ['Endereço IP inválido'];
        }
        
        if (!empty($errors)) {
            $this->data['title'] = 'Novo Host - ' . $cliente['nome'];
            $this->data['cliente'] = $cliente;
            $this->data['host'] = $data;
            $this->data['errors'] = $errors;
            $this->render('hosts/create', $this->data);
            return;
        }
        
        $hostData = [
            'cliente_id' => $clienteId,
            'nome' => Security::sanitize($data['nome']),
            'hostname' => Security::sanitize($data['hostname'] ?? ''),
            'ip' => Security::sanitize($data['ip'] ?? ''),
            'sistema_operacional' => Security::sanitize($data['sistema_operacional'] ?? ''),
            'tipo' => Security::sanitize($data['tipo'] ?? ''),
            'observacoes' => Security::sanitize($data['observacoes'] ?? ''),
            'ativo' => isset($data['ativo']) ? 1 : 0,
            'telemetry_enabled' => isset($data['telemetry_enabled']) ? 1 : 0,
            'telemetry_interval_minutes' => (int) ($data['telemetry_interval_minutes'] ?? 5),
            'telemetry_offline_threshold' => (int) ($data['telemetry_offline_threshold'] ?? 3),
            'online_status' => 'unknown'
        ];
        
        $hostId = Host::create($hostData);
        
        LogService::log('info', 'host', "Host '{$hostData['nome']}' criado para cliente {$cliente['nome']}", [
            'host_id' => $hostId,
            'cliente_id' => $clienteId
        ]);
        
        $this->flash('success', 'Host criado com sucesso');
        $this->redirect('/clientes/' . $clienteId . '/hosts');
    }

    /**
     * Exibe detalhes de um host
     * GET /clientes/{clienteId}/hosts/{id}
     */
    public function show(int $clienteId, int $id): void
    {
        $cliente = Cliente::find($clienteId);
        
        if (!$cliente) {
            $this->flash('error', 'Cliente não encontrado');
            $this->redirect('/clientes');
            return;
        }
        
        $host = Host::withStats($id);
        
        if (!$host || $host['cliente_id'] != $clienteId) {
            $this->flash('error', 'Host não encontrado');
            $this->redirect('/clientes/' . $clienteId . '/hosts');
            return;
        }
        
        // Buscar rotinas do host
        $rotinas = RotinaBackup::byHost($id);
        
        // Buscar últimas execuções
        $sql = "SELECT e.*, r.nome as rotina_nome 
                FROM execucoes_backup e
                INNER JOIN rotinas_backup r ON e.rotina_id = r.id
                WHERE e.host_id = ?
                ORDER BY e.data_inicio DESC
                LIMIT 10";
        $execucoes = \App\Database::fetchAll($sql, [$id]);
        
        $this->data['title'] = $host['nome'] . ' - ' . $cliente['nome'];
        $this->data['cliente'] = $cliente;
        $this->data['host'] = $host;
        $this->data['rotinas'] = $rotinas;
        $this->data['execucoes'] = $execucoes;
        $this->data['flash'] = $this->getFlash();
        
        $this->render('hosts/show', $this->data);
    }

    /**
     * Formulário de edição de host
     * GET /clientes/{clienteId}/hosts/{id}/edit
     */
    public function edit(int $clienteId, int $id): void
    {
        $cliente = Cliente::find($clienteId);
        
        if (!$cliente) {
            $this->flash('error', 'Cliente não encontrado');
            $this->redirect('/clientes');
            return;
        }
        
        $host = Host::find($id);
        
        if (!$host || $host['cliente_id'] != $clienteId) {
            $this->flash('error', 'Host não encontrado');
            $this->redirect('/clientes/' . $clienteId . '/hosts');
            return;
        }
        
        $this->data['title'] = 'Editar Host - ' . $host['nome'];
        $this->data['cliente'] = $cliente;
        $this->data['host'] = $host;
        $this->data['errors'] = [];
        
        $this->render('hosts/edit', $this->data);
    }

    /**
     * Atualiza host
     * POST /clientes/{clienteId}/hosts/{id}
     */
    public function update(int $clienteId, int $id): void
    {
        $cliente = Cliente::find($clienteId);
        
        if (!$cliente) {
            $this->flash('error', 'Cliente não encontrado');
            $this->redirect('/clientes');
            return;
        }
        
        $host = Host::find($id);
        
        if (!$host || $host['cliente_id'] != $clienteId) {
            $this->flash('error', 'Host não encontrado');
            $this->redirect('/clientes/' . $clienteId . '/hosts');
            return;
        }
        
        $data = $this->input();
        
        $errors = $this->validate($data, [
            'nome' => 'required|min:2|max:100'
        ]);
        
        // Verifica nome único por cliente (exceto o próprio host)
        if (empty($errors)) {
            $existente = Host::findByNomeAndCliente($data['nome'], $clienteId);
            if ($existente && $existente['id'] != $id) {
                $errors['nome'] = ['Já existe um host com este nome para este cliente'];
            }
        }
        
        // Valida IP se fornecido
        if (!empty($data['ip']) && !filter_var($data['ip'], FILTER_VALIDATE_IP)) {
            $errors['ip'] = ['Endereço IP inválido'];
        }
        
        if (!empty($errors)) {
            $this->data['title'] = 'Editar Host - ' . $host['nome'];
            $this->data['cliente'] = $cliente;
            $this->data['host'] = array_merge($host, $data);
            $this->data['errors'] = $errors;
            $this->render('hosts/edit', $this->data);
            return;
        }
        
        $hostData = [
            'nome' => Security::sanitize($data['nome']),
            'hostname' => Security::sanitize($data['hostname'] ?? ''),
            'ip' => Security::sanitize($data['ip'] ?? ''),
            'sistema_operacional' => Security::sanitize($data['sistema_operacional'] ?? ''),
            'tipo' => Security::sanitize($data['tipo'] ?? ''),
            'observacoes' => Security::sanitize($data['observacoes'] ?? ''),
            'ativo' => isset($data['ativo']) ? 1 : 0,
            'telemetry_enabled' => isset($data['telemetry_enabled']) ? 1 : 0,
            'telemetry_interval_minutes' => (int) ($data['telemetry_interval_minutes'] ?? 5),
            'telemetry_offline_threshold' => (int) ($data['telemetry_offline_threshold'] ?? 3)
        ];
        
        Host::update($id, $hostData);
        
        LogService::log('info', 'host', "Host '{$hostData['nome']}' atualizado", [
            'host_id' => $id,
            'cliente_id' => $clienteId
        ]);
        
        $this->flash('success', 'Host atualizado com sucesso');
        $this->redirect('/clientes/' . $clienteId . '/hosts/' . $id);
    }

    /**
     * Deleta host
     * POST /clientes/{clienteId}/hosts/{id}/delete
     */
    public function destroy(int $clienteId, int $id): void
    {
        $cliente = Cliente::find($clienteId);
        
        if (!$cliente) {
            $this->flash('error', 'Cliente não encontrado');
            $this->redirect('/clientes');
            return;
        }
        
        $host = Host::find($id);
        
        if (!$host || $host['cliente_id'] != $clienteId) {
            $this->flash('error', 'Host não encontrado');
            $this->redirect('/clientes/' . $clienteId . '/hosts');
            return;
        }
        
        // Verifica se pode deletar
        if (!Host::canDelete($id)) {
            $this->flash('error', 'Não é possível deletar este host pois existem rotinas ativas vinculadas a ele');
            $this->redirect('/clientes/' . $clienteId . '/hosts/' . $id);
            return;
        }
        
        Host::delete($id);
        
        LogService::log('info', 'host', "Host '{$host['nome']}' deletado", [
            'host_id' => $id,
            'cliente_id' => $clienteId
        ]);
        
        $this->flash('success', 'Host deletado com sucesso');
        $this->redirect('/clientes/' . $clienteId . '/hosts');
    }

    /**
     * Alterna status do host
     * POST /clientes/{clienteId}/hosts/{id}/toggle-status
     */
    public function toggleStatus(int $clienteId, int $id): void
    {
        $cliente = Cliente::find($clienteId);
        
        if (!$cliente) {
            $this->flash('error', 'Cliente não encontrado');
            $this->redirect('/clientes');
            return;
        }
        
        $host = Host::find($id);
        
        if (!$host || $host['cliente_id'] != $clienteId) {
            $this->flash('error', 'Host não encontrado');
            $this->redirect('/clientes/' . $clienteId . '/hosts');
            return;
        }
        
        Host::toggleStatus($id);
        
        $novoStatus = $host['ativo'] ? 'inativo' : 'ativo';
        
        LogService::log('info', 'host', "Status do host '{$host['nome']}' alterado para {$novoStatus}", [
            'host_id' => $id,
            'cliente_id' => $clienteId
        ]);
        
        $this->flash('success', 'Status do host alterado com sucesso');
        $this->redirect('/clientes/' . $clienteId . '/hosts');
    }

    /**
     * Exibe tela de telemetria do host
     * GET /clientes/{clienteId}/hosts/{id}/telemetria
     */
    public function telemetry(int $clienteId, int $id): void
    {
        $cliente = Cliente::find($clienteId);
        
        if (!$cliente) {
            $this->flash('error', 'Cliente não encontrado');
            $this->redirect('/clientes');
            return;
        }
        
        $host = Host::find($id);
        
        if (!$host || $host['cliente_id'] != $clienteId) {
            $this->flash('error', 'Host não encontrado');
            $this->redirect('/clientes/' . $clienteId . '/hosts');
            return;
        }
        
        // Buscar histórico de telemetria
        $sql = "SELECT * FROM telemetria_historico 
                WHERE host_id = ? 
                ORDER BY created_at DESC 
                LIMIT 100";
        $historico = \App\Database::fetchAll($sql, [$id]);
        
        // Calcular estatísticas de telemetria
        $stats = [
            'total_registros' => count($historico),
            'media_cpu' => 0,
            'media_memoria' => 0,
            'media_disco' => 0,
            'max_cpu' => 0,
            'max_memoria' => 0,
            'max_disco' => 0,
        ];
        
        if (!empty($historico)) {
            $stats['media_cpu'] = round(array_sum(array_column($historico, 'cpu_percent')) / count($historico), 2);
            $stats['media_memoria'] = round(array_sum(array_column($historico, 'memory_percent')) / count($historico), 2);
            $stats['media_disco'] = round(array_sum(array_column($historico, 'disk_percent')) / count($historico), 2);
            $stats['max_cpu'] = max(array_column($historico, 'cpu_percent'));
            $stats['max_memoria'] = max(array_column($historico, 'memory_percent'));
            $stats['max_disco'] = max(array_column($historico, 'disk_percent'));
        }
        
        // Buscar configuração de retenção
        $retencaoConfig = \App\Models\Configuracao::get('dias_retencao_telemetria');
        $diasRetencao = (int) ($retencaoConfig ?? 0);
        
        $this->data['title'] = 'Telemetria - ' . $host['nome'];
        $this->data['cliente'] = $cliente;
        $this->data['host'] = $host;
        $this->data['historico'] = $historico;
        $this->data['stats'] = $stats;
        $this->data['dias_retencao'] = $diasRetencao;
        $this->data['flash'] = $this->getFlash();
        
        $this->render('hosts/telemetry', $this->data);
    }
}
