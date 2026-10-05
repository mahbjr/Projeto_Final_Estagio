<?php

namespace Tests\Feature;

use App\Services\AtendimentoService;
use App\Services\ChecklistInicioService;
use App\Services\ConsumivelService;
use App\Services\MedidorOsService;
use Tests\Support\AppTestCase;

final class AtendimentoTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('tbl_os')->where('id_oss',1)->update(['tipo_oss'=>'corte']);
        $this->db->table('tbl_checklist')->insert(['id_chk'=>1,'nome_chk'=>'Início campo','tipo_os_chk'=>'corte','etapa_chk'=>'inicio','ativo_chk'=>1,'usuario_chk'=>1]);
        $this->db->table('tbl_checklist_item')->insert(['id_chi'=>1,'checklist_chi'=>1,'pergunta_chi'=>'Condição segura?','nivel_chi'=>'bloqueante','obrigatorio_chi'=>1]);
    }
    private function approve(string $value='1'): void { (new ChecklistInicioService($this->db))->answer(1,1,['1'=>$value],[],3); }
    private function order(): array { return $this->db->table('tbl_os')->where('id_oss',1)->get()->getRowArray(); }
    private function events(string $event): array { return $this->db->table('tbl_os_historico')->where('ordem_servico_osh',1)->where('evento_osh',$event)->get()->getResultArray(); }

    public function testRolesCsrfExpiredSessionAndGetDoNotWrite(): void
    {
        $before=$this->order();
        foreach (['os/1/iniciar-atendimento','os/1/observacoes-atendimento'] as $path) {
            foreach ([null,1,2,3] as $actor) {
                $response=$this->requestAs($actor,'POST',$path);
                if ($actor===null) { $response->assertRedirectTo(site_url('login')); }
                else { $response->assertStatus($actor===3 ? 422 : 403); }
            }
            $this->requestAs(3,'POST',$path,[],false)->assertStatus(403);
            $this->requestAs(3,'POST',$path,[],true,time()-7201)->assertRedirectTo(site_url('login'));
            $this->requestAs(3,'GET',$path)->assertStatus(404);
        }
        $this->assertSame($before,$this->order());
    }

    public function testCutStartsOnlyAfterApprovalOrCurrentReleaseAndCannotRepeat(): void
    {
        $this->requestAs(3,'POST','os/1/iniciar-atendimento')->assertStatus(422);
        $this->approve('0');
        $this->requestAs(3,'POST','os/1/iniciar-atendimento')->assertStatus(422);
        $id=(int)$this->db->table('tbl_checklist_avaliacao')->get()->getRow()->id_cav;
        (new ChecklistInicioService($this->db))->release(1,$id,'Gestor verificou a condição',1);
        $this->requestAs(3,'POST','os/1/iniciar-atendimento',['status_oss'=>'encerrada','inicio_atendimento_oss'=>'2000-01-01','usuario_osh'=>'1'])->assertStatus(303);
        $order=$this->order();
        $this->assertSame('em_atendimento',$order['status_oss']);
        $this->assertNotNull($order['inicio_atendimento_oss']);
        $this->assertNotSame('2000-01-01',$order['inicio_atendimento_oss']);
        $this->assertSame('3',(string)$this->events('inicio_atendimento')[0]['usuario_osh']);
        $this->assertSame('atribuida',$this->events('inicio_atendimento')[0]['status_anterior_osh']);
        $this->requestAs(3,'POST','os/1/iniciar-atendimento')->assertStatus(422);
        $this->requestAs(2,'POST','os/1/cancelar',['motivo'=>'Cancelamento tardio'])->assertStatus(422);
        $this->assertSame($order,$this->order());
        $this->assertCount(1,$this->events('inicio_atendimento'));
    }

    public function testNewConnectionRequiresMeterDeliveredInOwnCustody(): void
    {
        $this->db->table('tbl_os')->where('id_oss',1)->update(['tipo_oss'=>'nova_ligacao']);
        $this->db->table('tbl_checklist')->where('id_chk',1)->update(['tipo_os_chk'=>'nova_ligacao']);
        $this->approve();
        $this->requestAs(3,'POST','os/1/iniciar-atendimento')->assertStatus(422);
        $service=new MedidorOsService($this->db); $service->reserve(1,'1',1);
        $r=(int)$this->db->table('tbl_medidor_reserva')->get()->getRow()->id_rme;
        $this->requestAs(3,'POST','os/1/iniciar-atendimento')->assertStatus(422);
        $service->deliver(1,$r,1);
        $this->db->table('tbl_medidor')->where('id_med',1)->update(['eletricista_posse_med'=>2]);
        $this->requestAs(3,'POST','os/1/iniciar-atendimento')->assertStatus(422);
        $this->db->table('tbl_medidor')->where('id_med',1)->update(['eletricista_posse_med'=>1]);
        $service->occurrence(1,'dano','Dano antes do início',3,1);
        $this->requestAs(3,'POST','os/1/iniciar-atendimento')->assertStatus(422);
        $service->receive(1,$r,'defeito',1);
        $service->reserve(1,'2',1);
        $r=(int)$this->db->table('tbl_medidor_reserva')->orderBy('id_rme','DESC')->get()->getRow()->id_rme;
        $service->deliver(1,$r,1);
        $this->requestAs(3,'POST','os/1/iniciar-atendimento')->assertStatus(303);
        $this->assertSame('em_atendimento',$this->order()['status_oss']);
        $this->assertSame('em_transito',$this->db->table('tbl_medidor')->where('id_med',2)->get()->getRow()->status_med);
    }

    public function testReservedConsumablesAndChangedChecklistBlockStart(): void
    {
        $this->approve();
        $s=new ConsumivelService($this->db); $s->reserve(1,'2','1.125',1);
        $r=(int)$this->db->table('tbl_consumivel_reserva')->get()->getRow()->id_rco;
        $this->requestAs(3,'POST','os/1/iniciar-atendimento')->assertStatus(422);
        $s->deliver(1,$r,1);
        $this->db->table('tbl_checklist_item')->where('id_chi',1)->update(['pergunta_chi'=>'Nova condição?']);
        $this->requestAs(3,'POST','os/1/iniciar-atendimento')->assertStatus(422);
        $this->approve();
        $this->requestAs(3,'POST','os/1/iniciar-atendimento')->assertStatus(303);
        $this->assertSame('1.125',$this->db->table('tbl_consumivel_saldo')->where('consumivel_sco',2)->where('eletricista_sco',1)->get()->getRow()->quantidade_sco);
    }

    public function testObservationsAppendEvidenceValidateAndEscape(): void
    {
        $this->requestAs(3,'POST','os/1/observacoes-atendimento',['observacao_atendimento'=>'Antes do início'])->assertStatus(422);
        $this->approve(); (new AtendimentoService($this->db))->start(1,3);
        foreach (['',str_repeat('a',2001),['Texto']] as $value) { $this->requestAs(3,'POST','os/1/observacoes-atendimento',['observacao_atendimento'=>$value])->assertStatus(422); }
        $this->assertCount(0,$this->events('observacao_atendimento'));
        foreach (['Primeira visita','<script>alert(1)</script>'] as $note) { $this->requestAs(3,'POST','os/1/observacoes-atendimento',['observacao_atendimento'=>$note,'usuario_osh'=>'1'])->assertStatus(303); }
        $notes=$this->events('observacao_atendimento'); $this->assertCount(2,$notes);
        $this->assertSame('Primeira visita',$notes[0]['observacao_osh']);
        $this->assertSame('3',(string)$notes[1]['usuario_osh']);
        $this->assertSame('em_atendimento',$notes[1]['status_osh']);
        $body=$this->requestAs(3,'GET','os/1')->getBody();
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;',$body);
        $this->assertStringNotContainsString('<script>alert(1)</script>',$body);
        $this->assertNull($this->order()['observacoes_finais_oss']);
    }

    public function testForeignMissingAndFinalOrdersAreDenied(): void
    {
        foreach (['iniciar-atendimento','observacoes-atendimento'] as $action) {
            $this->requestAs(4,'POST',"os/1/$action",['observacao_atendimento'=>'Visita'])->assertStatus(403);
            $this->requestAs(3,'POST',"os/999/$action",['observacao_atendimento'=>'Visita'])->assertStatus(404);
            foreach (['aberta','cancelada','encerrada'] as $state) {
                $this->db->table('tbl_os')->where('id_oss',1)->update(['status_oss'=>$state]);
                $this->requestAs(3,'POST',"os/1/$action",['observacao_atendimento'=>'Visita'])->assertStatus(422);
            }
        }
        $this->assertCount(0,$this->events('inicio_atendimento'));
        $this->assertCount(0,$this->events('observacao_atendimento'));
    }

    public function testHistoricFailureRollsBackStartAndObservation(): void
    {
        $this->approve(); $before=$this->order();
        $this->db->query("ALTER TABLE tbl_os_historico ADD CONSTRAINT test_start_history CHECK (evento_osh <> 'inicio_atendimento')");
        $this->requestAs(3,'POST','os/1/iniciar-atendimento')->assertStatus(422);
        $this->assertSame($before,$this->order());
        $this->db->query('ALTER TABLE tbl_os_historico DROP CHECK test_start_history');
        (new AtendimentoService($this->db))->start(1,3);
        $this->db->query("ALTER TABLE tbl_os_historico ADD CONSTRAINT test_note_history CHECK (evento_osh <> 'observacao_atendimento')");
        $this->requestAs(3,'POST','os/1/observacoes-atendimento',['observacao_atendimento'=>'Falha de auditoria'])->assertStatus(422);
        $this->assertCount(0,$this->events('observacao_atendimento'));
        $this->assertSame('em_atendimento',$this->order()['status_oss']);
        $this->db->query('ALTER TABLE tbl_os_historico DROP CHECK test_note_history');
    }

    public function testServiceRechecksActorAndPreventsDirectManagerStart(): void
    {
        $this->approve();
        $this->db->table('tbl_usuario')->where('id_usu',3)->update(['ativo_usu'=>0]);
        foreach ([1,2,3] as $actor) {
            try { (new AtendimentoService($this->db))->start(1,$actor); $this->fail('Ator sem acesso iniciou atendimento.'); }
            catch (\App\Exceptions\FormException $e) { $this->assertArrayHasKey('operacao',$e->errors); }
        }
        $this->assertSame('atribuida',$this->order()['status_oss']);
    }
}
