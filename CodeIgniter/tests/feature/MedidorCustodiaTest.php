<?php
namespace Tests\Feature;

use App\Exceptions\FormException;
use App\Services\MedidorCustodiaService;
use App\Services\MedidorOsService;
use App\Services\MedidorService;
use Tests\Support\AppTestCase;

final class MedidorCustodiaTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('tbl_medidor')->insert(['id_med'=>7,'numero_med'=>'MED-OUTRO','modelo_med'=>'Modelo','fabricante_med'=>'Fabricante','eletricista_posse_med'=>2,'status_med'=>'em_transito','localizacao_med'=>'viatura']);
        foreach ([1=>'inicio',2=>'fechamento'] as $id=>$stage) {
            $this->db->table('tbl_checklist')->insert(['id_chk'=>$id,'nome_chk'=>'Modelo '.$stage,'tipo_os_chk'=>'nova_ligacao','etapa_chk'=>$stage,'ativo_chk'=>1,'usuario_chk'=>1]);
            $this->db->table('tbl_checklist_item')->insert(['id_chi'=>$id,'checklist_chi'=>$id,'pergunta_chi'=>'Seguro?','nivel_chi'=>'bloqueante','ordem_chi'=>1]);
        }
        (new \App\Services\ChecklistInicioService($this->db))->answer(1,1,['1'=>'1'],[],3);
    }

    private function approveFinal(): void { (new \App\Services\ChecklistFechamentoService($this->db))->answer(1,2,['2'=>'1'],[],3); }

    private function meter(int $id=1): array { return $this->db->table('tbl_medidor')->where('id_med',$id)->get()->getRowArray(); }
    private function movements(int $id=1): array { return $this->db->table('tbl_estoque_mov')->where('medidor_emv',$id)->orderBy('id_emv')->get()->getResultArray(); }

    public function testDirectPickupAndReturnWithoutOsAndAudit(): void
    {
        $before=count($this->movements());
        $this->requestAs(3,'POST','meus-medidores/1/retirar',['eletricista_posse_med'=>'2','usuario_emv'=>'1'])->assertStatus(303);
        $this->assertSame('em_transito',$this->meter()['status_med']);
        $this->assertSame('viatura',$this->meter()['localizacao_med']);
        $this->assertSame(1,(int)$this->meter()['eletricista_posse_med']);
        $this->requestAs(3,'POST','meus-medidores/1/retirar')->assertStatus(422);
        $this->requestAs(3,'POST','meus-medidores/1/devolver',['condicao'=>'disponivel'])->assertStatus(303);
        $this->assertSame('deposito',$this->meter()['localizacao_med']);
        $this->assertNull($this->meter()['eletricista_posse_med']);
        $this->requestAs(3,'POST','meus-medidores/1/devolver',['condicao'=>'disponivel'])->assertStatus(403);
        $moves=array_slice($this->movements(),$before);
        $this->assertCount(2,$moves);
        foreach ($moves as $move) {
            $this->assertSame(3,(int)$move['usuario_emv']); $this->assertSame(1,(int)$move['eletricista_emv']);
            $this->assertSame(1,(int)$move['quantidade_emv']); $this->assertNull($move['ordem_servico_emv']); $this->assertNotEmpty($move['data_emv']);
        }
    }

    public function testRouteRolesCsrfExpiredInactiveAndOwnScope(): void
    {
        $before=$this->meter(); $moves=$this->movements();
        foreach ([null,1,2] as $actor) {
            foreach ([['GET','meus-medidores'],['POST','meus-medidores/1/retirar'],['POST','meus-medidores/3/devolver']] as [$verb,$path]) {
                $response=$this->requestAs($actor,$verb,$path,['condicao'=>'disponivel']);
                if ($actor===null) { $response->assertRedirectTo(site_url('login')); } else { $response->assertStatus(403); }
            }
        }
        $this->requestAs(3,'POST','meus-medidores/1/retirar',[],false)->assertStatus(403);
        $this->requestAs(3,'POST','meus-medidores/1/retirar',[],true,time()-7201)->assertRedirectTo(site_url('login'));
        $this->requestAs(3,'POST','meus-medidores/7/retirar')->assertStatus(403);
        $this->requestAs(3,'POST','meus-medidores/7/devolver',['condicao'=>'disponivel'])->assertStatus(403);
        $this->requestAs(3,'GET','meus-medidores/1/retirar')->assertStatus(404);
        $this->requestAs(3,'GET','medidores')->assertStatus(403);
        $this->requestAs(3,'POST','meus-medidores/999/retirar')->assertStatus(404);
        $this->db->table('tbl_usuario')->where('id_usu',3)->update(['ativo_usu'=>0]);
        $this->requestAs(3,'POST','meus-medidores/1/retirar')->assertRedirectTo(site_url('login'));
        $this->assertSame($before,$this->meter()); $this->assertSame($moves,$this->movements());
    }

    public function testListExcludesOtherCustodyDeletedAndPendingLegacyEvidence(): void
    {
        $response=$this->requestAs(3,'GET','meus-medidores');
        $response->assertSee('MED-SP-2001','tbody'); $response->assertDontSee('MED-SP-3001','tbody');
        $this->db->table('tbl_medidor')->where('id_med',2)->update(['data_exclusao_med'=>'2026-01-01 00:00:00']);
        $this->db->table('tbl_estoque_mov')->insert(['medidor_emv'=>1,'ordem_servico_emv'=>1,'usuario_emv'=>1,'tipo_emv'=>'transferencia','motivo_emv'=>'ajuste','origem_emv'=>'galpao','destino_emv'=>'eletricista','quantidade_emv'=>1,'data_emv'=>date('Y-m-d H:i:s')]);
        $response=$this->requestAs(3,'GET','meus-medidores');
        $response->assertDontSee('MED-SP-1001','tbody'); $response->assertDontSee('MED-SP-1002','tbody');
        $before=$this->meter();
        $this->requestAs(3,'POST','meus-medidores/1/retirar')->assertStatus(422);
        $this->assertSame($before,$this->meter());
        $this->requestAs(3,'GET','meus-medidores?q=SEM-SERIE')->assertSee('Você não possui medidores nesta busca.');
        $this->requestAs(3,'GET','meus-medidores?q[]=x')->assertStatus(422);
    }

    public function testConditionAndDamagedStateCannotBecomeAvailable(): void
    {
        $this->requestAs(3,'POST','meus-medidores/1/retirar')->assertStatus(303);
        foreach (['', 'instalado', ['defeito']] as $condition) { $this->requestAs(3,'POST','meus-medidores/1/devolver',['condicao'=>$condition])->assertStatus(422); }
        $this->db->table('tbl_medidor')->where('id_med',1)->update(['status_med'=>'defeito']);
        $this->requestAs(3,'POST','meus-medidores/1/devolver',['condicao'=>'disponivel'])->assertStatus(422);
        $this->requestAs(3,'POST','meus-medidores/1/devolver',['condicao'=>'defeito'])->assertStatus(303);
        $this->assertSame('defeito',$this->meter()['status_med']);
        $this->requestAs(3,'POST','meus-medidores/1/retirar')->assertStatus(422);
    }

    public function testPickupStartApplyAndCloseNeedsNoManagerDelivery(): void
    {
        $this->requestAs(3,'POST','meus-medidores/1/retirar')->assertStatus(303);
        $this->requestAs(3,'GET','os/1')->assertSee('Medidor em minha posse');
        $this->requestAs(3,'POST','os/1/iniciar-atendimento',['medidor'=>'1'])->assertStatus(303);
        $reservation=$this->db->table('tbl_medidor_reserva')->where('ordem_servico_rme',1)->get()->getRowArray();
        $this->assertSame('entregue',$reservation['status_rme']); $this->assertSame(3,(int)$reservation['usuario_rme']);
        $this->requestAs(3,'POST','os/1/medidores/'.$reservation['id_rme'].'/aplicar')->assertStatus(303);
        $this->approveFinal();
        $this->requestAs(3,'POST','os/1/encerrar',['resultado_oss'=>'executado','observacoes_finais_oss'=>'Ligação executada.','corte_confirmado_oss'=>'0','leitura_final_oss'=>''])->assertStatus(303);
        $this->assertSame('instalado',$this->meter()['status_med']);
        $this->assertSame(1,$this->db->table('tbl_instalacao_atual')->where('medidor_ins',1)->countAllResults());
    }

    public function testStartSelectionOwnScopeAndBindingRollback(): void
    {
        foreach (['7','1','0','abc',['1']] as $id) {
            $this->requestAs(3,'POST','os/1/iniciar-atendimento',['medidor'=>$id])->assertStatus(422);
        }
        $this->assertSame(0,$this->db->table('tbl_medidor_reserva')->countAllResults());
        $this->db->query("ALTER TABLE tbl_os_historico ADD CONSTRAINT fail_start_binding CHECK (evento_osh <> 'inicio_atendimento')");
        $this->requestAs(3,'POST','os/1/iniciar-atendimento',['medidor'=>'3'])->assertStatus(422);
        $this->assertSame(0,$this->db->table('tbl_medidor_reserva')->countAllResults());
        $this->assertSame(0,$this->db->table('tbl_os_historico')->where('evento_osh','custodia_medidor')->countAllResults());
        $this->assertSame('atribuida',$this->db->table('tbl_os')->where('id_oss',1)->get()->getRow()->status_oss);
        $this->db->query('ALTER TABLE tbl_os_historico DROP CHECK fail_start_binding');
    }

    public function testReturnLinkedMeterSettlesReservationAndAllowsPartialClose(): void
    {
        $this->requestAs(3,'POST','os/1/iniciar-atendimento',['medidor'=>'3'])->assertStatus(303);
        $this->approveFinal();
        $this->requestAs(3,'POST','os/1/encerrar',['resultado_oss'=>'parcial','observacoes_finais_oss'=>'Sem condições de ligação.'])->assertStatus(422);
        $this->requestAs(3,'POST','meus-medidores/3/devolver',['condicao'=>'disponivel'])->assertStatus(303);
        $this->assertSame('devolvida',$this->db->table('tbl_medidor_reserva')->where('medidor_rme',3)->get()->getRow()->status_rme);
        $this->requestAs(3,'POST','os/1/encerrar',['resultado_oss'=>'parcial','observacoes_finais_oss'=>'Sem condições de ligação.'])->assertStatus(303);
        $this->assertSame(1,$this->db->table('tbl_os_historico')->where('evento_osh','devolucao_medidor')->countAllResults());
    }

    public function testLegacyReservationPickupCancellationAndReturn(): void
    {
        $this->db->table('tbl_medidor_reserva')->insert(['medidor_rme'=>1,'ordem_servico_rme'=>1,'eletricista_rme'=>1,'usuario_rme'=>1]);
        $this->db->table('tbl_medidor')->where('id_med',1)->update(['status_med'=>'reservado']);
        $this->requestAs(4,'POST','meus-medidores/1/retirar')->assertStatus(403);
        $this->requestAs(3,'POST','meus-medidores/1/retirar')->assertStatus(303);
        $this->requestAs(2,'POST','os/1/cancelar',['motivo'=>'Cancelamento'])->assertStatus(303);
        $this->assertSame('devolucao_pendente',$this->db->table('tbl_medidor_reserva')->get()->getRow()->status_rme);
        $this->requestAs(3,'POST','meus-medidores/1/devolver',['condicao'=>'disponivel'])->assertStatus(303);
        $this->assertSame('devolvida',$this->db->table('tbl_medidor_reserva')->get()->getRow()->status_rme);
    }

    public function testMovementFailureRollsBackPickupAndReturn(): void
    {
        $this->db->query("ALTER TABLE tbl_estoque_mov ADD CONSTRAINT fail_custody CHECK (observacao_emv NOT LIKE '%direta%')");
        try {
            foreach ([['retirar',1,[]],['devolver',3,['condicao'=>'defeito']]] as [$action,$id,$input]) {
                $before=$this->meter($id); $moves=$this->movements($id);
                $this->requestAs(3,'POST',"meus-medidores/$id/$action",$input)->assertStatus(422);
                $this->assertSame($before,$this->meter($id)); $this->assertSame($moves,$this->movements($id));
            }
        } finally { $this->db->query('ALTER TABLE tbl_estoque_mov DROP CHECK fail_custody'); }
    }

    public function testLegacyAdministrativeWritesAreDeniedIncludingServiceCalls(): void
    {
        $before=$this->meter(); $moves=$this->movements();
        foreach (['medidores/1/enviar','medidores/3/devolver','os/1/medidores/reservar','os/1/medidores/1/entregar','os/1/medidores/1/receber'] as $path) {
            foreach ([1,2,3] as $actor) { $this->requestAs($actor,'POST',$path,['destino'=>'1','medidor'=>'1','condicao'=>'disponivel'])->assertStatus(403); }
        }
        foreach ([fn()=>(new MedidorService($this->db))->send(1,'1',1),fn()=>(new MedidorService($this->db))->returnToDepot(3,'disponivel',1),fn()=>(new MedidorOsService($this->db))->reserve(1,'1',1),fn()=>(new MedidorOsService($this->db))->deliver(1,1,1),fn()=>(new MedidorOsService($this->db))->receive(1,1,'disponivel',1)] as $operation) {
            try { $operation(); $this->fail('Escrita administrativa aceita.'); } catch (FormException $e) { $this->assertArrayHasKey('operacao',$e->errors); }
        }
        $this->assertSame($before,$this->meter()); $this->assertSame($moves,$this->movements());
    }

    public function testNoJavascriptConfirmationPreservesSelectionAndDoesNotWriteUntilFinalPost(): void
    {
        $before=$this->meter();
        $this->requestAs(3,'POST','meus-medidores/1/retirar',['_confirmacao'=>'pendente'])->assertSee('Confirmar a retirada física');
        $this->assertSame($before,$this->meter());
        $this->requestAs(3,'POST','meus-medidores/1/retirar',['_confirmacao'=>'confirmada'])->assertStatus(303);
        $this->requestAs(3,'POST','os/1/iniciar-atendimento',['medidor'=>'1','_confirmacao'=>'pendente'])->assertSee('name="medidor" value="1"');
        $this->assertSame(0,$this->db->table('tbl_medidor_reserva')->countAllResults());
    }
}
