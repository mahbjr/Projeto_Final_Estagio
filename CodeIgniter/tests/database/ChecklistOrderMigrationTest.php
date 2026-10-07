<?php

namespace Tests\Database;

use App\Database\Migrations\NormalizeChecklistOrder;
use Config\Database;
use Tests\Support\AppTestCase;

require_once APPPATH . 'Database/Migrations/2026-10-07-000001_NormalizeChecklistOrder.php';

final class ChecklistOrderMigrationTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach ([1,2,3,4] as $id) {
            $this->db->table('tbl_checklist')->insert(['id_chk'=>$id,'nome_chk'=>'Modelo '.$id,'tipo_os_chk'=>'corte','etapa_chk'=>'inicio','ativo_chk'=>0,'usuario_chk'=>1,'data_exclusao_chk'=>$id===3?'2026-01-01 00:00:00':null]);
        }
        foreach ([1=>0,2=>0,3=>8,4=>3,5=>0,6=>9,7=>9] as $id=>$order) {
            $this->db->table('tbl_checklist_item')->insert(['id_chi'=>$id,'checklist_chi'=>$id===6?2:($id===7?3:1),'pergunta_chi'=>'Pergunta '.$id,'nivel_chi'=>'informativo','ordem_chi'=>$order,'data_atualizacao_chi'=>$id===1?null:'2026-01-02 00:00:00','data_exclusao_chi'=>$id===5?'2026-01-03 00:00:00':null]);
        }
        $this->db->table('tbl_checklist_avaliacao')->insert(['id_cav'=>1,'ordem_servico_cav'=>2,'checklist_cav'=>1,'usuario_cav'=>3,'etapa_cav'=>'inicio']);
        $this->db->table('tbl_checklist_resposta')->insert(['avaliacao_cre'=>1,'item_cre'=>2,'pergunta_cre'=>'Histórico','nivel_cre'=>'informativo','resposta_esperada_cre'=>1,'resposta_cre'=>1]);
    }

    private function migration(): NormalizeChecklistOrder { return new NormalizeChecklistOrder(Database::forge('tests')); }
    private function items(): array { return $this->db->table('tbl_checklist_item')->orderBy('id_chi')->get()->getResultArray(); }

    public function testDataMigrationNormalizesOnlyActiveQuestionsAndPreservesHistoryAndSchema(): void
    {
        $before=$this->items();
        $answers=$this->db->table('tbl_checklist_resposta')->get()->getResultArray();
        $evaluations=$this->db->table('tbl_checklist_avaliacao')->get()->getResultArray();
        $schema=$this->db->query('SHOW CREATE TABLE tbl_checklist_item')->getRowArray();
        $this->migration()->up();
        $after=$this->items();
        $this->assertSame([1,2,4,3,0,1,9],array_map('intval',array_column($after,'ordem_chi')));
        foreach ($before as $i=>$row) {
            unset($row['ordem_chi']); $item=$after[$i]; unset($item['ordem_chi']);
            $this->assertSame($row,$item);
        }
        $this->assertSame($answers,$this->db->table('tbl_checklist_resposta')->get()->getResultArray());
        $this->assertSame($evaluations,$this->db->table('tbl_checklist_avaliacao')->get()->getResultArray());
        $this->assertSame($schema,$this->db->query('SHOW CREATE TABLE tbl_checklist_item')->getRowArray());
        $this->migration()->up();
        $this->assertSame($after,$this->items());
    }

    public function testMigrationFailureRollsBackAllDataChanges(): void
    {
        $before=$this->items();
        $this->db->query("ALTER TABLE tbl_checklist_item ADD CONSTRAINT fail_order_migration CHECK (pergunta_chi <> 'Pergunta 3' OR ordem_chi = 8)");
        try {
            try { $this->migration()->up(); $this->fail('Falha de persistência não detectada.'); }
            catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) { $this->assertNotEmpty($e->getMessage()); }
            $this->assertSame($before,$this->items());
        } finally { $this->db->query('ALTER TABLE tbl_checklist_item DROP CHECK fail_order_migration'); }
    }

    public function testRollbackRequiresBackupWithoutChangingRows(): void
    {
        $before=$this->items();
        try { $this->migration()->down(); $this->fail('Ordem anterior inventada.'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('backup',$e->getMessage()); }
        $this->assertSame($before,$this->items());
    }
}
