<?php

namespace Tests\Feature;

use App\Services\OrdemServicoService;
use App\Exceptions\FormException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AppTestCase;

final class OrdensServicoTest extends AppTestCase
{
    private function input(array $overrides = []): array
    {
        return $overrides + ['cliente_oss' => '1', 'tipo_oss' => 'nova_ligacao', 'descricao_oss' => 'Atender unidade de teste.', 'unidade_consumidora_oss' => 'UC-TESTE', 'endereco_oss' => 'Rua do Atendimento, 25', 'bairro_oss' => 'Centro', 'cidade_oss' => 'Fortaleza', 'estado_oss' => 'ce', 'cep_oss' => '60010000', 'prioridade_oss' => 'normal', 'agendamento_oss' => '2026-10-10T09:30', 'observacoes_administrativas_oss' => 'Orientações administrativas.'];
    }

    public static function routes(): iterable
    {
        foreach ([null, 1, 2, 3] as $actor) {
            foreach ([['GET','os',true], ['GET','os/1',true], ['GET','os/nova',false], ['GET','os/1/editar',false], ['POST','os',false], ['POST','os/1/atualizar',false], ['POST','os/1/atribuir',false], ['POST','os/1/cancelar',false]] as [$verb,$path,$read]) {
                yield ($actor ?? 'visitante') . " $verb $path" => [$actor,$verb,$path,$actor !== null && ($read || $actor !== 3)];
            }
        }
    }

    #[DataProvider('routes')]
    public function testRoutesAndRoles(?int $actor, string $verb, string $path, bool $allowed): void
    {
        $before = $this->db->table('tbl_os')->get()->getResultArray();
        $history = $this->db->table('tbl_os_historico')->countAllResults();
        $response = $this->requestWithDeletionPassword($actor, $verb, $path);
        if ($actor === null) { $response->assertRedirectTo(site_url('login')); }
        elseif (!$allowed) { $response->assertStatus(403); }
        else { $this->assertContains($response->response()->getStatusCode(), [200,422]); }
        $this->assertSame($before, $this->db->table('tbl_os')->get()->getResultArray());
        $this->assertSame($history, $this->db->table('tbl_os_historico')->countAllResults());
    }

    public function testCreationAssignmentEditionAndCancellationAreAudited(): void
    {
        $this->requestAs(2, 'POST', 'os', $this->input(['status_oss'=>'encerrada','eletricista_oss'=>'2','id_oss'=>'1','usuario_osh'=>'4']))->assertStatus(303);
        $row = $this->db->table('tbl_os')->where('unidade_consumidora_oss','UC-TESTE')->get()->getRowArray();
        $id = (int) $row['id_oss'];
        $this->assertSame('aberta', $row['status_oss']);
        $this->assertNull($row['eletricista_oss']);
        $this->assertSame('CE', $row['estado_oss']);
        $this->assertSame('60010-000', $row['cep_oss']);
        $this->assertSame('2026-10-10 09:30:00', $row['agendamento_oss']);
        $this->requestAs(2, 'POST', "os/$id/atribuir", ['eletricista_oss'=>'1'])->assertStatus(303);
        $this->requestAs(1, 'POST', "os/$id/atribuir", ['eletricista_oss'=>'2'])->assertStatus(422);
        $this->requestAs(3, 'GET', "os/$id")->assertStatus(200);
        $this->requestAs(4, 'GET', "os/$id")->assertStatus(403);
        $this->requestAs(2, 'POST', "os/$id/atualizar", $this->input(['prioridade_oss'=>'urgente']))->assertStatus(303);
        $this->requestAs(2, 'POST', "os/$id/cancelar", ['motivo'=>'Empresa desistiu.'])->assertStatus(303);
        $this->requestAs(2, 'POST', "os/$id/cancelar", ['motivo'=>'Repetido'])->assertStatus(422);
        $this->requestAs(1, 'POST', "os/$id/atualizar", $this->input())->assertStatus(422);
        $history = $this->db->table('tbl_os_historico')->where('ordem_servico_osh',$id)->orderBy('id_osh')->get()->getResultArray();
        $this->assertSame(['criacao','atribuicao','edicao','cancelamento'], array_column($history,'evento_osh'));
        $this->assertSame(['2','2','2','2'], array_map('strval',array_column($history,'usuario_osh')));
        $this->assertSame('atribuida', $history[3]['status_anterior_osh']);
        $this->assertSame('cancelada', $history[3]['status_osh']);
        $this->assertNotNull($this->db->table('tbl_os')->where('id_oss',$id)->get()->getRow()->data_fechamento_oss);
    }

