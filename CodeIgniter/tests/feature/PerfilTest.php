<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AppTestCase;

final class PerfilTest extends AppTestCase
{
    public static function roles(): array { return [[1], [2], [3]]; }

    private function input(int $id = 1, array $overrides = []): array
    {
        $user = $this->db->table('tbl_usuario')->where('id_usu', $id)->get()->getRowArray();
        return $overrides + ['nome_completo_usu' => 'Nome Atualizado', 'telefone_usu' => '(85) 99999-1234', 'nome_usu' => $user['nome_usu'], 'senha_atual' => '', 'senha' => '', 'confirmacao' => ''];
    }

    #[DataProvider('roles')]
    public function testEachRoleEditsOnlyItsOwnPersonalData(int $id): void
    {
        $before = $this->db->table('tbl_usuario')->orderBy('id_usu')->get()->getResultArray();
        $response = $this->requestAs($id, 'GET', 'perfil'); $response->assertOK(); $response->assertSee('Editar perfil');
        $this->requestAs($id, 'POST', 'perfil/atualizar', $this->input($id))->assertStatus(303);
        $after = $this->db->table('tbl_usuario')->orderBy('id_usu')->get()->getResultArray();
        foreach ($before as $i => $user) {
            if ((int) $user['id_usu'] !== $id) { $this->assertSame($user, $after[$i]); continue; }
            $this->assertSame('Nome Atualizado', $after[$i]['nome_completo_usu']);
            foreach (['senha_usu', 'nome_usu', 'cpf_usu', 'cargo_usu', 'papel_usu', 'ativo_usu'] as $field) { $this->assertSame($user[$field], $after[$i][$field]); }
        }
        $path = $id === 3 ? 'os' : 'inicio';
        $response = $this->requestAs($id, 'GET', $path); $response->assertSee('Nome Atualizado');
    }

    public function testCredentialChangesRequireCurrentPasswordAndRegenerateSession(): void
    {
        $input = $this->input(1, ['nome_usu' => 'perfil@teste.example', 'senha' => 'senhaNova123', 'confirmacao' => 'senhaNova123']);
        $response = $this->requestAs(1, 'POST', 'perfil/atualizar', $input); $response->assertStatus(422); $response->assertSee('Informe sua senha atual');
        $input['senha_atual'] = 'senha123';
        $this->requestAs(1, 'POST', 'perfil/atualizar', $input)->assertStatus(303);
        $this->assertTrue(service('session')->didRegenerate);
        $saved = $this->db->table('tbl_usuario')->where('id_usu', 1)->get()->getRowArray();
        $this->assertTrue(password_verify('senhaNova123', $saved['senha_usu']));
        $this->assertSame('perfil@teste.example', $saved['nome_usu']);
        $this->assertArrayNotHasKey('senha_usu', service('auth')->user());
        $this->requestAs(null, 'POST', 'login', ['identificador' => 'perfil@teste.example', 'senha' => 'senhaNova123'])->assertStatus(303);
    }

    public function testAdministrativeAndIdentityFieldsAreRejectedWithoutWrites(): void
    {
        $before = $this->db->table('tbl_usuario')->get()->getResultArray();
        foreach (['id_usu' => '2', 'usuario' => '2', 'papel_usu' => 'gestor', 'ativo_usu' => '0', 'cpf_usu' => '11111111111', 'cargo_usu' => 'Diretor', 'matricula_ele' => 'X', 'senha_usu' => 'X'] as $field => $value) {
            $this->requestAs(2, 'POST', 'perfil/atualizar', $this->input(2, [$field => $value]))->assertStatus(422);
            $this->assertSame($before, $this->db->table('tbl_usuario')->get()->getResultArray());
        }
    }

    public function testInvalidAndReservedIdentifiersAndLegacyLogin(): void
    {
        $this->db->table('tbl_usuario')->where('id_usu', 2)->update(['data_exclusao_usu' => date('Y-m-d H:i:s')]);
        foreach (['operador@energia.com.br', 'identificador-sem-email'] as $login) {
            $this->requestAs(1, 'POST', 'perfil/atualizar', $this->input(1, ['nome_usu' => $login, 'senha_atual' => 'senha123']))->assertStatus(422);
        }
        $this->db->table('tbl_usuario')->where('id_usu', 1)->update(['nome_usu' => 'gestor-legado']);
        $this->requestAs(1, 'POST', 'perfil/atualizar', $this->input())->assertStatus(303);
        $this->assertSame('gestor-legado', $this->db->table('tbl_usuario')->where('id_usu', 1)->get()->getRowArray()['nome_usu']);
    }

    public function testErrorsDoNotExposePasswordsOrUnsafeInput(): void
    {
        $input = $this->input(1, ['nome_completo_usu' => '<script>alert(1)</script>', 'senha' => 'curta', 'confirmacao' => 'curta', 'senha_atual' => 'segredoIncorreto']);
        $response = $this->requestAs(1, 'POST', 'perfil/atualizar', $input);
        $response->assertStatus(422); $response->assertSee('&lt;script&gt;'); $response->assertDontSee('value="curta"'); $response->assertDontSee('value="segredoIncorreto"');
        foreach (['senha', 'senha_atual', 'confirmacao', 'senha_usu'] as $field) { $this->assertArrayNotHasKey($field, $_SESSION); }
        foreach ([['nome_usu' => ['array']], ['nome_completo_usu' => ''], ['senha' => 'senhaValida123', 'confirmacao' => 'diferente'], ['senha' => str_repeat('x', 73), 'confirmacao' => str_repeat('x', 73)] ] as $values) {
            $this->requestAs(1, 'POST', 'perfil/atualizar', $this->input(1, $values))->assertStatus(422);
        }
    }

    public function testAuthenticationCsrfAndInactiveAccountsBlockWrites(): void
    {
        $before = $this->db->table('tbl_usuario')->get()->getResultArray();
        $this->requestAs(null, 'GET', 'perfil')->assertRedirectTo(site_url('login'));
        $this->requestAs(null, 'POST', 'perfil/atualizar', $this->input())->assertRedirectTo(site_url('login'));
        $this->requestAs(1, 'GET', 'perfil', [], true, time() - 7200)->assertRedirectTo(site_url('login'));
        $this->requestAs(1, 'POST', 'perfil/atualizar', $this->input(), false)->assertStatus(403);
        $this->assertSame($before, $this->db->table('tbl_usuario')->get()->getResultArray());
        $this->db->table('tbl_usuario')->where('id_usu', 2)->update(['ativo_usu' => 0]);
        $this->requestAs(2, 'POST', 'perfil/atualizar', $this->input(2))->assertRedirectTo(site_url('login'));
        $this->assertNotSame('Nome Atualizado', $this->db->table('tbl_usuario')->where('id_usu', 2)->get()->getRowArray()['nome_completo_usu']);
    }

    public function testProfileFormAndDialogHaveDistinctFieldIds(): void
    {
        $response = $this->requestAs(1, 'GET', 'usuarios/1/editar');
        $response->assertOK(); $response->assertSee('id="nome_usu"'); $response->assertSee('id="perfil-modal-nome_usu"'); $response->assertSee('for="perfil-modal-nome_usu"');
        $response = $this->requestAs(1, 'GET', 'perfil');
        $response->assertOK(); $response->assertDontSee('id="profile-dialog"'); $response->assertSee('id="perfil-nome_usu"');
        $this->assertStringContainsString('no-store', $response->response()->getHeaderLine('Cache-Control'));
    }
}
