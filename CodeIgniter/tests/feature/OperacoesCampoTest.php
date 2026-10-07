<?php

namespace Tests\Feature;

use App\Services\AtendimentoService;
use App\Services\ChecklistInicioService;
use App\Services\ConsumivelService;
use App\Services\MedidorOsService;
use Tests\Support\AppTestCase;

final class OperacoesCampoTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('tbl_checklist')->insert(['id_chk'=>1,'nome_chk'=>'Início campo','tipo_os_chk'=>'nova_ligacao','etapa_chk'=>'inicio','ativo_chk'=>1,'usuario_chk'=>1]);
        $this->db->table('tbl_checklist_item')->insert(['id_chi'=>1,'checklist_chi'=>1,'pergunta_chi'=>'Seguro?','nivel_chi'=>'bloqueante','obrigatorio_chi'=>1]);
        (new ChecklistInicioService($this->db))->answer(1,1,['1'=>'1'],[],3);
        $s=new ConsumivelService($this->db); $s->reserve(1,'2','5.125',1); $s->deliver(1,1,1);
        $m=new MedidorOsService($this->db); $this->legacyMeterReservation(1,1); $this->pickupLegacyReservation(1,1);
        (new AtendimentoService($this->db))->start(1,3);
    }
    private function meter(int $id=1): array { return $this->db->table('tbl_medidor')->where('id_med',$id)->get()->getRowArray(); }
    private function reservation(): array { return $this->db->table('tbl_consumivel_reserva')->where('id_rco',1)->get()->getRowArray(); }
    private function balance(?int $owner=1): string { return $this->db->table('tbl_consumivel_saldo')->where('consumivel_sco',2)->where('eletricista_sco',$owner)->get()->getRow()->quantidade_sco; }
    private function amount(mixed $value='2.125'): array { return ['quantidade_consumo'=>$value,'observacao_consumo'=>'Material aplicado em campo']; }
    public function testRolesCsrfExpiryAndWrongVerbDoNotWrite(): void
    {
        $before=$this->meter(); $balance=$this->balance();
        foreach (['os/1/consumiveis/999/consumir','os/1/medidores/999/aplicar','os/1/medidores/5/retirar'] as $path) {
            foreach ([null,1,2,3] as $actor) {
                $r=$this->requestAs($actor,'POST',$path,$this->amount());
                if ($actor===null) { $r->assertRedirectTo(site_url('login')); }
                else { $r->assertStatus($actor===3 ? 404 : 403); }
            }
            $this->requestAs(3,'POST',$path,$this->amount(),false)->assertStatus(403);
            $this->requestAs(3,'POST',$path,$this->amount(),true,time()-7201)->assertRedirectTo(site_url('login'));
            $this->requestAs(3,'GET',$path)->assertStatus(404);
        }
        $this->assertSame($before,$this->meter()); $this->assertSame($balance,$this->balance());
    }
    public function testConsumptionPartialReturnAndIntegralReconciliationAreExact(): void
    {
        $this->requestAs(3,'POST','os/1/consumiveis/1/consumir',$this->amount('2,125')+['usuario_mco'=>'1'])->assertStatus(303);
        $this->assertSame('3.000',$this->balance()); $this->assertSame('20.375',$this->balance(null));
        $this->assertSame('2.125',$this->reservation()['consumido_rco']);
        $movement=$this->db->table('tbl_consumivel_mov')->where('tipo_mco','consumo')->get()->getRowArray();
        $this->assertSame('3',(string)$movement['usuario_mco']); $this->assertSame('1',(string)$movement['ordem_servico_mco']);
        (new ConsumivelService($this->db))->receive(1,1,'1.000','Sobra recebida',1);
        $this->requestAs(3,'POST','os/1/consumiveis/1/consumir',$this->amount('2.000'))->assertStatus(303);
        $this->assertSame('0.000',$this->balance()); $this->assertSame('21.375',$this->balance(null));
        $this->assertSame('4.125',$this->reservation()['consumido_rco']); $this->assertSame('1.000',$this->reservation()['devolvido_rco']);
        $this->assertSame('conciliada',$this->reservation()['status_rco']);
        $this->requestAs(3,'POST','os/1/consumiveis/1/consumir',$this->amount('2.000'))->assertStatus(422);
        $this->requestAs(1,'POST','os/1/consumiveis/1/receber',['quantidade_devolucao'=>'1','observacao_devolucao'=>'Repetida'])->assertStatus(422);
    }
    public function testConsumptionRejectsInvalidAmountsPrecisionAndInsufficientCustody(): void
    {
        foreach (['0','-1','1e2','1.0000','1,000.001','6',str_repeat('9',13),['1']] as $amount) { $this->requestAs(3,'POST','os/1/consumiveis/1/consumir',$this->amount($amount))->assertStatus(422); }
        foreach (['',str_repeat('a',256),['nota']] as $note) { $this->requestAs(3,'POST','os/1/consumiveis/1/consumir',['observacao_consumo'=>$note]+$this->amount())->assertStatus(422); }
        $this->db->table('tbl_consumivel_saldo')->where('consumivel_sco',2)->where('eletricista_sco',1)->update(['quantidade_sco'=>'1.000']);
        $this->requestAs(3,'POST','os/1/consumiveis/1/consumir',$this->amount())->assertStatus(422);
        $this->assertSame('0.000',$this->reservation()['consumido_rco']);
        $this->assertSame('1.000',$this->balance());
    }
    public function testApplicationUpdatesInstallationCustodyAndAuditWithoutRepeat(): void
    {
        $this->requestAs(3,'POST','os/1/medidores/1/aplicar',['unidade_consumidora_ins'=>'UC-FORJADA','usuario_ins'=>'1'])->assertStatus(303);
        $meter=$this->meter(); $this->assertSame('instalado',$meter['status_med']); $this->assertSame('cliente',$meter['localizacao_med']); $this->assertNull($meter['eletricista_posse_med']);
        $installation=$this->db->table('tbl_instalacao_atual')->where('medidor_ins',1)->get()->getRowArray();
        $this->assertSame('UC-CE-100234',$installation['unidade_consumidora_ins']); $this->assertSame('3',(string)$installation['usuario_ins']);
        $this->assertSame('aplicada',$this->db->table('tbl_medidor_reserva')->where('id_rme',1)->get()->getRow()->status_rme);
        $move=$this->db->table('tbl_estoque_mov')->where('medidor_emv',1)->where('destino_emv','cliente')->get()->getRowArray();
        $this->assertSame('baixa_saida',$move['tipo_emv']); $this->assertSame('1',(string)$move['quantidade_emv']); $this->assertSame('3',(string)$move['usuario_emv']);
        $this->requestAs(3,'POST','os/1/medidores/1/aplicar')->assertStatus(422);
        $this->assertSame(1,$this->db->table('tbl_os_medidor')->where('ordem_servico_osm',1)->where('tipo_osm','instalado')->countAllResults());
        $this->requestAs(1,'POST','os/1/medidores/1/receber',['condicao_medidor'=>'disponivel'])->assertStatus(403);
        $this->requestAs(3,'POST','meus-medidores/1/devolver',['condicao'=>'disponivel'])->assertStatus(403);
    }
    public function testExceptionalWithdrawalRequiresReasonInRouteAndService(): void
    {
        $service = new MedidorOsService($this->db);
        $service->apply(1,1,3);
        $before = $this->meter();
        $body = $this->requestAs(3,'GET','os/1')->getBody();
        $this->assertStringContainsString('Instalação registrada com sucesso', html_entity_decode($body));
        $this->assertStringContainsString('Retirada excepcional do medidor', html_entity_decode($body));
        foreach ([null, '', '   ', ['forjado'], str_repeat('x',1001)] as $reason) {
            $response = $this->requestAs(3,'POST','os/1/medidores/1/retirar', ['justificativa_retirada'=>$reason]);
            $response->assertStatus(422);
            $this->assertSame($before, $this->meter());
            $this->assertSame(1,$this->db->table('tbl_instalacao_atual')->where('medidor_ins',1)->countAllResults());
        }
        try { $service->withdraw(1,1,3); $this->fail('Service aceitou retirada sem justificativa.'); }
        catch (\App\Exceptions\FormException $e) { $this->assertArrayHasKey('justificativa_retirada',$e->errors); }
        $confirmation = $this->requestAs(3,'POST','os/1/medidores/1/retirar', ['_confirmacao'=>'pendente','justificativa_retirada'=>'Defeito na instalação']);
        $confirmation->assertStatus(200);
        $this->assertStringContainsString('name="justificativa_retirada"', $confirmation->getBody());
        $this->assertSame($before, $this->meter());
        $reason = '<script>defeito</script>';
        $this->requestAs(3,'POST','os/1/medidores/1/retirar',['justificativa_retirada'=>$reason])->assertStatus(303);
        $history = $this->db->table('tbl_os_historico')->where('evento_osh','retirada_medidor')->get()->getRowArray();
        $this->assertSame($reason,json_decode($history['dados_osh'],true)['justificativa']);
        $body = $this->requestAs(3,'GET','os/1')->getBody();
        $this->assertStringContainsString('&lt;script&gt;defeito&lt;/script&gt;',$body);
        $this->assertStringNotContainsString($reason,$body);
    }
    public function testWithdrawalThenPhysicalReturnPreservesHistoryAndAllowsNewReservation(): void
    {
        (new MedidorOsService($this->db))->apply(1,1,3);
        $this->requestAs(3,'POST','os/1/medidores/1/retirar',['justificativa_retirada'=>'Defeito técnico identificado após instalação'])->assertStatus(303);
        $this->assertSame('em_transito',$this->meter()['status_med']); $this->assertSame('viatura',$this->meter()['localizacao_med']); $this->assertSame('1',(string)$this->meter()['eletricista_posse_med']);
        $this->assertSame(0,$this->db->table('tbl_instalacao_atual')->where('medidor_ins',1)->countAllResults());
        $this->assertSame(2,$this->db->table('tbl_os_medidor')->where('ordem_servico_osm',1)->countAllResults());
        $this->assertSame('aplicada',$this->db->table('tbl_medidor_reserva')->where('id_rme',1)->get()->getRow()->status_rme);
        $r=(int)$this->db->table('tbl_medidor_reserva')->where('medidor_rme',1)->orderBy('id_rme','DESC')->get()->getRow()->id_rme;
        $this->requestAs(3,'POST','os/1/medidores/1/retirar')->assertStatus(404);
        $this->requestAs(3,'POST',"os/1/medidores/$r/aplicar")->assertStatus(422);
        $this->requestAs(3,'POST',"meus-medidores/1/devolver",['condicao'=>'disponivel'])->assertStatus(303);
        $this->assertSame('disponivel',$this->meter()['status_med']); $this->assertNull($this->meter()['eletricista_posse_med']);
        $this->db->table('tbl_os')->where('id_oss',2)->update(['status_oss'=>'atribuida','tipo_oss'=>'nova_ligacao']);
        $this->legacyMeterReservation(2,1);
        $this->assertSame('reservado',$this->meter()['status_med']);
        $this->assertSame(2,$this->db->table('tbl_os_medidor')->where('ordem_servico_osm',1)->countAllResults());
    }
    public function testWithdrawalRequiresSameUcAndCompanyAndCannotApplyWithdrawnMeter(): void
    {
        $this->requestAs(3,'POST','os/1/medidores/5/retirar')->assertStatus(404);
        $this->db->table('tbl_os')->where('id_oss',1)->update(['unidade_consumidora_oss'=>'UC-CE-300789']);
        $this->requestAs(3,'POST','os/1/medidores/5/retirar')->assertStatus(404);
        $this->db->table('tbl_os')->where('id_oss',1)->update(['cliente_oss'=>3]);
        $this->requestAs(3,'POST','os/1/medidores/5/retirar')->assertStatus(303);
        $r=(int)$this->db->table('tbl_medidor_reserva')->where('medidor_rme',5)->get()->getRow()->id_rme;
        $this->requestAs(3,'POST',"os/1/medidores/$r/aplicar")->assertStatus(422);
        $this->assertSame('em_transito',$this->meter(5)['status_med']);
        $this->assertSame('3',(string)$this->db->table('tbl_medidor_reserva')->where('id_rme',$r)->get()->getRow()->usuario_rme);
        $this->assertSame(0,$this->db->table('tbl_estoque_mov')->where('medidor_emv',5)->where('ordem_servico_emv',1)->where('destino_emv','galpao')->countAllResults());
    }
    public function testOwnOrderNestedIdsAndStateBoundaries(): void
    {
        foreach (['consumiveis/1/consumir','medidores/1/aplicar','medidores/1/retirar'] as $suffix) {
            $this->requestAs(4,'POST',"os/1/$suffix",$this->amount())->assertStatus(403);
            $this->requestAs(3,'POST',"os/999/$suffix",$this->amount())->assertStatus(404);
            foreach (['atribuida','cancelada','encerrada'] as $state) {
                $this->db->table('tbl_os')->where('id_oss',1)->update(['status_oss'=>$state]);
                $this->requestAs(3,'POST',"os/1/$suffix",$this->amount())->assertStatus(422);
            }
            $this->db->table('tbl_os')->where('id_oss',1)->update(['status_oss'=>'em_atendimento']);
        }
        $this->requestAs(3,'POST','os/2/consumiveis/1/consumir',$this->amount())->assertStatus(404);
        $this->requestAs(3,'POST','os/2/medidores/1/aplicar')->assertStatus(404);
        $this->db->table('tbl_os')->where('id_oss',1)->update(['tipo_oss'=>'corte']);
        $this->requestAs(3,'POST','os/1/medidores/1/aplicar')->assertStatus(422);
    }
    public function testDamagedOrForeignCustodyMeterCannotBeApplied(): void
    {
        $this->db->table('tbl_medidor')->where('id_med',1)->update(['eletricista_posse_med'=>2]);
        $this->requestAs(3,'POST','os/1/medidores/1/aplicar')->assertStatus(422);
        $this->db->table('tbl_medidor')->where('id_med',1)->update(['eletricista_posse_med'=>1]);
        (new MedidorOsService($this->db))->occurrence(1,'dano','Dano identificado',3,1);
        $this->requestAs(3,'POST','os/1/medidores/1/aplicar')->assertStatus(422);
        $this->assertSame(0,$this->db->table('tbl_instalacao_atual')->where('medidor_ins',1)->countAllResults());
    }
    public function testConsumptionMovementAndHistoryFailuresRollbackAllWrites(): void
    {
        foreach ([['tbl_consumivel_mov','test_consume_move',"tipo_mco <> 'consumo'"],['tbl_os_historico','test_consume_history',"evento_osh <> 'consumo_consumivel'"]] as [$table,$name,$condition]) {
            $this->db->query("ALTER TABLE $table ADD CONSTRAINT $name CHECK ($condition)");
            $this->requestAs(3,'POST','os/1/consumiveis/1/consumir',$this->amount())->assertStatus(422);
            $this->assertSame('5.125',$this->balance()); $this->assertSame('0.000',$this->reservation()['consumido_rco']);
            $this->assertSame(0,$this->db->table('tbl_consumivel_mov')->where('tipo_mco','consumo')->countAllResults());
            $this->db->query("ALTER TABLE $table DROP CHECK $name");
        }
    }
    public function testApplicationMovementAndHistoryFailuresRollbackAllWrites(): void
    {
        foreach ([['tbl_estoque_mov','test_apply_move',"observacao_emv NOT LIKE 'Aplicação na UC%'"],['tbl_os_historico','test_apply_history',"evento_osh <> 'aplicacao_medidor'"]] as [$table,$name,$condition]) {
            $this->db->query("ALTER TABLE $table ADD CONSTRAINT $name CHECK ($condition)");
            $this->requestAs(3,'POST','os/1/medidores/1/aplicar')->assertStatus(422);
            $this->assertSame('em_transito',$this->meter()['status_med']);
            $this->assertSame('entregue',$this->db->table('tbl_medidor_reserva')->where('id_rme',1)->get()->getRow()->status_rme);
            $this->assertSame(0,$this->db->table('tbl_instalacao_atual')->where('medidor_ins',1)->countAllResults());
            $this->assertSame(0,$this->db->table('tbl_os_medidor')->where('ordem_servico_osm',1)->countAllResults());
            $this->db->query("ALTER TABLE $table DROP CHECK $name");
        }
    }
    public function testWithdrawalMovementAndHistoryFailuresRestoreCurrentInstallation(): void
    {
        (new MedidorOsService($this->db))->apply(1,1,3);
        foreach ([['tbl_estoque_mov','test_withdraw_move',"observacao_emv NOT LIKE 'Retirada na UC%'"],['tbl_os_historico','test_withdraw_history',"evento_osh <> 'retirada_medidor'"]] as [$table,$name,$condition]) {
            $this->db->query("ALTER TABLE $table ADD CONSTRAINT $name CHECK ($condition)");
            $this->requestAs(3,'POST','os/1/medidores/1/retirar',['justificativa_retirada'=>'Defeito técnico'])->assertStatus(422);
            $this->assertSame('instalado',$this->meter()['status_med']);
            $this->assertSame(1,$this->db->table('tbl_instalacao_atual')->where('medidor_ins',1)->countAllResults());
            $this->assertSame(1,$this->db->table('tbl_medidor_reserva')->where('medidor_rme',1)->countAllResults());
            $this->assertSame(0,$this->db->table('tbl_os_medidor')->where('ordem_servico_osm',1)->where('tipo_osm','retirado')->countAllResults());
            $this->db->query("ALTER TABLE $table DROP CHECK $name");
        }
    }
    public function testServiceRechecksActorAndConsumptionObservationsAreEscaped(): void
    {
        $this->requestAs(3,'POST','os/1/consumiveis/1/consumir',['observacao_consumo'=>'<script>alert(1)</script>']+$this->amount())->assertStatus(303);
        $body=$this->requestAs(3,'GET','os/1')->getBody(); $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;',$body); $this->assertStringNotContainsString('<script>alert(1)</script>',$body);
        $this->db->table('tbl_usuario')->where('id_usu',3)->update(['ativo_usu'=>0]);
        foreach ([1,2,3] as $actor) {
            try { (new ConsumivelService($this->db))->consume(1,1,'1','Consumo',$actor); $this->fail('Ator sem acesso consumiu material.'); }
            catch (\App\Exceptions\FormException $e) { $this->assertArrayHasKey('operacao',$e->errors); }
            try { (new MedidorOsService($this->db))->apply(1,1,$actor); $this->fail('Ator sem acesso aplicou medidor.'); }
            catch (\App\Exceptions\FormException $e) { $this->assertArrayHasKey('operacao',$e->errors); }
        }
    }
}