    public function testOwnListSearchAndDirectAccess(): void
    {
        $response = $this->requestAs(3, 'GET', 'os');
        $response->assertSee('Minhas OS');
        $response->assertSee('UC-CE-100234');
        $response->assertDontSee('UC-CE-300789');
        $this->requestAs(3, 'GET', 'os?q=UC-CE-300789')->assertDontSee('UC-CE-300789', 'tbody');
        $response = $this->requestAs(3, 'GET', 'os/3');
        $response->assertStatus(403);
        $response->assertDontSee('UC-CE-300789');
        $this->requestAs(4, 'GET', 'os/1')->assertStatus(403);
        $this->requestAs(3, 'GET', 'os/9999')->assertStatus(404);
        $this->db->table('tbl_os')->where('id_oss',1)->update(['data_exclusao_oss'=>date('Y-m-d H:i:s')]);
        $this->requestAs(3, 'GET', 'os/1')->assertStatus(404);
    }

    public function testPendingFinalAndInProgressRestrictions(): void
    {
        $this->requestAs(2, 'POST', 'os/3/atualizar', $this->input())->assertStatus(422);
        $this->requestAs(2, 'POST', 'os/2/cancelar', ['motivo'=>'Não pode'])->assertStatus(422);
        $row = $this->db->table('tbl_os')->where('id_oss',2)->get()->getRowArray();
        $input = array_intersect_key($row, array_flip(OrdemServicoService::FIELDS));
        $input['agendamento_oss']=''; $input['prioridade_oss']='alta';
        $this->requestAs(2, 'POST', 'os/2/atualizar', $input)->assertStatus(303);
        $this->requestAs(2, 'POST', 'os/2/atualizar', ['endereco_oss'=>'Outro local'] + $input)->assertStatus(422);
        $this->requestWithDeletionPassword(1, 'POST', 'clientes/1/excluir')->assertStatus(422);
        $this->db->table('tbl_medidor')->update(['eletricista_posse_med'=>null]);
        $this->requestWithDeletionPassword(1, 'POST', 'usuarios/3/excluir')->assertStatus(422);
    }

    public function testInvalidDataInactiveDestinationAndPreservedInputs(): void
    {
        foreach ([['cliente_oss'=>'9999'], ['tipo_oss'=>'reparo'], ['estado_oss'=>'XX'], ['cep_oss'=>'abc'], ['prioridade_oss'=>'maxima'], ['agendamento_oss'=>'2026-02-30T09:30'], ['descricao_oss'=>'   '], ['cliente_oss'=>['1']]] as $change) {
            $response = $this->requestAs(2, 'POST', 'os', $this->input($change));
            $response->assertStatus(422);
            $response->assertSee('Rua do Atendimento, 25');
        }
        $this->db->table('tbl_cliente')->where('id_cli',1)->update(['status_cli'=>'inativo']);
        $this->requestAs(2, 'POST', 'os', $this->input())->assertStatus(422);
        $this->db->table('tbl_os')->where('id_oss',1)->update(['status_oss'=>'aberta','eletricista_oss'=>null]);
        $this->db->table('tbl_usuario')->where('id_usu',3)->update(['ativo_usu'=>0]);
        $this->requestAs(2, 'POST', 'os/1/atribuir', ['eletricista_oss'=>'1'])->assertStatus(422);
        $this->requestAs(2, 'POST', 'os/1/atribuir', ['eletricista_oss'=>['1']])->assertStatus(422);
        $this->requestAs(2, 'POST', 'os/1/cancelar', ['motivo'=>' '])->assertStatus(422);
        $this->assertSame(3,$this->db->table('tbl_os')->countAllResults());
    }

