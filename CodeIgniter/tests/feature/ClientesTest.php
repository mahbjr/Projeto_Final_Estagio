<?php

namespace Tests\Feature;

use App\Libraries\Identifiers;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AppTestCase;

final class ClientesTest extends AppTestCase
{
    public static function cnpjs(): array
    {
        return [
            ['34567890000112', '34567890000112', true],
            [' zz.12a.b34/0001-56 ', 'ZZ12AB34000156', true],
            ['ZZ12AB34000156', 'ZZ12AB34000156', true],
            ['', '', false], ['123', '123', false], ['ZZ12AB3400015A', '', false],
            ['ZZ12AB340001567', '', false], ['ZZ12AB34000!56', '', false],
            ['ZZ12 AB34000156', '', false], ['ZZ12ÁB34000156', '', false],
            ['ZZ12AB34000156%', '', false],
        ];
    }

    #[DataProvider('cnpjs')]
    public function testCnpjFormatAndNormalization(string $value, string $expected, bool $valid): void
    {
        $response = $this->requestAs(2, 'POST', 'clientes', $this->clientInput(['cnpj_cli' => $value]));
        $response->assertStatus($valid ? 303 : 422);
        $row = $this->db->table('tbl_cliente')->where('nome_cli', 'Empresa de Teste Ltda')->get()->getRowArray();
        if ($valid) { $this->assertSame($expected, $row['cnpj_cli']); }
        else { $this->assertNull($row); }
    }

    public function testDuplicateMasksAndExcludedIdentifiersRemainReserved(): void
    {
        $this->requestAs(1, 'POST', 'clientes', $this->clientInput(['cnpj_cli' => '12.345.678/0001-90']))->assertStatus(422);
        $this->requestWithDeletionPassword(1, 'POST', 'clientes/2/excluir')->assertStatus(303);
        $this->requestAs(1, 'POST', 'clientes', $this->clientInput(['cnpj_cli' => '23.456.789/0001-80']))->assertStatus(422);
        $this->assertSame('12345678000190', Identifiers::cnpj('12.345.678/0001-90'));
    }

    public function testEditIgnoresPostedIdAndAllowsOwnCnpj(): void
    {
        $existing = $this->db->table('tbl_cliente')->where('id_cli', 2)->get()->getRowArray();
        $this->requestAs(2, 'POST', 'clientes/2/atualizar', $existing + ['id_cli' => 1])->assertStatus(303);
        $existing['cnpj_cli'] = '12345678000190';
        $existing['id_cli'] = 1;
        $this->requestAs(2, 'POST', 'clientes/2/atualizar', $existing)->assertStatus(422);
        $this->assertSame('23456789000180', $this->db->table('tbl_cliente')->where('id_cli', 2)->get()->getRowArray()['cnpj_cli']);
    }

    public function testUntrustedExtraFieldsDoNotReachDatabase(): void
    {
        $input = $this->clientInput(['tipo_cli' => 'pessoa_fisica', 'cpf_cli' => '123', 'unidade_consumidora_cli' => 'IGNORADA', 'id_cli' => 1, 'data_exclusao_cli' => '2026-01-01']);
        $this->requestAs(1, 'POST', 'clientes', $input)->assertStatus(303);
        $created = $this->db->table('tbl_cliente')->where('cnpj_cli', $input['cnpj_cli'])->get()->getRowArray();
        $this->assertNotSame(1, (int) $created['id_cli']);
        $this->assertNull($created['data_exclusao_cli']);
        $this->assertArrayNotHasKey('cpf_cli', $created);
    }

    public static function statuses(): array
    {
        return [['aberta', true], ['atribuida', true], ['em_atendimento', true], ['encerrada', false], ['cancelada', false]];
    }

    #[DataProvider('statuses')]
    public function testEachOsStatusControlsDeletion(string $status, bool $blocked): void
    {
        $this->db->table('tbl_os')->where('cliente_oss', 1)->update(['status_oss' => $status]);
        $this->requestWithDeletionPassword(1, 'POST', 'clientes/1/excluir')->assertStatus($blocked ? 422 : 303);
        $row = $this->db->table('tbl_cliente')->where('id_cli', 1)->get()->getRowArray();
        $this->assertSame($blocked, $row['data_exclusao_cli'] === null);
        $this->assertSame(2, $this->db->table('tbl_os')->where('cliente_oss', 1)->countAllResults());
    }

    public function testInactivationAndSoftDeletedOrders(): void
    {
        $input = $this->db->table('tbl_cliente')->where('id_cli', 1)->get()->getRowArray();
        $input['status_cli'] = 'inativo';
        $this->requestAs(2, 'POST', 'clientes/1/atualizar', $input)->assertStatus(422);
        $this->db->table('tbl_os')->where('cliente_oss', 1)->update(['data_exclusao_oss' => date('Y-m-d H:i:s')]);
        $this->requestAs(2, 'POST', 'clientes/1/atualizar', $input)->assertStatus(303);
        $this->requestWithDeletionPassword(1, 'POST', 'clientes/1/excluir')->assertStatus(303);
        $this->requestAs(1, 'GET', 'clientes/1')->assertStatus(404);
    }

    public function testCommercialAddressDoesNotChangeOsAndUcCanRepeat(): void
    {
        $before = $this->db->table('tbl_os')->where('cliente_oss', 1)->get()->getResultArray();
        $this->assertCount(2, $before);
        $this->assertNotSame($before[0]['endereco_oss'], $before[1]['endereco_oss']);
        $input = $this->db->table('tbl_cliente')->where('id_cli', 1)->get()->getRowArray();
        $input['endereco_cli'] = 'Novo escritório, 500';
        $this->requestAs(2, 'POST', 'clientes/1/atualizar', $input)->assertStatus(303);
        $this->assertSame($before, $this->db->table('tbl_os')->where('cliente_oss', 1)->get()->getResultArray());
        $this->db->table('tbl_os')->where('id_oss', 2)->update(['unidade_consumidora_oss' => $before[0]['unidade_consumidora_oss']]);
        $this->assertSame(2, $this->db->table('tbl_os')->where('unidade_consumidora_oss', $before[0]['unidade_consumidora_oss'])->countAllResults());
    }

    public function testRequiredFieldsAndInvalidEmailUfCep(): void
    {
        foreach (['nome_cli' => '', 'telefone_cli' => '', 'endereco_cli' => '', 'bairro_cli' => '', 'cidade_cli' => '', 'email_cli' => 'invalid', 'estado_cli' => 'XX', 'cep_cli' => '123', 'status_cli' => 'outro'] as $field => $value) {
            $this->requestAs(1, 'POST', 'clientes', $this->clientInput([$field => $value]))->assertStatus(422);
        }
        $this->assertSame(3, $this->db->table('tbl_cliente')->countAllResults());
    }

    public function testSearchPaginationAndEscaping(): void
    {
        $this->db->table('tbl_cliente')->where('id_cli', 2)->update(['nome_cli' => '<script>alert(1)</script>']);
        $response = $this->requestAs(2, 'GET', 'clientes?q=23.456.789%2F0001-80');
        $response->assertDontSee('<script>alert(1)</script>');
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;');
        for ($i = 0; $i < 16; $i++) {
            $this->requestAs(2, 'POST', 'clientes', $this->clientInput(['nome_cli' => 'Empresa ' . $i, 'cnpj_cli' => sprintf('TT%010d12', $i)]))->assertStatus(303);
        }
        $this->requestAs(2, 'GET', 'clientes?page=2')->assertOK();
    }
}
