<?php
/**
 * Serviço de Backup
 * 
 * Gerencia operações relacionadas a backups
 */

namespace App\Services;

use App\Models\Cliente;
use App\Models\Servidor;
use App\Models\RotinaBackup;
use App\Models\ExecucaoBackup;
use App\Database;

class BackupService
{
    /**
     * Registra uma execução de backup recebida pela API
     */
    public function registrarExecucao(array $data, array $cliente): array
    {
        Database::beginTransaction();
        
        try {
            // Encontra ou cria o servidor
            $servidor = Servidor::findOrCreate($cliente['id'], $data['servidor'], [
                'hostname' => $data['hostname'] ?? null,
                'ip' => $data['ip'] ?? null,
                'sistema_operacional' => $data['sistema_operacional'] ?? null
            ]);
            
            // Encontra ou cria a rotina
            $rotina = RotinaBackup::findOrCreate($servidor['id'], $data['rotina'], [
                'tipo' => $data['tipo_backup'] ?? null,
                'destino' => $data['destino'] ?? null
            ]);
            
            // Registra a execução
            $execucaoData = [
                'rotina_id' => $rotina['id'],
                'cliente_id' => $cliente['id'],
                'servidor_id' => $servidor['id'],
                'data_inicio' => $data['data_inicio'],
                'data_fim' => $data['data_fim'] ?? null,
                'status' => $data['status'],
                'tamanho_bytes' => $data['tamanho_bytes'] ?? null,
                'destino' => $data['destino'] ?? null,
                'mensagem_erro' => $data['mensagem_erro'] ?? null,
                'detalhes' => !empty($data['detalhes']) ? json_encode($data['detalhes']) : null
            ];
            
            $execucaoId = ExecucaoBackup::registrar($execucaoData);
            
            Database::commit();
            
            // Log
            LogService::api('Execução de backup registrada', [
                'cliente_id' => $cliente['id'],
                'cliente' => $cliente['identificador'],
                'servidor' => $data['servidor'],
                'rotina' => $data['rotina'],
                'status' => $data['status'],
                'execucao_id' => $execucaoId
            ]);
            
            return [
                'success' => true,
                'execucao_id' => $execucaoId,
                'message' => 'Execução registrada com sucesso'
            ];
            
        } catch (\Exception $e) {
            Database::rollBack();
            
            LogService::error('api', 'Erro ao registrar execução de backup', [
                'cliente_id' => $cliente['id'],
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            throw $e;
        }
    }

    /**
     * Obtém resumo do dashboard
     */
    public function getDashboardData(): array
    {
        return [
            'stats' => ExecucaoBackup::getStats(30),
            'stats_periodo' => ExecucaoBackup::getStatsByPeriod(7),
            'stats_clientes' => ExecucaoBackup::getStatsByCliente(30),
            'execucoes_recentes' => ExecucaoBackup::getRecent(10),
            'total_clientes' => Cliente::count(['ativo' => 1]),
            'total_servidores' => \App\Database::fetch(
                "SELECT COUNT(*) as total FROM servidores WHERE ativo = 1"
            )['total'] ?? 0
        ];
    }

    /**
     * Valida dados recebidos pela API
     */
    public function validarDadosApi(array $data): array
    {
        $errors = [];
        
        // Campos obrigatórios
        $required = ['servidor', 'rotina', 'data_inicio', 'status'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[$field] = "O campo '{$field}' é obrigatório";
            }
        }
        
        // Valida status
        $statusValidos = ['sucesso', 'falha', 'alerta', 'executando'];
        if (!empty($data['status']) && !in_array($data['status'], $statusValidos)) {
            $errors['status'] = "Status inválido. Valores aceitos: " . implode(', ', $statusValidos);
        }
        
        // Valida data
        if (!empty($data['data_inicio'])) {
            $date = \DateTime::createFromFormat('Y-m-d H:i:s', $data['data_inicio']);
            if (!$date) {
                $date = \DateTime::createFromFormat('Y-m-d\TH:i:s', $data['data_inicio']);
            }
            if (!$date) {
                $errors['data_inicio'] = "Formato de data inválido. Use: Y-m-d H:i:s ou Y-m-d\TH:i:s";
            }
        }
        
        // Valida tamanho
        if (isset($data['tamanho_bytes']) && !is_numeric($data['tamanho_bytes'])) {
            $errors['tamanho_bytes'] = "O campo 'tamanho_bytes' deve ser numérico";
        }
        
        return $errors;
    }

    /**
     * Formata tamanho em bytes para exibição
     */
    public static function formatBytes(?int $bytes): string
    {
        if ($bytes === null || $bytes === 0) {
            return '-';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
