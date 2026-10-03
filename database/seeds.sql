-- =====================================================================
-- Sistema Web de Ordens de Serviço de Energia (CodeIgniter 4)
-- Script DML: seeds.sql
-- Alinhado rigorosamente com docs/diagramDB.mmd
-- Carga inicial de dados para testes operacionais
-- Senha padrão para todos os usuários: senha123 (hash bcrypt)
-- Hash: $2y$10$zJ4lsVZhtWe3.rHJunav4utO4X2IzjL6ai9vPkkoG9.Eu/pJCTxbK
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Limpeza prévia para garantir idempotência ao reexecutar as seeds
DELETE FROM `tbl_anexo`;
DELETE FROM `tbl_estoque_mov`;
DELETE FROM `tbl_os_medidor`;
DELETE FROM `tbl_os_historico`;
DELETE FROM `tbl_os`;
DELETE FROM `tbl_medidor`;
DELETE FROM `tbl_cliente`;
DELETE FROM `tbl_eletricista`;
DELETE FROM `tbl_usuario`;

ALTER TABLE `tbl_usuario` AUTO_INCREMENT = 1;
ALTER TABLE `tbl_eletricista` AUTO_INCREMENT = 1;
ALTER TABLE `tbl_cliente` AUTO_INCREMENT = 1;
ALTER TABLE `tbl_medidor` AUTO_INCREMENT = 1;
ALTER TABLE `tbl_os` AUTO_INCREMENT = 1;
ALTER TABLE `tbl_os_historico` AUTO_INCREMENT = 1;
ALTER TABLE `tbl_os_medidor` AUTO_INCREMENT = 1;
ALTER TABLE `tbl_estoque_mov` AUTO_INCREMENT = 1;
ALTER TABLE `tbl_anexo` AUTO_INCREMENT = 1;

-- ---------------------------------------------------------------------
-- 1. USUÁRIOS DO SISTEMA (tbl_usuario)
-- Papéis disponíveis: gestor, operador, eletricista
-- ---------------------------------------------------------------------
INSERT INTO `tbl_usuario` (`id_usu`, `nome_usu`, `senha_usu`, `papel_usu`, `ativo_usu`) VALUES
(1, 'gestor@energia.com.br', '$2y$10$zJ4lsVZhtWe3.rHJunav4utO4X2IzjL6ai9vPkkoG9.Eu/pJCTxbK', 'gestor', 1),
(2, 'operador@energia.com.br', '$2y$10$zJ4lsVZhtWe3.rHJunav4utO4X2IzjL6ai9vPkkoG9.Eu/pJCTxbK', 'operador', 1),
(3, 'eletricista1@energia.com.br', '$2y$10$zJ4lsVZhtWe3.rHJunav4utO4X2IzjL6ai9vPkkoG9.Eu/pJCTxbK', 'eletricista', 1),
(4, 'eletricista2@energia.com.br', '$2y$10$zJ4lsVZhtWe3.rHJunav4utO4X2IzjL6ai9vPkkoG9.Eu/pJCTxbK', 'eletricista', 1);

-- ---------------------------------------------------------------------
-- 2. CADASTRO TÉCNICO DE ELETRICISTAS (tbl_eletricista)
-- Vinculados 1:1 com usuários do papel eletricista
-- ---------------------------------------------------------------------
INSERT INTO `tbl_eletricista` (`id_ele`, `usuario_ele`, `cpf_ele`, `nome_ele`, `telefone_ele`, `matricula_ele`) VALUES
(1, 3, '333.333.333-33', 'João Eletricista', '(11) 97123-4567', 'ELE-2024-001'),
(2, 4, '444.444.444-44', 'Lucas Eletricista', '(11) 97987-6543', 'ELE-2024-002');

-- ---------------------------------------------------------------------
-- 3. EMPRESAS CONTRATANTES (tbl_cliente)
-- Dados fictícios; CNPJ normalizado, endereço exclusivamente comercial
-- ---------------------------------------------------------------------
INSERT INTO `tbl_cliente` (
    `id_cli`, `nome_cli`, `cnpj_cli`,
    `email_cli`, `telefone_cli`, `endereco_cli`, `bairro_cli`, `cidade_cli`, `estado_cli`, `cep_cli`, `status_cli`
) VALUES
(1, 'Alfa Serviços de Energia Ltda', '12345678000190', 'contato@alfa.example', '(85) 3211-0001', 'Av. Santos Dumont, 1000', 'Aldeota', 'Fortaleza', 'CE', '60150-160', 'ativo'),
(2, 'Nordeste Operações Elétricas Ltda', '23456789000180', 'contato@nordeste.example', '(85) 3211-0002', 'Rua General Sampaio, 245', 'Centro', 'Fortaleza', 'CE', '60020-030', 'ativo'),
(3, 'Beta Engenharia de Campo Ltda', 'AB12CD34000156', 'contato@beta.example', '(85) 3211-0003', 'Av. Bezerra de Menezes, 80', 'São Gerardo', 'Fortaleza', 'CE', '60325-000', 'ativo');

