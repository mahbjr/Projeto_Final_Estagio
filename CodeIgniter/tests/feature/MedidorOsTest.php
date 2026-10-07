<?php
namespace Tests\Feature;
use App\Services\ChecklistInicioService;
use App\Services\MedidorOsService;
use Tests\Support\AppTestCase;
final class MedidorOsTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('tbl_os')->where('id_oss',1)->update(['tipo_oss'=>'nova_ligacao']);
        $this->db->table('tbl_checklist')->insert(['id_chk'=>1,'nome_chk'=>'Início','tipo_os_chk'=>'nova_ligacao','etapa_chk'=>'inicio','ativo_chk'=>1,'usuario_chk'=>1]);
        $this->db->table('tbl_checklist_item')->insert(['id_chi'=>1,'checklist_chi'=>1,'pergunta_chi'=>'Seguro?','nivel_chi'=>'bloqueante','obrigatorio_chi'=>1]);
        (new ChecklistInicioService($this->db))->answer(1,1,['1'=>'1'],[],3);
    }
    private function meter(int $id=1): array { return $this->db->table('tbl_medidor')->where('id_med',$id)->get()->getRowArray(); }
    private function reserve(): int { $this->legacyMeterReservation(1,1); return (int)$this->db->table('tbl_medidor_reserva')->get()->getRow()->id_rme; }
    private function deliver(): int { $id=$this->reserve(); $this->pickupLegacyReservation(1,$id); return $id; }
    private function reservation(int $id): array { return $this->db->table('tbl_medidor_reserva')->where('id_rme',$id)->get()->getRowArray(); }
    public function testRolesCsrfSessionAndVerb(): void
    {
        $before=$this->meter();
        foreach (['os/1/medidores/reservar'=>[],'os/1/medidores/1/entregar'=>[],'os/1/medidores/1/receber'=>[],'os/1/medidores/1/ocorrencia'=>[1,3],'medidores/1/ocorrencia'=>[1]] as $path=>$allowed) {
            foreach ([null,1,2,3] as $actor) {
                $response=$this->requestWithDeletionPassword($actor,'POST',$path);
                if ($actor===null) { $response->assertRedirectTo(site_url('login')); }
                elseif (!in_array($actor,$allowed,true)) { $response->assertStatus(403); }
                else { $this->assertContains($response->response()->getStatusCode(),[404,422]); }
            }
            $this->requestWithDeletionPassword(1,'POST',$path,[],false)->assertStatus(403);
            $this->requestWithDeletionPassword(1,'POST',$path,[],true,time()-7201)->assertRedirectTo(site_url('login'));
            $this->requestWithDeletionPassword(1,'GET',$path)->assertStatus(404);
        }
        $this->assertSame($before,$this->meter());
        $this->assertSame(0,$this->db->table('tbl_medidor_reserva')->countAllResults());
    }
    public function testExclusiveReservationCancellationAndAudit(): void
    {
        $this->legacyMeterReservation(1,1);
        $this->assertSame('reservado',$this->meter()['status_med']);
        $this->assertSame('deposito',$this->meter()['localizacao_med']);
        $this->assertNull($this->meter()['eletricista_posse_med']);
        $this->assertSame('1',(string)$this->reservation(1)['usuario_rme']);
        foreach (['1','2'] as $meter) { $this->requestAs(1,'POST','os/1/medidores/reservar',['medidor'=>$meter])->assertStatus(403); }
        $this->db->table('tbl_os')->where('id_oss',3)->update(['status_oss'=>'atribuida','tipo_oss'=>'nova_ligacao','eletricista_oss'=>2]);
        $this->requestAs(1,'POST','os/3/medidores/reservar',['medidor'=>'1'])->assertStatus(403);
        $this->requestAs(2,'POST','os/1/cancelar',['motivo'=>'Cancelada'])->assertStatus(303);
        $this->assertSame('disponivel',$this->meter()['status_med']);
        $this->assertSame('liberada',$this->reservation(1)['status_rme']);
        $this->assertSame(0,$this->db->table('tbl_estoque_mov')->where('ordem_servico_emv',1)->countAllResults());
        $this->legacyMeterReservation(3,1);
        $this->assertSame('liberada',$this->reservation(1)['status_rme']);
        $this->assertSame(2,$this->db->table('tbl_medidor_reserva')->countAllResults());
    }
    public function testChecklistPhysicalDeliveryAndReturnAfterCancellation(): void
    {
        $r=$this->reserve();
        (new ChecklistInicioService($this->db))->answer(1,1,['1'=>'0'],[],3);
        $this->requestAs(3,'POST',"meus-medidores/1/retirar")->assertStatus(422);
        $id=$this->db->table('tbl_checklist_avaliacao')->orderBy('id_cav','DESC')->get()->getRow()->id_cav;
        $this->requestAs(1,'POST',"os/1/avaliacoes/$id/liberar-inicio",['justificativa'=>'Verificado pelo gestor'])->assertStatus(303);
        $this->requestAs(3,'POST',"meus-medidores/1/retirar",['eletricista_rme'=>'2'])->assertStatus(303);
        $this->assertSame('em_transito',$this->meter()['status_med']);
        $this->assertSame('1',(string)$this->meter()['eletricista_posse_med']);
        $this->requestAs(3,'POST',"meus-medidores/1/retirar")->assertStatus(422);
        $this->requestAs(2,'GET','os/1')->assertStatus(200);
        $this->requestAs(2,'POST','os/1/cancelar',['motivo'=>'Cancelada'])->assertStatus(303);
        $this->assertSame('devolucao_pendente',$this->reservation($r)['status_rme']);
        $this->assertSame('em_transito',$this->meter()['status_med']);
        $this->requestAs(3,'POST',"meus-medidores/1/devolver",['condicao'=>'disponivel'])->assertStatus(303);
        $this->assertSame('devolvida',$this->reservation($r)['status_rme']);
        $this->assertSame('disponivel',$this->meter()['status_med']);
        $this->assertNull($this->meter()['eletricista_posse_med']);
        $this->requestAs(3,'POST',"meus-medidores/1/devolver",['condicao'=>'disponivel'])->assertStatus(403);
        $movements=$this->db->table('tbl_estoque_mov')->where('ordem_servico_emv',1)->get()->getResultArray();
        $this->assertCount(2,$movements);
        foreach ($movements as $movement) { $this->assertSame('3',(string)$movement['usuario_emv']); $this->assertSame('1',(string)$movement['quantidade_emv']); }
    }
    public function testDamageRequiresPhysicalReceivingBeforeDown(): void
    {
        $r=$this->deliver();
        $this->requestAs(3,'POST','os/1/medidores/1/ocorrencia',['tipo_ocorrencia'=>'dano','justificativa_medidor'=>'Danificado'])->assertStatus(303);
        $this->assertSame('defeito',$this->meter()['status_med']);
        $this->assertSame('viatura',$this->meter()['localizacao_med']);
        $this->requestAs(1,'POST','os/1/medidores/1/ocorrencia',['tipo_ocorrencia'=>'baixa','justificativa_medidor'=>'Sem conserto'])->assertStatus(422);
        $this->requestAs(3,'POST',"meus-medidores/1/devolver",['condicao'=>'disponivel'])->assertStatus(422);
        $this->requestAs(3,'POST',"meus-medidores/1/devolver",['condicao'=>'defeito'])->assertStatus(303);
        $this->requestAs(1,'POST','medidores/1/ocorrencia',['tipo_ocorrencia'=>'baixa','justificativa_medidor'=>'Sem conserto'])->assertStatus(303);
        $this->assertSame('baixado',$this->meter()['status_med']);
        $this->assertNull($this->meter()['data_exclusao_med']);
        $this->requestAs(1,'POST','medidores/1/atualizar',['numero_med'=>$this->meter()['numero_med'],'status_med'=>'disponivel'])->assertStatus(422);
        $this->assertSame('baixado',$this->meter()['status_med']);
    }
    public function testLossCustodyAuthorizationAndHistoricOwner(): void
    {
        $r=$this->deliver();
        $input=['tipo_ocorrencia'=>'roubo','justificativa_medidor'=>'Roubo registrado','usuario_ome'=>'1'];
        $this->requestAs(4,'POST','os/1/medidores/1/ocorrencia',$input)->assertStatus(403);
        $this->requestAs(3,'POST','os/1/medidores/1/ocorrencia',$input)->assertStatus(303);
        $this->assertSame('perdido',$this->meter()['status_med']);
        $this->assertSame('viatura',$this->meter()['localizacao_med']);
        $this->assertSame('1',(string)$this->meter()['eletricista_posse_med']);
        $this->assertSame('perdida',$this->reservation($r)['status_rme']);
        $occ=$this->db->table('tbl_medidor_ocorrencia')->get()->getRowArray();
        $this->assertSame('3',(string)$occ['usuario_ome']);
        $this->requestAs(3,'POST','os/1/medidores/1/ocorrencia',['tipo_ocorrencia'=>'baixa','justificativa_medidor'=>'Baixa'])->assertStatus(422);
        $this->requestAs(2,'POST','os/1/cancelar',['motivo'=>'Cancelada'])->assertStatus(303);
        $this->db->table('tbl_os')->where('id_oss',2)->update(['status_oss'=>'encerrada']);
        $this->db->table('tbl_medidor')->where('id_med !=',1)->update(['eletricista_posse_med'=>null]);
        $this->requestWithDeletionPassword(1,'POST','usuarios/3/excluir')->assertStatus(422);
        $this->requestAs(1,'POST','os/1/medidores/1/ocorrencia',['tipo_ocorrencia'=>'baixa','justificativa_medidor'=>'Baixa administrativa'])->assertStatus(303);
        $this->assertSame('1',(string)$this->meter()['eletricista_posse_med']);
        $this->requestWithDeletionPassword(1,'POST','usuarios/3/excluir')->assertStatus(303);
    }
    public function testBoundariesValidationAndEscaping(): void
    {
        foreach (['0','abc'] as $id) { $this->requestAs(1,'POST','os/1/medidores/reservar',['medidor'=>$id])->assertStatus(403); }
        $this->requestAs(1,'POST','os/1/medidores/reservar',['medidor'=>'999'])->assertStatus(403);
        $r=$this->deliver();
        $this->requestAs(3,'POST','meus-medidores/999/devolver',['condicao'=>'disponivel'])->assertStatus(404);
        $this->requestAs(3,'POST','os/1/medidores/2/ocorrencia',['tipo_ocorrencia'=>'dano','justificativa_medidor'=>'Dano'])->assertStatus(404);
        foreach ([['tipo_ocorrencia'=>'reparo'],['justificativa_medidor'=>''],['justificativa_medidor'=>str_repeat('a',1001)],['tipo_ocorrencia'=>['dano']]] as $input) { $this->requestAs(3,'POST','os/1/medidores/1/ocorrencia',$input+['tipo_ocorrencia'=>'dano','justificativa_medidor'=>'Dano'])->assertStatus(422); }
        $this->requestAs(3,'POST','os/1/medidores/1/ocorrencia',['tipo_ocorrencia'=>'perda','justificativa_medidor'=>'<script>alert(1)</script>'])->assertStatus(303);
        $html=$this->requestAs(1,'GET','os/1'); $html->assertStatus(200); $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;',$html->getBody()); $this->assertStringNotContainsString('<script>alert(1)</script>',$html->getBody());
        $this->requestAs(2,'GET','medidores/1')->assertStatus(200);
    }
    public function testAuditFailuresRollbackStateReservationAndMovements(): void
    {
        $this->db->query("ALTER TABLE tbl_os_historico ADD CONSTRAINT test_meter_history CHECK (evento_osh <> 'reserva_medidor')");
        $this->requestAs(1,'POST','os/1/medidores/reservar',['medidor'=>'1'])->assertStatus(403);
        $this->assertSame('disponivel',$this->meter()['status_med']);
        $this->assertSame(0,$this->db->table('tbl_medidor_reserva')->countAllResults());
        $this->db->query('ALTER TABLE tbl_os_historico DROP CHECK test_meter_history');
        $r=$this->reserve();
        $this->db->query("ALTER TABLE tbl_os_historico ADD CONSTRAINT test_meter_history CHECK (evento_osh <> 'cancelamento')");
        $this->requestAs(2,'POST','os/1/cancelar',['motivo'=>'Cancelar'])->assertStatus(422);
        $this->assertSame('reservado',$this->meter()['status_med']);
        $this->assertSame('reservada',$this->reservation($r)['status_rme']);
        $this->db->query('ALTER TABLE tbl_os_historico DROP CHECK test_meter_history');
        $this->db->query("ALTER TABLE tbl_estoque_mov ADD CONSTRAINT test_meter_movement CHECK (observacao_emv NOT LIKE 'Retirada direta%')");
        $this->requestAs(3,'POST',"meus-medidores/1/retirar")->assertStatus(422);
        $this->assertSame('reservado',$this->meter()['status_med']);
        $this->assertSame('reservada',$this->reservation($r)['status_rme']);
        $this->db->query('ALTER TABLE tbl_estoque_mov DROP CHECK test_meter_movement');
        $this->pickupLegacyReservation(1,$r);
        $this->db->query("ALTER TABLE tbl_os_historico ADD CONSTRAINT test_meter_history CHECK (evento_osh <> 'ocorrencia_medidor')");
        $this->requestAs(3,'POST','os/1/medidores/1/ocorrencia',['tipo_ocorrencia'=>'perda','justificativa_medidor'=>'Perda'])->assertStatus(422);
        $this->assertSame('em_transito',$this->meter()['status_med']);
        $this->assertSame('entregue',$this->reservation($r)['status_rme']);
        $this->assertSame(0,$this->db->table('tbl_medidor_ocorrencia')->countAllResults());
        $this->db->query('ALTER TABLE tbl_os_historico DROP CHECK test_meter_history');
        $this->db->query("ALTER TABLE tbl_os_historico ADD CONSTRAINT test_meter_history CHECK (evento_osh <> 'cancelamento')");
        $this->requestAs(2,'POST','os/1/cancelar',['motivo'=>'Cancelar'])->assertStatus(422);
        $this->assertSame('entregue',$this->reservation($r)['status_rme']);
        $this->assertSame('atribuida',$this->db->table('tbl_os')->where('id_oss',1)->get()->getRow()->status_oss);
        $this->db->query('ALTER TABLE tbl_os_historico DROP CHECK test_meter_history');
    }
    public function testReceivingFailureRollsBackAndReturnedMeterCanBeReservedAgain(): void
    {
        $r=$this->deliver();
        $this->db->query("ALTER TABLE tbl_estoque_mov ADD CONSTRAINT test_meter_return CHECK (observacao_emv NOT LIKE 'Devolução direta:%')");
        $this->requestAs(3,'POST',"meus-medidores/1/devolver",['condicao'=>'disponivel'])->assertStatus(422);
        $this->assertSame('em_transito',$this->meter()['status_med']);
        $this->assertSame('entregue',$this->reservation($r)['status_rme']);
        $this->assertSame(1,$this->db->table('tbl_estoque_mov')->where('ordem_servico_emv',1)->countAllResults());
        $this->db->query('ALTER TABLE tbl_estoque_mov DROP CHECK test_meter_return');
        $this->requestAs(3,'POST',"meus-medidores/1/devolver",['condicao'=>'disponivel'])->assertStatus(303);
        $this->legacyMeterReservation(1,1);
        $this->assertSame('devolvida',$this->reservation($r)['status_rme']);
        $this->assertSame(2,$this->db->table('tbl_medidor_reserva')->countAllResults());
    }
    public function testInactiveOwnerAndAdministrativeBypassRejected(): void
    {
        $r=$this->reserve();
        $this->requestAs(1,'POST','medidores/1/ocorrencia',['tipo_ocorrencia'=>'dano','justificativa_medidor'=>'Dano'])->assertStatus(422);
        $this->requestWithDeletionPassword(1,'POST','medidores/1/excluir')->assertStatus(422);
        $this->requestAs(1,'POST','os/1/medidores/1/ocorrencia',['tipo_ocorrencia'=>'perda','justificativa_medidor'=>'Perda'])->assertStatus(422);
        $this->db->table('tbl_usuario')->where('id_usu',3)->update(['ativo_usu'=>0]);
        $this->requestAs(3,'POST',"meus-medidores/1/retirar")->assertRedirectTo(site_url('login'));
        $this->assertSame('reservado',$this->meter()['status_med']);
        $this->assertSame('reservada',$this->reservation($r)['status_rme']);
        $this->expectException(\App\Exceptions\FormException::class);
        (new MedidorOsService($this->db))->reserve(1,'2',2);
    }

}
