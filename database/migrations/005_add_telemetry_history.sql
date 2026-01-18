-- ============================================
-- Migration: Adicionar histórico de telemetria e configurações
-- ============================================

-- Tabela de histórico de telemetria
CREATE TABLE IF NOT EXISTS telemetria_historico (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    host_id INT UNSIGNED NOT NULL,
    cliente_id INT UNSIGNED NOT NULL,
    cpu_percent DECIMAL(5,2) NULL,
    memory_percent DECIMAL(5,2) NULL,
    disk_percent DECIMAL(5,2) NULL,
    uptime_seconds BIGINT UNSIGNED NULL,
    data_completa JSON NULL COMMENT 'Dados completos da telemetria',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (host_id) REFERENCES hosts(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_host (host_id),
    INDEX idx_cliente (cliente_id),
    INDEX idx_created_at (created_at),
    INDEX idx_host_created (host_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar configuração de retenção de telemetria
INSERT INTO configuracoes (chave, valor, tipo, descricao) VALUES
('dias_retencao_telemetria', '0', 'integer', 'Dias para reter histórico de telemetria. 0 = nunca apagar')
ON DUPLICATE KEY UPDATE chave = chave;
