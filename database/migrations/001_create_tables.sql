-- ============================================
-- Backup WebManager - Database Schema
-- World Informática
-- ============================================

-- Criar banco de dados
CREATE DATABASE IF NOT EXISTS backup_webmanager
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE backup_webmanager;

-- ============================================
-- Tabela: usuarios_roles (Papéis de usuário)
-- ============================================
CREATE TABLE IF NOT EXISTS usuarios_roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL UNIQUE,
    descricao VARCHAR(255) NULL,
    permissoes JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir papéis padrão
INSERT INTO usuarios_roles (id, nome, descricao, permissoes) VALUES
(1, 'admin', 'Administrador - Acesso total ao sistema', '{"all": true}'),
(2, 'operator', 'Operador - Pode gerenciar backups e clientes', '{"dashboard": true, "clientes": true, "backups": true, "relatorios": true}'),
(3, 'viewer', 'Visualização - Apenas visualização de dados', '{"dashboard": true, "relatorios": {"view": true}}');

-- ============================================
-- Tabela: usuarios
-- ============================================
CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    azure_id VARCHAR(100) NULL UNIQUE COMMENT 'ID do usuário no Azure AD',
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    avatar VARCHAR(500) NULL,
    role_id INT UNSIGNED NOT NULL DEFAULT 3,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (role_id) REFERENCES usuarios_roles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_email (email),
    INDEX idx_azure_id (azure_id),
    INDEX idx_role (role_id),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabela: clientes
-- ============================================
CREATE TABLE IF NOT EXISTS clientes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identificador VARCHAR(50) NOT NULL UNIQUE COMMENT 'Identificador único do cliente',
    nome VARCHAR(200) NOT NULL,
    email VARCHAR(255) NULL,
    telefone VARCHAR(20) NULL,
    api_key VARCHAR(64) NOT NULL UNIQUE COMMENT 'Token para autenticação da API',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    relatorios_ativos TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Se deve receber relatórios por e-mail',
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_identificador (identificador),
    INDEX idx_api_key (api_key),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabela: servidores
-- ============================================
CREATE TABLE IF NOT EXISTS servidores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    nome VARCHAR(100) NOT NULL COMMENT 'Nome do servidor',
    hostname VARCHAR(255) NULL,
    ip VARCHAR(45) NULL,
    sistema_operacional VARCHAR(100) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uk_cliente_servidor (cliente_id, nome),
    INDEX idx_cliente (cliente_id),
    INDEX idx_nome (nome),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabela: rotinas_backup
-- ============================================
CREATE TABLE IF NOT EXISTS rotinas_backup (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    servidor_id INT UNSIGNED NOT NULL,
    nome VARCHAR(100) NOT NULL COMMENT 'Nome da rotina de backup',
    tipo VARCHAR(50) NULL COMMENT 'Tipo de backup (full, incremental, etc)',
    agendamento VARCHAR(100) NULL COMMENT 'Descrição do agendamento',
    destino VARCHAR(500) NULL COMMENT 'Destino do backup',
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (servidor_id) REFERENCES servidores(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uk_servidor_rotina (servidor_id, nome),
    INDEX idx_servidor (servidor_id),
    INDEX idx_nome (nome),
    INDEX idx_ativa (ativa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabela: execucoes_backup
-- ============================================
CREATE TABLE IF NOT EXISTS execucoes_backup (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rotina_id INT UNSIGNED NOT NULL,
    cliente_id INT UNSIGNED NOT NULL COMMENT 'Redundância para consultas rápidas',
    servidor_id INT UNSIGNED NOT NULL COMMENT 'Redundância para consultas rápidas',
    data_inicio TIMESTAMP NOT NULL,
    data_fim TIMESTAMP NULL,
    status ENUM('sucesso', 'falha', 'alerta', 'executando') NOT NULL DEFAULT 'executando',
    tamanho_bytes BIGINT UNSIGNED NULL COMMENT 'Tamanho do backup em bytes',
    destino VARCHAR(500) NULL,
    mensagem_erro TEXT NULL,
    detalhes JSON NULL COMMENT 'Detalhes adicionais em formato JSON',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (rotina_id) REFERENCES rotinas_backup(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (servidor_id) REFERENCES servidores(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_rotina (rotina_id),
    INDEX idx_cliente (cliente_id),
    INDEX idx_servidor (servidor_id),
    INDEX idx_status (status),
    INDEX idx_data_inicio (data_inicio),
    INDEX idx_cliente_status (cliente_id, status),
    INDEX idx_cliente_data (cliente_id, data_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabela: logs
-- ============================================
CREATE TABLE IF NOT EXISTS logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('info', 'warning', 'error', 'debug', 'api') NOT NULL DEFAULT 'info',
    categoria VARCHAR(50) NULL COMMENT 'Categoria do log (auth, backup, api, etc)',
    mensagem TEXT NOT NULL,
    dados JSON NULL COMMENT 'Dados adicionais',
    usuario_id INT UNSIGNED NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_tipo (tipo),
    INDEX idx_categoria (categoria),
    INDEX idx_usuario (usuario_id),
    INDEX idx_created_at (created_at),
    INDEX idx_tipo_categoria (tipo, categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabela: configuracoes_email
-- ============================================
CREATE TABLE IF NOT EXISTS configuracoes_email (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NULL COMMENT 'NULL para configuração global',
    tipo VARCHAR(50) NOT NULL COMMENT 'Tipo de relatório',
    destinatarios JSON NOT NULL COMMENT 'Lista de e-mails',
    assunto VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    frequencia ENUM('manual', 'diario', 'semanal', 'mensal') NOT NULL DEFAULT 'manual',
    hora_envio TIME NULL COMMENT 'Hora preferida para envio automático',
    ultimo_envio TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uk_cliente_tipo (cliente_id, tipo),
    INDEX idx_cliente (cliente_id),
    INDEX idx_tipo (tipo),
    INDEX idx_ativo (ativo),
    INDEX idx_frequencia (frequencia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabela: api_tokens (Tokens de sessão da API)
-- ============================================
CREATE TABLE IF NOT EXISTS api_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    nome VARCHAR(100) NULL COMMENT 'Nome/descrição do token',
    ultimo_uso TIMESTAMP NULL,
    expira_em TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_token (token),
    INDEX idx_usuario (usuario_id),
    INDEX idx_expira (expira_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabela: configuracoes (Configurações do sistema)
-- ============================================
CREATE TABLE IF NOT EXISTS configuracoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT NULL,
    tipo ENUM('string', 'integer', 'boolean', 'json') NOT NULL DEFAULT 'string',
    descricao VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir configurações padrão
INSERT INTO configuracoes (chave, valor, tipo, descricao) VALUES
('relatorios_email_ativo', 'true', 'boolean', 'Ativar/desativar envio de relatórios por e-mail'),
('dias_retencao_logs', '90', 'integer', 'Quantidade de dias para manter logs no sistema'),
('alerta_backups_falhos', 'true', 'boolean', 'Alertar quando houver backups com falha'),
('timezone', 'America/Sao_Paulo', 'string', 'Fuso horário do sistema');