-- ---------------------------------------------------------------------
-- 4. MEDIDORES (tbl_medidor)
-- Depósito, Viatura do Eletricista e Instalado no Cliente
-- ---------------------------------------------------------------------
INSERT INTO `tbl_medidor` (`id_med`, `eletricista_posse_med`, `numero_med`, `modelo_med`, `fabricante_med`, `status_med`, `localizacao_med`) VALUES
-- Disponíveis no depósito central
(1, NULL, 'MED-SP-1001', 'EletroSmart B-200', 'Landis+Gyr', 'disponivel', 'deposito'),
(2, NULL, 'MED-SP-1002', 'EletroSmart B-200', 'Landis+Gyr', 'disponivel', 'deposito'),

-- Em posse/trânsito na viatura do Eletricista João (id_ele = 1)
(3, 1, 'MED-SP-2001', 'EcoPhase Monofásico', 'Schneider Electric', 'em_transito', 'viatura'),
(4, 1, 'MED-SP-2002', 'EcoPhase Trifásico', 'Schneider Electric', 'em_transito', 'viatura'),

-- Em posse/trânsito na viatura do Eletricista Lucas (id_ele = 2)
(5, 2, 'MED-SP-3001', 'Kron Multifunção 300', 'Kron Medidores', 'em_transito', 'viatura'),

-- Medidor instalado em uma UC atendida a pedido da empresa Alfa
(6, NULL, 'MED-SP-9001', 'Antigo Eletromecânico M-50', 'Nansen', 'instalado', 'cliente');

-- ---------------------------------------------------------------------
-- 5. ORDENS DE SERVIÇO (tbl_os)
-- Tipos: corte, nova_ligacao | Status: aberta, em_andamento, concluida, cancelada
-- ---------------------------------------------------------------------
INSERT INTO `tbl_os` (`id_oss`, `cliente_oss`, `eletricista_oss`, `tipo_oss`, `status_oss`, `descricao_oss`, `unidade_consumidora_oss`, `endereco_oss`, `bairro_oss`, `cidade_oss`, `estado_oss`, `cep_oss`, `data_abertura_oss`, `data_fechamento_oss`) VALUES
-- OS 1: Nova Ligação em aberto atribuída ao João
(1, 1, 1, 'nova_ligacao', 'aberta', 'Nova ligação solicitada pela Alfa para unidade comercial.', 'UC-CE-100234', 'Rua das Flores, 100', 'Centro', 'Fortaleza', 'CE', '60010-000', NOW(), NULL),

-- OS 2: Corte por inadimplência em andamento com João
(2, 1, 1, 'corte', 'em_andamento', 'Corte solicitado pela Alfa em outra UC, com recolhimento de medidor.', 'UC-CE-200456', 'Rua dos Jardins, 245', 'Meireles', 'Fortaleza', 'CE', '60165-000', NOW(), NULL),

-- OS 3: Concluída por Lucas
(3, 3, 2, 'nova_ligacao', 'concluida', 'Ligação finalizada na UC indicada pela Beta Engenharia.', 'UC-CE-300789', 'Rua das Acácias, 80', 'Cocó', 'Fortaleza', 'CE', '60192-000', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY));