    public function testHistoryFailureRollsBackEachWrite(): void
    {
        $this->db->query("ALTER TABLE tbl_os_historico ADD CONSTRAINT fail_os_history CHECK (evento_osh NOT IN ('criacao','edicao','atribuicao','cancelamento'))");
        try {
            $this->requestAs(2, 'POST', 'os', $this->input())->assertStatus(422);
            $this->assertSame(3,$this->db->table('tbl_os')->countAllResults());
            $before = $this->db->table('tbl_os')->where('id_oss',1)->get()->getRowArray();
            $input = array_intersect_key($before, array_flip(OrdemServicoService::FIELDS));
            $input['agendamento_oss']=''; $input['prioridade_oss']='urgente';
            $this->requestAs(2, 'POST', 'os/1/atualizar', $input)->assertStatus(422);
            $this->assertSame($before,$this->db->table('tbl_os')->where('id_oss',1)->get()->getRowArray());
            $this->requestAs(2, 'POST', 'os/1/cancelar', ['motivo'=>'Teste'])->assertStatus(422);
            $this->assertSame($before,$this->db->table('tbl_os')->where('id_oss',1)->get()->getRowArray());
            $this->db->table('tbl_os')->where('id_oss',1)->update(['status_oss'=>'aberta','eletricista_oss'=>null]);
            $this->requestAs(2, 'POST', 'os/1/atribuir', ['eletricista_oss'=>'1'])->assertStatus(422);
            $row=$this->db->table('tbl_os')->where('id_oss',1)->get()->getRowArray();
            $this->assertSame('aberta',$row['status_oss']); $this->assertNull($row['eletricista_oss']);
        } finally { $this->db->query('ALTER TABLE tbl_os_historico DROP CHECK fail_os_history'); }
    }

    public function testCsrfExpiredSessionGetWritesAndUnimplementedActions(): void
    {
        $before=$this->db->table('tbl_os')->get()->getResultArray();
        foreach (['os','os/1/atualizar','os/1/atribuir','os/1/cancelar'] as $path) {
            $this->requestWithDeletionPassword(2,'POST',$path,$this->input(),false)->assertStatus(403);
            $this->requestWithDeletionPassword(2,'POST',$path,$this->input(),true,time()-7201)->assertRedirectTo(site_url('login'));
        }
        foreach (['os/1/atribuir','os/1/cancelar','os/1/atualizar','os/1/excluir','os/1/iniciar','os/1/encerrar'] as $path) {
            $this->requestWithDeletionPassword(2,'GET',$path)->assertStatus(404);
        }
        $this->assertSame($before,$this->db->table('tbl_os')->get()->getResultArray());
    }

    public function testIncompatibleMeterReservationBlocksCancellationAndXssEscaped(): void
    {
        $this->db->table('tbl_medidor_reserva')->insert(['medidor_rme'=>1,'ordem_servico_rme'=>1,'eletricista_rme'=>1,'usuario_rme'=>1]);
        $this->requestAs(2,'POST','os/1/cancelar',['motivo'=>'Cancelar'])->assertStatus(422);
        $this->assertSame('atribuida',$this->db->table('tbl_os')->where('id_oss',1)->get()->getRow()->status_oss);
        $this->requestAs(2,'POST','os',$this->input(['descricao_oss'=>'<script>alert(1)</script>']))->assertStatus(303);
        $row=$this->db->table('tbl_os')->where('unidade_consumidora_oss','UC-TESTE')->get()->getRowArray();
        $body = $this->requestAs(2,'GET','os/'.$row['id_oss'])->response()->getBody();
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
    }

    public function testServiceRechecksActorAndInactiveCompany(): void
    {
        $this->expectException(FormException::class);
        (new OrdemServicoService($this->db))->save($this->input(),3);
    }
}
