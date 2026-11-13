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

-- Segurança de acesso: limite de tentativas de login e de pedidos de
-- recuperação de senha (por e-mail e por endereço IP).
CREATE TABLE IF NOT EXISTS tentativas_acesso (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo ENUM('login', 'recuperacao') NOT NULL,
    email VARCHAR(190) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    criado_em DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_tentativas_email (tipo, email, criado_em),
    KEY idx_tentativas_ip (tipo, ip, criado_em),
    KEY idx_tentativas_data (criado_em)
) ENGINE=InnoDB;

-- Links de redefinição de senha: guarda apenas o hash do código enviado por e-mail.
CREATE TABLE IF NOT EXISTS redefinicoes_senha (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expira_em DATETIME NOT NULL,
    usado_em DATETIME NULL,
    criado_em DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_redefinicoes_token (token_hash),
    KEY idx_redefinicoes_usuario (usuario_id),
    CONSTRAINT fk_redefinicoes_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
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

-- Contas de demonstração. Senhas: AdminDemo2026!, Organizador2026! e Participante2026!.
INSERT INTO usuarios (nome, email, matricula, senha_hash, perfil)
SELECT 'Administração Eventeen', 'admin.demo@eventeen.local', NULL,
       '$2y$10$eLmiSr47zvzBKz8mGYSOQeI3Az42Hq2R88O68pNc/hcKg/1LQuLEa', 'administrador'
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'admin.demo@eventeen.local');

INSERT INTO usuarios (nome, email, matricula, senha_hash, perfil)
SELECT 'Organização Eventeen', 'organizador.demo@eventeen.local', NULL,
       '$2y$10$nhGmVP5y90sIQ2wjgdP3BOAfyd8LFtvt.qb9y.m0hicapFSalr8A6', 'organizador'
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'organizador.demo@eventeen.local');

INSERT INTO usuarios (nome, email, matricula, senha_hash, perfil)
SELECT 'Ana Participante', 'participante.demo@eventeen.local', 'DEMO-2026-001',
       '$2y$10$VL7xQXcqqbWUYzAPIV.zKOWM14ZQTtVprLRd0jUo7.gmThR8NK/GC', 'participante'
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'participante.demo@eventeen.local');

INSERT INTO usuarios (nome, email, matricula, senha_hash, perfil)
SELECT 'Bruno Participante', 'participante2.demo@eventeen.local', 'DEMO-2026-002',
       '$2y$10$VL7xQXcqqbWUYzAPIV.zKOWM14ZQTtVprLRd0jUo7.gmThR8NK/GC', 'participante'
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'participante2.demo@eventeen.local');

INSERT INTO eventos
    (titulo, descricao, categoria, local, data_inicio, data_fim, capacidade, valor_inscricao, status)
SELECT 'Mostra de Projetos Acadêmicos',
       'Apresentação de projetos desenvolvidos por estudantes, com palestras e oficinas abertas à comunidade.',
       'Mostra acadêmica', 'Campus Fatec Ourinhos', '2026-11-10 09:00:00', '2026-11-10 17:00:00', 100, 0.00, 'encerrado'
WHERE NOT EXISTS (SELECT 1 FROM eventos WHERE titulo = 'Mostra de Projetos Acadêmicos');

INSERT INTO eventos
    (titulo, descricao, categoria, local, data_inicio, data_fim, capacidade, valor_inscricao, status)
SELECT 'Semana de Tecnologia e Inovação',
       'Encontro acadêmico sobre inteligência artificial, desenvolvimento de software e empreendedorismo.',
       'Tecnologia', 'Campus Fatec Ourinhos', '2026-12-10 09:00:00', '2026-12-10 18:00:00', 120, 25.00, 'publicado'
WHERE NOT EXISTS (SELECT 1 FROM eventos WHERE titulo = 'Semana de Tecnologia e Inovação');

INSERT INTO evento_organizadores (evento_id, usuario_id)
SELECT e.id, u.id
FROM eventos e
JOIN usuarios u ON u.email = 'organizador.demo@eventeen.local'
WHERE e.titulo IN ('Mostra de Projetos Acadêmicos', 'Semana de Tecnologia e Inovação')
  AND NOT EXISTS (
      SELECT 1 FROM evento_organizadores eo
      WHERE eo.evento_id = e.id AND eo.usuario_id = u.id
  );

