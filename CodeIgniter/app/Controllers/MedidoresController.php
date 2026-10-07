<?php
namespace App\Controllers;

use App\Exceptions\FormException;
use App\Models\MedidorModel;
use App\Services\MedidorService;
use CodeIgniter\Exceptions\PageNotFoundException;

class MedidoresController extends ApplicationController
{
    public function index()
    {
        $model = new MedidorModel();
        $filters = [];
        foreach (['q', 'status_med', 'localizacao_med'] as $field) {
            $value = $this->request->getGet($field);
            $filters[$field] = is_string($value) ? mb_substr(trim($value), 0, 100) : '';
        }
        if ($filters['q'] !== '') { $model->groupStart()->like('numero_med', $filters['q'])->orLike('modelo_med', $filters['q'])->groupEnd(); }
        foreach (['status_med', 'localizacao_med'] as $field) { if ($filters[$field] !== '') { $model->where($field, $filters[$field]); } }
        $rows = $model->orderBy('id_med', 'DESC')->paginate(15);
        return $this->page('medidores/index', ['title' => 'Estoque de medidores', 'active' => 'medidores', 'rows' => $rows, 'pager' => $model->pager, 'filters' => $filters]);
    }

    public function new() { return $this->form([]); }
    public function edit(int $id) { return $this->form($this->record($id)); }
    public function show(int $id) { return $this->detail($id); }
    public function create() { return $this->save(); }
    public function update(int $id) { return $this->save($id); }
    public function send(int $id) { return $this->operate($id, 'send'); }
    public function returnToDepot(int $id) { return $this->operate($id, 'returnToDepot'); }
    public function delete(int $id) { return $this->operate($id, 'delete'); }

    public function occurrence(int $id)
    {
        $this->record($id);
        $input = $this->safeInput(['tipo_ocorrencia', 'justificativa_medidor']);
        try {
            (new \App\Services\MedidorOsService())->occurrence($id, $input['tipo_ocorrencia'], $input['justificativa_medidor'], (int) service('auth')->user()['id_usu']);
            return redirect()->to(site_url('medidores/' . $id))->setStatusCode(303)->with('success', 'Ocorrência registrada. Último local e responsável preservados.');
        } catch (FormException $e) { return $this->detail($id, $e->errors, 422, $input); }
    }

    private function save(?int $id = null)
    {
        $existing = $id ? $this->record($id) : [];
        $input = $this->safeInput(['numero_med', 'modelo_med', 'fabricante_med']);
        foreach (['status_med', 'localizacao_med', 'eletricista_posse_med'] as $field) {
            if ($this->request->getPost($field) !== null) { $input += $this->safeInput([$field]); }
        }
        try {
            $id = (new MedidorService())->save($input, (int) service('auth')->user()['id_usu'], $id);
            return redirect()->to(site_url('medidores/' . $id))->setStatusCode(303)->with('success', 'Medidor salvo.');
        } catch (FormException $e) {
            return $this->form(array_replace($existing, array_intersect_key($input, array_flip(['numero_med', 'modelo_med', 'fabricante_med']))), $e->errors, 422);
        }
    }

    private function operate(int $id, string $action)
    {
        $this->record($id);
        $service = new MedidorService();
        $actor = (int) service('auth')->user()['id_usu'];
        try {
            match ($action) {
                'send' => $service->send($id, $this->request->getPost('destino'), $actor),
                'returnToDepot' => $service->returnToDepot($id, $this->request->getPost('condicao'), $actor),
                'delete' => $service->delete($id, $actor, $this->request->getPost('senha_atual')),
            };
            return redirect()->to(site_url($action === 'delete' ? 'medidores' : 'medidores/' . $id))->setStatusCode(303)->with('success', 'Operação registrada no histórico.');
        } catch (FormException $e) { return $this->detail($id, $e->errors, 422); }
    }

    private function record(int $id): array
    {
        $record = (new MedidorModel())->find($id);
        if (!$record) { throw PageNotFoundException::forPageNotFound('Medidor não encontrado.'); }
        return $record;
    }

    private function detail(int $id, array $errors = [], int $status = 200, array $input = [])
    {
        $model = new MedidorModel();
        $record = $this->record($id);
        return $this->page('medidores/show', ['title' => 'Consultar medidor', 'active' => 'medidores', 'record' => $record, 'history' => $model->history($id), 'destinations' => $model->destinations(), 'occurrenceChoices' => \App\Services\MedidorOsService::occurrenceChoices($record, true), 'consistent' => MedidorService::consistent($record), 'errors' => $errors, 'input' => $input, 'occurrences' => (new \App\Models\MedidorOcorrenciaModel())->withActors()->where('medidor_ome', $id)->orderBy('id_ome', 'DESC')->findAll(), 'linked' => (new \App\Models\MedidorReservaModel())->where('medidor_rme', $id)->whereIn('status_rme', ['reservada','entregue','devolucao_pendente','perdida'])->orderBy('id_rme', 'DESC')->first()], $status);
    }

    private function form(array $record, array $errors = [], int $status = 200)
    {
        return $this->page('medidores/form', ['title' => isset($record['id_med']) ? 'Editar medidor' : 'Novo medidor', 'active' => 'medidores', 'record' => $record, 'errors' => $errors, 'depot' => isset($record['id_med']) && MedidorService::consistent($record) && $record['localizacao_med'] === 'deposito' && in_array($record['status_med'], ['disponivel', 'defeito'], true)], $status);
    }
}
