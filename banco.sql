SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS eventeen
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE eventeen;

CREATE TABLE IF NOT EXISTS usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    matricula VARCHAR(40) NULL,
    senha_hash VARCHAR(255) NOT NULL,
    perfil ENUM('participante', 'organizador', 'administrador') NOT NULL DEFAULT 'participante',
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_email (email),
    UNIQUE KEY uq_usuarios_matricula (matricula)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS eventos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    titulo VARCHAR(180) NOT NULL,
    descricao TEXT NOT NULL,
    categoria VARCHAR(80) NOT NULL,
    local VARCHAR(180) NOT NULL,
    data_inicio DATETIME NOT NULL,
    data_fim DATETIME NOT NULL,
    capacidade INT UNSIGNED NULL,
    valor_inscricao DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('rascunho', 'publicado', 'cancelado', 'encerrado') NOT NULL DEFAULT 'publicado',
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_eventos_status_data (status, data_inicio),
    KEY idx_eventos_categoria (categoria)
) ENGINE=InnoDB;

ALTER TABLE eventos
    MODIFY status ENUM('rascunho', 'publicado', 'cancelado', 'encerrado') NOT NULL DEFAULT 'publicado';

-- Atualização de bancos criados por versões anteriores (compatível com MySQL e MariaDB).
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'valor_inscricao');
SET @ddl := IF(@existe = 0,
    'ALTER TABLE eventos ADD COLUMN valor_inscricao DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER capacidade',
    'DO 0');
PREPARE comando FROM @ddl;
EXECUTE comando;
DEALLOCATE PREPARE comando;

