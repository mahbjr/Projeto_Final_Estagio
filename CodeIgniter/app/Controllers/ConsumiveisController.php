<?php

namespace App\Controllers;

use App\Exceptions\FormException;
use App\Models\ConsumivelModel;
use App\Models\ConsumivelMovModel;
use App\Models\ConsumivelSaldoModel;
use App\Services\ConsumivelService;
use CodeIgniter\Exceptions\PageNotFoundException;

final class ConsumiveisController extends ApplicationController
{
    public function index()
    {
        $model = (new ConsumivelModel())->depotOverview();
        $rows = $model->orderBy('nome_con')->orderBy('id_con')->paginate(15);
        return $this->page('consumiveis/index', ['title' => 'Consumíveis', 'active' => 'consumiveis', 'rows' => $rows, 'pager' => $model->pager]);
    }
    public function new() { return $this->form(['precisao_con' => '0']); }
    public function edit(int $id) { return $this->form($this->record($id)); }
    public function show(int $id) { return $this->details($this->record($id)); }
    public function create() { return $this->save(); }
    public function update(int $id) { $this->record($id); return $this->save($id); }
    public function delete(int $id)
    {
        $this->record($id);
        try {
            (new ConsumivelService())->delete($id, (int) service('auth')->user()['id_usu'], $this->request->getPost('senha_atual'));
            return redirect()->to(site_url('consumiveis'))->setStatusCode(303)->with('success', 'Material excluído. Histórico preservado.');
        } catch (FormException $e) { return $this->details($this->record($id), $e->errors, 422); }
    }
    public function entry(int $id)
    {
        $this->record($id);
        $input = $this->safeInput(['quantidade', 'observacao']);
        try {
            (new ConsumivelService())->entry($id, $input['quantidade'], $input['observacao'], (int) service('auth')->user()['id_usu']);
            return redirect()->to(site_url('consumiveis/' . $id))->setStatusCode(303)->with('success', 'Entrada registrada no depósito.');
        } catch (FormException $e) { return $this->details($this->record($id), $e->errors, 422, $input); }
    }
    private function save(?int $id = null)
    {
        $input = $this->safeInput(ConsumivelService::FIELDS);
        try {
            $id = (new ConsumivelService())->save($input, (int) service('auth')->user()['id_usu'], $id);
            return redirect()->to(site_url('consumiveis/' . $id))->setStatusCode(303)->with('success', 'Consumível salvo.');
        } catch (FormException $e) { return $this->form(($id ? ['id_con' => $id] : []) + $input, $e->errors, 422); }
    }
    private function record(int $id): array
    {
        $record = (new ConsumivelModel())->depotOverview()->find($id);
        if (!$record) { throw PageNotFoundException::forPageNotFound('Consumível não encontrado.'); }
        return $record;
    }
    private function form(array $record, array $errors = [], int $status = 200)
    {
        return $this->page('consumiveis/form', ['title' => isset($record['id_con']) ? 'Editar consumível' : 'Novo consumível', 'active' => 'consumiveis', 'record' => $record, 'errors' => $errors], $status);
    }
    private function details(array $record, array $errors = [], int $status = 200, array $input = [])
    {
        $model = (new ConsumivelMovModel())->withActors();
        $movements = $model->where('consumivel_mco', $record['id_con'])->orderBy('id_mco', 'DESC')->paginate(15);
        $balances = (new ConsumivelSaldoModel())->withOwners()->where('consumivel_sco', $record['id_con'])->orderBy('id_sco')->findAll();
        return $this->page('consumiveis/show', ['title' => $record['nome_con'], 'active' => 'consumiveis', 'record' => $record, 'movements' => $movements, 'balances' => $balances, 'pager' => $model->pager, 'errors' => $errors, 'input' => $input], $status);
    }
}