-- ---------------------------------------------------------------------
-- 6. HISTÓRICO DAS ORDENS DE SERVIÇO (tbl_os_historico)
-- Trilha de auditoria das transições de status
-- ---------------------------------------------------------------------
INSERT INTO `tbl_os_historico` (`id_osh`, `ordem_servico_osh`, `eletricista_osh`, `status_osh`, `observacao_osh`, `data_osh`) VALUES
(1, 1, 1, 'aberta', 'OS gerada pelo operador e atribuída ao eletricista João.', NOW()),
(2, 2, NULL, 'aberta', 'Ordem de corte cadastrada pelo operador.', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(3, 2, 1, 'em_andamento', 'Eletricista João iniciou deslocamento para o local.', NOW()),
(4, 3, NULL, 'aberta', 'Abertura da OS de nova ligação.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 3, 2, 'em_andamento', 'Eletricista Lucas iniciou a instalação.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(6, 3, 2, 'concluida', 'Instalação finalizada com sucesso e medidor homologado.', DATE_SUB(NOW(), INTERVAL 1 DAY));

-- ---------------------------------------------------------------------
-- 7. MEDIDORES DA OS (tbl_os_medidor)
-- Tipos: instalado, retirado
-- ---------------------------------------------------------------------
INSERT INTO `tbl_os_medidor` (`id_osm`, `ordem_servico_osm`, `medidor_osm`, `tipo_osm`, `data_osm`) VALUES
-- Na OS 2 de corte, o medidor antigo é retirado
(1, 2, 6, 'retirado', NOW()),

-- Na OS 3 de nova ligação, um medidor foi instalado
(2, 3, 5, 'instalado', DATE_SUB(NOW(), INTERVAL 1 DAY));

-- ---------------------------------------------------------------------
-- 8. MOVIMENTAÇÕES DE ESTOQUE (tbl_estoque_mov)
-- Rastreabilidade de entradas, transferências e saídas
-- ---------------------------------------------------------------------
INSERT INTO `tbl_estoque_mov` (
    `id_emv`, `medidor_emv`, `ordem_servico_emv`, `eletricista_emv`,
    `tipo_emv`, `motivo_emv`, `origem_emv`, `destino_emv`,
    `quantidade_emv`, `observacao_emv`, `data_emv`
) VALUES
-- Carga inicial de entrada no galpão
(1, 1, NULL, NULL, 'entrada', 'compra', 'fornecedor', 'galpao', 1, 'Recebimento de lote de medidores do fabricante Landis+Gyr', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(2, 2, NULL, NULL, 'entrada', 'compra', 'fornecedor', 'galpao', 1, 'Recebimento de lote de medidores do fabricante Landis+Gyr', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(3, 3, NULL, NULL, 'entrada', 'compra', 'fornecedor', 'galpao', 1, 'Recebimento de lote de medidores do fabricante Schneider', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(4, 4, NULL, NULL, 'entrada', 'compra', 'fornecedor', 'galpao', 1, 'Recebimento de lote de medidores do fabricante Schneider', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(5, 5, NULL, NULL, 'entrada', 'compra', 'fornecedor', 'galpao', 1, 'Recebimento de lote de medidores do fabricante Kron', DATE_SUB(NOW(), INTERVAL 5 DAY)),

-- Transferência de galpão para as viaturas dos eletricistas
(6, 3, NULL, 1, 'transferencia', 'ajuste', 'galpao', 'eletricista', 1, 'Carregamento de viatura do eletricista João', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(7, 4, NULL, 1, 'transferencia', 'ajuste', 'galpao', 'eletricista', 1, 'Carregamento de viatura do eletricista João', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(8, 5, NULL, 2, 'transferencia', 'ajuste', 'galpao', 'eletricista', 1, 'Carregamento de viatura do eletricista Lucas', DATE_SUB(NOW(), INTERVAL 3 DAY)),

-- Baixa por instalação no cliente através da OS 3
(9, 5, 3, 2, 'baixa_saida', 'consumo', 'eletricista', 'cliente', 1, 'Instalação na UC indicada pela Beta Engenharia na OS #3', DATE_SUB(NOW(), INTERVAL 1 DAY));

-- ---------------------------------------------------------------------
-- 9. ANEXOS / FOTOS DA OS (tbl_anexo)
-- Tipos: foto, documento
-- ---------------------------------------------------------------------
INSERT INTO `tbl_anexo` (`id_anx`, `ordem_servico_anx`, `arquivo_anx`, `tipo_anx`, `descricao_anx`, `data_anx`) VALUES
(1, 2, 'uploads/os/2/foto_corte_medidor.jpg', 'foto', 'Registro fotográfico do medidor recolhido no corte', NOW()),
(2, 3, 'uploads/os/3/foto_padrao_instalado.jpg', 'foto', 'Foto do novo padrão instalado e energizado', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 3, 'uploads/os/3/termo_aceite.pdf', 'documento', 'Termo de vistoria e entrega assinado pelo cliente', DATE_SUB(NOW(), INTERVAL 1 DAY));

SET FOREIGN_KEY_CHECKS = 1;
