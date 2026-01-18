<?php
/**
 * Serviço de Backup
 * 
 * Gerencia operações relacionadas a backups
 */

namespace App\Services;

use App\Models\Cliente;
use App\Models\Host;
use App\Models\RotinaBackup;
use App\Models\ExecucaoBackup;
use App\Database;

class BackupService
{
    // Comprimento total mínimo para routine_key: 'rtk_' (4 chars) + pelo menos 10 hex chars = 14 chars
    const ROUTINE_KEY_MIN_LENGTH_TOTAL = 14;
    
    /**
     * Registra uma execução de backup recebida pela API
     */
    public function registrarExecucao(array $data, array $cliente): array
    {
        Database::beginTransaction();
        
        try {
            $rotina = null;
            $host = null;
            
            // Verifica se routine_key foi fornecido
            if (empty($data['routine_key'])) {
                throw new \Exception("O campo 'routine_key' é obrigatório");
            }
            
            $rotina = RotinaBackup::findByRoutineKey($data['routine_key']);
            
            if (!$rotina) {
                throw new \Exception("Rotina não encontrada com a routine_key fornecida");
            }
            
            // Verifica se a rotina pertence ao cliente autenticado
            if ($rotina['cliente_id'] != $cliente['id']) {
                throw new \Exception("Rotina não pertence ao cliente autenticado");
            }
            
            // Atualiza host_info se fornecido
            if (!empty($data['host_info'])) {
                $hostInfo = is_array($data['host_info']) ? json_encode($data['host_info']) : $data['host_info'];
                RotinaBackup::update($rotina['id'], ['host_info' => $hostInfo]);
                $rotina['host_info'] = $hostInfo;
            }
            
            // Se a rotina tem host vinculado, usa ele
            if ($rotina['host_id']) {
                $host = Host::find($rotina['host_id']);
            }
            
            // Se não tem host vinculado mas tem host_info, cria ou encontra o host automaticamente
            if (!$host && !empty($data['host_info'])) {
                $hostData = is_array($data['host_info']) ? $data['host_info'] : json_decode($data['host_info'], true);
                
                if (!empty($hostData['name']) || !empty($hostData['nome'])) {
                    $hostNome = $hostData['name'] ?? $hostData['nome'];
                    
                    // Tenta encontrar host existente pelo nome
                    $existingHost = Host::findByNomeAndCliente($hostNome, $cliente['id']);
                    
                    if ($existingHost) {
                        $host = $existingHost;
                        
                        // Atualiza informações do host se fornecidas
                        $updateData = [];
                        if (!empty($hostData['ip']) && $hostData['ip'] !== $existingHost['ip']) {
                            $updateData['ip'] = $hostData['ip'];
                        }
                        if (!empty($hostData['hostname']) && $hostData['hostname'] !== $existingHost['hostname']) {
                            $updateData['hostname'] = $hostData['hostname'];
                        }
                        if (!empty($hostData['os']) || !empty($hostData['sistema_operacional'])) {
                            $so = $hostData['os'] ?? $hostData['sistema_operacional'];
                            if ($so !== $existingHost['sistema_operacional']) {
                                $updateData['sistema_operacional'] = $so;
                            }
                        }
                        if (!empty($updateData)) {
                            Host::update($host['id'], $updateData);
                        }
                    } else {
                        // Cria novo host
                        $newHostData = [
                            'cliente_id' => $cliente['id'],
                            'nome' => $hostNome,
                            'hostname' => $hostData['hostname'] ?? $hostNome,
                            'ip' => $hostData['ip'] ?? null,
                            'sistema_operacional' => $hostData['os'] ?? $hostData['sistema_operacional'] ?? null,
                            'tipo' => $hostData['tipo'] ?? 'server',
                            'ativo' => 1,
                            'observacoes' => 'Criado automaticamente via API'
                        ];
                        
                        $hostId = Host::create($newHostData);
                        $host = Host::find($hostId);
                        
                        LogService::api('Host criado automaticamente via API', [
                            'cliente_id' => $cliente['id'],
                            'host_id' => $hostId,
                            'host_nome' => $hostNome
                        ]);
                    }
                    
                    // Vincula o host à rotina
                    if ($host && !$rotina['host_id']) {
                        RotinaBackup::update($rotina['id'], ['host_id' => $host['id']]);
                    }
                }
            }
            
            // Prepara host_info para armazenar na execução (se disponível)
            $hostInfo = null;
            if (!empty($data['host_info'])) {
                $hostInfo = is_array($data['host_info']) ? $data['host_info'] : json_decode($data['host_info'], true);
            } elseif (!empty($rotina['host_info'])) {
                $hostInfo = is_string($rotina['host_info']) ? json_decode($rotina['host_info'], true) : $rotina['host_info'];
            }
            
            // Adiciona host_info aos detalhes se disponível
            $detalhes = [];
            
            // Processa campo detalhes
            if (!empty($data['detalhes'])) {
                if (is_string($data['detalhes'])) {
                    // Se for string, tenta decodificar como JSON
                    $decoded = json_decode($data['detalhes'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $detalhes = $decoded;
                    } else {
                        // Se não for JSON válido, coloca a string em um campo 'mensagem'
                        $detalhes = ['mensagem' => $data['detalhes']];
                    }
                } elseif (is_array($data['detalhes'])) {
                    $detalhes = $data['detalhes'];
                }
            }
            
            if ($hostInfo) {
                $detalhes['host_info'] = $hostInfo;
            }
            
            // Registra a execução
            $execucaoData = [
                'rotina_id' => $rotina['id'],
                'cliente_id' => $cliente['id'],
                'host_id' => $host ? $host['id'] : null,
                'data_inicio' => $data['data_inicio'],
                'data_fim' => $data['data_fim'] ?? null,
                'status' => $data['status'],
                'tamanho_bytes' => $data['tamanho_bytes'] ?? null,
                'destino' => $data['destino'] ?? null,
                'mensagem_erro' => $data['mensagem_erro'] ?? null,
                'detalhes' => !empty($detalhes) ? json_encode($detalhes) : null
            ];
            
            $execucaoId = ExecucaoBackup::registrar($execucaoData);
            
            Database::commit();
            
            // Log
            LogService::api('Execução de backup registrada', [
                'cliente_id' => $cliente['id'],
                'cliente' => $cliente['identificador'],
                'rotina_id' => $rotina['id'],
                'rotina_nome' => $rotina['nome'],
                'routine_key' => $rotina['routine_key'],
                'host' => $host ? $host['nome'] : 'N/A',
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
            'total_hosts' => \App\Database::fetch(
                "SELECT COUNT(*) as total FROM hosts WHERE ativo = 1"
            )['total'] ?? 0
        ];
    }

    /**
     * Valida dados recebidos pela API
     */
    public function validarDadosApi(array $data): array
    {
        $errors = [];
        
        // Verifica se routine_key foi fornecido
        if (empty($data['routine_key'])) {
            $errors['routine_key'] = "O campo 'routine_key' é obrigatório";
        }
        
        // Valida routine_key
        if (!empty($data['routine_key']) && strlen($data['routine_key']) < self::ROUTINE_KEY_MIN_LENGTH_TOTAL) {
            $errors['routine_key'] = "routine_key inválido (muito curto)";
        }
        
        // Campos obrigatórios
        $required = ['data_inicio', 'status'];
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
        
        // Valida host_info se fornecido
        if (!empty($data['host_info']) && is_string($data['host_info'])) {
            $decoded = json_decode($data['host_info'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors['host_info'] = "host_info deve ser um JSON válido";
            }
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
