<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AppTestCase;

final class AuthTest extends AppTestCase
{
    public static function users(): array
    {
        return [[1, 'gestor@energia.com.br'], [2, 'operador@energia.com.br'], [3, 'eletricista1@energia.com.br']];
    }

    #[DataProvider('users')]
    public function testLoginRegeneratesSessionForEachRole(int $id, string $name): void
    {
        $response = $this->requestAs(null, 'POST', 'login', ['identificador' => $name, 'senha' => 'senha123']);
        $response->assertStatus(303);
        $response->assertRedirectTo(site_url('inicio'));
        $response->assertSessionHas('auth_user_id', $id);
        $this->assertTrue(service('session')->didRegenerate);
        $this->assertArrayNotHasKey('senha_usu', $_SESSION);
        $this->assertArrayNotHasKey('senha_usu', service('auth')->user());
    }

    public static function invalidAccounts(): array
    {
        return [['wrong_password'], ['missing_user'], ['inactive'], ['deleted'], ['missing_technical'], ['deleted_technical']];
    }

    #[DataProvider('invalidAccounts')]
    public function testInvalidAccountsUseGenericError(string $scenario): void
    {
        $name = 'gestor@energia.com.br';
        $password = 'senha123';
        if ($scenario === 'wrong_password') { $password = 'senhaErrada'; }
        if ($scenario === 'missing_user') { $name = 'ausente@teste.example'; }
        if ($scenario === 'inactive') { $this->db->table('tbl_usuario')->where('id_usu', 1)->update(['ativo_usu' => 0]); }
        if ($scenario === 'deleted') { $this->db->table('tbl_usuario')->where('id_usu', 1)->update(['data_exclusao_usu' => date('Y-m-d H:i:s')]); }
        if (in_array($scenario, ['missing_technical', 'deleted_technical'], true)) {
            $name = 'eletricista1@energia.com.br';
            $this->db->table('tbl_eletricista')->where('id_ele', 1)->update($scenario === 'missing_technical' ? ['usuario_ele' => null] : ['data_exclusao_ele' => date('Y-m-d H:i:s')]);
        }
        $response = $this->requestAs(null, 'POST', 'login', ['identificador' => $name, 'senha' => $password]);
        $response->assertStatus(422);
        $response->assertSee('Usuário ou senha inválidos.');
        $response->assertSessionMissing('auth_user_id');
        $response->assertDontSee('value="' . $password . '"');
    }

    public function testExpiredSessionIsDestroyed(): void
    {
        $response = $this->requestAs(1, 'GET', 'inicio', [], true, time() - 7200);
        $response->assertRedirectTo(site_url('login'));
        $response->assertSessionMissing('auth_user_id');
    }

    public function testLogoutRemovesAuthenticationAndExpiresCookie(): void
    {
        $response = $this->requestAs(1, 'POST', 'logout');
        $response->assertStatus(303);
        $response->assertSessionMissing('auth_user_id');
        $this->assertTrue($response->response()->getCookie(config('Session')->cookieName)->isExpired());
        $this->withSession($_SESSION)->get('inicio')->assertRedirectTo(site_url('login'));
    }

    public function testDatabaseRoleAndActivityAreReadOnNextRequest(): void
    {
        $this->requestAs(1, 'GET', 'usuarios')->assertOK();
        $this->db->table('tbl_usuario')->where('id_usu', 1)->update(['papel_usu' => 'operador']);
        $this->requestAs(1, 'GET', 'usuarios')->assertStatus(403);
        $this->db->table('tbl_usuario')->where('id_usu', 1)->update(['ativo_usu' => 0]);
        $this->requestAs(1, 'GET', 'inicio')->assertRedirectTo(site_url('login'));
    }

    public function testLoginUpdatesAnOutdatedHash(): void
    {
        $hash = password_hash('senha123', PASSWORD_BCRYPT, ['cost' => 4]);
        $this->db->table('tbl_usuario')->where('id_usu', 1)->update(['senha_usu' => $hash]);
        $this->requestAs(null, 'POST', 'login', ['identificador' => 'gestor@energia.com.br', 'senha' => 'senha123'])->assertStatus(303);
        $updated = $this->db->table('tbl_usuario')->where('id_usu', 1)->get()->getRowArray()['senha_usu'];
        $this->assertNotSame($hash, $updated);
        $this->assertTrue(password_verify('senha123', $updated));
    }
}
