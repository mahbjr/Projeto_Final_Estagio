<?php

namespace Tests\Feature;

use App\Exceptions\FormException;
use App\Services\ChecklistService;
use App\Services\ClienteService;
use App\Services\FuncionarioService;
use App\Services\MedidorService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AppTestCase;

final class ConfirmacoesTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('tbl_usuario')->insert(['id_usu' => 90, 'nome_usu' => 'excluir@teste.example', 'senha_usu' => password_hash('senha123', PASSWORD_DEFAULT), 'papel_usu' => 'operador', 'ativo_usu' => 1]);
        $this->db->table('tbl_medidor')->insert(['id_med' => 90, 'numero_med' => 'EXCLUIR-TESTE', 'modelo_med' => 'Modelo teste', 'fabricante_med' => 'Fabricante teste', 'status_med' => 'disponivel', 'localizacao_med' => 'deposito']);
        $this->db->table('tbl_checklist')->insert(['id_chk' => 90, 'nome_chk' => 'Modelo de teste', 'tipo_os_chk' => 'corte', 'etapa_chk' => 'inicio', 'usuario_chk' => 1, 'ativo_chk' => 0]);
        $this->db->table('tbl_checklist_item')->insert(['id_chi' => 90, 'checklist_chi' => 90, 'pergunta_chi' => 'Pergunta?', 'nivel_chi' => 'informativo', 'ordem_chi' => 1]);
        $this->clearPendingWork();
    }

    private function snapshot(): array
    {
        $rows = [];
        foreach (['tbl_usuario', 'tbl_eletricista', 'tbl_cliente', 'tbl_medidor', 'tbl_estoque_mov', 'tbl_checklist_item'] as $table) {
            $rows[$table] = $this->db->table($table)->get()->getResultArray();
        }
        return $rows;
    }

    public static function deletions(): array
    {
        return [
            ['usuarios/90/excluir', 'tbl_usuario', 'id_usu', 90, 'data_exclusao_usu'],
            ['clientes/2/excluir', 'tbl_cliente', 'id_cli', 2, 'data_exclusao_cli'],
            ['medidores/90/excluir', 'tbl_medidor', 'id_med', 90, 'data_exclusao_med'],
            ['checklists/90/itens/90/excluir', 'tbl_checklist_item', 'id_chi', 90, 'data_exclusao_chi'],
        ];
    }

    #[DataProvider('deletions')]
    public function testEveryDeletionRequiresPasswordInManualPosts(string $path, string $table, string $key, int $id, string $deleted): void
    {
        $before = $this->snapshot();
        foreach ([[], ['senha_atual' => ''], ['senha_atual' => 'errada'], ['senha_atual' => ['senha123']], ['senha_atual' => str_repeat('x', 73)], ['_confirmacao' => 'confirmada']] as $data) {
            $response = $this->requestAs(1, 'POST', $path, $data);
            $response->assertStatus(422);
            $response->assertDontSee('value="errada"');
            $this->assertSame($before, $this->snapshot());
        }
        $this->requestAs(1, 'POST', $path, ['senha_atual' => 'senha123'])->assertStatus(303);
        $this->assertNotNull($this->db->table($table)->where($key, $id)->get()->getRowArray()[$deleted]);
    }

    #[DataProvider('deletions')]
    public function testFallbackIsReadOnlyAndNeverCarriesPassword(string $path, string $table, string $key, int $id, string $deleted): void
    {
        $before = $this->snapshot();
        $response = $this->requestAs(1, 'POST', $path, ['_confirmacao' => 'pendente', 'senha_atual' => 'segredoNaoPersistir', 'target' => 'https://evil.example']);
        $response->assertOK();
        $response->assertSee('Confirmar operação');
        $response->assertSee('value="confirmada"');
        $response->assertDontSee('segredoNaoPersistir');
        $response->assertDontSee('evil.example');
        $this->assertSame($before, $this->snapshot());
        $this->assertArrayNotHasKey('senha_atual', $_SESSION);
        $this->requestAs(1, 'POST', $path, ['_confirmacao' => 'confirmada', 'senha_atual' => 'senha123'])->assertStatus(303);
    }

    #[DataProvider('deletions')]
    public function testDeniedCsrfExpiredAndInactiveRequestsNeverWrite(string $path, string $table, string $key, int $id, string $deleted): void
    {
        $before = $this->snapshot();
        foreach ([null, 2, 3] as $actor) {
            $response = $this->requestAs($actor, 'POST', $path, ['senha_atual' => 'senha123', '_confirmacao' => 'pendente']);
            if ($actor === null) { $response->assertRedirectTo(site_url('login')); } else { $response->assertStatus(403); }
        }
        $this->requestAs(1, 'POST', $path, ['senha_atual' => 'senha123'], false)->assertStatus(403);
        $this->requestAs(1, 'POST', $path, ['senha_atual' => 'senha123'], true, time() - 7200)->assertRedirectTo(site_url('login'));
        $this->assertSame($before, $this->snapshot());
        $this->db->table('tbl_usuario')->where('id_usu', 1)->update(['ativo_usu' => 0]);
        $this->requestAs(1, 'POST', $path, ['senha_atual' => 'senha123'])->assertRedirectTo(site_url('login'));
        $this->assertNull($this->db->table($table)->where($key, $id)->get()->getRowArray()[$deleted]);
    }

    public function testServicesRejectMissingPasswordAndStaleManager(): void
    {
        $services = [
            fn () => (new FuncionarioService($this->db))->delete(90, 1),
            fn () => (new ClienteService($this->db))->delete(2, 1),
            fn () => (new MedidorService($this->db))->delete(90, 1),
            fn () => (new ChecklistService($this->db))->deleteItem(90, 90, 1),
        ];
        $before = $this->snapshot();
        foreach ($services as $call) {
            try { $call(); $this->fail('Senha ausente deve impedir exclusão pelo Service.'); }
            catch (FormException $e) { $this->assertArrayHasKey('senha_atual', $e->errors); }
            $this->assertSame($before, $this->snapshot());
        }
        $this->db->table('tbl_usuario')->where('id_usu', 1)->update(['papel_usu' => 'operador']);
        $before = $this->snapshot();
        try { (new ClienteService($this->db))->delete(2, 1, 'senha123'); $this->fail('Conta sem papel Gestor não pode excluir.'); }
        catch (FormException $e) { $this->assertArrayHasKey('operacao', $e->errors); }
        $this->assertSame($before, $this->snapshot());
    }

    public function testPasswordIsRecheckedForEachDeletionAgainstCurrentHash(): void
    {
        $this->requestAs(1, 'POST', 'usuarios/90/excluir', ['senha_atual' => 'senha123'])->assertStatus(303);
        $this->requestAs(1, 'POST', 'medidores/90/excluir')->assertStatus(422);
        $this->db->table('tbl_usuario')->where('id_usu', 1)->update(['senha_usu' => password_hash('novaSenha123', PASSWORD_DEFAULT)]);
        $this->requestAs(1, 'POST', 'medidores/90/excluir', ['senha_atual' => 'senha123'])->assertStatus(422);
        $this->assertNull($this->db->table('tbl_medidor')->where('id_med', 90)->get()->getRowArray()['data_exclusao_med']);
        $this->requestAs(1, 'POST', 'medidores/90/excluir', ['senha_atual' => 'novaSenha123'])->assertStatus(303);
    }

    public function testOperationConfirmationHasNoPasswordAndEscapesPayload(): void
    {
        $this->db->table('tbl_os')->where('id_oss', 1)->update(['status_oss' => 'aberta', 'eletricista_oss' => null]);
        $before = $this->db->table('tbl_os')->get()->getResultArray();
        $response = $this->requestAs(1, 'POST', 'os/1/cancelar', ['_confirmacao' => 'pendente', 'motivo' => '<script>alert(1)</script>', 'senha_atual' => 'segredo']);
        $response->assertOK();
        $response->assertSee('&lt;script&gt;');
        $response->assertDontSee('id="senha_atual"');
        $response->assertDontSee('segredo');
        $this->assertSame($before, $this->db->table('tbl_os')->get()->getResultArray());
        $this->requestAs(1, 'POST', 'os/1/cancelar', ['_confirmacao' => 'pendente', 'motivo' => ['array']])->assertStatus(422);
        $this->requestAs(1, 'POST', 'os/1/cancelar', ['_confirmacao' => 'confirmada', 'motivo' => 'Cancelamento confirmado.'])->assertStatus(303);
    }
}
