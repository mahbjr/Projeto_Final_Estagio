<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * Greenfield operational schema. Never translates or discards existing OS data.
 * MySQL DDL is not transactionally reversible: backup and suspend writes first.
 */
final class CreateOperationalFlow extends Migration
{
    public const TABLES = [
        'tbl_consumivel', 'tbl_consumivel_saldo', 'tbl_consumivel_reserva', 'tbl_consumivel_mov',
        'tbl_medidor_reserva', 'tbl_instalacao_atual', 'tbl_checklist', 'tbl_checklist_item',
        'tbl_checklist_avaliacao', 'tbl_checklist_resposta', 'tbl_medidor_ocorrencia',
    ];

    public function up(): void
    {
        $this->db->resetDataCache();
        $path = dirname(ROOTPATH) . '/database/schema.sql';
        $schema = file_get_contents($path);
        if ($schema === false) {
            throw new RuntimeException('Esquema operacional não encontrado.');
        }
        // All source definitions are checked before issuing any DDL.
        $definitions = [];
        foreach (self::TABLES as $table) {
            if (!preg_match('/CREATE TABLE `' . preg_quote($table, '/') . '` \(.*?\) ENGINE=.*?;/s', $schema, $match)) {
                throw new RuntimeException('Definição operacional ausente: ' . $table);
            }
            $definitions[$table] = $match[0];
        }
        if ($this->db->fieldExists('prioridade_oss', 'tbl_os')) {
            $this->assertCurrentSchema($schema);
            return;
        }
        // Abort before changing anything if this is not a fresh operational database.
        foreach (['tbl_os', 'tbl_os_historico', 'tbl_os_medidor', 'tbl_anexo'] as $table) {
            if (!$this->db->tableExists($table) || $this->db->table($table)->countAllResults() > 0) {
                throw new RuntimeException('Etapa greenfield exige tabelas operacionais vazias. Preserve o banco existente e inicialize outro banco vazio.');
            }
        }
        if ($this->db->table('tbl_estoque_mov')->where('ordem_servico_emv !=', null)->countAllResults() > 0) {
            throw new RuntimeException('Movimentos vinculados a OS existentes não podem ser convertidos nesta etapa greenfield.');
        }
        foreach (self::TABLES as $table) {
            if ($this->db->tableExists($table)) {
                throw new RuntimeException('Esquema operacional parcial. Restaure o backup antes de tentar novamente.');
            }
        }
        foreach (['usuario_osh' => 'tbl_os_historico', 'usuario_emv' => 'tbl_estoque_mov', 'usuario_anx' => 'tbl_anexo'] as $field => $table) {
            if ($this->db->fieldExists($field, $table)) {
                throw new RuntimeException('Esquema operacional parcial. Restaure o backup antes de tentar novamente.');
            }
        }
        $this->db->query("ALTER TABLE tbl_os
            MODIFY status_oss ENUM('aberta','atribuida','em_atendimento','encerrada','cancelada') NOT NULL DEFAULT 'aberta',
            ADD prioridade_oss ENUM('baixa','normal','alta','urgente') NOT NULL DEFAULT 'normal',
            ADD agendamento_oss DATETIME DEFAULT NULL,
            ADD resultado_oss ENUM('executado','parcial','nao_executado') DEFAULT NULL,
            ADD observacoes_finais_oss TEXT DEFAULT NULL,
            ADD observacoes_administrativas_oss TEXT DEFAULT NULL,
            ADD corte_confirmado_oss TINYINT(1) NOT NULL DEFAULT 0,
            ADD leitura_final_oss DECIMAL(15,3) DEFAULT NULL,
            ADD inicio_atendimento_oss DATETIME DEFAULT NULL");
        $this->db->query("ALTER TABLE tbl_medidor MODIFY status_med ENUM('disponivel','reservado','em_transito','instalado','defeito','perdido','baixado') NOT NULL DEFAULT 'disponivel'");
        $this->db->query("ALTER TABLE tbl_os_historico ADD usuario_osh INT UNSIGNED DEFAULT NULL,
            ADD evento_osh VARCHAR(50) NOT NULL DEFAULT 'status', ADD status_anterior_osh VARCHAR(50) DEFAULT NULL,
            ADD dados_osh JSON DEFAULT NULL,
            ADD CONSTRAINT fk_osh_usuario FOREIGN KEY (usuario_osh) REFERENCES tbl_usuario(id_usu) ON DELETE RESTRICT ON UPDATE CASCADE");
        $this->db->query('ALTER TABLE tbl_estoque_mov ADD usuario_emv INT UNSIGNED DEFAULT NULL,
            ADD CONSTRAINT fk_emv_usuario FOREIGN KEY (usuario_emv) REFERENCES tbl_usuario(id_usu) ON DELETE RESTRICT ON UPDATE CASCADE');
        $this->db->query('ALTER TABLE tbl_anexo ADD usuario_anx INT UNSIGNED DEFAULT NULL, ADD mime_anx VARCHAR(50) DEFAULT NULL,
            ADD tamanho_anx INT UNSIGNED DEFAULT NULL,
            ADD CONSTRAINT fk_anx_usuario FOREIGN KEY (usuario_anx) REFERENCES tbl_usuario(id_usu) ON DELETE RESTRICT ON UPDATE CASCADE');
        foreach ($definitions as $sql) {
            $this->db->query($sql);
        }
        $this->db->resetDataCache();
    }

    private function assertCurrentSchema(string $schema): void
    {
        // A schema.sql initialized database is already current. A partial DDL run isn't.
        $tables = array_merge(self::TABLES, ['tbl_os', 'tbl_medidor', 'tbl_os_historico', 'tbl_estoque_mov', 'tbl_anexo']);
        foreach ($tables as $table) {
            if (!$this->db->tableExists($table)) {
                throw new RuntimeException('Esquema operacional parcial. Restaure o backup antes de tentar novamente.');
            }
            preg_match('/CREATE TABLE `' . preg_quote($table, '/') . '` \((.*?)\) ENGINE=/s', $schema, $match);
            preg_match_all('/^\s*`?(\w+)`?\s+(?:INT|TINYINT|VARCHAR|CHAR|TEXT|DATETIME|ENUM|DECIMAL|JSON)\b/m', $match[1], $fields);
            foreach ($fields[1] as $field) {
                if (!$this->db->fieldExists($field, $table)) {
                    throw new RuntimeException('Esquema operacional parcial. Restaure o backup antes de tentar novamente.');
                }
            }
        }
        foreach (['tbl_os' => ['status_oss', "'encerrada'"], 'tbl_medidor' => ['status_med', "'reservado'"]] as $table => [$column, $expected]) {
            $type = $this->db->query('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column])->getRowArray();
            if (!str_contains($type['COLUMN_TYPE'] ?? '', $expected)) {
                throw new RuntimeException('Esquema operacional parcial. Restaure o backup antes de tentar novamente.');
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback exige restauração do backup: remover estruturas operacionais descartaria evidências e dados.');
    }
}
