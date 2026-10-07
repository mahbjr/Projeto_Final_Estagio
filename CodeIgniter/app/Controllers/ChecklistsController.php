<?php

namespace App\Controllers;

use App\Exceptions\FormException;
use App\Models\ChecklistModel;
use App\Models\ChecklistItemModel;
use App\Services\ChecklistService;
use CodeIgniter\Exceptions\PageNotFoundException;

final class ChecklistsController extends ApplicationController
{
    public function index()
    {
        $model = new ChecklistModel();
        $rows = $model->orderBy('id_chk', 'DESC')->paginate(15);
        return $this->page('checklists/index', ['title' => 'Checklists', 'active' => 'checklists', 'rows' => $rows, 'pager' => $model->pager]);
    }
    public function new() { return $this->form(['ativo_chk' => '0']); }
    public function edit(int $id) { return $this->form($this->record($id)); }
    public function show(int $id) { return $this->details($this->record($id)); }
    public function create() { return $this->save(); }
    public function update(int $id) { $this->record($id); return $this->save($id); }

    public function createItem(int $id) { return $this->saveItem($id); }
    public function updateItem(int $id, int $item) { $this->item($id, $item); return $this->saveItem($id, $item); }
    public function editItem(int $id, int $item)
    {
        return $this->page('checklists/item', ['title' => 'Editar pergunta', 'active' => 'checklists', 'checklist' => $this->record($id), 'record' => $this->item($id, $item), 'errors' => []]);
    }
    public function deleteItem(int $id, int $item)
    {
        $this->item($id, $item);
        try {
            (new ChecklistService())->deleteItem($id, $item, (int) service('auth')->user()['id_usu'], $this->request->getPost('senha_atual'));
            return redirect()->to(site_url('checklists/' . $id))->setStatusCode(303)->with('success', 'Pergunta removida. As respostas anteriores foram preservadas.');
        } catch (FormException $e) { return $this->details($this->record($id), $e->errors, 422); }
    }

    public function moveItem(int $id, int $item)
    {
        $this->item($id, $item);
        try {
            (new ChecklistService())->moveItem($id, $item, $this->request->getPost('direcao'), (int) service('auth')->user()['id_usu']);
            return redirect()->to(site_url('checklists/' . $id))->setStatusCode(303)->with('success', 'Ordem das perguntas atualizada.');
        } catch (FormException $e) { return $this->details($this->record($id), $e->errors, 422); }
    }

    private function save(?int $id = null)
    {
        $input = $this->safeInput(ChecklistService::FIELDS);
        try {
            $savedId = (new ChecklistService())->save($input, (int) service('auth')->user()['id_usu'], $id);
            return redirect()->to(site_url('checklists/' . $savedId))->setStatusCode(303)->with('success', 'Modelo salvo.');
        } catch (FormException $e) { return $this->form(($id ? ['id_chk' => $id] : []) + $input, $e->errors, 422); }
    }
    private function saveItem(int $id, ?int $item = null)
    {
        $record = $this->record($id);
        $input = $this->safeInput(ChecklistService::ITEM_FIELDS);
        try {
            (new ChecklistService())->saveItem($id, $input, (int) service('auth')->user()['id_usu'], $item);
            return redirect()->to(site_url('checklists/' . $id))->setStatusCode(303)->with('success', 'Pergunta salva.');
        } catch (FormException $e) {
            if ($item) { return $this->page('checklists/item', ['title' => 'Editar pergunta', 'active' => 'checklists', 'checklist' => $record, 'record' => ['id_chi' => $item] + $input, 'errors' => $e->errors], 422); }
            return $this->details($record, $e->errors, 422, $input);
        }
    }
    private function record(int $id): array
    {
        $record = (new ChecklistModel())->find($id);
        if (!$record) { throw PageNotFoundException::forPageNotFound('Checklist não encontrado.'); }
        return $record;
    }
    private function item(int $id, int $item): array
    {
        $this->record($id);
        $record = (new ChecklistItemModel())->where('checklist_chi', $id)->find($item);
        if (!$record) { throw PageNotFoundException::forPageNotFound('Pergunta não encontrada neste modelo.'); }
        return $record;
    }
    private function form(array $record, array $errors = [], int $status = 200)
    {
        return $this->page('checklists/form', ['title' => isset($record['id_chk']) ? 'Editar checklist' : 'Novo checklist', 'active' => 'checklists', 'record' => $record, 'errors' => $errors], $status);
    }
    private function details(array $record, array $errors = [], int $status = 200, array $input = [])
    {
        $items = (new ChecklistItemModel())->where('checklist_chi', $record['id_chk'])->orderBy('ordem_chi')->orderBy('id_chi')->findAll();
        return $this->page('checklists/show', ['title' => $record['nome_chk'], 'active' => 'checklists', 'record' => $record, 'items' => $items, 'errors' => $errors, 'input' => $input], $status);
    }
}
