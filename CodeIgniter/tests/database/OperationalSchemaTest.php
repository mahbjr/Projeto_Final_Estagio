<?php

namespace Tests\Database;

use App\Database\Migrations\CreateOperationalFlow;
use App\Libraries\DemoDatabase;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Database;
use Tests\Support\AppTestCase;

require_once APPPATH . 'Database/Migrations/2026-10-04-000001_CreateOperationalFlow.php';

final class OperationalSchemaTest extends AppTestCase
{
    private function previousSchema(): void
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach (array_reverse(CreateOperationalFlow::TABLES) as $table) {
                $this->db->query('DROP TABLE IF EXISTS ' . $table);
            }
        } finally {
            $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
        }
        DemoDatabase::execute($this->db, TESTPATH . '_support/fixtures/cadastros_schema.sql');
        DemoDatabase::execute($this->db, TESTPATH . '_support/fixtures/cadastros_seeds.sql');
    }

    private function migration(): CreateOperationalFlow
    {
        return new CreateOperationalFlow(Database::forge('tests'));
    }

    private function rejectsSql(string $sql, array $params = []): void
    {
        try {
            $this->db->query($sql, $params);
            $this->fail('A integridade física deve rejeitar esta gravação.');
        } catch (DatabaseException) {
            $this->addToAssertionCount(1);
        }
    }

    public function testGreenfieldMigrationPreservesAccountsMetersAndUnlinkedMovements(): void
    {
        $this->previousSchema();
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach (['tbl_anexo', 'tbl_os_medidor', 'tbl_os_historico', 'tbl_os'] as $table) {
                $this->db->table($table)->emptyTable();
            }
            $this->db->table('tbl_estoque_mov')->where('ordem_servico_emv !=', null)->delete();
        } finally {
            $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
        }
        $snapshots = [];
        foreach (['tbl_usuario', 'tbl_eletricista', 'tbl_cliente', 'tbl_medidor', 'tbl_estoque_mov'] as $table) {
            $snapshots[$table] = $this->db->table($table)->get()->getResultArray();
        }
        $this->migration()->up();
        foreach ($snapshots as $table => $rows) {
            $after = $this->db->table($table)->get()->getResultArray();
            foreach ($rows as $index => $row) {
                $this->assertSame($row, array_intersect_key($after[$index], $row));
            }
        }
        foreach (CreateOperationalFlow::TABLES as $table) {
            $this->assertTrue($this->db->tableExists($table));
        }
        $this->assertTrue($this->db->fieldExists('usuario_emv', 'tbl_estoque_mov'));
        $this->assertTrue($this->db->fieldExists('resultado_oss', 'tbl_os'));
        $this->migration()->up();
    }

    public function testPopulatedPreviousSchemaIsRejectedBeforeAnyDdl(): void
    {
        $this->previousSchema();
        $orders = $this->db->table('tbl_os')->get()->getResultArray();
        try {
            $this->migration()->up();
            $this->fail('Dados antigos devem ser preservados sem conversão implícita.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('greenfield', $e->getMessage());
        }
        $this->assertSame($orders, $this->db->table('tbl_os')->get()->getResultArray());
        $this->assertFalse($this->db->fieldExists('prioridade_oss', 'tbl_os'));
        $this->assertFalse($this->db->tableExists('tbl_medidor_reserva'));
    }

    public function testCurrentInitializationAndPartialSchemaDetection(): void
    {
        $this->migration()->up();
        $this->assertSame('executado', $this->db->table('tbl_os')->where('id_oss', 3)->get()->getRow()->resultado_oss);
        $this->db->query('ALTER TABLE tbl_anexo DROP COLUMN mime_anx');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('parcial');
        $this->migration()->up();
    }

    public function testExclusiveReservationAndPreservedReservationHistory(): void
    {
        $sql = "INSERT INTO tbl_medidor_reserva (medidor_rme,ordem_servico_rme,eletricista_rme,usuario_rme) VALUES (1,1,1,1)";
        $this->db->query($sql);
        $this->rejectsSql($sql);
        $this->db->query("UPDATE tbl_medidor_reserva SET status_rme = 'liberada' WHERE medidor_rme = 1");
        $this->db->query($sql);
        $this->assertSame(2, $this->db->table('tbl_medidor_reserva')->where('medidor_rme', 1)->countAllResults());
        $this->rejectsSql("INSERT INTO tbl_instalacao_atual (medidor_ins,ordem_servico_ins,unidade_consumidora_ins,usuario_ins) VALUES (5,1,'OUTRA-UC',1)");
    }

    public function testClosingChecklistCannotBeReleased(): void
    {
        $this->db->query("INSERT INTO tbl_checklist (nome_chk,tipo_os_chk,etapa_chk,usuario_chk) VALUES ('Exemplo demonstrativo','corte','fechamento',1)");
        $id = $this->db->insertID();
        $this->rejectsSql("INSERT INTO tbl_checklist_avaliacao (ordem_servico_cav,checklist_cav,usuario_cav,etapa_cav,bloqueada_cav,liberado_por_cav,justificativa_liberacao_cav,data_liberacao_cav) VALUES (2,?,3,'fechamento',1,1,'Liberação',NOW())", [$id]);
        $this->db->query("INSERT INTO tbl_checklist_avaliacao (ordem_servico_cav,checklist_cav,usuario_cav,etapa_cav,bloqueada_cav) VALUES (2,?,3,'fechamento',1)", [$id]);
        $this->assertSame(1, $this->db->table('tbl_checklist_avaliacao')->countAllResults());
    }

    public function testAdministrativeMovementsHaveActorFkAndRollbackRemainsAtomic(): void
    {
        $this->clearPendingWork();
        $id = (new \App\Services\MedidorService($this->db))->save(['numero_med' => 'ETAPA-1-AUDIT'], 1);
        $this->assertSame('1', (string) $this->db->table('tbl_estoque_mov')->where('medidor_emv', $id)->get()->getRow()->usuario_emv);
        $before = $this->db->table('tbl_medidor')->where('id_med',$id)->get()->getRowArray();
        $this->db->transBegin();
        $this->db->table('tbl_medidor')->where('id_med',$id)->update(['modelo_med'=>'Temporário']);
        $this->db->transRollback();
        $this->assertSame($before,$this->db->table('tbl_medidor')->where('id_med',$id)->get()->getRowArray());
    }

    public function testDownRequiresBackupInsteadOfDeletingEvidence(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('backup');
        $this->migration()->down();
    }
}
