<?php
namespace Tests\Feature;

use App\Exceptions\FormException;
use App\Services\MedidorService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AppTestCase;

final class MedidoresTest extends AppTestCase
{
    public static function accessCases(): array
    {
        $cases = [];
        foreach ([null, 1, 2, 3, 4] as $actor) {
            foreach ([['GET','medidores'], ['GET','medidores/1'], ['GET','medidores/novo'], ['GET','medidores/1/editar'], ['POST','medidores'], ['POST','medidores/1/atualizar'], ['POST','medidores/1/enviar'], ['POST','medidores/3/devolver'], ['POST','medidores/1/excluir']] as [$method, $path]) {
                $allowed = $actor === 1 || ($actor === 2 && $method === 'GET' && in_array($path, ['medidores','medidores/1'], true));
                $cases[] = [$actor, $method, $path, $allowed];
            }
        }
        return $cases;
    }

    #[DataProvider('accessCases')]
    public function testRealRoutes(?int $actor, string $method, string $path, bool $allowed): void
    {
        $before = $this->db->table('tbl_estoque_mov')->countAllResults();
        $response = $this->requestAs($actor, $method, $path, ['numero_med' => 'NEW-TEST', 'destino' => '2', 'condicao' => 'disponivel']);
        if (!$actor) { $response->assertRedirectTo(site_url('login')); }
        elseif (!$allowed) { $response->assertStatus(403); }
        else { $response->assertStatus($method === 'GET' ? 200 : 303); }
        if (!$allowed) { $this->assertSame($before, $this->db->table('tbl_estoque_mov')->countAllResults()); }
    }

    public function testLifecycleAndReservedSerial(): void
    {
        $s = new MedidorService();
        $id = $s->save(['numero_med' => '  TEST-SERIAL  '], 1);
        $s->send($id, '2', 1);
        $this->requestAs(1, 'POST', "medidores/$id/enviar", ['destino' => '2'])->assertStatus(422);
        $s->returnToDepot($id, 'defeito', 1);
        $this->requestAs(1, 'POST', "medidores/$id/devolver", ['condicao' => 'disponivel'])->assertStatus(422);
        $s->save(['numero_med' => 'TEST-SERIAL', 'status_med' => 'disponivel'], 1, $id);
        $s->send($id, '1', 1);
        $s->returnToDepot($id, 'disponivel', 1);
        $s->delete($id, 1);
        $this->assertSame(6, $this->db->table('tbl_estoque_mov')->where('medidor_emv', $id)->countAllResults());
        $this->assertNotNull($this->db->table('tbl_medidor')->where('id_med', $id)->get()->getRow()->data_exclusao_med);
        $this->requestAs(1, 'POST', "medidores/$id/excluir")->assertStatus(404);
        $this->requestAs(1, 'POST', 'medidores', ['numero_med' => 'TEST-SERIAL'])->assertStatus(422);
    }

    public function testValidationAndStateBlocks(): void
    {
        foreach ([['numero_med' => ' '], ['numero_med' => 'MED-SP-1001'], ['numero_med' => str_repeat('x', 51)], ['numero_med' => 'X', 'status_med' => 'instalado'], ['numero_med' => 'X', 'localizacao_med' => 'cliente'], ['numero_med' => 'X', 'fabricante_med' => str_repeat('x', 81)]] as $input) {
            $this->requestAs(1, 'POST', 'medidores', $input)->assertStatus(422);
        }
        foreach ([['5/excluir', []], ['3/excluir', []], ['6/devolver', ['condicao' => 'disponivel']], ['1/enviar', ['destino' => '999']], ['3/devolver', ['condicao' => 'instalado']], ['1/enviar', ['destino' => ['1']]]] as [$path,$input]) {
            $this->requestAs(1, 'POST', 'medidores/' . $path, $input)->assertStatus(422);
        }
        $this->db->table('tbl_usuario')->where('id_usu', 4)->update(['ativo_usu' => 0]);
        $this->requestAs(1, 'POST', 'medidores/1/enviar', ['destino' => '2'])->assertStatus(422);
        $this->db->table('tbl_medidor')->where('id_med', 1)->update(['localizacao_med' => 'viatura']);
        $this->requestAs(1, 'POST', 'medidores/1/excluir')->assertStatus(422);
        $this->requestAs(1, 'GET', 'medidores/1')->assertSee('Estado legado incompatível');
    }

    public function testCsrfExpiryAndWrongVerbDoNotWrite(): void
    {
        foreach (['medidores', 'medidores/1/atualizar', 'medidores/1/enviar', 'medidores/3/devolver', 'medidores/1/excluir'] as $path) {
            $this->requestAs(1, 'POST', $path, [], false)->assertStatus(403);
            $this->requestAs(1, 'POST', $path, [], true, time()-7201)->assertRedirectTo(site_url('login'));
        }
        $this->requestAs(1, 'GET', 'medidores/1/excluir')->assertStatus(404);
        $this->assertSame(6, $this->db->table('tbl_medidor')->countAllResults());
    }

    public function testMovementFailureRollsBackEveryTransition(): void
    {
        $this->db->query("ALTER TABLE tbl_estoque_mov ADD CONSTRAINT fail_movement CHECK (observacao_emv NOT LIKE '%usuário #%')");
        foreach ([['medidores', ['numero_med' => 'ROLLBACK']], ['medidores/1/enviar', ['destino' => '2']], ['medidores/3/devolver', ['condicao' => 'defeito']], ['medidores/1/excluir', []]] as [$path, $input]) {
            $before = $this->db->table('tbl_medidor')->orderBy('id_med')->get()->getResultArray();
            $this->requestAs(1, 'POST', $path, $input)->assertStatus(422);
            $this->assertSame($before, $this->db->table('tbl_medidor')->orderBy('id_med')->get()->getResultArray());
        }
    }
}
