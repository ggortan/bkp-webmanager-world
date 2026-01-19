<?php
/**
 * Script de diagnóstico para verificar os detalhes de backup armazenados
 * 
 * Execute: php scripts/check-backup-details.php
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../app/Database.php';

use App\Database;

try {
    $pdo = Database::connection();
    
    echo "=== Últimas 5 Execuções de Backup (Veeam) ===\n\n";
    
    $sql = "SELECT e.id, e.data_inicio, e.status, e.mensagem_erro, e.detalhes, r.nome as rotina_nome
            FROM execucoes_backup e
            JOIN rotinas_backup r ON e.rotina_id = r.id
            WHERE r.tipo = 'veeam' OR r.nome LIKE '%Veeam%' OR e.detalhes LIKE '%Veeam%' OR e.detalhes LIKE '%veeam%'
            ORDER BY e.id DESC
            LIMIT 5";
    
    $stmt = $pdo->query($sql);
    $execucoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($execucoes)) {
        echo "Nenhuma execução Veeam encontrada. Verificando todas as últimas execuções...\n\n";
        
        $sql = "SELECT e.id, e.data_inicio, e.status, e.mensagem_erro, e.detalhes, r.nome as rotina_nome
                FROM execucoes_backup e
                LEFT JOIN rotinas_backup r ON e.rotina_id = r.id
                ORDER BY e.id DESC
                LIMIT 5";
        
        $stmt = $pdo->query($sql);
        $execucoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    foreach ($execucoes as $exec) {
        echo "--- Execução ID: {$exec['id']} ---\n";
        echo "Rotina: {$exec['rotina_nome']}\n";
        echo "Data: {$exec['data_inicio']}\n";
        echo "Status: {$exec['status']}\n";
        
        if ($exec['mensagem_erro']) {
            echo "Mensagem Erro: {$exec['mensagem_erro']}\n";
        }
        
        echo "Detalhes (raw length): " . strlen($exec['detalhes'] ?? '') . " bytes\n";
        
        if ($exec['detalhes']) {
            $detalhes = json_decode($exec['detalhes'], true);
            
            if ($detalhes) {
                echo "Detalhes (chaves): " . implode(', ', array_keys($detalhes)) . "\n";
                
                // Verifica campos importantes
                $campos = ['source', 'tipo_backup', 'ProcessedVMs', 'Warnings', 'ErrorLogs', 'FailureMessage', 'TargetRepository'];
                foreach ($campos as $campo) {
                    if (isset($detalhes[$campo])) {
                        $valor = is_array($detalhes[$campo]) ? '[array: ' . count($detalhes[$campo]) . ' items]' : substr(strval($detalhes[$campo]), 0, 50);
                        echo "  - $campo: $valor\n";
                    }
                }
                
                // Verifica host_info
                if (isset($detalhes['host_info'])) {
                    echo "  - host_info: " . json_encode($detalhes['host_info']) . "\n";
                }
            } else {
                echo "Detalhes (JSON inválido): " . substr($exec['detalhes'], 0, 200) . "\n";
            }
        } else {
            echo "Detalhes: NULL\n";
        }
        
        echo "\n";
    }
    
    echo "=== Estatísticas ===\n";
    
    // Conta quantas execuções têm detalhes completos vs básicos
    $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN detalhes LIKE '%ProcessedVMs%' THEN 1 ELSE 0 END) as com_vms,
                SUM(CASE WHEN detalhes LIKE '%source%' THEN 1 ELSE 0 END) as com_source,
                SUM(CASE WHEN detalhes LIKE '%Warnings%' THEN 1 ELSE 0 END) as com_warnings,
                SUM(CASE WHEN LENGTH(detalhes) > 500 THEN 1 ELSE 0 END) as detalhes_grandes
            FROM execucoes_backup";
    
    $stmt = $pdo->query($sql);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Total de execuções: {$stats['total']}\n";
    echo "Com ProcessedVMs: {$stats['com_vms']}\n";
    echo "Com source: {$stats['com_source']}\n";
    echo "Com Warnings: {$stats['com_warnings']}\n";
    echo "Com detalhes > 500 bytes: {$stats['detalhes_grandes']}\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
