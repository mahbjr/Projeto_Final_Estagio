<?php

namespace Tests\Support;

use App\Libraries\DemoDatabase;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

abstract class AppTestCase extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $config = config('Database');
        $name = $config->tests['database'];
        if ($config->tests['DBDriver'] !== 'MySQLi' || !str_ends_with($name, '_tests') || in_array($name, [$config->default['database'], $config->demo['database']], true)) {
            throw new \RuntimeException('Configure um banco MySQL separado com sufixo _tests. Os testes não podem usar default/demo.');
        }
        $this->db = Database::connect('tests');
        DemoDatabase::execute($this->db, dirname(ROOTPATH) . '/database/schema.sql');
        DemoDatabase::execute($this->db, dirname(ROOTPATH) . '/database/seeds.sql');
        helper(['app', 'form', 'heroicon']);
    }

    protected function requestAs(?int $userId, string $method, string $path, array $data = [], bool $csrf = true, ?int $lastActivity = null)
    {
        $session = $userId ? ['auth_user_id' => $userId, 'auth_last_activity' => $lastActivity ?? time()] : [];
        if ($csrf) {
            $name = config('Security')->tokenName;
            $token = service('security')->getHash();
            $session[$name] = $token;
            if ($method === 'POST') { $data[$name] = $token; }
        }
        try {
            return $this->withSession($session)->call($method, $path, $data);
        } catch (\CodeIgniter\Exceptions\PageNotFoundException $e) {
            // CI4 feature testing rethrows 404s; the normal web handler returns HTTP 404.
            $this->assertSame(404, $e->getCode());
            return new \CodeIgniter\Test\TestResponse(service('response')->setStatusCode(404));
        }
    }

    protected function clientInput(array $overrides = []): array
    {
        return $overrides + [
            'nome_cli' => 'Empresa de Teste Ltda', 'cnpj_cli' => 'ZZ12AB34000156',
            'email_cli' => 'contato@teste.example', 'telefone_cli' => '(85) 3333-4444',
            'endereco_cli' => 'Rua de Teste, 100', 'bairro_cli' => 'Centro', 'cidade_cli' => 'Fortaleza',
            'estado_cli' => 'CE', 'cep_cli' => '60010-000', 'status_cli' => 'ativo',
        ];
    }

    protected function userInput(array $overrides = []): array
    {
        return $overrides + ['nome_usu' => 'novo@teste.example', 'papel_usu' => 'operador', 'ativo_usu' => '1', 'senha' => 'novaSenha123', 'confirmacao' => 'novaSenha123'];
    }

    protected function technicalInput(array $overrides = []): array
    {
        return $overrides + $this->userInput(['papel_usu' => 'eletricista']) + ['nome_ele' => 'Técnico de Teste', 'cpf_ele' => '12345678900', 'telefone_ele' => '(85) 99999-0000', 'matricula_ele' => 'ELE-TESTE-01'];
    }

    protected function clearPendingWork(): void
    {
        $this->db->table('tbl_os')->update(['status_oss' => 'concluida']);
        $this->db->table('tbl_medidor')->update(['eletricista_posse_med' => null]);
    }
}
