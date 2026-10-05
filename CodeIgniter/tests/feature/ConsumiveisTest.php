<?php

namespace Tests\Feature;

use App\Exceptions\FormException;
use App\Services\ConsumivelService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AppTestCase;

final class ConsumiveisTest extends AppTestCase
{
    private function material(array $overrides = []): array
    {
        return $overrides + ['nome_con' => 'Cabo de teste', 'unidade_con' => 'metro', 'precisao_con' => '3'];
    }

    public static function routes(): iterable
    {
        foreach ([null, 1, 2, 3] as $actor) {
            foreach ([['GET','consumiveis'], ['GET','consumiveis/1'], ['GET','consumiveis/novo'], ['GET','consumiveis/1/editar'], ['POST','consumiveis'], ['POST','consumiveis/1/atualizar'], ['POST','consumiveis/1/entrada'], ['POST','consumiveis/1/excluir'], ['POST','os/1/consumiveis/reservar']] as [$verb, $path]) {
                yield ($actor ?? 'visitante') . " $verb $path" => [$actor, $verb, $path];
            }
        }
    }

    #[DataProvider('routes')]
    public function testAuthorization(?int $actor, string $verb, string $path): void
    {
        $before = $this->db->table('tbl_consumivel_saldo')->get()->getResultArray();
        $response = $this->requestAs($actor, $verb, $path);
        if ($actor === null) { $response->assertRedirectTo(site_url('login')); }
        elseif ($actor === 3 || ($actor === 2 && !($verb === 'GET' && in_array($path, ['consumiveis','consumiveis/1'], true)))) { $response->assertStatus(403); }
        else { $this->assertContains($response->response()->getStatusCode(), [200,303,422]); }
        if ($actor !== 1) { $this->assertSame($before, $this->db->table('tbl_consumivel_saldo')->get()->getResultArray()); }
    }

    public function testCatalogEntryAndHistoryWithServerActor(): void
    {
        $this->requestAs(1, 'POST', 'consumiveis', $this->material(['id_con'=>'1','quantidade_sco'=>'999']))->assertStatus(303);
        $row = $this->db->table('tbl_consumivel')->where('nome_con','Cabo de teste')->get()->getRowArray();
        $id = (int) $row['id_con'];
        $this->assertSame('0.000', $this->balance($id)['quantidade_sco']);
        $this->requestAs(1, 'POST', "consumiveis/$id/entrada", ['quantidade'=>'0,125','observacao'=>'Compra teste','usuario_mco'=>'4'])->assertStatus(303);
        $this->requestAs(1, 'POST', "consumiveis/$id/entrada", ['quantidade'=>'0.375','observacao'=>'Reposição teste'])->assertStatus(303);
        $this->assertSame('0.500', $this->balance($id)['quantidade_sco']);
        $events = $this->db->table('tbl_consumivel_mov')->where('consumivel_mco',$id)->get()->getResultArray();
        $this->assertCount(2, $events);
        $this->assertSame('1', (string) $events[0]['usuario_mco']);
        $this->assertSame('fornecedor', $events[0]['origem_mco']);
        $this->assertSame('deposito', $events[0]['destino_mco']);
        $this->requestAs(1, 'POST', "consumiveis/$id/atualizar", $this->material(['unidade_con'=>'unidade']))->assertStatus(422);
        $this->requestAs(1, 'POST', "consumiveis/$id/atualizar", $this->material(['precisao_con'=>'0']))->assertStatus(422);
        $this->requestAs(1, 'POST', "consumiveis/$id/atualizar", $this->material(['nome_con'=>'Cabo atualizado']))->assertStatus(303);
        $this->requestAs(1, 'POST', "consumiveis/$id/excluir")->assertStatus(422);
        $response = $this->requestAs(2, 'GET', "consumiveis/$id");
        $response->assertStatus(200);
        $response->assertSee('Compra teste');
    }

    public function testEmptyMaterialSoftDeleteAndHistoryForeignKeys(): void
    {
        $service = new ConsumivelService($this->db);
        $id = $service->save($this->material(), 1);
        $service->save($this->material(['precisao_con'=>'0']), 1, $id);
        $this->requestAs(1,'POST',"consumiveis/$id/excluir")->assertStatus(303);
        $this->assertNotNull($this->db->table('tbl_consumivel')->where('id_con',$id)->get()->getRow()->data_exclusao_con);
        $this->assertSame(1, $this->db->table('tbl_consumivel_saldo')->where('consumivel_sco',$id)->countAllResults());
        $this->requestAs(1,'GET',"consumiveis/$id")->assertStatus(404);
        $this->requestAs(1,'POST',"consumiveis/$id/entrada",['quantidade'=>'1','observacao'=>'Compra'])->assertStatus(404);
    }

