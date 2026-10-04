<?php

namespace Tests\Feature;

use App\Models\ConsumivelModel;
use App\Models\MedidorModel;
use App\Services\AtendimentoService;
use App\Services\ChecklistInicioService;
use App\Services\ConsumivelService;
use App\Services\MedidorOsService;
use Tests\Support\AppTestCase;

final class RelatorioEstoqueTest extends AppTestCase
{
    private function filters(array $extra = []): array
    {
        return $extra + ['q' => '', 'detentor' => 'todos', 'status_med' => '', 'localizacao_med' => ''];
    }

    private function balances(array $extra = []): array
    {
        return (new ConsumivelModel($this->db))->stockReport($this->filters($extra))->findAll();
    }

    private function meters(array $extra = []): array
    {
        return (new MedidorModel($this->db))->stockReport($this->filters($extra))->findAll();
    }

    private function opening(): void
    {
        $this->db->table('tbl_checklist')->insert(['id_chk'=>1, 'nome_chk'=>'Início relatório', 'tipo_os_chk'=>'nova_ligacao', 'etapa_chk'=>'inicio', 'ativo_chk'=>1, 'usuario_chk'=>1]);
        $this->db->table('tbl_checklist_item')->insert(['id_chi'=>1, 'checklist_chi'=>1, 'pergunta_chi'=>'Seguro?', 'nivel_chi'=>'bloqueante', 'obrigatorio_chi'=>1]);
        (new ChecklistInicioService($this->db))->answer(1, 1, ['1'=>'1'], [], 3);
    }

    public function testRealRouteRolesSessionAndReadOnly(): void
    {
        $before = $this->db->table('tbl_medidor')->get()->getResultArray();
        $saldo = $this->db->table('tbl_consumivel_saldo')->get()->getResultArray();
        $this->requestAs(null, 'GET', 'relatorios/estoque')->assertRedirectTo(site_url('login'));
        foreach ([1, 2] as $user) {
            foreach (['medidores', 'consumiveis'] as $type) {
                $r = $this->requestAs($user, 'GET', 'relatorios/estoque?tipo=' . $type);
                $r->assertStatus(200); $r->assertSee('Relatório de estoque');
                $this->assertStringContainsString('no-store', $r->response()->getHeaderLine('Cache-Control'));
            }
        }
        foreach ([3, 4] as $user) { $this->requestAs($user, 'GET', 'relatorios/estoque')->assertStatus(403); }
        $this->requestAs(1, 'GET', 'relatorios/estoque', [], true, time()-7201)->assertRedirectTo(site_url('login'));
        $this->requestAs(1, 'POST', 'relatorios/estoque', ['quantidade_sco'=>'0'])->assertStatus(404);
        $this->requestAs(1, 'POST', 'relatorios/estoque', [], false)->assertStatus(404);
        $this->assertSame($before, $this->db->table('tbl_medidor')->get()->getResultArray());
        $this->assertSame($saldo, $this->db->table('tbl_consumivel_saldo')->get()->getResultArray());
    }

    public function testInactiveDeletedAndChangedRoleCannotConsult(): void
    {
        foreach ([['ativo_usu'=>0], ['data_exclusao_usu'=>date('Y-m-d H:i:s')]] as $change) {
            $this->db->table('tbl_usuario')->where('id_usu', 2)->update($change);
            $this->requestAs(2, 'GET', 'relatorios/estoque')->assertRedirectTo(site_url('login'));
            $this->db->table('tbl_usuario')->where('id_usu', 2)->update(['ativo_usu'=>1, 'data_exclusao_usu'=>null]);
        }
        $this->db->table('tbl_usuario')->where('id_usu', 2)->update(['papel_usu'=>'eletricista']);
        $this->requestAs(2, 'GET', 'relatorios/estoque')->assertRedirectTo(site_url('login'));
    }