INSERT INTO espacos (evento_id, nome, capacidade, recursos)
SELECT e.id, 'Auditório Principal', 100, 'Projetor, microfone, sistema de som e acessibilidade'
FROM eventos e
WHERE e.titulo = 'Mostra de Projetos Acadêmicos'
  AND NOT EXISTS (SELECT 1 FROM espacos s WHERE s.evento_id = e.id AND s.nome = 'Auditório Principal');

INSERT INTO espacos (evento_id, nome, capacidade, recursos)
SELECT e.id, 'Laboratório de Informática', 30, 'Computadores, internet e projetor'
FROM eventos e
WHERE e.titulo = 'Mostra de Projetos Acadêmicos'
  AND NOT EXISTS (SELECT 1 FROM espacos s WHERE s.evento_id = e.id AND s.nome = 'Laboratório de Informática');

INSERT INTO espacos (evento_id, nome, capacidade, recursos)
SELECT e.id, 'Auditório Principal', 120, 'Projetor, microfone, sistema de som e acessibilidade'
FROM eventos e
WHERE e.titulo = 'Semana de Tecnologia e Inovação'
  AND NOT EXISTS (SELECT 1 FROM espacos s WHERE s.evento_id = e.id AND s.nome = 'Auditório Principal');

INSERT INTO espacos (evento_id, nome, capacidade, recursos)
SELECT e.id, 'Sala de Oficinas', 30, 'Computadores, internet e quadro branco'
FROM eventos e
WHERE e.titulo = 'Semana de Tecnologia e Inovação'
  AND NOT EXISTS (SELECT 1 FROM espacos s WHERE s.evento_id = e.id AND s.nome = 'Sala de Oficinas');

INSERT INTO palestrantes (evento_id, nome, email, bio)
SELECT e.id, 'Marina Costa', 'marina.costa.demo@eventeen.local',
       'Pesquisadora em inovação e orientação de projetos acadêmicos.'
FROM eventos e
WHERE e.titulo = 'Mostra de Projetos Acadêmicos'
  AND NOT EXISTS (SELECT 1 FROM palestrantes p WHERE p.evento_id = e.id AND p.email = 'marina.costa.demo@eventeen.local');

INSERT INTO palestrantes (evento_id, nome, email, bio)
SELECT e.id, 'Rafael Almeida', 'rafael.almeida.demo@eventeen.local',
       'Especialista em desenvolvimento de software e inteligência artificial.'
FROM eventos e
WHERE e.titulo = 'Semana de Tecnologia e Inovação'
  AND NOT EXISTS (SELECT 1 FROM palestrantes p WHERE p.evento_id = e.id AND p.email = 'rafael.almeida.demo@eventeen.local');

INSERT INTO palestrantes (evento_id, nome, email, bio)
SELECT e.id, 'Camila Souza', 'camila.souza.demo@eventeen.local',
       'Designer de produtos digitais e facilitadora de oficinas de experiência do usuário.'
FROM eventos e
WHERE e.titulo = 'Semana de Tecnologia e Inovação'
  AND NOT EXISTS (SELECT 1 FROM palestrantes p WHERE p.evento_id = e.id AND p.email = 'camila.souza.demo@eventeen.local');

INSERT INTO atividades (evento_id, espaco_id, titulo, descricao, tipo, inicio, fim, capacidade)
SELECT e.id, s.id, 'Abertura e apresentação dos projetos',
       'Boas-vindas e apresentação dos trabalhos acadêmicos selecionados.',
       'Palestra', '2026-11-10 09:30:00', '2026-11-10 10:30:00', 100
FROM eventos e
JOIN espacos s ON s.evento_id = e.id AND s.nome = 'Auditório Principal'
WHERE e.titulo = 'Mostra de Projetos Acadêmicos'
  AND NOT EXISTS (SELECT 1 FROM atividades a WHERE a.evento_id = e.id AND a.titulo = 'Abertura e apresentação dos projetos');

