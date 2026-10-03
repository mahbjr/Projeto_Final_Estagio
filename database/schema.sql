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
    `senha_usu` VARCHAR(255) NOT NULL COMMENT 'hash bcrypt/argon2',
    `papel_usu` ENUM('gestor', 'operador', 'eletricista') NOT NULL,
    `ativo_usu` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=ativo, 0=inativo',
    `data_criacao_usu` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao_usu` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `data_exclusao_usu` DATETIME DEFAULT NULL COMMENT 'soft delete',
    PRIMARY KEY (`id_usu`),
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
    `cpf_ele` VARCHAR(14) NOT NULL,
    `nome_ele` VARCHAR(120) NOT NULL,
    `telefone_ele` VARCHAR(20) DEFAULT NULL,
    `matricula_ele` VARCHAR(50) NOT NULL,
    `data_criacao_ele` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao_ele` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `data_exclusao_ele` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id_ele`),
    UNIQUE KEY `uk_cpf_ele` (`cpf_ele`),
    UNIQUE KEY `uk_usuario_ele` (`usuario_ele`),
    UNIQUE KEY `uk_matricula_ele` (`matricula_ele`),
    INDEX `idx_eletricista_nome` (`nome_ele`),
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
    `status_med` ENUM('disponivel', 'em_transito', 'instalado', 'defeito') NOT NULL DEFAULT 'disponivel',
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
    `status_oss` ENUM('aberta', 'em_andamento', 'concluida', 'cancelada') NOT NULL DEFAULT 'aberta',
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
    PRIMARY KEY (`id_osh`),
    INDEX `idx_osh_os` (`ordem_servico_osh`),
    INDEX `idx_osh_eletricista` (`eletricista_osh`),
    INDEX `idx_osh_data` (`data_osh`),
    CONSTRAINT `fk_osh_os` FOREIGN KEY (`ordem_servico_osh`) REFERENCES `tbl_os` (`id_oss`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_osh_eletricista` FOREIGN KEY (`eletricista_osh`) REFERENCES `tbl_eletricista` (`id_ele`) ON DELETE RESTRICT ON UPDATE CASCADE
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
    PRIMARY KEY (`id_emv`),
    INDEX `idx_emv_medidor` (`medidor_emv`),
    INDEX `idx_emv_os` (`ordem_servico_emv`),
    INDEX `idx_emv_eletricista` (`eletricista_emv`),
    INDEX `idx_emv_tipo` (`tipo_emv`),
    INDEX `idx_emv_data` (`data_emv`),
    INDEX `idx_emv_exclusao` (`data_exclusao_emv`),
    CONSTRAINT `fk_emv_medidor` FOREIGN KEY (`medidor_emv`) REFERENCES `tbl_medidor` (`id_med`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_emv_os` FOREIGN KEY (`ordem_servico_emv`) REFERENCES `tbl_os` (`id_oss`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_emv_eletricista` FOREIGN KEY (`eletricista_emv`) REFERENCES `tbl_eletricista` (`id_ele`) ON DELETE RESTRICT ON UPDATE CASCADE
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
    PRIMARY KEY (`id_anx`),
    INDEX `idx_anx_os` (`ordem_servico_anx`),
    INDEX `idx_anx_exclusao` (`data_exclusao_anx`),
    CONSTRAINT `fk_anx_os` FOREIGN KEY (`ordem_servico_anx`) REFERENCES `tbl_os` (`id_oss`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
