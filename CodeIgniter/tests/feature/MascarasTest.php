<?php

namespace Tests\Feature;

use Tests\Support\AppTestCase;

final class MascarasTest extends AppTestCase
{
    public function testPhonesValidateWithoutJavascriptInAllThreeServices(): void
    {
        foreach (['8533334444' => '(85) 3333-4444', '85999991234' => '(85) 99999-1234'] as $value => $expected) {
            $input = $this->clientInput(['telefone_cli' => (string) $value]);
            $this->requestAs(2, 'POST', 'clientes', $input)->assertStatus(303);
            $row = $this->db->table('tbl_cliente')->where('cnpj_cli', $input['cnpj_cli'])->get()->getRowArray();
            $this->assertSame($expected, $row['telefone_cli']);
            $this->db->table('tbl_cliente')->where('id_cli', $row['id_cli'])->update(['cnpj_cli' => sprintf('LEGACY%06d00', $row['id_cli'])]);
            $user = $this->db->table('tbl_usuario')->where('id_usu', 2)->get()->getRowArray();
            $this->requestAs(2, 'POST', 'perfil/atualizar', ['nome_completo_usu' => 'Perfil Teste', 'nome_usu' => $user['nome_usu'], 'telefone_usu' => (string) $value])->assertStatus(303);
            $this->assertSame($expected, $this->db->table('tbl_usuario')->where('id_usu', 2)->get()->getRowArray()['telefone_usu']);
            $this->requestAs(1, 'POST', 'usuarios/2/atualizar', array_replace($user, ['telefone_usu' => (string) $value, 'senha' => '', 'confirmacao' => '']))->assertStatus(303);
            $this->assertSame($expected, $this->db->table('tbl_usuario')->where('id_usu', 2)->get()->getRowArray()['telefone_usu']);
        }
        foreach (['123', '859999912345', '08533334444', '85abc33334444', '+558533334444', '85/33334444'] as $value) {
            $this->requestAs(2, 'POST', 'clientes', $this->clientInput(['telefone_cli' => $value]))->assertStatus(422);
            $user = $this->db->table('tbl_usuario')->where('id_usu', 2)->get()->getRowArray();
            $this->requestAs(2, 'POST', 'perfil/atualizar', ['nome_completo_usu' => 'Perfil Teste', 'nome_usu' => $user['nome_usu'], 'telefone_usu' => $value])->assertStatus(422);
            $this->requestAs(1, 'POST', 'usuarios/2/atualizar', array_replace($user, ['telefone_usu' => $value]))->assertStatus(422);
        }
    }

    public function testMalformedPhoneParametersCannotClearAnExistingPhone(): void
    {
        $user = $this->db->table('tbl_usuario')->where('id_usu', 2)->get()->getRowArray();
        $this->requestAs(1, 'POST', 'usuarios/2/atualizar', array_replace($user, ['telefone_usu' => ['85999991234']]))->assertStatus(422);
        $this->requestAs(2, 'POST', 'perfil/atualizar', ['nome_completo_usu' => 'Perfil', 'nome_usu' => $user['nome_usu'], 'telefone_usu' => ['85999991234']])->assertStatus(422);
        $this->assertSame($user['telefone_usu'], $this->db->table('tbl_usuario')->where('id_usu', 2)->get()->getRowArray()['telefone_usu']);
    }

    public function testUnchangedHistoricalPhonesAndCnpjsRemainEditable(): void
    {
        foreach (['XY12CD34000156', '23456789000180'] as $cnpj) {
            $this->db->table('tbl_cliente')->where('id_cli', 2)->update(['cnpj_cli' => $cnpj, 'telefone_cli' => 'ramal antigo']);
            $row = $this->db->table('tbl_cliente')->where('id_cli', 2)->get()->getRowArray();
            $row['nome_cli'] = 'Nome atualizado';
            $this->requestAs(2, 'POST', 'clientes/2/atualizar', $row)->assertStatus(303);
            $saved = $this->db->table('tbl_cliente')->where('id_cli', 2)->get()->getRowArray();
            $this->assertSame($cnpj, $saved['cnpj_cli']);
            $this->assertSame('ramal antigo', $saved['telefone_cli']);
            $row['cnpj_cli'] = 'XY12CD34000157';
            $this->requestAs(2, 'POST', 'clientes/2/atualizar', $row)->assertStatus(422);
        }
        $this->db->table('tbl_usuario')->where('id_usu', 2)->update(['telefone_usu' => '  ramal 123  ']);
        $user = $this->db->table('tbl_usuario')->where('id_usu', 2)->get()->getRowArray();
        $this->requestAs(2, 'POST', 'perfil/atualizar', ['nome_completo_usu' => 'Outro Nome', 'nome_usu' => $user['nome_usu'], 'telefone_usu' => $user['telefone_usu']])->assertStatus(303);
        $this->requestAs(1, 'POST', 'usuarios/2/atualizar', $user)->assertStatus(303);
        $this->assertSame('  ramal 123  ', $this->db->table('tbl_usuario')->where('id_usu', 2)->get()->getRowArray()['telefone_usu']);
    }

    public function testCnpjUniquenessIncludesDeletedAccountsAndIgnoresForgedLegacyFlag(): void
    {
        $this->db->table('tbl_cliente')->where('id_cli', 2)->update(['cnpj_cli' => '11222333000181']);
        $this->requestAs(2, 'POST', 'clientes', $this->clientInput())->assertStatus(422);
        $this->requestWithDeletionPassword(1, 'POST', 'clientes/2/excluir')->assertStatus(303);
        $this->requestAs(2, 'POST', 'clientes', $this->clientInput(['cnpj_cli' => '11.222.333/0001-81']))->assertStatus(422);
        $this->requestAs(2, 'POST', 'clientes', $this->clientInput(['cnpj_cli' => 'XY12CD34000156', '_original' => ['cnpj_cli' => 'XY12CD34000156']]))->assertStatus(422);
    }

    public function testCepValidationAndNormalizationAndMaskMarkup(): void
    {
        $this->requestAs(2, 'POST', 'clientes', $this->clientInput(['cep_cli' => '60010000']))->assertStatus(303);
        $row = $this->db->table('tbl_cliente')->where('cnpj_cli', '11222333000181')->get()->getRowArray();
        $this->assertSame('60010-000', $row['cep_cli']);
        foreach (['123', '6001A000', '60010/000', '600100000'] as $value) {
            $this->requestAs(2, 'POST', 'clientes/' . $row['id_cli'] . '/atualizar', array_replace($row, ['cep_cli' => $value]))->assertStatus(422);
        }
        $response = $this->requestAs(2, 'GET', 'clientes/' . $row['id_cli'] . '/editar');
        $response->assertSee('data-mask="cnpj"');
        $response->assertSee('data-mask="telefone"');
        $response->assertSee('data-mask="cep"');
        $response->assertSee('inputmode="numeric"');
        $response->assertSee('11.222.333/0001-81');
        foreach ([1,2,3] as $id) { $this->requestAs($id, 'GET', 'perfil')->assertSee('data-mask="telefone"'); }
    }
}
