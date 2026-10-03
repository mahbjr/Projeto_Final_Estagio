<?php

namespace App\Services;

use App\Exceptions\FormException;
use App\Libraries\Identifiers;
use App\Models\ClienteModel;

class ClienteService extends WriteService
{
    public function save(array $input, ?int $id = null): int
    {
        $data = [];
        foreach (['nome_cli', 'cnpj_cli', 'email_cli', 'telefone_cli', 'endereco_cli', 'bairro_cli', 'cidade_cli', 'estado_cli', 'cep_cli', 'status_cli'] as $field) {
            $data[$field] = is_string($input[$field] ?? null) ? trim($input[$field]) : '';
        }
        $data['cnpj_cli'] = Identifiers::cnpj($data['cnpj_cli']);
        $data['cep_cli'] = Identifiers::cep($data['cep_cli']);
        $data['estado_cli'] = strtoupper($data['estado_cli']);
        $data['id_cli'] = $id;
        $rules = [
            'nome_cli' => ['label' => 'Razão social', 'rules' => 'required|max_length[150]'],
            'cnpj_cli' => ['label' => 'CNPJ', 'rules' => 'required|exact_length[14]|cnpj_formato|' . ($id ? 'is_unique[tbl_cliente.cnpj_cli,id_cli,{id_cli}]' : 'is_unique[tbl_cliente.cnpj_cli]')],
            'email_cli' => ['label' => 'E-mail', 'rules' => 'permit_empty|valid_email|max_length[120]'],
            'telefone_cli' => ['label' => 'Telefone', 'rules' => 'required|max_length[20]'],
            'endereco_cli' => ['label' => 'Endereço comercial', 'rules' => 'required|max_length[255]'],
            'bairro_cli' => ['label' => 'Bairro', 'rules' => 'required|max_length[100]'],
            'cidade_cli' => ['label' => 'Cidade', 'rules' => 'required|max_length[100]'],
            'estado_cli' => ['label' => 'UF', 'rules' => 'required|in_list[AC,AL,AP,AM,BA,CE,DF,ES,GO,MA,MT,MS,MG,PA,PB,PR,PE,PI,RJ,RN,RS,RO,RR,SC,SP,SE,TO]'],
            'cep_cli' => ['label' => 'CEP', 'rules' => 'required|cep_formato'],
            'status_cli' => ['label' => 'Situação', 'rules' => 'required|in_list[ativo,inativo]'],
        ];
        if ($id) {
            $rules['id_cli'] = 'required|is_natural_no_zero';
        }
        $this->validate($data, $rules);
        unset($data['id_cli']);
        return $this->transaction(function () use ($id, $data) {
            if ($id) {
                $existing = $this->db->query('SELECT id_cli FROM tbl_cliente WHERE id_cli = ? AND data_exclusao_cli IS NULL FOR UPDATE', [$id])->getRowArray();
                if (!$existing) { $this->notFound(); }
                if ($data['status_cli'] === 'inativo' && $this->pendingOrders('cliente_oss', $id)) {
                    throw new FormException(['operacao' => 'A empresa possui OS pendentes. Conclua ou cancele os atendimentos antes de inativar.']);
                }
            }
            $model = new ClienteModel($this->db);
            if ($id) {
                $model->update($id, $data);
                return $id;
            }
            return (int) $model->insert($data);
        });
    }

    public function delete(int $id): void
    {
        $this->transaction(function () use ($id) {
            $row = $this->db->query('SELECT id_cli FROM tbl_cliente WHERE id_cli = ? AND data_exclusao_cli IS NULL FOR UPDATE', [$id])->getRowArray();
            if (!$row) { $this->notFound(); }
            if ($this->pendingOrders('cliente_oss', $id)) {
                throw new FormException(['operacao' => 'A empresa possui OS pendentes. Conclua ou cancele os atendimentos antes de excluir.']);
            }
            (new ClienteModel($this->db))->delete($id);
        });
    }
}
