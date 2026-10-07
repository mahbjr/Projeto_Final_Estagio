<?php

namespace Tests\Feature;

use App\Exceptions\FormException;
use App\Services\ChecklistInicioService;
use Tests\Support\AppTestCase;

final class ChecklistEntregaTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('tbl_os')->where('id_oss',1)->update(['tipo_oss'=>'nova_ligacao']);
        $this->db->table('tbl_checklist')->insertBatch([
            ['id_chk'=>1,'nome_chk'=>'Início teste','tipo_os_chk'=>'nova_ligacao','etapa_chk'=>'inicio','ativo_chk'=>1,'usuario_chk'=>1],
            ['id_chk'=>2,'nome_chk'=>'Fim teste','tipo_os_chk'=>'nova_ligacao','etapa_chk'=>'fechamento','ativo_chk'=>1,'usuario_chk'=>1],
            ['id_chk'=>3,'nome_chk'=>'Corte teste','tipo_os_chk'=>'corte','etapa_chk'=>'inicio','ativo_chk'=>1,'usuario_chk'=>1],
        ]);
        $this->db->table('tbl_checklist_item')->insertBatch([
            ['id_chi'=>1,'checklist_chi'=>1,'pergunta_chi'=>'Condição obrigatória?','nivel_chi'=>'bloqueante','obrigatorio_chi'=>1],
            ['id_chi'=>2,'checklist_chi'=>1,'pergunta_chi'=>'Informação opcional?','nivel_chi'=>'informativo','obrigatorio_chi'=>0],
            ['id_chi'=>3,'checklist_chi'=>2,'pergunta_chi'=>'Fechamento?','nivel_chi'=>'bloqueante','obrigatorio_chi'=>1],
            ['id_chi'=>4,'checklist_chi'=>3,'pergunta_chi'=>'Corte?','nivel_chi'=>'bloqueante','obrigatorio_chi'=>1],
        ]);
    }
    private function answer(string $value = '1'): array { return ['respostas'=>['1'=>$value,'2'=>'0'],'observacoes'=>['1'=>'Condição teste','2'=>'Informação']]; }
    private function latest(): array { return $this->db->table('tbl_checklist_avaliacao')->orderBy('id_cav','DESC')->get()->getRowArray(); }

    private function assertReady(bool $expected): void
    {
        $order = $this->db->table('tbl_os')->where('id_oss',1)->get()->getRowArray();
        try {
            ChecklistInicioService::assertApproved($this->db, $order);
            $this->assertTrue($expected, 'Checklist deveria bloquear o início.');
        } catch (FormException $e) {
            $this->assertFalse($expected, 'Checklist deveria permitir o início.');
        }
    }

    public function testRolesCsrfExpiredSessionsAndGetDoNotMutate(): void
    {
        $routes = ['os/1/checklists/1/responder-inicio'=>3,'os/1/avaliacoes/1/liberar-inicio'=>1];
        foreach ($routes as $path => $allowed) {
            foreach ([null,1,2,3] as $actor) {
                $response = $this->requestWithDeletionPassword($actor,'POST',$path);
                if ($actor === null) { $response->assertRedirectTo(site_url('login')); }
                else { $response->assertStatus($actor === $allowed ? 422 : 403); }
            }
            $this->requestWithDeletionPassword($allowed,'POST',$path,[],false)->assertStatus(403);
            $this->requestWithDeletionPassword($allowed,'POST',$path,[],true,time()-7201)->assertRedirectTo(site_url('login'));
            $this->requestWithDeletionPassword($allowed,'GET',$path)->assertStatus(404);
        }
        $this->assertSame(0,$this->db->table('tbl_checklist_avaliacao')->countAllResults());
    }

    public function testOwnOrderTemplateBoundariesAndValidation(): void
    {
        $this->requestAs(4,'POST','os/1/checklists/1/responder-inicio',$this->answer())->assertStatus(403);
        foreach ([2,3,999] as $template) { $this->requestAs(3,'POST',"os/1/checklists/$template/responder-inicio",$this->answer())->assertStatus(404); }
        $this->requestAs(3,'POST','os/999/checklists/1/responder-inicio',$this->answer())->assertStatus(404);
        foreach ([['respostas'=>['1'=>'']],['respostas'=>['1'=>'sim']],['respostas'=>['1'=>['1']]],['respostas'=>'1'],['respostas'=>['1'=>'1','999'=>'1']],['observacoes'=>['1'=>str_repeat('a',1001)]]] as $input) {
            $this->requestAs(3,'POST','os/1/checklists/1/responder-inicio',$input + $this->answer())->assertStatus(422);
        }
        $this->db->table('tbl_os')->where('id_oss',1)->update(['status_oss'=>'em_atendimento']);
        $this->requestAs(3,'POST','os/1/checklists/1/responder-inicio',$this->answer())->assertStatus(422);
        $this->assertSame(0,$this->db->table('tbl_checklist_avaliacao')->countAllResults());
    }

    public function testBlockedChecklistReleaseCorrectionAndHistoricEvidence(): void
    {
        $this->requestAs(3,'POST','os/1/checklists/1/responder-inicio',$this->answer('0'))->assertStatus(303);
        $id = (int)$this->latest()['id_cav'];
        $this->assertSame('1',(string)$this->latest()['bloqueada_cav']);
        $this->requestAs(1,'POST',"os/1/avaliacoes/$id/liberar-inicio",['justificativa'=>' '])->assertStatus(422);
        $this->requestAs(2,'POST',"os/1/avaliacoes/$id/liberar-inicio",['justificativa'=>'Liberação'])->assertStatus(403);
        $this->requestAs(1,'POST',"os/1/avaliacoes/$id/liberar-inicio",['justificativa'=>'Condição verificada pelo Gestor'])->assertStatus(303);
        $before = $this->latest();
        $this->requestAs(1,'POST',"os/1/avaliacoes/$id/liberar-inicio",['justificativa'=>'Repetição'])->assertStatus(422);
        $this->requestAs(3,'POST','os/1/checklists/1/responder-inicio',$this->answer('0'))->assertStatus(303);
        $this->requestAs(1,'POST',"os/1/avaliacoes/$id/liberar-inicio",['justificativa'=>'Antiga'])->assertStatus(422);
        $this->requestAs(3,'POST','os/1/checklists/1/responder-inicio',$this->answer())->assertStatus(303);
        $this->assertSame($before,$this->db->table('tbl_checklist_avaliacao')->where('id_cav',$id)->get()->getRowArray());
        $this->assertSame(6,$this->db->table('tbl_checklist_resposta')->countAllResults());
        $this->requestAs(3,'GET','os/1')->assertSee('Condição verificada pelo Gestor');
    }

    public function testReleaseAndClosingNeverAllowsOverride(): void
    {
        $this->requestAs(3,'POST','os/1/checklists/1/responder-inicio',$this->answer('0'))->assertStatus(303);
        $id = (int)$this->latest()['id_cav'];
        $this->requestAs(1,'POST',"os/1/avaliacoes/$id/liberar-inicio",['justificativa'=>'Condição verificada'])->assertStatus(303);
        $this->db->table('tbl_checklist_avaliacao')->insert(['ordem_servico_cav'=>1,'checklist_cav'=>2,'usuario_cav'=>3,'etapa_cav'=>'fechamento','bloqueada_cav'=>1]);
        $closing = (int)$this->latest()['id_cav'];
        $this->requestAs(1,'POST',"os/1/avaliacoes/$closing/liberar-inicio",['justificativa'=>'Não permitido'])->assertStatus(422);
        $this->requestAs(1,'POST',"os/2/avaliacoes/$id/liberar-inicio",['justificativa'=>'Outra OS'])->assertStatus(422);
        $this->assertNull($this->latest()['liberado_por_cav']);
    }

    public function testNewQuestionsAndAllActiveTemplatesRequireFreshAnswers(): void
    {
        $this->requestAs(3,'POST','os/1/checklists/1/responder-inicio',$this->answer())->assertStatus(303);
        $this->assertReady(true);
        $this->db->table('tbl_checklist_item')->where('id_chi',1)->update(['pergunta_chi'=>'Pergunta nova?']);
        $this->assertReady(false);
        $old = $this->db->table('tbl_checklist_resposta')->where('item_cre',1)->get()->getRowArray();
        $this->assertSame('Condição obrigatória?',$old['pergunta_cre']);
        $this->requestAs(3,'POST','os/1/checklists/1/responder-inicio',$this->answer())->assertStatus(303);
        $this->db->table('tbl_checklist')->insert(['id_chk'=>4,'nome_chk'=>'Outro início','tipo_os_chk'=>'nova_ligacao','etapa_chk'=>'inicio','ativo_chk'=>1,'usuario_chk'=>1]);
        $this->db->table('tbl_checklist_item')->insert(['id_chi'=>5,'checklist_chi'=>4,'pergunta_chi'=>'Segunda condição?','nivel_chi'=>'bloqueante']);
        $this->assertReady(false);
        $this->requestAs(3,'POST','os/1/checklists/4/responder-inicio',['respostas'=>['5'=>'1']])->assertStatus(303);
        $this->assertReady(true);
    }

    public function testEachRelatedWriteRollsBackWhenAuditFails(): void
    {
        $this->db->query("ALTER TABLE tbl_os_historico ADD CONSTRAINT fail_checklist_event CHECK (evento_osh NOT IN ('checklist_inicio','liberacao_inicio'))");
        try {
            $this->requestAs(3,'POST','os/1/checklists/1/responder-inicio',$this->answer('0'))->assertStatus(422);
            $this->assertSame(0,$this->db->table('tbl_checklist_resposta')->countAllResults());
            $this->assertSame(0,$this->db->table('tbl_checklist_avaliacao')->countAllResults());
        } finally { $this->db->query('ALTER TABLE tbl_os_historico DROP CHECK fail_checklist_event'); }
        $this->requestAs(3,'POST','os/1/checklists/1/responder-inicio',$this->answer('0'))->assertStatus(303);
        $id = (int)$this->latest()['id_cav'];
        $this->db->query("ALTER TABLE tbl_os_historico ADD CONSTRAINT fail_release CHECK (evento_osh <> 'liberacao_inicio')");
        try {
            $this->requestAs(1,'POST',"os/1/avaliacoes/$id/liberar-inicio",['justificativa'=>'Falha'])->assertStatus(422);
            $this->assertNull($this->latest()['liberado_por_cav']);
        } finally { $this->db->query('ALTER TABLE tbl_os_historico DROP CHECK fail_release'); }
        $this->requestAs(3,'POST','os/1/checklists/1/responder-inicio',$this->answer())->assertStatus(303);
    }

    public function testDirectServiceCannotBypassOwnershipOrManagerAccess(): void
    {
        foreach ([1,2,4] as $actor) {
            try { (new ChecklistInicioService($this->db))->answer(1,1,$this->answer()['respostas'],[], $actor); $this->fail('Ator inválido aceito.'); }
            catch (FormException $e) { $this->assertArrayHasKey('operacao',$e->errors); }
        }
    }

    public function testOptionalAnswersBlockingAndEscapedEvidence(): void
    {
        $this->requestAs(3,'POST','os/1/checklists/1/responder-inicio',['respostas'=>['1'=>'1'],'observacoes'=>['1'=>'<script>alert(1)</script>']])->assertStatus(303);
        $this->assertSame('0',(string)$this->latest()['bloqueada_cav']);
        $body = $this->requestAs(3,'GET','os/1')->response()->getBody();
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;',$body);
        $this->assertStringNotContainsString('<script>alert(1)</script>',$body);
        $this->db->table('tbl_checklist_item')->where('id_chi',2)->update(['nivel_chi'=>'bloqueante']);
        $this->requestAs(3,'POST','os/1/checklists/1/responder-inicio',['respostas'=>['1'=>'1']])->assertStatus(303);
        $this->assertSame('1',(string)$this->latest()['bloqueada_cav']);
    }


}
