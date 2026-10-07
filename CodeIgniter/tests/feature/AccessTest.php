<?php

namespace Tests\Feature;

use App\Libraries\PermissionPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AppTestCase;

final class AccessTest extends AppTestCase
{
    public static function routeCases(): iterable
    {
        foreach ([null, 1, 2, 3] as $id) {
            foreach ([
                ['GET', '/', 'common'], ['GET', 'inicio', 'common'], ['POST', 'logout', 'common'],
                ['GET', 'usuarios', 'users'], ['GET', 'usuarios/novo', 'users'], ['GET', 'usuarios/2', 'users'], ['GET', 'usuarios/2/editar', 'users'],
                ['POST', 'usuarios', 'users'], ['POST', 'usuarios/2/atualizar', 'users'], ['POST', 'usuarios/2/excluir', 'users'],
                ['GET', 'clientes', 'clients'], ['GET', 'clientes/novo', 'clients'], ['GET', 'clientes/2', 'clients'], ['GET', 'clientes/2/editar', 'clients'],
                ['POST', 'clientes', 'clients'], ['POST', 'clientes/2/atualizar', 'clients'], ['POST', 'clientes/2/excluir', 'delete'],
            ] as [$method, $path, $scope]) {
                $allowed = $id === 1 || ($scope === 'common' && $id !== null) || ($scope === 'clients' && $id === 2);
                yield ($id ?? 'visitante') . ' ' . $method . ' ' . $path => [$id, $method, $path, $allowed];
            }
        }
    }

    #[DataProvider('routeCases')]
    public function testEveryRouteWithRealFilters(?int $id, string $method, string $path, bool $allowed): void
    {
        $beforeUsers = $this->db->table('tbl_usuario')->get()->getResultArray();
        $beforeClients = $this->db->table('tbl_cliente')->get()->getResultArray();
        $response = $this->requestWithDeletionPassword($id, $method, $path);
        if ($id === null) {
            $response->assertRedirectTo(site_url('login'));
        } elseif (!$allowed) {
            $response->assertStatus(403);
        } else {
            // Empty writes may fail validation; the permission filter must allow them through.
            $this->assertContains($response->response()->getStatusCode(), [200, 302, 303, 422]);
        }
        if (!$allowed) {
            $this->assertSame($beforeUsers, $this->db->table('tbl_usuario')->get()->getResultArray());
            $this->assertSame($beforeClients, $this->db->table('tbl_cliente')->get()->getResultArray());
        }
    }

    public function testUnregisteredActionIsDenied(): void
    {
        $this->assertFalse(PermissionPolicy::allows('nova.acao', 'gestor'));
    }

    public function testCsrfMissingOrIncorrectDoesNotWrite(): void
    {
        $this->requestAs(null, 'POST', 'login', ['identificador' => 'gestor@energia.com.br', 'senha' => 'senha123'], false)->assertStatus(403);
        $this->requestAs(1, 'POST', 'clientes', $this->clientInput(), false)->assertStatus(403);
        $this->requestAs(1, 'POST', 'clientes', $this->clientInput([config('Security')->tokenName => 'incorreto']), false)->assertStatus(403);
        $this->assertSame(3, $this->db->table('tbl_cliente')->countAllResults());
    }

    public function testNoWriteActionsAreAvailableThroughGet(): void
    {
        foreach (['logout', 'clientes/2/excluir', 'clientes/2/atualizar', 'usuarios/2/excluir', 'usuarios/2/atualizar', 'AuthController/logout'] as $path) {
            $this->requestWithDeletionPassword(1, 'GET', $path)->assertStatus(404);
        }
        $this->assertSame(4, $this->db->table('tbl_usuario')->where('data_exclusao_usu', null)->countAllResults());
    }

    public function testMenusFollowTheSamePolicy(): void
    {
        $operator = $this->requestAs(2, 'GET', 'inicio');
        $operator->assertSee('Empresas clientes');
        $operator->assertDontSee('Acessar equipe');
        $electrician = $this->requestAs(3, 'GET', 'inicio');
        $electrician->assertSee('Seu espaço de atendimento');
        $electrician->assertDontSee('Acessar clientes');
        $operatorClient = $this->requestAs(2, 'GET', 'clientes/2');
        $operatorClient->assertDontSee('Excluir empresa');
    }

    public function testProtectedPagesAreNotCached(): void
    {
        $response = $this->requestAs(1, 'GET', 'clientes');
        $this->assertStringContainsString('no-store', $response->response()->getHeaderLine('Cache-Control'));
    }
}
