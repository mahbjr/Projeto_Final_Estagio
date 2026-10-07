-- =====================================================================
-- Sistema Web de Ordens de Serviço de Energia (CodeIgniter 4)
-- Script DDL: schema.sql
-- Alinhado rigorosamente com docs/diagramDB.mmd
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- Padrões: Chaves Primárias, Estrangeiras, Auditoria e Soft Deletes
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. TABELA DE USUÁRIOS (tbl_usuario)
-- Autenticação, controle de acesso e papéis do sistema
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `tbl_usuario`;
CREATE TABLE `tbl_usuario` (
    `id_usu` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nome_usu` VARCHAR(150) NOT NULL COMMENT 'login ou email',
    `nome_completo_usu` VARCHAR(120) DEFAULT NULL,
    `cpf_usu` VARCHAR(14) DEFAULT NULL,
    `telefone_usu` VARCHAR(20) DEFAULT NULL,
    `cargo_usu` VARCHAR(80) DEFAULT NULL,
    `senha_usu` VARCHAR(255) NOT NULL COMMENT 'hash bcrypt/argon2',
    `papel_usu` ENUM('gestor', 'operador', 'eletricista') NOT NULL,
    `ativo_usu` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=ativo, 0=inativo',
    `data_criacao_usu` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao_usu` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `data_exclusao_usu` DATETIME DEFAULT NULL COMMENT 'soft delete',
    PRIMARY KEY (`id_usu`),
    UNIQUE KEY `uk_cpf_usu` (`cpf_usu`),
    UNIQUE KEY `uk_nome_usu` (`nome_usu`),
    INDEX `idx_usuario_papel` (`papel_usu`),
    INDEX `idx_usuario_ativo` (`ativo_usu`),
    INDEX `idx_usuario_exclusao` (`data_exclusao_usu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. TABELA DE ELETRICISTAS (tbl_eletricista)
-- Cadastro técnico dos eletricistas de campo (1:1 opcional com tbl_usuario)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `tbl_eletricista`;
CREATE TABLE `tbl_eletricista` (
    `id_ele` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_ele` INT UNSIGNED DEFAULT NULL COMMENT 'UK nullable - 1:1 com tbl_usuario',
    `matricula_ele` VARCHAR(50) NOT NULL,
    `data_criacao_ele` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao_ele` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `data_exclusao_ele` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id_ele`),
    UNIQUE KEY `uk_usuario_ele` (`usuario_ele`),
    UNIQUE KEY `uk_matricula_ele` (`matricula_ele`),
    INDEX `idx_eletricista_exclusao` (`data_exclusao_ele`),
    CONSTRAINT `fk_ele_usuario` FOREIGN KEY (`usuario_ele`) REFERENCES `tbl_usuario` (`id_usu`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. TABELA DE CLIENTES (tbl_cliente)
-- Empresas contratantes de serviços de campo (B2B)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `tbl_cliente`;
CREATE TABLE `tbl_cliente` (
    `id_cli` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nome_cli` VARCHAR(150) NOT NULL COMMENT 'razao social da empresa contratante',
    `cnpj_cli` VARCHAR(14) NOT NULL COMMENT '12 caracteres alfanumericos e 2 digitos, sem mascara, maiusculo',
    `email_cli` VARCHAR(120) DEFAULT NULL,
    `telefone_cli` VARCHAR(20) NOT NULL,
    `endereco_cli` VARCHAR(255) NOT NULL COMMENT 'endereco comercial, nao local de atendimento',
    `bairro_cli` VARCHAR(100) NOT NULL,
    `cidade_cli` VARCHAR(100) NOT NULL,
    `estado_cli` CHAR(2) NOT NULL,
    `cep_cli` VARCHAR(10) NOT NULL,
    `status_cli` ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    `data_criacao_cli` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao_cli` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `data_exclusao_cli` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id_cli`),
    UNIQUE KEY `uk_cli_cnpj` (`cnpj_cli`),
    INDEX `idx_cliente_nome` (`nome_cli`),
    INDEX `idx_cliente_status` (`status_cli`),
    INDEX `idx_cliente_exclusao` (`data_exclusao_cli`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. TABELA DE MEDIDORES (tbl_medidor)
-- Controle dos medidores em depósito, viaturas e instalados
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `tbl_medidor`;
CREATE TABLE `tbl_medidor` (
    `id_med` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `eletricista_posse_med` INT UNSIGNED DEFAULT NULL COMMENT 'nullable - eletricista com posse',
    `numero_med` VARCHAR(50) NOT NULL,
    `modelo_med` VARCHAR(80) NOT NULL,
    `fabricante_med` VARCHAR(80) NOT NULL,
    `status_med` ENUM('disponivel', 'reservado', 'em_transito', 'instalado', 'defeito', 'perdido', 'baixado') NOT NULL DEFAULT 'disponivel',
    `localizacao_med` ENUM('deposito', 'viatura', 'cliente') NOT NULL DEFAULT 'deposito',
    `data_criacao_med` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao_med` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `data_exclusao_med` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id_med`),
    UNIQUE KEY `uk_med_numero` (`numero_med`),
    INDEX `idx_med_status` (`status_med`),
    INDEX `idx_med_localizacao` (`localizacao_med`),
    INDEX `idx_med_eletricista_posse` (`eletricista_posse_med`),
    INDEX `idx_med_exclusao` (`data_exclusao_med`),
    CONSTRAINT `fk_med_eletricista_posse` FOREIGN KEY (`eletricista_posse_med`) REFERENCES `tbl_eletricista` (`id_ele`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. TABELA DE ORDENS DE SERVIÇO (tbl_os)
-- Ordens de serviço para corte e nova ligação
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `tbl_os`;
CREATE TABLE `tbl_os` (
    `id_oss` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cliente_oss` INT UNSIGNED NOT NULL,
    `eletricista_oss` INT UNSIGNED DEFAULT NULL COMMENT 'nullable se não despachada',
    `tipo_oss` ENUM('corte', 'nova_ligacao') NOT NULL,
    `status_oss` ENUM('aberta', 'atribuida', 'em_atendimento', 'encerrada', 'cancelada') NOT NULL DEFAULT 'aberta',
    `descricao_oss` TEXT NOT NULL,
    `unidade_consumidora_oss` VARCHAR(50) NOT NULL COMMENT 'UC atendida, pode receber varias OS',
    `endereco_oss` VARCHAR(255) NOT NULL COMMENT 'local de atendimento registrado nesta OS',
    `bairro_oss` VARCHAR(100) NOT NULL,
    `cidade_oss` VARCHAR(100) NOT NULL,
    `estado_oss` CHAR(2) NOT NULL,
    `cep_oss` VARCHAR(10) NOT NULL,
    `data_abertura_oss` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_fechamento_oss` DATETIME DEFAULT NULL,
    `data_criacao_oss` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao_oss` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `data_exclusao_oss` DATETIME DEFAULT NULL,
    prioridade_oss ENUM('baixa','normal','alta','urgente') NOT NULL DEFAULT 'normal',
    agendamento_oss DATETIME DEFAULT NULL,
    resultado_oss ENUM('executado','parcial','nao_executado') DEFAULT NULL,
    observacoes_finais_oss TEXT DEFAULT NULL,
    observacoes_administrativas_oss TEXT DEFAULT NULL,
    corte_confirmado_oss TINYINT(1) NOT NULL DEFAULT 0,
    leitura_final_oss DECIMAL(15,3) DEFAULT NULL,
    inicio_atendimento_oss DATETIME DEFAULT NULL,
    PRIMARY KEY (`id_oss`),
    INDEX `idx_oss_cliente` (`cliente_oss`),
    INDEX `idx_oss_eletricista` (`eletricista_oss`),
    INDEX `idx_oss_status` (`status_oss`),
    INDEX `idx_oss_tipo` (`tipo_oss`),
    INDEX `idx_oss_abertura` (`data_abertura_oss`),
    INDEX `idx_oss_exclusao` (`data_exclusao_oss`),
    CONSTRAINT `fk_oss_cliente` FOREIGN KEY (`cliente_oss`) REFERENCES `tbl_cliente` (`id_cli`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_oss_eletricista` FOREIGN KEY (`eletricista_oss`) REFERENCES `tbl_eletricista` (`id_ele`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. TABELA DE HISTÓRICO DA OS (tbl_os_historico)
-- Trilha de auditoria das mudanças de status da OS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `tbl_os_historico`;
CREATE TABLE `tbl_os_historico` (
    `id_osh` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ordem_servico_osh` INT UNSIGNED NOT NULL,
    `eletricista_osh` INT UNSIGNED DEFAULT NULL COMMENT 'nullable - eletricista ou técnico',
    `status_osh` VARCHAR(50) NOT NULL,
    `observacao_osh` TEXT DEFAULT NULL,
    `data_osh` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_criacao_osh` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usuario_osh INT UNSIGNED DEFAULT NULL,
    evento_osh VARCHAR(50) NOT NULL DEFAULT 'status',
    status_anterior_osh VARCHAR(50) DEFAULT NULL,
    dados_osh JSON DEFAULT NULL,
    PRIMARY KEY (`id_osh`),
    INDEX `idx_osh_os` (`ordem_servico_osh`),
    INDEX `idx_osh_eletricista` (`eletricista_osh`),
    INDEX `idx_osh_data` (`data_osh`),
    CONSTRAINT `fk_osh_os` FOREIGN KEY (`ordem_servico_osh`) REFERENCES `tbl_os` (`id_oss`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_osh_eletricista` FOREIGN KEY (`eletricista_osh`) REFERENCES `tbl_eletricista` (`id_ele`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_osh_usuario FOREIGN KEY (usuario_osh) REFERENCES tbl_usuario(id_usu) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. TABELA DE MEDIDORES NA OS (tbl_os_medidor)
-- Registro de medidores instalados ou retirados na OS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `tbl_os_medidor`;
CREATE TABLE `tbl_os_medidor` (
    `id_osm` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ordem_servico_osm` INT UNSIGNED NOT NULL,
    `medidor_osm` INT UNSIGNED NOT NULL,
    `tipo_osm` ENUM('instalado', 'retirado') NOT NULL,
    `data_osm` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_criacao_osm` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao_osm` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `data_exclusao_osm` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id_osm`),
    INDEX `idx_osm_os` (`ordem_servico_osm`),
    INDEX `idx_osm_medidor` (`medidor_osm`),
    INDEX `idx_osm_exclusao` (`data_exclusao_osm`),
    CONSTRAINT `fk_osm_os` FOREIGN KEY (`ordem_servico_osm`) REFERENCES `tbl_os` (`id_oss`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_osm_medidor` FOREIGN KEY (`medidor_osm`) REFERENCES `tbl_medidor` (`id_med`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. TABELA DE MOVIMENTAÇÃO DE ESTOQUE (tbl_estoque_mov)
-- Rastreabilidade de entradas, transferências e baixas de medidores
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `tbl_estoque_mov`;
CREATE TABLE `tbl_estoque_mov` (
    `id_emv` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `medidor_emv` INT UNSIGNED NOT NULL,
    `ordem_servico_emv` INT UNSIGNED DEFAULT NULL COMMENT 'nullable',
    `eletricista_emv` INT UNSIGNED DEFAULT NULL COMMENT 'nullable',
    `tipo_emv` ENUM('entrada', 'transferencia', 'baixa_saida') NOT NULL,
    `motivo_emv` ENUM('compra', 'perda', 'roubo', 'dano', 'consumo', 'ajuste') NOT NULL,
    `origem_emv` ENUM('galpao', 'eletricista', 'cliente', 'fornecedor') NOT NULL,
    `destino_emv` ENUM('galpao', 'eletricista', 'cliente', 'descarte') NOT NULL,
    `quantidade_emv` INT NOT NULL DEFAULT 1,
    `observacao_emv` VARCHAR(255) DEFAULT NULL,
    `data_emv` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_criacao_emv` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao_emv` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `data_exclusao_emv` DATETIME DEFAULT NULL,
    usuario_emv INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (`id_emv`),
    INDEX `idx_emv_medidor` (`medidor_emv`),
    INDEX `idx_emv_os` (`ordem_servico_emv`),
    INDEX `idx_emv_eletricista` (`eletricista_emv`),
    INDEX `idx_emv_tipo` (`tipo_emv`),
    INDEX `idx_emv_data` (`data_emv`),
    INDEX `idx_emv_exclusao` (`data_exclusao_emv`),
    CONSTRAINT `fk_emv_medidor` FOREIGN KEY (`medidor_emv`) REFERENCES `tbl_medidor` (`id_med`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_emv_os` FOREIGN KEY (`ordem_servico_emv`) REFERENCES `tbl_os` (`id_oss`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_emv_eletricista` FOREIGN KEY (`eletricista_emv`) REFERENCES `tbl_eletricista` (`id_ele`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_emv_usuario FOREIGN KEY (usuario_emv) REFERENCES tbl_usuario(id_usu) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. TABELA DE ANEXOS DA OS (tbl_anexo)
-- Fotos e documentos comprobatórios das Ordens de Serviço
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `tbl_anexo`;
CREATE TABLE `tbl_anexo` (
    `id_anx` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ordem_servico_anx` INT UNSIGNED NOT NULL,
    `arquivo_anx` VARCHAR(255) NOT NULL COMMENT 'caminho do arquivo/foto',
    `tipo_anx` ENUM('foto', 'documento') NOT NULL,
    `descricao_anx` VARCHAR(255) DEFAULT NULL,
    `data_anx` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_criacao_anx` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao_anx` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `data_exclusao_anx` DATETIME DEFAULT NULL,
    usuario_anx INT UNSIGNED DEFAULT NULL,
    mime_anx VARCHAR(50) DEFAULT NULL,
    tamanho_anx INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (`id_anx`),
    INDEX `idx_anx_os` (`ordem_servico_anx`),
    INDEX `idx_anx_exclusao` (`data_exclusao_anx`),
    CONSTRAINT `fk_anx_os` FOREIGN KEY (`ordem_servico_anx`) REFERENCES `tbl_os` (`id_oss`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_anx_usuario FOREIGN KEY (usuario_anx) REFERENCES tbl_usuario(id_usu) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Estruturas do fluxo operacional
DROP TABLE IF EXISTS `tbl_medidor_reserva`;
CREATE TABLE `tbl_medidor_reserva` (
    id_rme INT UNSIGNED NOT NULL AUTO_INCREMENT,
    medidor_rme INT UNSIGNED NOT NULL,
    ordem_servico_rme INT UNSIGNED NOT NULL,
    eletricista_rme INT UNSIGNED NOT NULL,
    usuario_rme INT UNSIGNED NOT NULL,
    status_rme ENUM('reservada','entregue','devolucao_pendente','aplicada','devolvida','perdida','liberada') NOT NULL DEFAULT 'reservada',
    medidor_ativo_rme INT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status_rme IN ('reservada','entregue','devolucao_pendente') AND data_exclusao_rme IS NULL THEN medidor_rme ELSE NULL END) STORED,
    data_criacao_rme DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao_rme DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    data_exclusao_rme DATETIME DEFAULT NULL,
    PRIMARY KEY (id_rme),
    INDEX idx_rme_medidor_rme (medidor_rme),
    CONSTRAINT fk_rme_medidor_rme FOREIGN KEY (medidor_rme) REFERENCES tbl_medidor(id_med) ON DELETE RESTRICT ON UPDATE RESTRICT,
    INDEX idx_rme_ordem_servico_rme (ordem_servico_rme),
    CONSTRAINT fk_rme_ordem_servico_rme FOREIGN KEY (ordem_servico_rme) REFERENCES tbl_os(id_oss) ON DELETE RESTRICT ON UPDATE RESTRICT,
    INDEX idx_rme_eletricista_rme (eletricista_rme),
    CONSTRAINT fk_rme_eletricista_rme FOREIGN KEY (eletricista_rme) REFERENCES tbl_eletricista(id_ele) ON DELETE RESTRICT ON UPDATE RESTRICT,
    INDEX idx_rme_usuario_rme (usuario_rme),
    CONSTRAINT fk_rme_usuario_rme FOREIGN KEY (usuario_rme) REFERENCES tbl_usuario(id_usu) ON DELETE RESTRICT ON UPDATE RESTRICT,
    UNIQUE KEY uk_rme_medidor_ativo (medidor_ativo_rme)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tbl_instalacao_atual`;
CREATE TABLE `tbl_instalacao_atual` (
    id_ins INT UNSIGNED NOT NULL AUTO_INCREMENT,
    medidor_ins INT UNSIGNED NOT NULL,
    ordem_servico_ins INT UNSIGNED NOT NULL,
    unidade_consumidora_ins VARCHAR(50) NOT NULL,
    usuario_ins INT UNSIGNED NOT NULL,
    data_criacao_ins DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_ins),
    INDEX idx_ins_medidor_ins (medidor_ins),
    CONSTRAINT fk_ins_medidor_ins FOREIGN KEY (medidor_ins) REFERENCES tbl_medidor(id_med) ON DELETE RESTRICT ON UPDATE RESTRICT,
    INDEX idx_ins_ordem_servico_ins (ordem_servico_ins),
    CONSTRAINT fk_ins_ordem_servico_ins FOREIGN KEY (ordem_servico_ins) REFERENCES tbl_os(id_oss) ON DELETE RESTRICT ON UPDATE RESTRICT,
    INDEX idx_ins_usuario_ins (usuario_ins),
    CONSTRAINT fk_ins_usuario_ins FOREIGN KEY (usuario_ins) REFERENCES tbl_usuario(id_usu) ON DELETE RESTRICT ON UPDATE RESTRICT,
    UNIQUE KEY uk_ins_medidor (medidor_ins)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tbl_checklist`;
CREATE TABLE `tbl_checklist` (
    id_chk INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome_chk VARCHAR(100) NOT NULL,
    tipo_os_chk ENUM('corte','nova_ligacao') NOT NULL,
    etapa_chk ENUM('inicio','fechamento') NOT NULL,
    ativo_chk TINYINT(1) NOT NULL DEFAULT 1,
    usuario_chk INT UNSIGNED NOT NULL,
    data_criacao_chk DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao_chk DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    data_exclusao_chk DATETIME DEFAULT NULL,
    PRIMARY KEY (id_chk),
    INDEX idx_chk_usuario_chk (usuario_chk),
    CONSTRAINT fk_chk_usuario_chk FOREIGN KEY (usuario_chk) REFERENCES tbl_usuario(id_usu) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tbl_checklist_item`;
CREATE TABLE `tbl_checklist_item` (
    id_chi INT UNSIGNED NOT NULL AUTO_INCREMENT,
    checklist_chi INT UNSIGNED NOT NULL,
    pergunta_chi VARCHAR(255) NOT NULL,
    resposta_esperada_chi TINYINT(1) NOT NULL DEFAULT 1,
    obrigatorio_chi TINYINT(1) NOT NULL DEFAULT 1,
    nivel_chi ENUM('bloqueante','informativo') NOT NULL,
    ordem_chi INT UNSIGNED NOT NULL DEFAULT 0,
    data_criacao_chi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao_chi DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    data_exclusao_chi DATETIME DEFAULT NULL,
    PRIMARY KEY (id_chi),
    INDEX idx_chi_checklist_chi (checklist_chi),
    CONSTRAINT fk_chi_checklist_chi FOREIGN KEY (checklist_chi) REFERENCES tbl_checklist(id_chk) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tbl_checklist_avaliacao`;
CREATE TABLE `tbl_checklist_avaliacao` (
    id_cav INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ordem_servico_cav INT UNSIGNED NOT NULL,
    checklist_cav INT UNSIGNED NOT NULL,
    usuario_cav INT UNSIGNED NOT NULL,
    etapa_cav ENUM('inicio','fechamento') NOT NULL,
    bloqueada_cav TINYINT(1) NOT NULL DEFAULT 0,
    liberado_por_cav INT UNSIGNED DEFAULT NULL,
    justificativa_liberacao_cav TEXT DEFAULT NULL,
    data_liberacao_cav DATETIME DEFAULT NULL,
    data_criacao_cav DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_cav),
    INDEX idx_cav_ordem_servico_cav (ordem_servico_cav),
    CONSTRAINT fk_cav_ordem_servico_cav FOREIGN KEY (ordem_servico_cav) REFERENCES tbl_os(id_oss) ON DELETE RESTRICT ON UPDATE RESTRICT,
    INDEX idx_cav_checklist_cav (checklist_cav),
    CONSTRAINT fk_cav_checklist_cav FOREIGN KEY (checklist_cav) REFERENCES tbl_checklist(id_chk) ON DELETE RESTRICT ON UPDATE RESTRICT,
    INDEX idx_cav_usuario_cav (usuario_cav),
    CONSTRAINT fk_cav_usuario_cav FOREIGN KEY (usuario_cav) REFERENCES tbl_usuario(id_usu) ON DELETE RESTRICT ON UPDATE RESTRICT,
    INDEX idx_cav_liberado_por_cav (liberado_por_cav),
    CONSTRAINT fk_cav_liberado_por_cav FOREIGN KEY (liberado_por_cav) REFERENCES tbl_usuario(id_usu) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CHECK (etapa_cav = 'inicio' OR (liberado_por_cav IS NULL AND justificativa_liberacao_cav IS NULL AND data_liberacao_cav IS NULL)),
    CHECK ((liberado_por_cav IS NULL AND justificativa_liberacao_cav IS NULL AND data_liberacao_cav IS NULL) OR (liberado_por_cav IS NOT NULL AND justificativa_liberacao_cav IS NOT NULL AND data_liberacao_cav IS NOT NULL AND bloqueada_cav = 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tbl_checklist_resposta`;
CREATE TABLE `tbl_checklist_resposta` (
    id_cre INT UNSIGNED NOT NULL AUTO_INCREMENT,
    avaliacao_cre INT UNSIGNED NOT NULL,
    item_cre INT UNSIGNED NOT NULL,
    pergunta_cre VARCHAR(255) NOT NULL,
    nivel_cre ENUM('bloqueante','informativo') NOT NULL,
    resposta_esperada_cre TINYINT(1) NOT NULL,
    resposta_cre TINYINT(1) DEFAULT NULL,
    observacao_cre TEXT DEFAULT NULL,
    data_criacao_cre DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_cre),
    INDEX idx_cre_avaliacao_cre (avaliacao_cre),
    CONSTRAINT fk_cre_avaliacao_cre FOREIGN KEY (avaliacao_cre) REFERENCES tbl_checklist_avaliacao(id_cav) ON DELETE RESTRICT ON UPDATE RESTRICT,
    INDEX idx_cre_item_cre (item_cre),
    CONSTRAINT fk_cre_item_cre FOREIGN KEY (item_cre) REFERENCES tbl_checklist_item(id_chi) ON DELETE RESTRICT ON UPDATE RESTRICT,
    UNIQUE KEY uk_cre_item (avaliacao_cre,item_cre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tbl_medidor_ocorrencia`;
CREATE TABLE `tbl_medidor_ocorrencia` (
    id_ome INT UNSIGNED NOT NULL AUTO_INCREMENT,
    medidor_ome INT UNSIGNED NOT NULL,
    ordem_servico_ome INT UNSIGNED DEFAULT NULL,
    usuario_ome INT UNSIGNED NOT NULL,
    eletricista_ome INT UNSIGNED DEFAULT NULL,
    tipo_ome ENUM('perda','roubo','dano','baixa') NOT NULL,
    justificativa_ome TEXT NOT NULL,
    estado_anterior_ome VARCHAR(30) NOT NULL,
    local_anterior_ome VARCHAR(30) NOT NULL,
    data_criacao_ome DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_ome),
    INDEX idx_ome_medidor_ome (medidor_ome),
    CONSTRAINT fk_ome_medidor_ome FOREIGN KEY (medidor_ome) REFERENCES tbl_medidor(id_med) ON DELETE RESTRICT ON UPDATE RESTRICT,
    INDEX idx_ome_ordem_servico_ome (ordem_servico_ome),
    CONSTRAINT fk_ome_ordem_servico_ome FOREIGN KEY (ordem_servico_ome) REFERENCES tbl_os(id_oss) ON DELETE RESTRICT ON UPDATE RESTRICT,
    INDEX idx_ome_usuario_ome (usuario_ome),
    CONSTRAINT fk_ome_usuario_ome FOREIGN KEY (usuario_ome) REFERENCES tbl_usuario(id_usu) ON DELETE RESTRICT ON UPDATE RESTRICT,
    INDEX idx_ome_eletricista_ome (eletricista_ome),
    CONSTRAINT fk_ome_eletricista_ome FOREIGN KEY (eletricista_ome) REFERENCES tbl_eletricista(id_ele) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
