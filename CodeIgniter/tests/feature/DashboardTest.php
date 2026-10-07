<?php

namespace Tests\Feature;

use App\Models\IndicadoresModel;
use App\Services\IndicadoresService;
use Tests\Support\AppTestCase;

final class DashboardTest extends AppTestCase
{
    private function service(): IndicadoresService { return new IndicadoresService(new IndicadoresModel($this->db)); }
    private function actor(int $id = 1): array { return ['papel_usu' => $id === 3 ? 'eletricista' : 'gestor', 'id_ele' => 1, 'display_name' => 'João']; }
    private function period(): array { return ['data_inicio' => '2026-01-01', 'data_fim' => '2026-12-31']; }
    private function metrics(array $filters = [], int $actor = 1): array
    {
        $s = $this->service(); return $s->dashboard($s->context($this->actor($actor), $filters + $this->period()));
    }
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('tbl_os')->update(['data_abertura_oss' => '2026-10-06 12:00:00']);
    }
    public function testTotalsReconcileWithoutJoiningOperationsAndIncludeUnassigned(): void
    {
        $this->db->table('tbl_os')->where('id_oss', 1)->update(['eletricista_oss' => null]);
        $this->db->table('tbl_os_medidor')->insert(['ordem_servico_osm' => 1, 'medidor_osm' => 1, 'tipo_osm' => 'instalado']);
        $this->db->table('tbl_os_medidor')->insert(['ordem_servico_osm' => 1, 'medidor_osm' => 1, 'tipo_osm' => 'retirado']);
        $m = $this->metrics();
        $this->assertSame(3, $m['total']); $this->assertSame(3, array_sum($m['states']));
        $this->assertSame(3, array_sum($m['types'])); $this->assertSame(3, array_sum(array_column($m['owners'], 'total')));
        $this->assertSame(0, $m['states']['aberta']); $this->assertSame(0, $m['states']['cancelada']);
        $this->assertCount(1, array_filter($m['owners'], fn($row) => $row['eletricista_oss'] === null));
        $this->assertSame(1, $this->metrics(['eletricista' => 'sem_atribuicao'])['total']);
    }
    public function testInclusiveDayLimitsAndCombinedFilters(): void
    {
        foreach ([1 => '2026-10-01 00:00:00', 2 => '2026-10-31 23:59:59', 3 => '2026-11-01 00:00:00'] as $id => $date) {
            $this->db->table('tbl_os')->where('id_oss', $id)->update(['data_abertura_oss' => $date]);
        }
        $s = $this->service();
        $c = $s->context($this->actor(), ['data_inicio'=>'2026-10-01', 'data_fim'=>'2026-10-31']);
        $this->assertSame(2, $s->dashboard($c)['total']);
        $this->assertSame(1, $this->metrics(['status_oss'=>'atribuida', 'cliente'=>'1', 'eletricista'=>'1'])['total']);
        $this->assertSame(0, $this->metrics(['status_oss'=>'encerrada', 'cliente'=>'1'])['total']);
        $this->assertSame(0, $this->metrics(['cliente'=>'2'])['total']);
    }
    public function testDefaultsAndEmptyState(): void
    {
        $s=$this->service(); $c=$s->context($this->actor(), []);
        $now=new \DateTimeImmutable('now', new \DateTimeZone('America/Fortaleza'));
        $this->assertSame($now->format('Y-m-01'), $c['filters']['data_inicio']); $this->assertSame($now->format('Y-m-d'), $c['filters']['data_fim']);
        $c=$s->context($this->actor(), ['data_inicio'=>'2020-01-01','data_fim'=>'2020-01-31']);
        $m=$s->dashboard($c); $this->assertSame(0,$m['total']); $this->assertSame([], $m['owners']); $this->assertSame(0,array_sum($m['states']));
    }
    public function testRealRoutesRolesSessionAndReadOnly(): void
    {
        $before=$this->db->table('tbl_os')->get()->getResultArray();
        $this->requestAs(null,'GET','inicio')->assertRedirectTo(site_url('login'));
        foreach ([1,2] as $id) {
            $r=$this->requestAs($id,'GET','inicio?data_inicio=2026-01-01&data_fim=2026-12-31');
            $r->assertStatus(200); $r->assertSee('Total de OS no período');
            $this->assertStringContainsString('no-store',$r->response()->getHeaderLine('Cache-Control'));
        }
        $this->requestAs(1,'POST','inicio',[],false)->assertStatus(404);
        $this->requestAs(1,'GET','inicio',[],true,time()-7201)->assertRedirectTo(site_url('login'));
        $this->db->table('tbl_usuario')->where('id_usu',2)->update(['ativo_usu'=>0]);
        $this->requestAs(2,'GET','inicio')->assertRedirectTo(site_url('login'));
        $this->assertSame($before,$this->db->table('tbl_os')->get()->getResultArray());
    }
    public function testElectricianScopeCannotBeExpandedThroughFiltersOrChoices(): void
    {
        $m=$this->metrics([],3); $this->assertSame(2,$m['total']); $this->assertCount(1,$m['owners']);
        $c=$this->service()->context($this->actor(3),[]); $this->assertArrayNotHasKey('2',$c['owners']); $this->assertArrayNotHasKey('3',$c['clients']);
        foreach (['eletricista=2','eletricista=sem_atribuicao','cliente=3','cliente=9999'] as $q) { $this->requestAs(3,'GET','inicio?'.$q)->assertRedirectTo(site_url('os')); }
        $r=$this->requestAs(3,'GET','inicio?data_inicio=2026-01-01&data_fim=2026-12-31');
        $r->assertRedirectTo(site_url('os'));
        $this->requestAs(3,'GET','inicio?eletricista=1')->assertRedirectTo(site_url('os'));
    }
    public function testMalformedFiltersDoNotProduceIndicators(): void
    {
        foreach (['data_inicio=2026-02-30','data_inicio=2026-12-01&data_fim=2026-01-01','data_fim=','status_oss=forjado','cliente=-1','cliente=9999','eletricista=1e2','data_inicio[]=2026-01-01','eletricista[]=2','cliente[]=1','status_oss[]=aberta','data_fim=9999-12-31'] as $q) {
            $r=$this->requestAs(1,'GET','inicio?'.$q); $r->assertStatus(422); $r->assertSee('Os indicadores não foram consultados.'); $r->assertDontSee('Total de OS no período');
        }
    }
    public function testSoftDeleteAndHistoricalNamesAreHandledWithoutDataLoss(): void
    {
        $this->db->table('tbl_os')->where('id_oss',1)->update(['data_exclusao_oss'=>date('Y-m-d H:i:s')]);
        $this->db->table('tbl_cliente')->where('id_cli',3)->update(['data_exclusao_cli'=>date('Y-m-d H:i:s'), 'nome_cli'=>'<script>cliente</script>']);
        $this->db->table('tbl_usuario')->where('id_usu',4)->update(['ativo_usu'=>0, 'data_exclusao_usu'=>date('Y-m-d H:i:s'), 'nome_completo_usu'=>'<script>nome</script>']);
        $this->assertSame(2,$this->metrics()['total']);
        $r=$this->requestAs(1,'GET','inicio?data_inicio=2026-01-01&data_fim=2026-12-31');
        $this->assertStringContainsString('&lt;script&gt;nome&lt;/script&gt;',$r->getBody()); $this->assertStringNotContainsString('<script>nome</script>',$r->getBody());
        $this->assertStringContainsString('&lt;script&gt;cliente&lt;/script&gt;',$r->getBody());
    }
}
