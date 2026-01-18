#!/usr/bin/env php
<?php
/**
 * Script de verificação de hosts offline
 * 
 * Deve ser executado periodicamente via cron job para verificar
 * quais hosts não enviaram telemetria dentro do threshold configurado
 * e marcá-los como offline.
 * 
 * Exemplo de cron job (a cada 5 minutos):
 * * /5 * * * * /usr/bin/php /caminho/para/check-offline-hosts.php
 */

// Carrega autoload
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Models/Model.php';
require_once __DIR__ . '/../app/Models/Host.php';

use App\Models\Host;

// Modo silencioso (não exibe output) ou verbose
$verbose = in_array('-v', $argv) || in_array('--verbose', $argv);

function log_msg(string $message, bool $verbose): void
{
    if ($verbose) {
        echo "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;
    }
}

try {
    log_msg("Iniciando verificação de hosts offline...", $verbose);
    
    // Executa a verificação
    $affectedRows = Host::checkOfflineHosts();
    
    if ($affectedRows > 0) {
        log_msg("$affectedRows host(s) marcado(s) como offline", $verbose);
    } else {
        log_msg("Nenhum host marcado como offline", $verbose);
    }
    
    // Lista hosts atualmente offline se verbose
    if ($verbose) {
        $offlineHosts = Host::offlineHosts();
        
        if (!empty($offlineHosts)) {
            log_msg("Hosts offline atualmente:", $verbose);
            foreach ($offlineHosts as $host) {
                $lastSeen = $host['last_seen_at'] ? date('d/m/Y H:i:s', strtotime($host['last_seen_at'])) : 'nunca';
                log_msg("  - {$host['nome']} ({$host['cliente_nome']}) - Último contato: $lastSeen", $verbose);
            }
        }
    }
    
    exit(0);
    
} catch (Exception $e) {
    log_msg("Erro: " . $e->getMessage(), true);
    exit(1);
}