    public function testReservationCancellationPreservesPhysicalStockAndAudit(): void
    {
        $this->requestAs(1,'POST','os/1/consumiveis/reservar',['consumivel'=>'2','quantidade'=>'10,125','usuario_rco'=>'4','eletricista_rco'=>'2'])->assertStatus(303);
        $row = $this->db->table('tbl_consumivel_reserva')->get()->getRowArray();
        $this->assertSame('1', (string) $row['usuario_rco']);
        $this->assertSame('1', (string) $row['eletricista_rco']);
        $this->assertSame('10.125', $this->balance(2)['reservado_sco']);
        $this->assertSame('25.500', $this->balance(2)['quantidade_sco']);
        $this->requestAs(1,'POST','os/1/consumiveis/reservar',['consumivel'=>'2','quantidade'=>'1'])->assertStatus(422);
        $this->assertSame(1, $this->db->table('tbl_consumivel_reserva')->countAllResults());
        $this->requestAs(3,'GET','os/1')->assertSee('10.125');
        $this->requestAs(4,'GET','os/1')->assertStatus(403);
        $this->requestAs(2,'POST','os/1/cancelar',['motivo'=>'Solicitação cancelada'])->assertStatus(303);
        $this->assertSame('0.000', $this->balance(2)['reservado_sco']);
        $this->assertSame('25.500', $this->balance(2)['quantidade_sco']);
        $this->assertSame('liberada', $this->db->table('tbl_consumivel_reserva')->get()->getRow()->status_rco);
        $event = $this->db->table('tbl_os_historico')->where('evento_osh','cancelamento')->get()->getRowArray();
        $this->assertSame([(int) $row['id_rco']], json_decode($event['dados_osh'],true)['reservas_consumiveis_liberadas']);
        $this->requestAs(2,'POST','os/1/cancelar',['motivo'=>'Repetido'])->assertStatus(422);
        $this->assertSame('0.000', $this->balance(2)['reservado_sco']);
    }

    public function testInvalidPrecisionAvailabilityOrderAndMissingResource(): void
    {
        foreach (['0','-1','1e3','1.000','1.1','1,000.00','1000000000000', ['1']] as $value) {
            $this->requestAs(1,'POST','consumiveis/1/entrada',['quantidade'=>$value,'observacao'=>'Entrada teste'])->assertStatus(422);
        }
        foreach (['0.0001','-1','999999999999.999'] as $value) {
            $this->requestAs(1,'POST','os/1/consumiveis/reservar',['consumivel'=>'2','quantidade'=>$value])->assertStatus(422);
        }
        foreach ([2,3] as $order) { $this->requestAs(1,'POST',"os/$order/consumiveis/reservar",['consumivel'=>'1','quantidade'=>'1'])->assertStatus(422); }
        $this->requestAs(1,'POST','os/999/consumiveis/reservar',['consumivel'=>'1','quantidade'=>'1'])->assertStatus(404);
        $this->requestAs(1,'POST','os/1/consumiveis/reservar',['consumivel'=>'999','quantidade'=>'1'])->assertStatus(404);
        $this->requestAs(1,'POST','consumiveis',$this->material(['precisao_con'=>'4']))->assertStatus(422);
        $this->assertSame('100.000', $this->balance(1)['quantidade_sco']);
        $this->assertSame('0.000', $this->balance(2)['reservado_sco']);
    }

    public function testCsrfExpirationMethodsAndXss(): void
    {
        foreach (['consumiveis','consumiveis/1/atualizar','consumiveis/1/entrada','consumiveis/1/excluir','os/1/consumiveis/reservar'] as $path) {
            $this->requestAs(1,'POST',$path,[],false)->assertStatus(403);
            $this->requestAs(1,'POST',$path,[],true,time()-7201)->assertRedirectTo(site_url('login'));
            if ($path !== 'consumiveis') { $this->requestAs(1,'GET',$path)->assertStatus(404); }
        }
        $this->requestAs(1,'POST','consumiveis/1/entrada',['quantidade'=>'1','observacao'=>'<script>alert(1)</script>'])->assertStatus(303);
        $response = $this->requestAs(2,'GET','consumiveis/1');
        $response->assertStatus(200);
        $body = $response->response()->getBody();
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
    }

    public function testEntryReservationAndCancellationRollback(): void
    {
        $this->db->query("ALTER TABLE tbl_consumivel_mov ADD CONSTRAINT fail_entry CHECK (observacao_mco <> 'Falha teste')");
        try {
            $this->requestAs(1,'POST','consumiveis/2/entrada',['quantidade'=>'1','observacao'=>'Falha teste'])->assertStatus(422);
            $this->assertSame('25.500', $this->balance(2)['quantidade_sco']);
        } finally { $this->db->query('ALTER TABLE tbl_consumivel_mov DROP CHECK fail_entry'); }
        $this->db->query("ALTER TABLE tbl_os_historico ADD CONSTRAINT fail_reserve CHECK (evento_osh <> 'reserva_consumivel')");
        try {
            $this->requestAs(1,'POST','os/1/consumiveis/reservar',['consumivel'=>'2','quantidade'=>'1'])->assertStatus(422);
            $this->assertSame('0.000', $this->balance(2)['reservado_sco']);
            $this->assertSame(0, $this->db->table('tbl_consumivel_reserva')->countAllResults());
        } finally { $this->db->query('ALTER TABLE tbl_os_historico DROP CHECK fail_reserve'); }
        $this->requestAs(1,'POST','os/1/consumiveis/reservar',['consumivel'=>'2','quantidade'=>'1'])->assertStatus(303);
        $this->db->query("ALTER TABLE tbl_os_historico ADD CONSTRAINT fail_cancel CHECK (evento_osh <> 'cancelamento')");
        try {
            $this->requestAs(2,'POST','os/1/cancelar',['motivo'=>'Cancelar'])->assertStatus(422);
            $this->assertSame('1.000', $this->balance(2)['reservado_sco']);
            $this->assertSame('reservada', $this->db->table('tbl_consumivel_reserva')->get()->getRow()->status_rco);
            $this->assertSame('atribuida', $this->db->table('tbl_os')->where('id_oss',1)->get()->getRow()->status_oss);
        } finally { $this->db->query('ALTER TABLE tbl_os_historico DROP CHECK fail_cancel'); }
    }

