<?php

namespace Tests\Feature;

use App\Services\ChecklistService;
use App\Services\ChecklistInicioService;
use Tests\Support\AppTestCase;

final class ChecklistComentariosTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach ([[1,'nova_ligacao','inicio'],[2,'corte','fechamento']] as [$id,$type,$stage]) {
            $this->db->table('tbl_checklist')->insert(['id_chk'=>$id,'nome_chk'=>'Comentários '.$stage,'tipo_os_chk'=>$type,'etapa_chk'=>$stage,'ativo_chk'=>1,'usuario_chk'=>1]);
            $this->db->table('tbl_checklist_item')->insert(['id_chi'=>$id,'checklist_chi'=>$id,'pergunta_chi'=>'Condição verificada?','nivel_chi'=>'bloqueante','ordem_chi'=>1]);
        }
        $this->db->table('tbl_os')->where('id_oss',1)->update(['status_oss'=>'atribuida']);
        $this->db->table('tbl_os')->where('id_oss',2)->update(['status_oss'=>'em_atendimento']);
    }

    public function testQuestionFormHasNoRequiredSettingAndServerIgnoresForgedValues(): void
    {
        $this->requestAs(1,'GET','checklists/1')->assertDontSee('name="obrigatorio_chi"');
        $this->requestAs(1,'GET','checklists/1/itens/1/editar')->assertDontSee('name="obrigatorio_chi"');
        $input=['pergunta_chi'=>'Nova pergunta?','resposta_esperada_chi'=>'1','nivel_chi'=>'informativo','obrigatorio_chi'=>'0'];
        $this->requestAs(1,'POST','checklists/1/itens',$input)->assertStatus(303);
        $row=$this->db->table('tbl_checklist_item')->where('pergunta_chi','Nova pergunta?')->get()->getRowArray();
        $this->assertSame(1,(int)$row['obrigatorio_chi']);
        $this->db->table('tbl_checklist_item')->where('id_chi',1)->update(['obrigatorio_chi'=>0]);
        (new ChecklistService($this->db))->saveItem(1,$input+['ignorado'=>'1'],1,1);
        $this->assertSame(0,(int)$this->db->table('tbl_checklist_item')->where('id_chi',1)->get()->getRow()->obrigatorio_chi);
    }

    public function testCommentsPersistAtBothStagesWithLimitAndHistoryPreserved(): void
    {
        foreach ([[1,1,'inicio'],[2,2,'fechamento']] as [$order,$item,$stage]) {
            $path="os/$order/checklists/$item/responder-$stage";
            $note=str_repeat('á',1000);
            $this->requestAs(3,'POST',$path,['respostas'=>[$item=>'1'],'observacoes'=>[$item=>$note]])->assertStatus(303);
            $before=$this->db->table('tbl_checklist_resposta')->where('item_cre',$item)->get()->getRowArray();
            $this->assertSame($note,$before['observacao_cre']);
            foreach ([str_repeat('á',1001),['texto']] as $invalid) {
                $response=$this->requestAs(3,'POST',$path,['respostas'=>[$item=>'1'],'observacoes'=>[$item=>$invalid]]);
                $response->assertStatus(422);
                $this->assertStringContainsString('observacao-error',$response->getBody());
                $this->assertSame(1,$this->db->table('tbl_checklist_resposta')->where('item_cre',$item)->countAllResults());
            }
            $this->requestAs(3,'POST',$path,['respostas'=>[$item=>'1']])->assertStatus(303);
            $this->assertSame($before,$this->db->table('tbl_checklist_resposta')->where('id_cre',$before['id_cre'])->get()->getRowArray());
            $last=$this->db->table('tbl_checklist_resposta')->where('item_cre',$item)->orderBy('id_cre','DESC')->get()->getRowArray();
            $this->assertSame('',$last['observacao_cre']);
        }
    }

    public function testOptionalControlsAndEscapedMultilineCommentAreVisibleInEvidence(): void
    {
        $body=$this->requestAs(3,'GET','os/1')->getBody();
        $this->assertStringContainsString('data-comment-toggle',$body);
        $this->assertStringContainsString('maxlength="1000"',$body);
        $this->assertStringContainsString('data-comment-panel',$body);
        $note="<script>alert(1)</script>\nDetalhes do atendimento.";
        $this->requestAs(3,'POST','os/1/checklists/1/responder-inicio',['respostas'=>[1=>'1'],'observacoes'=>[1=>$note]])->assertStatus(303);
        $body=$this->requestAs(3,'GET','os/1')->getBody();
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;',$body);
        $this->assertStringNotContainsString('<script>alert(1)</script>',$body);
        $this->assertStringContainsString('Detalhes do atendimento.',$body);
    }

    public function testServiceRejectsOversizedCommentWithoutPartialWrite(): void
    {
        try {
            (new ChecklistInicioService($this->db))->answer(1,1,[1=>'1'],[1=>str_repeat('x',1001)],3);
            $this->fail('Comentário excedente aceito.');
        } catch (\App\Exceptions\FormException $e) { $this->assertArrayHasKey('observacao_1',$e->errors); }
        $this->assertSame(0,$this->db->table('tbl_checklist_avaliacao')->countAllResults());
        $this->assertSame(0,$this->db->table('tbl_checklist_resposta')->countAllResults());
    }
}
