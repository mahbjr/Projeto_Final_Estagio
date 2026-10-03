<?php

namespace App\Controllers;

use App\Exceptions\FormException;
use App\Libraries\Identifiers;
use App\Models\ClienteModel;
use App\Services\ClienteService;
use CodeIgniter\Exceptions\PageNotFoundException;

class ClientesController extends ApplicationController
{
    private const FIELDS = ['nome_cli', 'cnpj_cli', 'email_cli', 'telefone_cli', 'endereco_cli', 'bairro_cli', 'cidade_cli', 'estado_cli', 'cep_cli', 'status_cli'];

    public function index()
    {
        $model = new ClienteModel();
        $query = $this->request->getGet('q');
        $query = is_string($query) ? mb_substr(trim($query), 0, 150) : '';
        if ($query !== '') {
            $model->groupStart()->like('nome_cli', $query)->orLike('cnpj_cli', Identifiers::cnpj($query))->groupEnd();
        }
        $rows = $model->orderBy('nome_cli')->paginate(15);
        return $this->page('clientes/index', ['title' => 'Clientes', 'active' => 'clientes', 'rows' => $rows, 'pager' => $model->pager, 'query' => $query]);
    }

    public function new()
    {
        return $this->form(['status_cli' => 'ativo']);
    }

    public function show(int $id)
    {
        return $this->page('clientes/show', ['title' => 'Consultar empresa', 'active' => 'clientes', 'record' => $this->record($id)]);
    }

    public function edit(int $id)
    {
        return $this->form($this->record($id));
    }

    public function create()
    {
        return $this->save();
    }

    public function update(int $id)
    {
        $this->record($id);
        return $this->save($id);
    }

    public function delete(int $id)
    {
        $this->record($id);
        try {
            (new ClienteService())->delete($id);
            return redirect()->to(site_url('clientes'))->setStatusCode(303)->with('success', 'Empresa excluída. As OS e o histórico foram preservados.');
        } catch (FormException $e) {
            return $this->page('clientes/show', ['title' => 'Consultar empresa', 'active' => 'clientes', 'record' => $this->record($id), 'errors' => $e->errors], 422);
        }
    }

    private function save(?int $id = null)
    {
        $input = $this->safeInput(self::FIELDS);
        try {
            $savedId = (new ClienteService())->save($input, $id);
            return redirect()->to(site_url('clientes/' . $savedId))->setStatusCode(303)->with('success', 'Empresa salva com sucesso.');
        } catch (FormException $e) {
            return $this->form(($id ? ['id_cli' => $id] : []) + $input, $e->errors, 422);
        }
    }

    private function record(int $id): array
    {
        $record = (new ClienteModel())->find($id);
        if (!$record) { throw PageNotFoundException::forPageNotFound('Empresa não encontrada.'); }
        return $record;
    }

    private function form(array $record, array $errors = [], int $status = 200)
    {
        return $this->page('clientes/form', ['title' => isset($record['id_cli']) ? 'Editar empresa' : 'Nova empresa', 'active' => 'clientes', 'record' => $record, 'errors' => $errors], $status);
    }
}
