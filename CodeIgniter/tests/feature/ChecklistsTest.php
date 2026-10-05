<?php

namespace Tests\Feature;

use App\Services\ChecklistService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AppTestCase;

final class ChecklistsTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('tbl_checklist')->insert(['id_chk'=>1,'nome_chk'=>'Exemplo de demonstração','tipo_os_chk'=>'corte','etapa_chk'=>'inicio','usuario_chk'=>1,'ativo_chk'=>0]);
        $this->db->table('tbl_checklist_item')->insert(['id_chi'=>1,'checklist_chi'=>1,'pergunta_chi'=>'Condição demonstrativa confirmada?','nivel_chi'=>'bloqueante']);
    }
    private function modelInput(array $overrides=[]): array { return $overrides+['nome_chk'=>'Checklist exemplo','tipo_os_chk'=>'nova_ligacao','etapa_chk'=>'fechamento','ativo_chk'=>'0']; }
    private function itemInput(array $overrides=[]): array { return $overrides+['pergunta_chi'=>'Pergunta de demonstração?','resposta_esperada_chi'=>'1','obrigatorio_chi'=>'1','nivel_chi'=>'bloqueante','ordem_chi'=>'0']; }

    public static function routes(): iterable
    {
        foreach ([null,1,2,3] as $actor) {
            foreach ([['GET','checklists'],['GET','checklists/novo'],['GET','checklists/1'],['GET','checklists/1/editar'],['POST','checklists'],['POST','checklists/1/atualizar'],['POST','checklists/1/itens'],['GET','checklists/1/itens/1/editar'],['POST','checklists/1/itens/1/atualizar'],['POST','checklists/1/itens/1/excluir']] as [$verb,$path]) {
                yield ($actor??'visitante')." $verb $path"=>[$actor,$verb,$path];
            }
        }
    }
    #[DataProvider('routes')]
    public function testRoles(?int $actor,string $verb,string $path): void
    {
        $before=$this->db->table('tbl_checklist')->get()->getResultArray();
        $items=$this->db->table('tbl_checklist_item')->get()->getResultArray();
        $response=$this->requestAs($actor,$verb,$path);
        if ($actor===null) { $response->assertRedirectTo(site_url('login')); }
        elseif ($actor!==1) { $response->assertStatus(403); }
        else { $this->assertContains($response->response()->getStatusCode(),[200,303,422]); }
        if ($actor!==1) {
            $this->assertSame($before,$this->db->table('tbl_checklist')->get()->getResultArray());
            $this->assertSame($items,$this->db->table('tbl_checklist_item')->get()->getResultArray());
        }
    }
    public function testCreateQuestionsActivationEditionAndLogicalRemoval(): void
    {
        $this->requestAs(1,'POST','checklists',$this->modelInput(['ativo_chk'=>'1']))->assertStatus(422);
        $this->requestAs(1,'POST','checklists',$this->modelInput(['usuario_chk'=>'4','id_chk'=>'1']))->assertStatus(303);
        $row=$this->db->table('tbl_checklist')->where('nome_chk','Checklist exemplo')->get()->getRowArray();$id=(int)$row['id_chk'];
        $this->assertSame('1',(string)$row['usuario_chk']);
        $this->requestAs(1,'POST',"checklists/$id/itens",$this->itemInput())->assertStatus(303);
        $item=$this->db->table('tbl_checklist_item')->where('checklist_chi',$id)->get()->getRowArray();$itemId=(int)$item['id_chi'];
        $this->requestAs(1,'POST',"checklists/$id/atualizar",$this->modelInput(['ativo_chk'=>'1']))->assertStatus(303);
        $this->requestAs(1,'POST',"checklists/$id/itens/$itemId/atualizar",$this->itemInput(['nivel_chi'=>'informativo','obrigatorio_chi'=>'0']))->assertStatus(303);
        $this->requestAs(1,'POST',"checklists/$id/itens/$itemId/excluir")->assertStatus(422);
        $this->requestAs(1,'POST',"checklists/$id/atualizar",$this->modelInput())->assertStatus(303);
        $this->requestAs(1,'POST',"checklists/$id/itens/$itemId/excluir")->assertStatus(303);
        $this->assertNotNull($this->db->table('tbl_checklist_item')->where('id_chi',$itemId)->get()->getRow()->data_exclusao_chi);
        $this->requestAs(1,'GET',"checklists/$id/itens/$itemId/editar")->assertStatus(404);
        $this->requestAs(1,'POST',"checklists/$id/atualizar",$this->modelInput(['ativo_chk'=>'1']))->assertStatus(422);
    }
    public function testNestedIdsInvalidLevelsAndCsrfCannotChangeData(): void
    {
        $this->db->table('tbl_checklist')->insert(['id_chk'=>2,'nome_chk'=>'Outro exemplo','tipo_os_chk'=>'corte','etapa_chk'=>'inicio','usuario_chk'=>1]);
        $this->requestAs(1,'POST','checklists/2/itens/1/atualizar',$this->itemInput())->assertStatus(404);
        $this->requestAs(1,'POST','checklists/2/itens/1/excluir')->assertStatus(404);
        foreach ([['nivel_chi'=>'alerta'],['pergunta_chi'=>' '],['ordem_chi'=>'-1'],['ordem_chi'=>'10000'],['resposta_esperada_chi'=>'2'],['obrigatorio_chi'=>'true']] as $overrides) {
            $this->requestAs(1,'POST','checklists/1/itens',$this->itemInput($overrides))->assertStatus(422);
        }
        foreach (['checklists','checklists/1/atualizar','checklists/1/itens','checklists/1/itens/1/atualizar','checklists/1/itens/1/excluir'] as $path) {
            $this->requestAs(1,'POST',$path,$this->itemInput(),false)->assertStatus(403);
            $this->requestAs(1,'POST',$path,$this->itemInput(),true,time()-7201)->assertRedirectTo(site_url('login'));
        }
        foreach (['checklists/1/atualizar','checklists/1/itens','checklists/1/itens/1/atualizar','checklists/1/itens/1/excluir'] as $path) { $this->requestAs(1,'GET',$path)->assertStatus(404); }
        $this->assertSame(1,$this->db->table('tbl_checklist_item')->countAllResults());
    }
    public function testAnswersArePreservedWhenQuestionsChangeOrAreRemoved(): void
    {
        $this->db->table('tbl_checklist_avaliacao')->insert(['id_cav'=>1,'ordem_servico_cav'=>2,'checklist_cav'=>1,'usuario_cav'=>3,'etapa_cav'=>'inicio']);
        $this->db->table('tbl_checklist_resposta')->insert(['avaliacao_cre'=>1,'item_cre'=>1,'pergunta_cre'=>'Pergunta anterior','nivel_cre'=>'bloqueante','resposta_esperada_cre'=>1,'resposta_cre'=>1]);
        $before=$this->db->table('tbl_checklist_resposta')->get()->getResultArray();
        $this->requestAs(1,'POST','checklists/1/atualizar',$this->modelInput())->assertStatus(422);
        $this->requestAs(1,'POST','checklists/1/itens/1/atualizar',$this->itemInput())->assertStatus(303);
        $this->requestAs(1,'POST','checklists/1/itens/1/excluir')->assertStatus(303);
        $this->assertSame($before,$this->db->table('tbl_checklist_resposta')->get()->getResultArray());
    }
    public function testFailureAndActorAreValidatedInService(): void
    {
        $this->db->query("ALTER TABLE tbl_checklist_item ADD CONSTRAINT fail_question CHECK (pergunta_chi <> 'Pergunta de demonstração?')");
        try {
            $this->requestAs(1,'POST','checklists/1/itens',$this->itemInput())->assertStatus(422);
            $this->assertSame(1,$this->db->table('tbl_checklist_item')->countAllResults());
        } finally { $this->db->query('ALTER TABLE tbl_checklist_item DROP CHECK fail_question'); }
        $this->expectException(\App\Exceptions\FormException::class);
        (new ChecklistService($this->db))->save($this->modelInput(),2);
    }
}
