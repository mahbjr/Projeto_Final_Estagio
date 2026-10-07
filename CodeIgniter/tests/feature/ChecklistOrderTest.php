<?php

namespace Tests\Feature;

use App\Exceptions\FormException;
use App\Services\ChecklistService;
use Tests\Support\AppTestCase;

final class ChecklistOrderTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('tbl_checklist')->insert(['id_chk'=>1,'nome_chk'=>'Ordem de teste','tipo_os_chk'=>'corte','etapa_chk'=>'inicio','ativo_chk'=>0,'usuario_chk'=>1]);
        foreach ([1,2,3] as $id) {
            $this->db->table('tbl_checklist_item')->insert(['id_chi'=>$id,'checklist_chi'=>1,'pergunta_chi'=>'Pergunta ' . $id,'nivel_chi'=>'informativo','ordem_chi'=>$id]);
        }
    }

    private function input(): array
    {
        return ['pergunta_chi'=>'Nova pergunta','nivel_chi'=>'informativo','obrigatorio_chi'=>'0','resposta_esperada_chi'=>'1'];
    }

    private function items(): array
    {
        return $this->db->table('tbl_checklist_item')->where('checklist_chi',1)->where('data_exclusao_chi',null)->orderBy('ordem_chi')->orderBy('id_chi')->get()->getResultArray();
    }

    private function sequence(array $ids): void
    {
        $rows = $this->items();
        $this->assertSame($ids, array_map('intval', array_column($rows, 'id_chi')));
        $this->assertSame(range(1,count($ids)), array_map('intval', array_column($rows, 'ordem_chi')));
    }

    public function testAppendEditDeleteAndPostedPositionsCannotBreakSequence(): void
    {
        $this->requestAs(1,'POST','checklists/1/itens',$this->input()+['ordem_chi'=>['0']])->assertStatus(303);
        $this->sequence([1,2,3,4]);
        $this->requestAs(1,'POST','checklists/1/itens/2/atualizar',$this->input()+['ordem_chi'=>'999999'])->assertStatus(303);
        $this->sequence([1,2,3,4]);
        $this->requestWithDeletionPassword(1,'POST','checklists/1/itens/2/excluir')->assertStatus(303);
        $this->sequence([1,3,4]);
        $deleted = $this->db->table('tbl_checklist_item')->where('id_chi',2)->get()->getRowArray();
        $this->assertNotNull($deleted['data_exclusao_chi']);
        $this->assertSame(2,(int)$deleted['ordem_chi']);
        $this->requestAs(1,'POST','checklists/1/itens',$this->input())->assertStatus(303);
        $this->sequence([1,3,4,5]);
        $this->requestAs(1,'GET','checklists/1')->assertDontSee('name="ordem_chi"');
        $this->requestAs(1,'GET','checklists/1/itens/1/editar')->assertDontSee('name="ordem_chi"');
    }

    public function testMoveSwapsOnlyImmediateNeighborsAndPreservesAnswers(): void
    {
        $this->db->table('tbl_checklist_avaliacao')->insert(['id_cav'=>1,'ordem_servico_cav'=>2,'checklist_cav'=>1,'usuario_cav'=>3,'etapa_cav'=>'inicio']);
        $this->db->table('tbl_checklist_resposta')->insert(['avaliacao_cre'=>1,'item_cre'=>2,'pergunta_cre'=>'Pergunta anterior','nivel_cre'=>'informativo','resposta_esperada_cre'=>1,'resposta_cre'=>1]);
        $before=$this->db->table('tbl_checklist_resposta')->get()->getResultArray();
        $this->requestAs(1,'POST','checklists/1/itens/2/mover',['direcao'=>'subir'])->assertStatus(303);
        $this->sequence([2,1,3]);
        $this->requestAs(1,'POST','checklists/1/itens/2/mover',['direcao'=>'descer'])->assertStatus(303);
        $this->sequence([1,2,3]);
        $this->assertSame($before,$this->db->table('tbl_checklist_resposta')->get()->getResultArray());
    }

    public function testBoundsMalformedDirectionsAndGetDoNotWrite(): void
    {
        $before=$this->items();
        foreach ([[1,'subir'],[3,'descer'],[2,'lado'],[2,['subir']],[2,null]] as [$id,$direction]) {
            $this->requestAs(1,'POST',"checklists/1/itens/$id/mover",['direcao'=>$direction])->assertStatus(422);
            $this->assertSame($before,$this->items());
        }
        $this->requestAs(1,'GET','checklists/1/itens/2/mover')->assertStatus(404);
        $this->assertSame($before,$this->items());
        $response=$this->requestAs(1,'GET','checklists/1');
        $response->assertSee('aria-label="Subir pergunta 1" disabled');
        $response->assertSee('aria-label="Descer pergunta 3" disabled');
    }

    public function testAuthorizationSessionCsrfAndInactiveAccountDoNotWrite(): void
    {
        $before=$this->items();
        foreach ([null,2,3] as $actor) {
            $response=$this->requestAs($actor,'POST','checklists/1/itens/2/mover',['direcao'=>'subir']);
            if ($actor===null) { $response->assertRedirectTo(site_url('login')); } else { $response->assertStatus(403); }
        }
        $this->requestAs(1,'POST','checklists/1/itens/2/mover',['direcao'=>'subir'],false)->assertStatus(403);
        $this->requestAs(1,'POST','checklists/1/itens/2/mover',['direcao'=>'subir'],true,time()-7201)->assertRedirectTo(site_url('login'));
        $this->db->table('tbl_usuario')->where('id_usu',1)->update(['ativo_usu'=>0]);
        $this->requestAs(1,'POST','checklists/1/itens/2/mover',['direcao'=>'subir'])->assertRedirectTo(site_url('login'));
        $this->assertSame($before,$this->items());
    }

    public function testForeignAndDeletedItemsAreNotFound(): void
    {
        $this->db->table('tbl_checklist')->insert(['id_chk'=>2,'nome_chk'=>'Outro','tipo_os_chk'=>'corte','etapa_chk'=>'inicio','usuario_chk'=>1]);
        $before=$this->items();
        $this->requestAs(1,'POST','checklists/2/itens/2/mover',['direcao'=>'subir'])->assertStatus(404);
        $this->requestAs(1,'POST','checklists/99/itens/2/mover',['direcao'=>'subir'])->assertStatus(404);
        $this->assertSame($before,$this->items());
        $this->requestWithDeletionPassword(1,'POST','checklists/1/itens/2/excluir')->assertStatus(303);
        $this->requestAs(1,'POST','checklists/1/itens/2/mover',['direcao'=>'subir'])->assertStatus(404);
        $this->sequence([1,3]);
    }

    public function testLegacySequenceRequiresExplicitMigrationWithoutSilentWrites(): void
    {
        $this->db->table('tbl_checklist_item')->where('checklist_chi',1)->update(['ordem_chi'=>0]);
        $before=$this->items();
        $this->requestAs(1,'POST','checklists/1/itens',$this->input())->assertStatus(422);
        $this->requestAs(1,'POST','checklists/1/itens/1/atualizar',$this->input())->assertStatus(422);
        $this->requestAs(1,'POST','checklists/1/itens/2/mover',['direcao'=>'subir'])->assertStatus(422);
        $this->requestWithDeletionPassword(1,'POST','checklists/1/itens/2/excluir')->assertStatus(422);
        $this->assertSame($before,$this->items());
    }

    public function testSecondSwapFailureRollsBackBothPositions(): void
    {
        $before=$this->items();
        $this->db->query("ALTER TABLE tbl_checklist_item ADD CONSTRAINT fail_swap CHECK (pergunta_chi <> 'Pergunta 2' OR ordem_chi >= 2)");
        try {
            $this->requestAs(1,'POST','checklists/1/itens/1/mover',['direcao'=>'descer'])->assertStatus(422);
            $this->assertSame($before,$this->items());
        } finally { $this->db->query('ALTER TABLE tbl_checklist_item DROP CHECK fail_swap'); }
    }

    public function testServiceRejectsStaleManagerWithoutMovingItems(): void
    {
        $before=$this->items();
        $this->db->table('tbl_usuario')->where('id_usu',1)->update(['papel_usu'=>'operador']);
        try { (new ChecklistService($this->db))->moveItem(1,2,'subir',1); $this->fail('Gestor desatualizado aceito.'); }
        catch (FormException $e) { $this->assertArrayHasKey('operacao',$e->errors); }
        $this->assertSame($before,$this->items());
    }
}
