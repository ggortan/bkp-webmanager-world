-- ============================================
-- Backup WebManager - Transformação para Sistema Baseado em Rotinas
-- Migração 002 - World Informática
-- ============================================

USE backup_webmanager;

-- ============================================
-- 1. Modificar tabela rotinas_backup
-- ============================================

-- Adicionar coluna cliente_id para vincular rotinas diretamente aos clientes
ALTER TABLE rotinas_backup 
ADD COLUMN cliente_id INT UNSIGNED NULL COMMENT 'Cliente ao qual a rotina pertence' AFTER id;

-- Adicionar routine_key para identificação única da rotina
ALTER TABLE rotinas_backup 
ADD COLUMN routine_key VARCHAR(64) NULL UNIQUE COMMENT 'Chave única para identificar a rotina na API' AFTER cliente_id;

-- Adicionar host_info para armazenar informações do host
ALTER TABLE rotinas_backup 
ADD COLUMN host_info JSON NULL COMMENT 'Informações do host (nome, hostname, IP, SO, etc)' AFTER routine_key;

-- Tornar servidor_id opcional (NULL) para rotinas que não estão vinculadas a um servidor específico
ALTER TABLE rotinas_backup 
MODIFY COLUMN servidor_id INT UNSIGNED NULL COMMENT 'Servidor (opcional, para compatibilidade)';

-- Remover constraint de servidor_id (permitir NULL)
ALTER TABLE rotinas_backup 
DROP FOREIGN KEY rotinas_backup_ibfk_1;

-- Adicionar constraint com ON DELETE SET NULL
ALTER TABLE rotinas_backup 
ADD CONSTRAINT fk_rotina_servidor 
FOREIGN KEY (servidor_id) REFERENCES servidores(id) 
ON DELETE SET NULL ON UPDATE CASCADE;

-- Adicionar constraint para cliente_id
ALTER TABLE rotinas_backup 
ADD CONSTRAINT fk_rotina_cliente 
FOREIGN KEY (cliente_id) REFERENCES clientes(id) 
ON DELETE CASCADE ON UPDATE CASCADE;

-- Adicionar índices
ALTER TABLE rotinas_backup 
ADD INDEX idx_cliente (cliente_id);

ALTER TABLE rotinas_backup 
ADD INDEX idx_routine_key (routine_key);

-- Atualizar unique key - agora permite servidor_id NULL
ALTER TABLE rotinas_backup 
DROP INDEX uk_servidor_rotina;

ALTER TABLE rotinas_backup 
ADD UNIQUE KEY uk_cliente_rotina_nome (cliente_id, nome);

-- ============================================
-- 2. Preencher cliente_id nas rotinas existentes
-- ============================================

-- Atualizar rotinas existentes com o cliente_id do servidor
UPDATE rotinas_backup rb
INNER JOIN servidores s ON rb.servidor_id = s.id
SET rb.cliente_id = s.cliente_id
WHERE rb.cliente_id IS NULL;

-- ============================================
-- 3. Gerar routine_key para rotinas existentes
-- ============================================

-- Criar procedure temporária para gerar routine_keys
DELIMITER //

CREATE PROCEDURE generate_routine_keys()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE rotina_id INT;
    DECLARE cur CURSOR FOR SELECT id FROM rotinas_backup WHERE routine_key IS NULL;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO rotina_id;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
-- Gera uma chave única baseada em random bytes (14 bytes = 28 hex chars + prefixo 'rtk_' = 32 chars)
        UPDATE rotinas_backup 
        SET routine_key = CONCAT('rtk_', LOWER(HEX(RANDOM_BYTES(14))))
        WHERE id = rotina_id;
    END LOOP;
    
    CLOSE cur;
END//

DELIMITER ;

-- Executar a procedure
CALL generate_routine_keys();

-- Remover a procedure temporária
DROP PROCEDURE generate_routine_keys;

-- ============================================
-- 4. Tornar campos obrigatórios após migração
-- ============================================

-- Agora que todas as rotinas têm cliente_id, tornar obrigatório
ALTER TABLE rotinas_backup 
MODIFY COLUMN cliente_id INT UNSIGNED NOT NULL COMMENT 'Cliente ao qual a rotina pertence';

-- Agora que todas as rotinas têm routine_key, tornar obrigatório
ALTER TABLE rotinas_backup 
MODIFY COLUMN routine_key VARCHAR(64) NOT NULL UNIQUE COMMENT 'Chave única para identificar a rotina na API';

-- ============================================
-- 5. Modificar tabela execucoes_backup
-- ============================================

-- servidor_id já existe e pode ser NULL para rotinas sem servidor específico
-- Adicionar constraint para permitir NULL
ALTER TABLE execucoes_backup 
DROP FOREIGN KEY execucoes_backup_ibfk_3;

ALTER TABLE execucoes_backup 
MODIFY COLUMN servidor_id INT UNSIGNED NULL COMMENT 'Servidor (opcional, extraído do host_info da rotina)';

ALTER TABLE execucoes_backup 
ADD CONSTRAINT fk_execucao_servidor 
FOREIGN KEY (servidor_id) REFERENCES servidores(id) 
ON DELETE SET NULL ON UPDATE CASCADE;

-- ============================================
-- 6. Criar view para facilitar consultas
-- ============================================

CREATE OR REPLACE VIEW v_rotinas_completas AS
SELECT 
    r.id,
    r.cliente_id,
    r.servidor_id,
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
    s.nome AS servidor_nome,
    s.hostname AS servidor_hostname,
    s.ip AS servidor_ip
FROM rotinas_backup r
INNER JOIN clientes c ON r.cliente_id = c.id
LEFT JOIN servidores s ON r.servidor_id = s.id;

-- ============================================
-- 7. Criar view para execuções com informações completas
-- ============================================

CREATE OR REPLACE VIEW v_execucoes_completas AS
SELECT 
    e.id,
    e.rotina_id,
    e.cliente_id,
    e.servidor_id,
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
    s.nome AS servidor_nome,
    s.hostname AS servidor_hostname
FROM execucoes_backup e
INNER JOIN rotinas_backup r ON e.rotina_id = r.id
INNER JOIN clientes c ON e.cliente_id = c.id
LEFT JOIN servidores s ON e.servidor_id = s.id;

-- ============================================
-- Registrar migração como concluída
-- ============================================

INSERT INTO configuracoes (chave, valor, tipo, descricao) VALUES
('schema_version', '002', 'string', 'Versão do schema do banco de dados')
ON DUPLICATE KEY UPDATE valor = '002', updated_at = CURRENT_TIMESTAMP;

-- ============================================
-- FIM DA MIGRAÇÃO
-- ============================================
