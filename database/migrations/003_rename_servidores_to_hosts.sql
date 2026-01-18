-- ============================================
-- Backup WebManager - Renomeação: Servidores → Hosts
-- Migração 003 - World Informática
-- Data: 2026-01-18
-- ============================================

USE backup_webmanager;

-- ============================================
-- 1. Renomear tabela servidores para hosts
-- ============================================

RENAME TABLE servidores TO hosts;

-- ============================================
-- 2. Renomear coluna servidor_id para host_id em rotinas_backup
-- ============================================

-- Remover constraint antiga
ALTER TABLE rotinas_backup 
DROP FOREIGN KEY fk_rotina_servidor;

-- Renomear coluna
ALTER TABLE rotinas_backup 
CHANGE COLUMN servidor_id host_id INT UNSIGNED NULL COMMENT 'Host (opcional, para compatibilidade)';

-- Adicionar constraint com novo nome
ALTER TABLE rotinas_backup 
ADD CONSTRAINT fk_rotina_host 
FOREIGN KEY (host_id) REFERENCES hosts(id) 
ON DELETE SET NULL ON UPDATE CASCADE;

-- Renomear índice
ALTER TABLE rotinas_backup 
DROP INDEX idx_servidor;

ALTER TABLE rotinas_backup 
ADD INDEX idx_host (host_id);

-- ============================================
-- 3. Renomear coluna servidor_id para host_id em execucoes_backup
-- ============================================

-- Remover constraint antiga
ALTER TABLE execucoes_backup 
DROP FOREIGN KEY fk_execucao_servidor;

-- Renomear coluna
ALTER TABLE execucoes_backup 
CHANGE COLUMN servidor_id host_id INT UNSIGNED NULL COMMENT 'Host (opcional, extraído do host_info da rotina)';

-- Adicionar constraint com novo nome
ALTER TABLE execucoes_backup 
ADD CONSTRAINT fk_execucao_host 
FOREIGN KEY (host_id) REFERENCES hosts(id) 
ON DELETE SET NULL ON UPDATE CASCADE;

-- Renomear índice
ALTER TABLE execucoes_backup 
DROP INDEX idx_servidor;

ALTER TABLE execucoes_backup 
ADD INDEX idx_host (host_id);

-- ============================================
-- 4. Adicionar novos campos em hosts
-- ============================================

-- Adicionar campo descricao se ainda não existir
ALTER TABLE hosts
ADD COLUMN IF NOT EXISTS descricao TEXT NULL COMMENT 'Descrição detalhada do host' AFTER observacoes;

-- Adicionar campo tipo se ainda não existir
ALTER TABLE hosts
ADD COLUMN IF NOT EXISTS tipo VARCHAR(50) NULL COMMENT 'Tipo do host: server, workstation, vm, container' AFTER sistema_operacional;

-- ============================================
-- 5. Atualizar views
-- ============================================

-- Recriar view v_rotinas_completas com novos nomes
DROP VIEW IF EXISTS v_rotinas_completas;

CREATE VIEW v_rotinas_completas AS
SELECT 
    r.id,
    r.cliente_id,
    r.host_id,
    r.routine_key,
    r.nome,
    r.tipo,
    r.agendamento,
    r.destino,
    r.ativa,
    r.host_info,
    r.observacoes,
    r.created_at,
    r.updated_at,
    c.identificador AS cliente_identificador,
    c.nome AS cliente_nome,
    c.ativo AS cliente_ativo,
    h.nome AS host_nome,
    h.hostname AS host_hostname,
    h.ip AS host_ip
FROM rotinas_backup r
INNER JOIN clientes c ON r.cliente_id = c.id
LEFT JOIN hosts h ON r.host_id = h.id;

-- Recriar view v_execucoes_completas com novos nomes
DROP VIEW IF EXISTS v_execucoes_completas;

CREATE VIEW v_execucoes_completas AS
SELECT 
    e.id,
    e.rotina_id,
    e.cliente_id,
    e.host_id,
    e.data_inicio,
    e.data_fim,
    e.status,
    e.tamanho_bytes,
    e.destino,
    e.mensagem_erro,
    e.detalhes,
    e.created_at,
    r.nome AS rotina_nome,
    r.routine_key,
    r.tipo AS rotina_tipo,
    r.host_info,
    c.identificador AS cliente_identificador,
    c.nome AS cliente_nome,
    h.nome AS host_nome,
    h.hostname AS host_hostname
FROM execucoes_backup e
INNER JOIN rotinas_backup r ON e.rotina_id = r.id
INNER JOIN clientes c ON e.cliente_id = c.id
LEFT JOIN hosts h ON e.host_id = h.id;

-- ============================================
-- 6. Registrar migração como concluída
-- ============================================

INSERT INTO configuracoes (chave, valor, tipo, descricao) VALUES
('schema_version', '003', 'string', 'Versão do schema do banco de dados')
ON DUPLICATE KEY UPDATE valor = '003', updated_at = CURRENT_TIMESTAMP;

-- ============================================
-- ROLLBACK INSTRUCTIONS (comentado)
-- ============================================

/*
-- Para reverter esta migração:

-- Renomear views
DROP VIEW IF EXISTS v_rotinas_completas;
DROP VIEW IF EXISTS v_execucoes_completas;

-- Renomear constraints e colunas em execucoes_backup
ALTER TABLE execucoes_backup DROP FOREIGN KEY fk_execucao_host;
ALTER TABLE execucoes_backup CHANGE COLUMN host_id servidor_id INT UNSIGNED NULL;
ALTER TABLE execucoes_backup ADD CONSTRAINT fk_execucao_servidor FOREIGN KEY (servidor_id) REFERENCES servidores(id) ON DELETE SET NULL;
ALTER TABLE execucoes_backup DROP INDEX idx_host;
ALTER TABLE execucoes_backup ADD INDEX idx_servidor (servidor_id);

-- Renomear constraints e colunas em rotinas_backup
ALTER TABLE rotinas_backup DROP FOREIGN KEY fk_rotina_host;
ALTER TABLE rotinas_backup CHANGE COLUMN host_id servidor_id INT UNSIGNED NULL;
ALTER TABLE rotinas_backup ADD CONSTRAINT fk_rotina_servidor FOREIGN KEY (servidor_id) REFERENCES servidores(id) ON DELETE SET NULL;
ALTER TABLE rotinas_backup DROP INDEX idx_host;
ALTER TABLE rotinas_backup ADD INDEX idx_servidor (servidor_id);

-- Renomear tabela
RENAME TABLE hosts TO servidores;

-- Recriar views originais
-- (Executar scripts das views originais da migration 002)
*/

-- ============================================
-- FIM DA MIGRAÇÃO
-- ============================================
