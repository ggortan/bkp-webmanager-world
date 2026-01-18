-- ============================================
-- Migration: Adiciona campos de telemetria na tabela hosts
-- Versão: 2.1.0
-- Data: 2026-01-18
-- ============================================

-- Adiciona campos de telemetria
ALTER TABLE hosts
    ADD COLUMN telemetry_enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Se telemetria está habilitada para este host' AFTER descricao,
    ADD COLUMN telemetry_interval_minutes INT UNSIGNED NOT NULL DEFAULT 5 COMMENT 'Intervalo esperado de telemetria em minutos' AFTER telemetry_enabled,
    ADD COLUMN telemetry_offline_threshold INT UNSIGNED NOT NULL DEFAULT 3 COMMENT 'Quantidade de pings perdidos para considerar offline' AFTER telemetry_interval_minutes,
    ADD COLUMN last_seen_at TIMESTAMP NULL COMMENT 'Última vez que o host enviou telemetria' AFTER telemetry_offline_threshold,
    ADD COLUMN online_status ENUM('online', 'offline', 'unknown') NOT NULL DEFAULT 'unknown' COMMENT 'Status atual do host' AFTER last_seen_at,
    ADD COLUMN telemetry_data JSON NULL COMMENT 'Dados da última telemetria (CPU, RAM, disco, etc)' AFTER online_status;

-- Adiciona índices
ALTER TABLE hosts
    ADD INDEX idx_online_status (online_status),
    ADD INDEX idx_last_seen (last_seen_at);

-- Atualiza hosts existentes para status 'unknown'
UPDATE hosts SET online_status = 'unknown' WHERE online_status IS NULL;
