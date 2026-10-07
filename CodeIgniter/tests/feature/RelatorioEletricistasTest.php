<?php

namespace Tests\Feature;

use App\Models\IndicadoresModel;
use App\Services\IndicadoresService;
use Tests\Support\AppTestCase;

final class RelatorioEletricistasTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('tbl_os')->update(['data_abertura_oss'=>'2026-10-06 12:00:00']);
    }
    private function report(array $filters = [], int $page = 1, bool $personal = false): array
    {
        $s = new IndicadoresService(new IndicadoresModel($this->db));
        $c = $s->context(['papel_usu'=>$personal ? 'eletricista' : 'gestor','id_ele'=>1,'display_name'=>'João'], $filters + ['data_inicio'=>'2026-01-01','data_fim'=>'2026-12-31']);
        return $s->report($c,$page);
    }
    private function close(int $id, ?string $start, ?string $end, string $result = 'executado'): void
    {
        $this->db->table('tbl_os')->where('id_oss',$id)->update(['status_oss'=>'encerrada','inicio_atendimento_oss'=>$start,'data_fechamento_oss'=>$end,'resultado_oss'=>$result]);
    }
    private function copyOrder(array $overrides): void
    {
        $row=$this->db->table('tbl_os')->where('id_oss',1)->get()->getRowArray();unset($row['id_oss']);
        $this->db->table('tbl_os')->insert(array_replace($row,$overrides));
    }
    public function testAverageUsesAllSamplesInsteadOfAverageOfAverages(): void
    {
        $this->close(1,'2026-10-06 08:00:00','2026-10-06 09:00:00');
        $this->close(2,'2026-10-06 10:00:00','2026-10-06 11:00:00','parcial');
        $this->close(3,'2026-10-06 08:00:00','2026-10-06 17:00:00','nao_executado');
        $r=$this->report();$s=$r['summary'];
        $this->assertSame(3,$s['total']);$this->assertSame(3,$s['atendidas']);$this->assertSame(3,$s['amostras']);
        $this->assertSame(13200,$s['media_segundos']);$this->assertSame(0,$s['fora_media']);
        $groups=array_column($r['ownersSummary'],null,'eletricista_oss');
        $this->assertSame(3600,$groups[1]['media_segundos']);$this->assertSame(32400,$groups[2]['media_segundos']);
        $this->assertSame('3h 40min 00s',attendance_duration($s['media_segundos']));
    }
    public function testZeroLongMissingInvertedAndUnfinishedDurations(): void
    {
        $this->close(1,'2026-10-06 08:00:00','2026-10-06 08:00:00');
        $this->close(2,'2026-10-06 08:00:00','2026-10-07 10:00:01');
        $this->close(3,null,'2026-10-06 17:00:00');
        $this->copyOrder(['inicio_atendimento_oss'=>'2026-10-06 09:00:00','data_fechamento_oss'=>'2026-10-06 08:00:00']);
        $this->copyOrder(['inicio_atendimento_oss'=>'2026-10-06 09:00:00','data_fechamento_oss'=>null]);
        $this->copyOrder(['status_oss'=>'em_atendimento','inicio_atendimento_oss'=>'2026-10-06 09:00:00','data_fechamento_oss'=>'2026-10-06 12:00:00']);
        $s=$this->report()['summary'];$this->assertSame(6,$s['total']);$this->assertSame(5,$s['atendidas']);
        $this->assertSame(2,$s['amostras']);$this->assertSame(3,$s['fora_media']);$this->assertSame(46801,$s['media_segundos']);
        $this->assertSame('26h 00min 01s',attendance_duration('93601'));$this->assertSame('0h 00min 00s',attendance_duration(0));
        $this->assertSame('Sem dados',attendance_duration(null));
        $this->assertNull($this->report(['status_oss'=>'em_atendimento'])['summary']['media_segundos']);
    }
    public function testMovementsCountHistoricalOperationsWithoutDuplicatingOrders(): void
    {
        $this->db->table('tbl_os_medidor')->where('id_osm >',0)->delete();
        foreach ([[1,1,'instalado'],[1,1,'retirado'],[1,2,'retirado'],[3,1,'instalado']] as [$order,$meter,$type]) {
            $this->db->table('tbl_os_medidor')->insert(['ordem_servico_osm'=>$order,'medidor_osm'=>$meter,'tipo_osm'=>$type]);
        }
        $this->db->table('tbl_os_medidor')->insert(['ordem_servico_osm'=>1,'medidor_osm'=>2,'tipo_osm'=>'instalado','data_exclusao_osm'=>date('Y-m-d H:i:s')]);
        $r=$this->report();$this->assertSame(3,$r['summary']['total']);$this->assertCount(3,$r['rows']);
        $this->assertSame(2,$r['summary']['aplicados']);$this->assertSame(2,$r['summary']['retirados']);
        $own=$this->report([],1,true);$this->assertSame(1,$own['summary']['aplicados']);$this->assertSame(2,$own['summary']['retirados']);
        $this->db->table('tbl_os')->where('id_oss',1)->update(['data_exclusao_oss'=>date('Y-m-d H:i:s')]);
        $this->assertSame(1,$this->report()['summary']['aplicados']);$this->assertSame(0,$this->report()['summary']['retirados']);
    }
    public function testSelectionUsesOpeningDateAndFiltersInsteadOfClosingOrMovementDates(): void
    {
        $this->close(1,'2026-11-01 08:00:00','2026-11-01 09:00:00');
        $this->db->table('tbl_os')->where('id_oss',1)->update(['data_abertura_oss'=>'2026-10-31 23:59:59']);
        $this->db->table('tbl_os')->where('id_oss',2)->update(['data_abertura_oss'=>'2026-11-01 00:00:00']);
        $s=$this->report(['data_inicio'=>'2026-10-31','data_fim'=>'2026-10-31','cliente'=>'1','eletricista'=>'1','status_oss'=>'encerrada'])['summary'];
        $this->assertSame(1,$s['total']);$this->assertSame(1,$s['atendidas']);$this->assertSame(3600,$s['media_segundos']);
    }
    public function testRouteRolesScopeSessionAndNoMutation(): void
    {
        $before=$this->db->table('tbl_os')->get()->getResultArray();
        $this->requestAs(null,'GET','relatorios/eletricistas')->assertRedirectTo(site_url('login'));
        foreach ([1,2] as $id) {
            $r=$this->requestAs($id,'GET','relatorios/eletricistas?data_inicio=2026-01-01&data_fim=2026-12-31');
            $r->assertStatus(200);$r->assertSee('Tempo médio de atendimento');
            $this->assertStringContainsString('no-store',$r->response()->getHeaderLine('Cache-Control'));
        }
        $r=$this->requestAs(3,'GET','relatorios/eletricistas?data_inicio=2026-01-01&data_fim=2026-12-31');
        $r->assertStatus(403);$r->assertDontSee('UC-CE-300789');$r->assertDontSee('ELE-2024-002');
        $this->requestAs(4,'GET','relatorios/eletricistas')->assertStatus(403);
        foreach (['eletricista=2','cliente=3','eletricista=sem_atribuicao'] as $q) {$this->requestAs(3,'GET','relatorios/eletricistas?'.$q)->assertStatus(403);}
        $this->requestAs(1,'POST','relatorios/eletricistas',[],false)->assertStatus(404);
        $this->requestAs(1,'GET','relatorios/eletricistas',[],true,time()-7201)->assertRedirectTo(site_url('login'));
        $this->db->table('tbl_usuario')->where('id_usu',2)->update(['ativo_usu'=>0]);
        $this->requestAs(2,'GET','relatorios/eletricistas')->assertRedirectTo(site_url('login'));
        $this->assertSame($before,$this->db->table('tbl_os')->get()->getResultArray());
    }
    public function testInvalidFiltersAndPageDoNotRenderReport(): void
    {
        foreach (['data_inicio=2026-02-30','data_inicio=2026-12-01&data_fim=2026-01-01','cliente=1e2','status_oss[]=encerrada','eletricista[]=1','page[]=2','page=-1','page=1e2','page=0'] as $q) {
            $r=$this->requestAs(1,'GET','relatorios/eletricistas?'.$q);$r->assertStatus(422);$r->assertDontSee('Tempo médio de atendimento');
        }
    }
    public function testPaginationTotalsTieOrderingAndLinksPreserveAllFilters(): void
    {
        for($i=0;$i<20;$i++){$this->copyOrder(['unidade_consumidora_oss'=>'REPORT-'.$i]);}
        $filters=['data_inicio'=>'2026-01-01','data_fim'=>'2026-12-31','status_oss'=>'atribuida','eletricista'=>'1','cliente'=>'1'];
        $first=$this->report($filters);$second=$this->report($filters,2);
        $this->assertSame(21,$second['summary']['total']);$this->assertSame($first['summary'],$second['summary']);
        $this->assertCount(15,$first['rows']);$this->assertCount(6,$second['rows']);
        $this->assertSame('23',(string)$first['rows'][0]['id_oss']);$this->assertSame('1',(string)$second['rows'][5]['id_oss']);
        $this->assertSame(2,$this->report($filters,999999999)['page']);
        $r=$this->requestAs(2,'GET','relatorios/eletricistas?'.http_build_query($filters+['page'=>'2']));$r->assertStatus(200);$r->assertSee('21 registro(s)');
        $dom=new \DOMDocument();@$dom->loadHTML($r->getBody());$xpath=new \DOMXPath($dom);
        foreach($xpath->query('//nav[@aria-label="Paginação"]//a') as $link){parse_str(parse_url($link->getAttribute('href'),PHP_URL_QUERY),$query);foreach($filters as $key=>$value){$this->assertSame($value,$query[$key]);}}
        $this->assertSame(6,$xpath->query('//section[div/h2="Ordens selecionadas"]//tbody/tr')->length);
    }
    public function testEmptyReportAndHistoricalNamesAreEscaped(): void
    {
        $s=$this->report(['data_inicio'=>'2020-01-01','data_fim'=>'2020-01-31'])['summary'];
        $this->assertSame(0,$s['total']);$this->assertNull($s['media_segundos']);$this->assertSame(0,$s['amostras']);
        $this->db->table('tbl_usuario')->where('id_usu',4)->update(['nome_completo_usu'=>'<script>nome</script>','ativo_usu'=>0,'data_exclusao_usu'=>date('Y-m-d H:i:s')]);
        $this->db->table('tbl_cliente')->where('id_cli',3)->update(['nome_cli'=>'<script>empresa</script>','data_exclusao_cli'=>date('Y-m-d H:i:s')]);
        $r=$this->requestAs(1,'GET','relatorios/eletricistas?data_inicio=2026-01-01&data_fim=2026-12-31');
        $this->assertStringContainsString('&lt;script&gt;nome&lt;/script&gt;',$r->getBody());$this->assertStringNotContainsString('<script>nome</script>',$r->getBody());
        $this->assertStringContainsString('&lt;script&gt;empresa&lt;/script&gt;',$r->getBody());
    }
}