    public function testDeliveredMaterialsSurviveCancellationAndBlockEmployeeDeletion(): void
    {
        // Fixture for physical custody; delivery itself is a later review step.
        $this->db->table('tbl_consumivel_saldo')->insert(['consumivel_sco'=>1,'eletricista_sco'=>1,'quantidade_sco'=>'2.000']);
        $this->db->table('tbl_consumivel_reserva')->insert(['ordem_servico_rco'=>1,'consumivel_rco'=>1,'eletricista_rco'=>1,'usuario_rco'=>1,'quantidade_rco'=>'2.000','entregue_rco'=>'2.000','status_rco'=>'entregue']);
        $this->requestAs(2,'POST','os/1/cancelar',['motivo'=>'Cancelar'])->assertStatus(303);
        $this->clearPendingWork();
        $this->requestAs(1,'POST','usuarios/3/excluir')->assertStatus(422);
        $this->assertSame('entregue', $this->db->table('tbl_consumivel_reserva')->get()->getRow()->status_rco);
        $this->assertSame('2.000', $this->db->table('tbl_consumivel_saldo')->where('eletricista_sco',1)->get()->getRow()->quantidade_sco);
    }

    public function testDirectServiceCannotUseOperatorOrInactiveManager(): void
    {
        foreach ([2,3] as $actor) {
            try { (new ConsumivelService($this->db))->entry(1,'1','Teste',$actor); $this->fail('Acesso inválido aceito.'); }
            catch (FormException $e) { $this->assertArrayHasKey('operacao',$e->errors); }
        }
        $this->db->table('tbl_usuario')->where('id_usu',1)->update(['ativo_usu'=>0]);
        $this->expectException(FormException::class);
        (new ConsumivelService($this->db))->reserve(1,'1','1',1);
    }

    public function testAvailabilityIsSharedAcrossOrdersAndCancellationReleasesOnlyItsReserve(): void
    {
        $this->db->table('tbl_os')->where('id_oss',3)->update(['status_oss'=>'atribuida']);
        $this->requestAs(1,'POST','os/1/consumiveis/reservar',['consumivel'=>'2','quantidade'=>'20'])->assertStatus(303);
        $this->requestAs(1,'POST','os/3/consumiveis/reservar',['consumivel'=>'2','quantidade'=>'6'])->assertStatus(422);
        $this->requestAs(1,'POST','os/3/consumiveis/reservar',['consumivel'=>'2','quantidade'=>'5.500'])->assertStatus(303);
        $this->assertSame('25.500', $this->balance(2)['reservado_sco']);
        $this->requestAs(2,'POST','os/1/cancelar',['motivo'=>'Cancelar reserva maior'])->assertStatus(303);
        $this->assertSame('5.500', $this->balance(2)['reservado_sco']);
        $this->assertSame('reservada', $this->db->table('tbl_consumivel_reserva')->where('ordem_servico_rco',3)->get()->getRow()->status_rco);
    }

    public function testMaximumDecimalAndCatalogCreationRollback(): void
    {
        $service = new ConsumivelService($this->db);
        $this->db->query('ALTER TABLE tbl_consumivel_saldo ADD CONSTRAINT fail_catalog_balance CHECK (consumivel_sco <> 3)');
        try {
            $this->requestAs(1,'POST','consumiveis',$this->material())->assertStatus(422);
            $this->assertSame(2, $this->db->table('tbl_consumivel')->countAllResults());
        } finally { $this->db->query('ALTER TABLE tbl_consumivel_saldo DROP CHECK fail_catalog_balance'); }
        $id = $service->save($this->material(), 1);
        $this->requestAs(1,'POST',"consumiveis/$id/entrada",['quantidade'=>'999999999999.999','observacao'=>'Teste limite'])->assertStatus(303);
        $this->requestAs(1,'POST',"consumiveis/$id/entrada",['quantidade'=>'0.001','observacao'=>'Excede limite'])->assertStatus(422);
        $this->assertSame('999999999999.999', $this->balance($id)['quantidade_sco']);
        $this->assertSame(1, $this->db->table('tbl_consumivel_mov')->where('consumivel_mco',$id)->countAllResults());
    }

    private function balance(int $id): array
    {
        return $this->db->table('tbl_consumivel_saldo')->where('consumivel_sco',$id)->where('eletricista_sco',null)->get()->getRowArray();
    }
}
