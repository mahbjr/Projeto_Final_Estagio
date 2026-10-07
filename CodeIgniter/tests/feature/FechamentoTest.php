<?php

namespace Tests\Feature;

use App\Services\AtendimentoService;
use App\Services\ChecklistFechamentoService;
use App\Services\ChecklistInicioService;
use App\Services\MedidorOsService;
use Tests\Support\AppTestCase;

final class FechamentoTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('tbl_os')->where('id_oss',1)->update(['tipo_oss'=>'corte','status_oss'=>'em_atendimento','inicio_atendimento_oss'=>date('Y-m-d H:i:s')]);
        $this->db->table('tbl_checklist')->insert(['id_chk'=>1,'nome_chk'=>'Final campo','tipo_os_chk'=>'corte','etapa_chk'=>'fechamento','ativo_chk'=>1,'usuario_chk'=>1]);
        $this->db->table('tbl_checklist_item')->insert(['id_chi'=>1,'checklist_chi'=>1,'pergunta_chi'=>'Serviço seguro?','nivel_chi'=>'bloqueante','obrigatorio_chi'=>1]);
    }
    private function approve(string $value='1'): void { (new ChecklistFechamentoService($this->db))->answer(1,1,['1'=>$value],[],3); }
    private function order(): array { return $this->db->table('tbl_os')->where('id_oss',1)->get()->getRowArray(); }
    private function finalData(string $result='executado'): array { return ['resultado_oss'=>$result,'observacoes_finais_oss'=>'Atendimento concluído e materiais conferidos.','corte_confirmado_oss'=>'1','leitura_final_oss'=>'0']; }
    private function close(array $data=[]): void { $this->requestAs(3,'POST','os/1/encerrar',array_replace($this->finalData(),$data))->assertStatus(303); }
    private function rowCount(string $table): int { return $this->db->table($table)->countAllResults(); }
    private function newConnection(): void
    {
        $this->db->table('tbl_os')->where('id_oss',1)->update(['tipo_oss'=>'nova_ligacao','status_oss'=>'atribuida','inicio_atendimento_oss'=>null]);
        $this->db->table('tbl_checklist')->where('id_chk',1)->update(['tipo_os_chk'=>'nova_ligacao']);
        $this->db->table('tbl_checklist')->insert(['id_chk'=>2,'nome_chk'=>'Início','tipo_os_chk'=>'nova_ligacao','etapa_chk'=>'inicio','ativo_chk'=>1,'usuario_chk'=>1]);
        $this->db->table('tbl_checklist_item')->insert(['id_chi'=>2,'checklist_chi'=>2,'pergunta_chi'=>'Seguro?','nivel_chi'=>'bloqueante','obrigatorio_chi'=>1]);
        (new ChecklistInicioService($this->db))->answer(1,2,['2'=>'1'],[],3);
        $m=new MedidorOsService($this->db); $this->legacyMeterReservation(1,1); $this->pickupLegacyReservation(1,1);
        (new AtendimentoService($this->db))->start(1,3);
        $this->approve();
    }
    public function testRolesCsrfExpiryGetAndOwnershipCannotWrite(): void
    {
        $before=$this->order();
        foreach (['os/1/encerrar','os/1/checklists/1/responder-fechamento'] as $path) {
            foreach ([null,1,2,4] as $actor) {
                $r=$this->requestAs($actor,'POST',$path,$this->finalData());
                if ($actor===null) { $r->assertRedirectTo(site_url('login')); } else { $r->assertStatus(403); }
            }
            $this->requestAs(3,'POST',$path,$this->finalData(),false)->assertStatus(403);
            $this->requestAs(3,'POST',$path,$this->finalData(),true,time()-7201)->assertRedirectTo(site_url('login'));
            $this->requestAs(3,'GET',$path)->assertStatus(404);
        }
        $this->assertSame($before,$this->order()); $this->assertSame(0,$this->rowCount('tbl_checklist_avaliacao'));
        $this->requestAs(3,'POST','os/999/encerrar',$this->finalData())->assertStatus(404);
        $this->requestAs(3,'POST','os/1/checklists/999/responder-fechamento',['respostas'=>['1'=>'1']])->assertStatus(404);
        $this->db->table('tbl_checklist')->where('id_chk',1)->update(['etapa_chk'=>'inicio']);
        $this->requestAs(3,'POST','os/1/checklists/1/responder-fechamento',['respostas'=>['1'=>'1']])->assertStatus(404);
    }
    public function testBlockedFinalNeedsCorrectionAndHasNoManagerRelease(): void
    {
        $this->requestAs(3,'POST','os/1/encerrar',$this->finalData())->assertStatus(422);
        $this->approve('0');
        $this->requestAs(3,'POST','os/1/encerrar',$this->finalData())->assertStatus(422);
        $this->requestAs(1,'POST','os/1/avaliacoes/1/liberar-inicio',['justificativa'=>'Tentativa de exceção'])->assertStatus(422);
        $this->db->table('tbl_os')->where('id_oss',1)->update(['status_oss'=>'atribuida','inicio_atendimento_oss'=>null]);
        $this->requestAs(1,'POST','os/1/avaliacoes/1/liberar-inicio',['justificativa'=>'Ainda não pode liberar final'])->assertStatus(422);
        $this->db->table('tbl_os')->where('id_oss',1)->update(['status_oss'=>'em_atendimento']);
        $this->requestAs(3,'POST','os/1/checklists/1/responder-fechamento',['respostas'=>['1'=>'1'],'observacoes'=>['1'=>'Corrigido']])->assertStatus(303);
        $this->assertSame(2,$this->rowCount('tbl_checklist_avaliacao')); $this->assertSame(2,$this->rowCount('tbl_checklist_resposta'));
        $old=$this->db->table('tbl_checklist_avaliacao')->where('id_cav',1)->get()->getRowArray();
        $this->assertSame('1',(string)$old['bloqueada_cav']); $this->assertNull($old['liberado_por_cav']);
        $this->close(); $this->assertSame('encerrada',$this->order()['status_oss']);
    }
    public function testCurrentTemplatesQuestionsAndRequiredAnswersMustBeApproved(): void
    {
        $this->approve();
        $this->db->table('tbl_checklist_item')->where('id_chi',1)->update(['pergunta_chi'=>'Nova verificação?']);
        $this->requestAs(3,'POST','os/1/encerrar',$this->finalData())->assertStatus(422);
        $this->approve();
        $this->db->table('tbl_checklist')->insert(['id_chk'=>2,'nome_chk'=>'Outro final','tipo_os_chk'=>'corte','etapa_chk'=>'fechamento','ativo_chk'=>1,'usuario_chk'=>1]);
        $this->db->table('tbl_checklist_item')->insert(['id_chi'=>2,'checklist_chi'=>2,'pergunta_chi'=>'Informação?','nivel_chi'=>'informativo','obrigatorio_chi'=>0]);
        $this->requestAs(3,'POST','os/1/encerrar',$this->finalData())->assertStatus(422);
        (new ChecklistFechamentoService($this->db))->answer(1,2,[],[],3);
        $this->db->table('tbl_checklist_item')->where('id_chi',2)->update(['obrigatorio_chi'=>1]);
        $this->requestAs(3,'POST','os/1/encerrar',$this->finalData())->assertStatus(422);
        (new ChecklistFechamentoService($this->db))->answer(1,2,['2'=>'0'],[],3);
        $this->close();
        $this->assertSame('Serviço seguro?',$this->db->table('tbl_checklist_resposta')->where('avaliacao_cre',1)->get()->getRow()->pergunta_cre);
    }
    public function testInvalidFinalFieldsAndReadingRejectWithoutChanges(): void
    {
        $this->approve(); $before=$this->order();
        foreach ([['resultado_oss'=>'outro'],['resultado_oss'=>['executado']],['observacoes_finais_oss'=>''],['observacoes_finais_oss'=>str_repeat('x',2001)],['observacoes_finais_oss'=>['texto']],['corte_confirmado_oss'=>'0'],['corte_confirmado_oss'=>'2'],['corte_confirmado_oss'=>['1']],['leitura_final_oss'=>''],['leitura_final_oss'=>'-1'],['leitura_final_oss'=>'1e3'],['leitura_final_oss'=>'1.0000'],['leitura_final_oss'=>'9999999999999'],['leitura_final_oss'=>['0']],['leitura_final_oss'=>'1,000.123']] as $override) {
            $this->requestAs(3,'POST','os/1/encerrar',array_replace($this->finalData(),$override))->assertStatus(422);
            $this->assertSame($before,$this->order());
        }
        $this->close(['leitura_final_oss'=>'999999999999,999']);
        $this->assertSame('999999999999.999',$this->order()['leitura_final_oss']);
    }
    public function testCutZeroReadingAuditAndFinalDataAreImmutable(): void
    {
        $this->approve(); $this->close(['usuario_osh'=>'1','status_oss'=>'cancelada','data_fechamento_oss'=>'2000-01-01','observacoes_finais_oss'=>'<script>alert(1)</script>','leitura_final_oss'=>'000']);
        $order=$this->order(); $this->assertSame('0.000',$order['leitura_final_oss']); $this->assertSame('1',(string)$order['corte_confirmado_oss']); $this->assertSame('executado',$order['resultado_oss']); $this->assertNotSame('2000-01-01',$order['data_fechamento_oss']);
        $event=$this->db->table('tbl_os_historico')->where('evento_osh','encerramento')->get()->getRowArray();
        $this->assertSame('3',(string)$event['usuario_osh']); $this->assertSame('em_atendimento',$event['status_anterior_osh']); $this->assertSame('encerrada',$event['status_osh']);
        foreach (['encerrar','iniciar-atendimento','observacoes-atendimento','cancelar'] as $action) { $this->requestAs($action==='cancelar'?1:3,'POST',"os/1/$action",$this->finalData()+['motivo'=>'Tardio','observacao_atendimento'=>'Tardio'])->assertStatus(422); }
        $this->requestAs(3,'POST','os/1/checklists/1/responder-fechamento',['respostas'=>['1'=>'1']])->assertStatus(422);
        $this->requestAs(2,'POST','os/1/atualizar',['prioridade_oss'=>'urgente'])->assertStatus(422);
        $this->assertSame($order,$this->order());
        foreach ([1,2,3] as $actor) { $body=$this->requestAs($actor,'GET','os/1')->getBody(); $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;',$body); $this->assertStringNotContainsString('<script>alert(1)</script>',$body); $this->assertStringNotContainsString('action="'.site_url('os/1/encerrar').'"',$body); }
    }
    public function testPartialAndUnexecutedResultsPermitNoCutOrReading(): void
    {
        $this->approve(); $this->close(['resultado_oss'=>'parcial','corte_confirmado_oss'=>'0','leitura_final_oss'=>'','observacoes_finais_oss'=>'Acesso parcial à UC.']);
        $this->assertSame('parcial',$this->order()['resultado_oss']); $this->assertNull($this->order()['leitura_final_oss']);
        $this->db->table('tbl_os')->where('id_oss',1)->update(['status_oss'=>'em_atendimento','data_fechamento_oss'=>null]);
        $this->close(['resultado_oss'=>'nao_executado','corte_confirmado_oss'=>'0','leitura_final_oss'=>'','observacoes_finais_oss'=>'Local inacessível.']);
        $this->assertSame('nao_executado',$this->order()['resultado_oss']);
    }
    public function testNewConnectionNeedsInstalledMeterAndNoCutOnlyFields(): void
    {
        $this->newConnection();
        $data=['corte_confirmado_oss'=>'0','leitura_final_oss'=>''];
        $this->requestAs(3,'POST','os/1/encerrar',array_replace($this->finalData(),$data))->assertStatus(422);
        (new MedidorOsService($this->db))->apply(1,1,3);
        $this->requestAs(3,'POST','os/1/encerrar',$this->finalData())->assertStatus(422);
        $this->close($data); $this->assertSame('executado',$this->order()['resultado_oss']);
        $this->assertSame('instalado',$this->db->table('tbl_medidor')->where('id_med',1)->get()->getRow()->status_med);
        foreach (['aplicar','retirar'] as $action) { $this->requestAs(3,'POST',"os/1/medidores/1/$action")->assertStatus(422); }
    }
    public function testWithdrawnMeterMustReturnAndCannotRepresentExecutedConnection(): void
    {
        $this->newConnection(); $m=new MedidorOsService($this->db); $m->apply(1,1,3); $m->withdraw(1,1,3,'Defeito identificado após instalação');
        $data=array_replace($this->finalData(),['corte_confirmado_oss'=>'0','leitura_final_oss'=>'','resultado_oss'=>'parcial']);
        $this->requestAs(3,'POST','os/1/encerrar',$data)->assertStatus(422);
        $this->returnLegacyReservation(1,2,'disponivel');
        $this->requestAs(3,'POST','os/1/encerrar',array_replace($data,['resultado_oss'=>'executado']))->assertStatus(422);
        $this->close($data); $this->assertSame('parcial',$this->order()['resultado_oss']);
        $this->assertSame(2,$this->db->table('tbl_os_medidor')->where('ordem_servico_osm',1)->countAllResults());
    }
    public function testDamagedVehicleMeterRequiresReturnAndLostRequiresAdministrativeWriteOff(): void
    {
        $this->newConnection(); $m=new MedidorOsService($this->db); $m->occurrence(1,'dano','Defeito em campo',3,1);
        $data=array_replace($this->finalData(),['resultado_oss'=>'nao_executado','corte_confirmado_oss'=>'0','leitura_final_oss'=>'']);
        $this->requestAs(3,'POST','os/1/encerrar',$data)->assertStatus(422);
        $this->returnLegacyReservation(1,1,'defeito'); $this->close($data);
        $this->assertSame('defeito',$this->db->table('tbl_medidor')->where('id_med',1)->get()->getRow()->status_med);
        $this->db->table('tbl_os')->where('id_oss',1)->update(['status_oss'=>'em_atendimento','data_fechamento_oss'=>null]);
        $this->db->table('tbl_medidor')->where('id_med',2)->update(['status_med'=>'em_transito','localizacao_med'=>'viatura','eletricista_posse_med'=>1]);
        $this->db->table('tbl_medidor_reserva')->insert(['id_rme'=>2,'medidor_rme'=>2,'ordem_servico_rme'=>1,'eletricista_rme'=>1,'usuario_rme'=>1,'status_rme'=>'entregue']);
        $m->occurrence(2,'perda','Perda em campo',3,1);
        $this->requestAs(3,'POST','os/1/encerrar',$data)->assertStatus(422);
        $m->occurrence(2,'baixa','Gestor verificou perda',1,1); $this->close($data);
        $meter=$this->db->table('tbl_medidor')->where('id_med',2)->get()->getRowArray(); $this->assertSame('baixado',$meter['status_med']); $this->assertSame('1',(string)$meter['eletricista_posse_med']);
    }
    public function testMissingActiveTemplateAndOptionalArrayInputsCannotBypassValidation(): void
    {
        $this->approve();
        $this->db->table('tbl_checklist')->where('id_chk',1)->update(['ativo_chk'=>0]);
        $this->requestAs(3,'POST','os/1/encerrar',$this->finalData())->assertStatus(422);
        $this->db->table('tbl_checklist')->where('id_chk',1)->update(['ativo_chk'=>1]);
        foreach (['leitura_final_oss','corte_confirmado_oss'] as $field) {
            $data=array_replace($this->finalData(),['resultado_oss'=>'nao_executado','corte_confirmado_oss'=>'0','leitura_final_oss'=>'',$field=>['0']]);
            $this->requestAs(3,'POST','os/1/encerrar',$data)->assertStatus(422);
        }
        $this->assertSame('em_atendimento',$this->order()['status_oss']);
    }

    public function testLegacyMeterEvidenceWithoutReconciliationBlocksClosing(): void
    {
        $this->approve();
        $this->db->table('tbl_os_medidor')->insert(['ordem_servico_osm'=>1,'medidor_osm'=>3,'tipo_osm'=>'retirado']);
        $this->requestAs(3,'POST','os/1/encerrar',$this->finalData())->assertStatus(422);
        $this->db->table('tbl_medidor_reserva')->insert(['id_rme'=>1,'medidor_rme'=>3,'ordem_servico_rme'=>1,'eletricista_rme'=>1,'usuario_rme'=>1,'status_rme'=>'entregue']);
        $this->returnLegacyReservation(1,1,'disponivel');
        $this->close();
        $this->assertSame(1,$this->db->table('tbl_os_medidor')->where('ordem_servico_osm',1)->countAllResults());
    }

    public function testHistoryAndResponseFailuresRollbackClosingAndChecklist(): void
    {
        $before=$this->order();
        foreach ([['tbl_checklist_resposta','test_final_response',"pergunta_cre <> 'Serviço seguro?'"],['tbl_os_historico','test_final_check',"evento_osh <> 'checklist_fechamento'"]] as [$table,$name,$rule]) {
            $this->db->query("ALTER TABLE $table ADD CONSTRAINT $name CHECK ($rule)");
            $this->requestAs(3,'POST','os/1/checklists/1/responder-fechamento',['respostas'=>['1'=>'1']])->assertStatus(422);
            $this->assertSame(0,$this->rowCount('tbl_checklist_avaliacao')); $this->assertSame(0,$this->rowCount('tbl_checklist_resposta'));
            $this->db->query("ALTER TABLE $table DROP CHECK $name");
        }
        $this->approve();
        $this->db->query("ALTER TABLE tbl_os_historico ADD CONSTRAINT test_close_history CHECK (evento_osh <> 'encerramento')");
        $this->requestAs(3,'POST','os/1/encerrar',$this->finalData())->assertStatus(422);
        $this->assertSame($before,$this->order()); $this->assertSame(0,$this->db->table('tbl_os_historico')->where('evento_osh','encerramento')->countAllResults());
        $this->db->query('ALTER TABLE tbl_os_historico DROP CHECK test_close_history');
    }
    public function testInvalidResponsesActorAndStatesAreRejected(): void
    {
        foreach ([['respostas'=>['1'=>['1']]],['respostas'=>['99'=>'1']],['respostas'=>[]],['respostas'=>['1'=>'1'],'observacoes'=>['1'=>str_repeat('x',1001)]],['respostas'=>'1']] as $data) { $this->requestAs(3,'POST','os/1/checklists/1/responder-fechamento',$data)->assertStatus(422); }
        foreach (['aberta','atribuida','cancelada','encerrada'] as $status) {
            $this->db->table('tbl_os')->where('id_oss',1)->update(['status_oss'=>$status]);
            $this->requestAs(3,'POST','os/1/encerrar',$this->finalData())->assertStatus(422);
            $this->requestAs(3,'POST','os/1/checklists/1/responder-fechamento',['respostas'=>['1'=>'1']])->assertStatus(422);
        }
        $this->db->table('tbl_os')->where('id_oss',1)->update(['status_oss'=>'em_atendimento']);
        $this->db->table('tbl_usuario')->where('id_usu',3)->update(['ativo_usu'=>0]);
        foreach ([1,2,3,4] as $actor) {
            try { (new AtendimentoService($this->db))->close(1,$this->finalData(),$actor); $this->fail('Ator sem acesso encerrou OS.'); } catch (\App\Exceptions\FormException $e) { $this->assertArrayHasKey('operacao',$e->errors); }
        }
        $this->assertSame(0,$this->rowCount('tbl_checklist_avaliacao'));
    }
}