    public function testExactAvailabilityReservationDeliveryConsumptionAndReturn(): void
    {
        $s = new ConsumivelService($this->db);
        $s->reserve(1, '2', '5.125', 1);
        $row = $this->balances(['detentor'=>'deposito', 'q'=>'Fita'])[0];
        $this->assertSame('25.500', $row['quantidade_sco']);
        $this->assertSame('5.125', $row['reservado_sco']);
        $this->assertSame('20.375', $row['disponivel_sco']);
        $this->opening(); $s->deliver(1, 1, 1);
        $meters = new MedidorOsService($this->db);
        $meters->reserve(1, '1', 1); $meters->deliver(1, 1, 1);
        (new AtendimentoService($this->db))->start(1, 3);
        $s->consume(1, 1, '2.125', 'Aplicação de fita', 3);
        $s->receive(1, 1, '1.000', 'Sobra recebida', 1);
        $depot = $this->balances(['detentor'=>'deposito', 'q'=>'Fita'])[0];
        $custody = $this->balances(['detentor'=>'1', 'q'=>'Fita'])[0];
        $this->assertSame('21.375', $depot['quantidade_sco']);
        $this->assertSame('0.000', $depot['reservado_sco']);
        $this->assertSame('21.375', $depot['disponivel_sco']);
        $this->assertSame('2.000', $custody['quantidade_sco']);
        $this->assertSame('2.000', $custody['disponivel_sco']);
        $r = $this->requestAs(2, 'GET', 'relatorios/estoque?tipo=consumiveis&q=Fita&detentor=1');
        $r->assertStatus(200); $r->assertSee('2.000', 'tbody'); $r->assertDontSee('21.375', 'tbody');
    }

    public function testMissingBalanceIsDifferentFromZeroAndDeletedRowsAreExcluded(): void
    {
        $this->db->table('tbl_consumivel_saldo')->where('consumivel_sco', 1)->update(['quantidade_sco'=>'0.000']);
        $this->db->table('tbl_consumivel_saldo')->where('consumivel_sco', 2)->update(['data_exclusao_sco'=>date('Y-m-d H:i:s')]);
        $rows = array_column($this->balances(['detentor'=>'deposito']), null, 'id_con');
        $this->assertSame('0.000', $rows[1]['disponivel_sco']);
        $this->assertNull($rows[2]['id_sco']); $this->assertNull($rows[2]['disponivel_sco']);
        $r = $this->requestAs(1, 'GET', 'relatorios/estoque?tipo=consumiveis&detentor=deposito');
        $r->assertSee('Saldo não cadastrado', 'tbody'); $r->assertSee('0.000', 'tbody');
        $this->db->table('tbl_consumivel')->where('id_con', 2)->update(['data_exclusao_con'=>date('Y-m-d H:i:s')]);
        $this->assertCount(1, $this->balances());
        $this->db->table('tbl_medidor')->where('id_med', 1)->update(['data_exclusao_med'=>date('Y-m-d H:i:s')]);
        $this->assertCount(5, $this->meters());
    }

    public function testMeterCurrentReservationAndInstallationWithoutHistoricalDuplication(): void
    {
        $this->opening(); $s = new MedidorOsService($this->db);
        $s->reserve(1, '1', 1); $s->deliver(1, 1, 1);
        (new AtendimentoService($this->db))->start(1, 3);
        $s->apply(1, 1, 3);
        $all = array_column($this->meters(), null, 'id_med');
        $this->assertCount(6, $all); $this->assertSame('UC-CE-100234', $all[1]['unidade_consumidora_ins']);
        $this->assertNull($all[1]['ordem_servico_rme']);
        $s->withdraw(1, 1, 3);
        $all = array_column($this->meters(), null, 'id_med');
        $this->assertCount(6, $all); $this->assertNull($all[1]['unidade_consumidora_ins']);
        $this->assertSame('1', (string) $all[1]['ordem_servico_rme']);
        $this->assertSame('em_transito', $all[1]['status_med']);
        $r = (int) $this->db->table('tbl_medidor_reserva')->orderBy('id_rme', 'DESC')->get()->getRow()->id_rme;
        $s->receive(1, $r, 'disponivel', 1);
        $all = array_column($this->meters(), null, 'id_med');
        $this->assertCount(6, $all); $this->assertNull($all[1]['ordem_servico_rme']);
        $this->assertSame('disponivel', $all[1]['status_med']);
        $this->assertSame(2, $this->db->table('tbl_os_medidor')->where('medidor_osm', 1)->countAllResults());
        $this->requestAs(2, 'GET', 'relatorios/estoque?status_med=instalado')->assertSee('UC-CE-300789', 'tbody');
    }

