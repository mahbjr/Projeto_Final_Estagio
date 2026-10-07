<?php

namespace Tests\Feature;

use App\Exceptions\FormException;
use App\Services\FuncionarioService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AppTestCase;

final class UsuariosTest extends AppTestCase
{
    public function testCreationHashesPasswordAndIgnoresExtraFields(): void
    {
        $input = $this->userInput(['id_usu' => 1, 'senha_usu' => 'texto', 'data_exclusao_usu' => '2026-01-01']);
        $this->requestAs(1, 'POST', 'usuarios', $input)->assertStatus(303);
        $created = $this->db->table('tbl_usuario')->where('nome_usu', $input['nome_usu'])->get()->getRowArray();
        $this->assertNotSame('novaSenha123', $created['senha_usu']);
        $this->assertTrue(password_verify('novaSenha123', $created['senha_usu']));
        $this->assertNull($created['data_exclusao_usu']);
        $this->requestAs(null, 'POST', 'login', ['identificador' => $input['nome_usu'], 'senha' => 'novaSenha123'])->assertStatus(303);
        $this->requestAs(1, 'GET', 'usuarios/' . $created['id_usu'])->assertDontSee($created['senha_usu']);
    }

    public function testTechnicalCreationIsAtomicAndNormalizesCpf(): void
    {
        $input = $this->technicalInput();
        $this->requestAs(1, 'POST', 'usuarios', $input)->assertStatus(303);
        $created = $this->db->table('tbl_usuario')->where('nome_usu', $input['nome_usu'])->get()->getRowArray();
        $technical = $this->db->table('tbl_eletricista')->where('usuario_ele', $created['id_usu'])->get()->getRowArray();
        $this->assertSame('123.456.789-00', $created['cpf_usu']);
        $this->requestAs(null, 'POST', 'login', ['identificador' => $input['nome_usu'], 'senha' => 'novaSenha123'])->assertStatus(303);
        $this->requestAs(1, 'POST', 'usuarios', $this->technicalInput(['nome_usu' => 'segundo@teste.example', 'cpf_usu' => '123.456.789-00', 'matricula_ele' => 'ELE-TESTE-02']))->assertStatus(422);
        $this->assertSame(0, $this->db->table('tbl_usuario')->where('nome_usu', 'segundo@teste.example')->countAllResults());
    }

    public function testDatabaseFailureRollsBackBothAccountAndTechnicalData(): void
    {
        // Exercise a failure AFTER account insertion, rather than only prevalidation.
        $this->db->query("ALTER TABLE tbl_eletricista ADD CONSTRAINT fail_technical CHECK (matricula_ele <> 'ELE-TESTE-01')");
        try {
            $this->requestAs(1, 'POST', 'usuarios', $this->technicalInput())->assertStatus(422);
            $this->assertSame(0, $this->db->table('tbl_usuario')->where('nome_usu', 'novo@teste.example')->countAllResults());
            $this->assertSame(2, $this->db->table('tbl_eletricista')->countAllResults());
        } finally { $this->db->query('ALTER TABLE tbl_eletricista DROP CHECK fail_technical'); }
    }

    public function testBlankPasswordPreservesHashAndNewPasswordWorks(): void
    {
        $existing = $this->db->table('tbl_usuario')->where('id_usu', 2)->get()->getRowArray();
        $input = ['nome_usu' => $existing['nome_usu'], 'papel_usu' => 'operador', 'ativo_usu' => '1', 'senha' => '', 'confirmacao' => ''] + $existing;
        $this->requestAs(1, 'POST', 'usuarios/2/atualizar', $input)->assertStatus(303);
        $this->assertSame($existing['senha_usu'], $this->db->table('tbl_usuario')->where('id_usu', 2)->get()->getRowArray()['senha_usu']);
        $input['senha'] = $input['confirmacao'] = 'alterada123';
        $this->requestAs(1, 'POST', 'usuarios/2/atualizar', $input)->assertStatus(303);
        $this->requestAs(null, 'POST', 'login', ['identificador' => $existing['nome_usu'], 'senha' => 'alterada123'])->assertStatus(303);
        $this->requestAs(null, 'POST', 'login', ['identificador' => $existing['nome_usu'], 'senha' => 'senha123'])->assertStatus(422);
    }

    public function testRoleChangesAndDisabledElectricianField(): void
    {
        $input = $this->userInput(['nome_usu' => 'operador@energia.com.br', 'papel_usu' => 'gestor', 'senha' => '', 'confirmacao' => '']);
        $this->requestAs(1, 'POST', 'usuarios/2/atualizar', $input)->assertStatus(303);
        $input['papel_usu'] = 'operador';
        $this->requestAs(1, 'POST', 'usuarios/2/atualizar', $input)->assertStatus(303);
        $input['papel_usu'] = 'eletricista';
        $this->requestAs(1, 'POST', 'usuarios/2/atualizar', $input)->assertStatus(422);
        $technical = $this->db->table('tbl_eletricista')->where('id_ele', 1)->get()->getRowArray();
        $input = $technical + ['nome_usu' => 'eletricista1@energia.com.br', 'ativo_usu' => '1', 'senha' => '', 'confirmacao' => ''] + $this->db->table('tbl_usuario')->where('id_usu', 3)->get()->getRowArray();
        $this->requestAs(1, 'POST', 'usuarios/3/atualizar', $input)->assertStatus(303);
        $input['papel_usu'] = 'gestor';
        $this->requestAs(1, 'POST', 'usuarios/3/atualizar', $input)->assertStatus(422);
        $this->assertSame('eletricista', $this->db->table('tbl_usuario')->where('id_usu', 3)->get()->getRowArray()['papel_usu']);
    }