INSERT INTO atividades (evento_id, espaco_id, titulo, descricao, tipo, inicio, fim, capacidade)
SELECT e.id, s.id, 'Prototipação de soluções',
       'Oficina prática de ideação e prototipação de soluções para problemas reais.',
       'Oficina', '2026-11-10 14:00:00', '2026-11-10 16:00:00', 25
FROM eventos e
JOIN espacos s ON s.evento_id = e.id AND s.nome = 'Laboratório de Informática'
WHERE e.titulo = 'Mostra de Projetos Acadêmicos'
  AND NOT EXISTS (SELECT 1 FROM atividades a WHERE a.evento_id = e.id AND a.titulo = 'Prototipação de soluções');

INSERT INTO atividades (evento_id, espaco_id, titulo, descricao, tipo, inicio, fim, capacidade)
SELECT e.id, s.id, 'Inteligência artificial aplicada',
       'Panorama de aplicações de inteligência artificial em produtos e serviços.',
       'Palestra', '2026-12-10 09:30:00', '2026-12-10 11:00:00', 100
FROM eventos e
JOIN espacos s ON s.evento_id = e.id AND s.nome = 'Auditório Principal'
WHERE e.titulo = 'Semana de Tecnologia e Inovação'
  AND NOT EXISTS (SELECT 1 FROM atividades a WHERE a.evento_id = e.id AND a.titulo = 'Inteligência artificial aplicada');

INSERT INTO atividades (evento_id, espaco_id, titulo, descricao, tipo, inicio, fim, capacidade)
SELECT e.id, s.id, 'Oficina de experiência do usuário',
       'Exercício de pesquisa, jornada do usuário e prototipação de interfaces.',
       'Oficina', '2026-12-10 14:00:00', '2026-12-10 16:00:00', 30
FROM eventos e
JOIN espacos s ON s.evento_id = e.id AND s.nome = 'Sala de Oficinas'
WHERE e.titulo = 'Semana de Tecnologia e Inovação'
  AND NOT EXISTS (SELECT 1 FROM atividades a WHERE a.evento_id = e.id AND a.titulo = 'Oficina de experiência do usuário');

INSERT INTO atividade_palestrantes (atividade_id, palestrante_id, evento_id)
SELECT a.id, p.id, a.evento_id
FROM atividades a
JOIN eventos e ON e.id = a.evento_id
JOIN palestrantes p ON p.evento_id = e.id
WHERE (
       (e.titulo = 'Mostra de Projetos Acadêmicos' AND a.titulo = 'Abertura e apresentação dos projetos'
        AND p.email = 'marina.costa.demo@eventeen.local')
    OR (e.titulo = 'Mostra de Projetos Acadêmicos' AND a.titulo = 'Prototipação de soluções'
        AND p.email = 'marina.costa.demo@eventeen.local')
    OR (e.titulo = 'Semana de Tecnologia e Inovação' AND a.titulo = 'Inteligência artificial aplicada'
        AND p.email = 'rafael.almeida.demo@eventeen.local')
    OR (e.titulo = 'Semana de Tecnologia e Inovação' AND a.titulo = 'Oficina de experiência do usuário'
        AND p.email = 'camila.souza.demo@eventeen.local')
)
  AND NOT EXISTS (
      SELECT 1 FROM atividade_palestrantes ap
      WHERE ap.atividade_id = a.id AND ap.palestrante_id = p.id AND ap.evento_id = a.evento_id
  );

INSERT INTO inscricoes (usuario_id, evento_id, status)
SELECT u.id, e.id, 'ativa'
FROM usuarios u
JOIN eventos e ON e.titulo IN ('Mostra de Projetos Acadêmicos', 'Semana de Tecnologia e Inovação')
WHERE u.email IN ('participante.demo@eventeen.local', 'participante2.demo@eventeen.local')
  AND NOT EXISTS (
      SELECT 1 FROM inscricoes i WHERE i.usuario_id = u.id AND i.evento_id = e.id
  );

