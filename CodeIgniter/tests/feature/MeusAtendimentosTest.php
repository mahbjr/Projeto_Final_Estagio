<?php
namespace Tests\Feature;

use App\Models\OrdemServicoModel;
use App\Services\MeusAtendimentosService;
use Tests\Support\AppTestCase;

final class MeusAtendimentosTest extends AppTestCase
{
    private function copy(array $changes): void
    {
        $row=$this->db->table('tbl_os')->where('id_oss',1)->get()->getRowArray(); unset($row['id_oss']);
        $this->db->table('tbl_os')->insert(array_replace($row,$changes));
    }
    public function testEntryMenuAndReportsUseCentralPolicy(): void
    {
        $before=$this->db->table('tbl_os')->get()->getResultArray();
        $this->requestAs(null,'GET','os')->assertRedirectTo(site_url('login'));
        foreach ([3,4] as $actor) {
            $this->requestAs($actor,'GET','inicio')->assertRedirectTo(site_url('os'));
            $r=$this->requestAs($actor,'GET','os');$r->assertOK();$r->assertSee('Meus atendimentos');$r->assertSee('Meus medidores');
            $r->assertDontSee('Visão geral');$r->assertDontSee('Relatórios');
            foreach (['relatorios/eletricistas','relatorios/estoque'] as $path) { $this->requestAs($actor,'GET',$path)->assertStatus(403); }
            $this->requestAs($actor,'GET','os',[],true,time()-7201)->assertRedirectTo(site_url('login'));
        }
        foreach ([1,2] as $actor) { $this->requestAs($actor,'GET','inicio')->assertOK();$this->requestAs($actor,'GET','relatorios/eletricistas')->assertOK(); }
        $this->db->table('tbl_usuario')->where('id_usu',3)->update(['ativo_usu'=>0]);
        $this->requestAs(3,'GET','os')->assertRedirectTo(site_url('login'));
        $this->assertSame($before,$this->db->table('tbl_os')->get()->getResultArray());
    }
    public function testDefaultCardsAndCountExcludeForeignAndDeleted(): void
    {
        $this->copy(['id_oss'=>10,'status_oss'=>'encerrada','unidade_consumidora_oss'=>'HISTORICO-10']);
        $this->copy(['id_oss'=>11,'status_oss'=>'cancelada','unidade_consumidora_oss'=>'HISTORICO-11']);
        $this->copy(['id_oss'=>12,'data_exclusao_oss'=>'2026-10-07 00:00:00','unidade_consumidora_oss'=>'EXCLUIDA-12']);
        $r=$this->requestAs(3,'GET','os?eletricista=2&cliente=3');$r->assertOK();
        $r->assertSee('2 atendimento(s) pendente(s)');$r->assertSee('os-card-1');$r->assertSee('os-card-2');
        foreach ([3,10,11,12] as $id) { $r->assertDontSee('os-card-'.$id.'"'); }
        $this->assertStringContainsString('no-store',$r->response()->getHeaderLine('Cache-Control'));
        $this->requestAs(3,'GET','os/3')->assertStatus(403);
    }
    public function testHistorySearchAndEmptyStatePreserveOwnScope(): void
    {
        $this->copy(['id_oss'=>10,'status_oss'=>'encerrada','unidade_consumidora_oss'=>'HISTORICO-10']);
        $this->requestAs(3,'GET','os?status_oss=encerrada&q=HISTORICO')->assertSee('os-card-10');
        $this->requestAs(3,'GET','os?status_oss=cancelada')->assertSee('Nenhum atendimento encontrado');
        $this->requestAs(3,'GET','os?q=UC-CE-300789&status_oss=todos')->assertDontSee('os-card-3');
        $this->db->table('tbl_os')->where('id_oss',1)->update(['descricao_oss'=>'<script>bad()</script>','endereco_oss'=>'<script>bad()</script>']);
        $r=$this->requestAs(3,'GET','os');$r->assertDontSee('<script>bad()</script>');$r->assertSee('&lt;script&gt;bad()&lt;/script&gt;');
    }
    public function testSortGroupsThenScheduleNullsAndId(): void
    {
        $this->db->table('tbl_os')->where('id_oss',1)->update(['status_oss'=>'atribuida','agendamento_oss'=>null]);
        $this->db->table('tbl_os')->where('id_oss',2)->update(['status_oss'=>'em_atendimento','agendamento_oss'=>null]);
        $this->copy(['id_oss'=>10,'status_oss'=>'atribuida','agendamento_oss'=>'2026-10-09 09:00:00']);
        $this->copy(['id_oss'=>11,'status_oss'=>'em_atendimento','agendamento_oss'=>'2026-10-10 09:00:00']);
        $this->copy(['id_oss'=>12,'status_oss'=>'atribuida','agendamento_oss'=>'2026-10-09 09:00:00']);
        $filters=(new MeusAtendimentosService())->filters([]);
        $rows=(new OrdemServicoModel($this->db))->forAttendance(1,$filters)->findAll();
        $this->assertSame([11,2,10,12,1],array_map('intval',array_column($rows,'id_oss')));
        $r=$this->requestAs(3,'GET','os');$html=$r->response()->getBody();
        $this->assertLessThan(strpos($html,'id="os-card-2"'),strpos($html,'id="os-card-11"'));
    }
    public function testPaginationRetainsFiltersAndCountIsIndependent(): void
    {
        for ($id=10;$id<30;$id++) { $this->copy(['id_oss'=>$id,'unidade_consumidora_oss'=>'PAGINADA-'.$id]); }
        $r=$this->requestAs(3,'GET','os?q=PAGINADA&status_oss=atribuida');$r->assertOK();
        $this->assertSame(15,substr_count($r->response()->getBody(),'<article class="attendance-card"'));
        $r->assertSee('22 atendimento(s) pendente(s)');$r->assertSee('q=PAGINADA');$r->assertSee('status_oss=atribuida');
        $r=$this->requestAs(3,'GET','os?q=PAGINADA&status_oss=atribuida&page=2');
        $this->assertSame(5,substr_count($r->response()->getBody(),'<article class="attendance-card"'));$r->assertSee('22 atendimento(s) pendente(s)');
    }
    public function testMalformedFiltersNeverExpandList(): void
    {
        foreach (['q[]=x','status_oss[]=atribuida','status_oss=inventado','page=0','page[]=2','page=1e2','q='.str_repeat('x',151)] as $query) {
            $r=$this->requestAs(3,'GET','os?'.$query);$r->assertStatus(422);$r->assertDontSee('os-card-1');$r->assertSee('Corrija os filtros');
        }
        $this->requestAs(3,'POST','inicio',[],false)->assertStatus(404);
        $before=$this->db->table('tbl_os')->countAllResults();$this->requestAs(3,'POST','os',[],false)->assertStatus(403);
        $this->assertSame($before,$this->db->table('tbl_os')->countAllResults());
    }
}