    public function testOwnAccessAndLastManagerCannotBeRemoved(): void
    {
        $this->requestWithDeletionPassword(1, 'POST', 'usuarios/1/excluir')->assertStatus(422);
        $this->requestAs(1, 'POST', 'usuarios/1/atualizar', $this->userInput(['nome_usu' => 'gestor@energia.com.br', 'papel_usu' => 'operador']))->assertStatus(422);
        $this->requestAs(1, 'POST', 'usuarios/1/atualizar', $this->userInput(['nome_usu' => 'gestor@energia.com.br', 'papel_usu' => 'gestor', 'ativo_usu' => '0']))->assertStatus(422);
        $this->expectException(FormException::class);
        (new FuncionarioService())->delete(1, 2);
    }

    public function testPersonalFieldsRequiredForAllRolesAndReservedAfterDeletion(): void
    {
        foreach (['gestor', 'operador', 'eletricista'] as $role) {
            foreach (['nome_completo_usu', 'cpf_usu', 'cargo_usu'] as $field) {
                $this->requestAs(1, 'POST', 'usuarios', $this->technicalInput(['papel_usu' => $role, $field => '   ']))->assertStatus(422);
            }
        }
        $this->requestAs(1, 'POST', 'usuarios', $this->userInput(['cpf_usu' => '123 45678900']))->assertStatus(422);
        $this->requestAs(1, 'POST', 'usuarios', $this->userInput())->assertStatus(303);
        $id = (int) $this->db->table('tbl_usuario')->where('nome_usu', 'novo@teste.example')->get()->getRow()->id_usu;
        $this->requestWithDeletionPassword(1, 'POST', "usuarios/$id/excluir")->assertStatus(303);
        $this->requestAs(1, 'POST', 'usuarios', $this->userInput(['nome_usu' => 'outro@teste.example']))->assertStatus(422);
        $this->db->table('tbl_usuario')->where('id_usu', 2)->update(['nome_completo_usu' => null, 'cpf_usu' => null, 'cargo_usu' => null]);
        $this->requestAs(2, 'GET', 'inicio')->assertStatus(200);
        $this->requestAs(1, 'POST', 'usuarios/2/atualizar', ['nome_usu' => 'operador@energia.com.br', 'papel_usu' => 'operador', 'ativo_usu' => '1'])->assertStatus(422);
    }

    public static function statuses(): array
    {
        return [['aberta', true], ['atribuida', true], ['em_atendimento', true], ['encerrada', false], ['cancelada', false]];
    }

    #[DataProvider('statuses')]
    public function testEachOsStatusControlsElectricianDeletion(string $status, bool $blocked): void
    {
        $this->db->table('tbl_medidor')->update(['eletricista_posse_med' => null]);
        $this->db->table('tbl_os')->where('eletricista_oss', 1)->update(['status_oss' => $status]);
        $this->requestWithDeletionPassword(1, 'POST', 'usuarios/3/excluir')->assertStatus($blocked ? 422 : 303);
        $user = $this->db->table('tbl_usuario')->where('id_usu', 3)->get()->getRowArray();
        $technical = $this->db->table('tbl_eletricista')->where('id_ele', 1)->get()->getRowArray();
        $this->assertSame($blocked, $user['data_exclusao_usu'] === null);
        $this->assertSame($blocked, $technical['data_exclusao_ele'] === null);
        $this->assertSame(2, $this->db->table('tbl_os')->where('eletricista_oss', 1)->countAllResults());
    }

    public function testCustodyAndPendingOrdersBlockDeactivation(): void
    {
        $technical = $this->db->table('tbl_eletricista')->where('id_ele', 1)->get()->getRowArray();
        $input = $technical + ['nome_usu' => 'eletricista1@energia.com.br', 'ativo_usu' => '0', 'senha' => '', 'confirmacao' => ''] + $this->db->table('tbl_usuario')->where('id_usu', 3)->get()->getRowArray();
        $this->requestAs(1, 'POST', 'usuarios/3/atualizar', $input)->assertStatus(422);
        $this->db->table('tbl_os')->update(['status_oss' => 'encerrada']);
        $this->requestWithDeletionPassword(1, 'POST', 'usuarios/3/excluir')->assertStatus(422);
        $this->db->table('tbl_medidor')->update(['eletricista_posse_med' => null]);
        $this->requestAs(1, 'POST', 'usuarios/3/atualizar', $input)->assertStatus(303);
        $this->assertNull($this->db->table('tbl_eletricista')->where('id_ele', 1)->get()->getRowArray()['data_exclusao_ele']);
        $this->requestAs(3, 'GET', 'inicio')->assertRedirectTo(site_url('login'));
        $this->requestWithDeletionPassword(1, 'POST', 'usuarios/3/excluir')->assertStatus(303);
        $this->requestAs(1, 'GET', 'usuarios/3')->assertStatus(404);
    }

    public function testValidationAndNoSensitiveFormPersistence(): void
    {
        foreach ([['senha' => 'curta', 'confirmacao' => 'curta'], ['confirmacao' => 'outra'], ['senha' => str_repeat('é', 37), 'confirmacao' => str_repeat('é', 37)], ['nome_usu' => 'gestor@energia.com.br'], ['papel_usu' => 'admin']] as $override) {
            $response = $this->requestAs(1, 'POST', 'usuarios', $this->userInput($override));
            $response->assertStatus(422);
            $response->assertDontSee('value="novaSenha123"');
            $response->assertDontSee('value="curta"');
        }
        $this->assertSame(4, $this->db->table('tbl_usuario')->countAllResults());
    }
}