INSERT INTO presencas_atividades (atividade_id, evento_id, usuario_id)
SELECT a.id, a.evento_id, u.id
FROM atividades a
JOIN eventos e ON e.id = a.evento_id
JOIN usuarios u ON u.email = 'participante.demo@eventeen.local'
JOIN inscricoes i ON i.usuario_id = u.id AND i.evento_id = e.id
WHERE e.titulo = 'Mostra de Projetos Acadêmicos'
  AND a.titulo IN ('Abertura e apresentação dos projetos', 'Prototipação de soluções')
  AND NOT EXISTS (
      SELECT 1 FROM presencas_atividades pa
      WHERE pa.atividade_id = a.id AND pa.usuario_id = u.id
  );

INSERT INTO certificados (atividade_id, usuario_id, evento_id, codigo_validacao, carga_horaria)
SELECT a.id, u.id, a.evento_id,
       IF(a.titulo = 'Abertura e apresentação dos projetos', 'EVT2026DEMO00001', 'EVT2026DEMO00002'),
       IF(a.titulo = 'Abertura e apresentação dos projetos', 1.00, 2.00)
FROM atividades a
JOIN eventos e ON e.id = a.evento_id
JOIN usuarios u ON u.email = 'participante.demo@eventeen.local'
JOIN presencas_atividades pa ON pa.atividade_id = a.id AND pa.usuario_id = u.id
WHERE e.titulo = 'Mostra de Projetos Acadêmicos'
  AND a.titulo IN ('Abertura e apresentação dos projetos', 'Prototipação de soluções')
  AND NOT EXISTS (
      SELECT 1 FROM certificados c WHERE c.atividade_id = a.id AND c.usuario_id = u.id
  );

INSERT INTO parceiros (criado_por, nome, email, telefone)
SELECT u.id, 'Nuvem do Vale Tecnologia', 'contato.demo@nuvemdovale.local', '(14) 3000-0000'
FROM usuarios u
WHERE u.email = 'organizador.demo@eventeen.local'
  AND NOT EXISTS (
      SELECT 1 FROM parceiros p
      WHERE p.criado_por = u.id AND p.email = 'contato.demo@nuvemdovale.local'
  );

INSERT INTO patrocinios
    (evento_id, parceiro_id, tipo_apoio, cota, descricao_apoio, contrapartida, valor, status)
SELECT e.id, p.id, 'financeiro', 'Ouro', 'Apoio financeiro para infraestrutura e materiais.',
       'Exibição da marca nos materiais e no local do evento.', 1500.00, 'confirmado'
FROM eventos e
JOIN parceiros p ON p.email = 'contato.demo@nuvemdovale.local'
WHERE e.titulo = 'Semana de Tecnologia e Inovação'
  AND NOT EXISTS (
      SELECT 1 FROM patrocinios pa WHERE pa.evento_id = e.id AND pa.parceiro_id = p.id AND pa.cota = 'Ouro'
  );

INSERT INTO financeiro_lancamentos
    (criado_por, patrocinio_id, descricao, tipo, valor, data_movimentacao, status)
SELECT u.id, pa.id, 'Patrocínio confirmado - Nuvem do Vale', 'entrada', 1500.00, '2026-11-01', 'realizado'
FROM usuarios u
JOIN patrocinios pa ON pa.cota = 'Ouro'
JOIN eventos e ON e.id = pa.evento_id AND e.titulo = 'Semana de Tecnologia e Inovação'
JOIN parceiros p ON p.id = pa.parceiro_id AND p.email = 'contato.demo@nuvemdovale.local'
WHERE u.email = 'organizador.demo@eventeen.local'
  AND NOT EXISTS (
      SELECT 1 FROM financeiro_lancamentos fl
      WHERE fl.patrocinio_id = pa.id AND fl.descricao = 'Patrocínio confirmado - Nuvem do Vale'
  );

INSERT INTO financeiro_rateios (lancamento_id, evento_id, valor)
SELECT fl.id, e.id, fl.valor
FROM financeiro_lancamentos fl
JOIN patrocinios pa ON pa.id = fl.patrocinio_id
JOIN eventos e ON e.id = pa.evento_id AND e.titulo = 'Semana de Tecnologia e Inovação'
WHERE fl.descricao = 'Patrocínio confirmado - Nuvem do Vale'
  AND NOT EXISTS (
      SELECT 1 FROM financeiro_rateios fr WHERE fr.lancamento_id = fl.id AND fr.evento_id = e.id
  );