CREATE TABLE IF NOT EXISTS evento_organizadores (
    evento_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (evento_id, usuario_id),
    KEY idx_evento_organizadores_usuario (usuario_id),
    CONSTRAINT fk_evento_organizadores_evento
        FOREIGN KEY (evento_id) REFERENCES eventos (id) ON DELETE CASCADE,
    CONSTRAINT fk_evento_organizadores_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inscricoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    evento_id BIGINT UNSIGNED NOT NULL,
    status ENUM('ativa', 'cancelada') NOT NULL DEFAULT 'ativa',
    inscrito_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inscricoes_usuario_evento (usuario_id, evento_id),
    KEY idx_inscricoes_evento_status (evento_id, status),
    KEY idx_inscricoes_usuario_status (usuario_id, status),
    CONSTRAINT fk_inscricoes_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_inscricoes_evento
        FOREIGN KEY (evento_id) REFERENCES eventos (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS palestrantes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    evento_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(190) NULL,
    bio TEXT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_palestrantes_id_evento (id, evento_id),
    KEY idx_palestrantes_evento_nome (evento_id, nome),
    CONSTRAINT fk_palestrantes_evento
        FOREIGN KEY (evento_id) REFERENCES eventos (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS espacos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    evento_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(120) NOT NULL,
    capacidade INT UNSIGNED NOT NULL,
    recursos VARCHAR(500) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_espacos_id_evento (id, evento_id),
    KEY idx_espacos_evento_nome (evento_id, nome),
    CONSTRAINT fk_espacos_evento
        FOREIGN KEY (evento_id) REFERENCES eventos (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS atividades (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    evento_id BIGINT UNSIGNED NOT NULL,
    espaco_id BIGINT UNSIGNED NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    descricao TEXT NULL,
    tipo VARCHAR(80) NOT NULL,
    inicio DATETIME NOT NULL,
    fim DATETIME NOT NULL,
    capacidade INT UNSIGNED NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_atividades_id_evento (id, evento_id),
    KEY idx_atividades_evento_inicio (evento_id, inicio),
    KEY idx_atividades_espaco_horario (espaco_id, inicio, fim),
    CONSTRAINT fk_atividades_evento
        FOREIGN KEY (evento_id) REFERENCES eventos (id) ON DELETE CASCADE,
    CONSTRAINT fk_atividades_espaco_evento
        FOREIGN KEY (espaco_id, evento_id) REFERENCES espacos (id, evento_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS atividade_palestrantes (
    atividade_id BIGINT UNSIGNED NOT NULL,
    palestrante_id BIGINT UNSIGNED NOT NULL,
    evento_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (atividade_id, palestrante_id),
    KEY idx_atividade_palestrantes_palestrante (palestrante_id, evento_id),
    CONSTRAINT fk_atividade_palestrantes_atividade
        FOREIGN KEY (atividade_id, evento_id) REFERENCES atividades (id, evento_id) ON DELETE CASCADE,
    CONSTRAINT fk_atividade_palestrantes_palestrante
        FOREIGN KEY (palestrante_id, evento_id) REFERENCES palestrantes (id, evento_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS presencas_atividades (
    atividade_id BIGINT UNSIGNED NOT NULL,
    evento_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    confirmado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (atividade_id, usuario_id),
    KEY idx_presencas_atividades_usuario_evento (usuario_id, evento_id, confirmado_em),
    CONSTRAINT fk_presencas_atividades_atividade
        FOREIGN KEY (atividade_id, evento_id) REFERENCES atividades (id, evento_id) ON DELETE CASCADE,
    CONSTRAINT fk_presencas_atividades_inscricao
        FOREIGN KEY (usuario_id, evento_id) REFERENCES inscricoes (usuario_id, evento_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS parceiros (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    criado_por BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(190) NULL,
    telefone VARCHAR(40) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_parceiros_criador_email (criado_por, email),
    KEY idx_parceiros_criador_nome (criado_por, nome),
    CONSTRAINT fk_parceiros_criador
        FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS patrocinios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    evento_id BIGINT UNSIGNED NOT NULL,
    parceiro_id BIGINT UNSIGNED NOT NULL,
    tipo_apoio ENUM('financeiro', 'material', 'institucional') NOT NULL DEFAULT 'financeiro',
    cota VARCHAR(80) NOT NULL,
    descricao_apoio VARCHAR(500) NULL,
    contrapartida VARCHAR(500) NULL,
    valor DECIMAL(12,2) NULL,
    status ENUM('negociacao', 'confirmado', 'cancelado') NOT NULL DEFAULT 'negociacao',
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_patrocinios_evento_status (evento_id, status),
    KEY idx_patrocinios_parceiro (parceiro_id),
    CONSTRAINT fk_patrocinios_evento
        FOREIGN KEY (evento_id) REFERENCES eventos (id) ON DELETE CASCADE,
    CONSTRAINT fk_patrocinios_parceiro
        FOREIGN KEY (parceiro_id) REFERENCES parceiros (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'patrocinios' AND COLUMN_NAME = 'tipo_apoio');
SET @ddl := IF(@existe = 0,
    'ALTER TABLE patrocinios ADD COLUMN tipo_apoio ENUM(''financeiro'', ''material'', ''institucional'') NOT NULL DEFAULT ''financeiro'' AFTER parceiro_id, ADD COLUMN descricao_apoio VARCHAR(500) NULL AFTER cota',
    'DO 0');
PREPARE comando FROM @ddl;
EXECUTE comando;
DEALLOCATE PREPARE comando;

ALTER TABLE patrocinios MODIFY valor DECIMAL(12,2) NULL;

CREATE TABLE IF NOT EXISTS financeiro_lancamentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    criado_por BIGINT UNSIGNED NOT NULL,
    patrocinio_id BIGINT UNSIGNED NULL,
    descricao VARCHAR(180) NOT NULL,
    tipo ENUM('entrada', 'despesa') NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    data_movimentacao DATE NOT NULL,
    status ENUM('pendente', 'realizado', 'cancelado') NOT NULL DEFAULT 'pendente',
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_financeiro_criador_status (criado_por, status),
    KEY idx_financeiro_patrocinio (patrocinio_id),
    CONSTRAINT fk_financeiro_criador
        FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE RESTRICT,
    CONSTRAINT fk_financeiro_patrocinio
        FOREIGN KEY (patrocinio_id) REFERENCES patrocinios (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS financeiro_rateios (
    lancamento_id BIGINT UNSIGNED NOT NULL,
    evento_id BIGINT UNSIGNED NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (lancamento_id, evento_id),
    KEY idx_financeiro_rateios_evento (evento_id),
    CONSTRAINT fk_financeiro_rateios_lancamento
        FOREIGN KEY (lancamento_id) REFERENCES financeiro_lancamentos (id) ON DELETE CASCADE,
    CONSTRAINT fk_financeiro_rateios_evento
        FOREIGN KEY (evento_id) REFERENCES eventos (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS certificados (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    atividade_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    evento_id BIGINT UNSIGNED NOT NULL,
    codigo_validacao CHAR(16) NOT NULL,
    carga_horaria DECIMAL(5,2) NOT NULL,
    emitido_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_certificados_presenca (atividade_id, usuario_id),
    UNIQUE KEY uq_certificados_codigo (codigo_validacao),
    KEY idx_certificados_usuario (usuario_id, emitido_em),
    KEY idx_certificados_evento (evento_id),
    CONSTRAINT fk_certificados_presenca
        FOREIGN KEY (atividade_id, usuario_id) REFERENCES presencas_atividades (atividade_id, usuario_id) ON DELETE RESTRICT,
    CONSTRAINT fk_certificados_evento
        FOREIGN KEY (evento_id) REFERENCES eventos (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pagamentos_inscricoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    inscricao_id BIGINT UNSIGNED NOT NULL,
    registrado_por BIGINT UNSIGNED NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    forma_pagamento ENUM('pix', 'dinheiro', 'cartao', 'transferencia', 'boleto') NOT NULL,
    status ENUM('pendente', 'confirmado', 'estornado') NOT NULL DEFAULT 'pendente',
    data_pagamento DATE NULL,
    data_estorno DATE NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pagamentos_inscricao (inscricao_id),
    KEY idx_pagamentos_status (status),
    CONSTRAINT fk_pagamentos_inscricao
        FOREIGN KEY (inscricao_id) REFERENCES inscricoes (id) ON DELETE RESTRICT,
    CONSTRAINT fk_pagamentos_registrado_por
        FOREIGN KEY (registrado_por) REFERENCES usuarios (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO eventos
    (titulo, descricao, categoria, local, data_inicio, data_fim, capacidade, status)
SELECT 'Conexões Inteligentes',
       'Uma conversa sobre redes, dispositivos conectados e as oportunidades da Internet das Coisas.',
       'Tecnologia', 'Bloco A', '2026-11-12 18:00:00', '2026-11-12 20:00:00', 120, 'publicado'
WHERE NOT EXISTS (SELECT 1 FROM eventos WHERE titulo = 'Conexões Inteligentes');

INSERT INTO eventos
    (titulo, descricao, categoria, local, data_inicio, data_fim, capacidade, status)
SELECT 'Inovação em IA',
       'Palestra introdutória sobre aplicações de inteligência artificial e seus impactos.',
       'Palestra', 'Auditório', '2026-11-18 19:30:00', '2026-11-18 21:00:00', 80, 'publicado'
WHERE NOT EXISTS (SELECT 1 FROM eventos WHERE titulo = 'Inovação em IA');

INSERT INTO eventos
    (titulo, descricao, categoria, local, data_inicio, data_fim, capacidade, status)
SELECT 'UX para Produtos Digitais',
       'Oficina prática de pesquisa com usuários, prototipação e avaliação de experiências digitais.',
       'Oficina', 'Sala 3', '2026-11-24 15:00:00', '2026-11-24 17:00:00', 30, 'publicado'
WHERE NOT EXISTS (SELECT 1 FROM eventos WHERE titulo = 'UX para Produtos Digitais');