    public function testLostWrittenOffAndInactiveOwnersRemainExplicit(): void
    {
        $this->db->table('tbl_medidor')->where('id_med', 3)->update(['status_med'=>'perdido']);
        $this->db->table('tbl_medidor')->where('id_med', 4)->update(['status_med'=>'baixado']);
        $this->db->table('tbl_usuario')->where('id_usu', 3)->update(['ativo_usu'=>0, 'data_exclusao_usu'=>date('Y-m-d H:i:s')]);
        $this->db->table('tbl_consumivel_saldo')->insert(['consumivel_sco'=>2, 'eletricista_sco'=>1, 'quantidade_sco'=>'0.001']);
        $this->assertCount(1, $this->meters(['status_med'=>'baixado', 'detentor'=>'1']));
        $this->assertCount(1, $this->meters(['status_med'=>'perdido', 'detentor'=>'1']));
        $this->assertSame('0.001', $this->balances(['detentor'=>'1'])[0]['disponivel_sco']);
        $this->assertNotEmpty((new MedidorModel($this->db))->reportOwners()[0]['nome_completo_usu']);
        $r = $this->requestAs(1, 'GET', 'relatorios/estoque?status_med=baixado&detentor=1');
        $r->assertStatus(200); $r->assertSee('Baixado', 'tbody'); $r->assertDontSee('Perdido', 'tbody');
        $r->assertSee('não representa posse ativa');
        $this->assertCount(0, $this->meters(['status_med'=>'perdido', 'detentor'=>'deposito']));
    }

    public function testFiltersAreParameterizedEscapedAndMalformedValuesAreNormalized(): void
    {
        $this->db->table('tbl_consumivel')->where('id_con', 1)->update(['nome_con'=>"<script>alert('x')</script>"]);
        $r = $this->requestAs(1, 'GET', 'relatorios/estoque?tipo=consumiveis');
        $this->assertStringContainsString('&lt;script&gt;', $r->getBody());
        $this->assertStringNotContainsString("<script>alert('x')</script>", $r->getBody());
        $this->requestAs(1, 'GET', 'relatorios/estoque?q=' . rawurlencode("' OR 1=1 --"))->assertSee('Nenhum medidor encontrado.', 'tbody');
        $this->requestAs(1, 'GET', 'relatorios/estoque?tipo[]=consumiveis&q[]=x&status_med[]=x&localizacao_med[]=x&detentor[]=1')->assertStatus(200);
        $r = $this->requestAs(1, 'GET', 'relatorios/estoque?status_med=forjado&localizacao_med=forjado&detentor=1e2');
        $r->assertStatus(200); $r->assertSee('6 registro(s)');
        $this->requestAs(1, 'GET', 'relatorios/estoque?status_med=disponivel&localizacao_med=viatura')->assertSee('Nenhum medidor encontrado.', 'tbody');
        $this->assertCount(2, $this->meters(['status_med'=>'disponivel', 'localizacao_med'=>'deposito']));
    }

    public function testPaginationKeepsFiltersAndCountsAllMatchingRows(): void
    {
        for ($i=0; $i<20; $i++) {
            $this->db->table('tbl_medidor')->insert(['numero_med'=>'REPORT-' . $i, 'modelo_med'=>'Modelo relatório', 'fabricante_med'=>'GPM', 'status_med'=>'disponivel', 'localizacao_med'=>'deposito']);
        }
        $r = $this->requestAs(2, 'GET', 'relatorios/estoque?tipo=medidores&q=REPORT&detentor=deposito&status_med=disponivel&page=2');
        $r->assertStatus(200); $r->assertSee('20 registro(s)');
        $dom = new \DOMDocument(); @$dom->loadHTML($r->getBody()); $xpath = new \DOMXPath($dom);
        $this->assertSame(5, $xpath->query('//tbody/tr')->length);
        $links = $xpath->query('//nav[@aria-label="Paginação"]//a');
        $this->assertGreaterThan(0, $links->length);
        foreach ($links as $link) {
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
            $this->assertSame('REPORT', $query['q']); $this->assertSame('deposito', $query['detentor']);
            $this->assertSame('disponivel', $query['status_med']); $this->assertSame('medidores', $query['tipo']);
        }
    }
}
