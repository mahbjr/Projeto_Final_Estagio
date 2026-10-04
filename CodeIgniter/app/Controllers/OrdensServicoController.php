<?php

namespace App\Controllers;

use App\Domain\StatusOS;
use App\Exceptions\FormException;
use App\Models\ClienteModel;
use App\Models\MedidorModel;
use App\Models\OrdemServicoModel;
use App\Services\OrdemServicoService;
use CodeIgniter\Exceptions\PageNotFoundException;

final class OrdensServicoController extends ApplicationController
{
    public function index()
    {
        $user = service('auth')->user();
        $model = (new OrdemServicoModel())->overview();
        if ($user['papel_usu'] === 'eletricista') { $model->where('eletricista_oss', $user['id_ele']); }
        $query = $this->request->getGet('q');
        $query = is_string($query) ? mb_substr(trim($query), 0, 150) : '';
        if ($query !== '') {
            $model->groupStart()->like('unidade_consumidora_oss', $query)->orLike('endereco_oss', $query)->orLike('nome_cli', $query)->groupEnd();
        }
        $filters = [];
        foreach (['status_oss' => StatusOS::TODOS, 'prioridade_oss' => StatusOS::PRIORIDADES] as $field => $allowed) {
            $value = $this->request->getGet($field);
            $filters[$field] = is_string($value) && in_array($value, $allowed, true) ? $value : '';
            if ($filters[$field] !== '') { $model->where($field, $filters[$field]); }
        }
        $day = $this->request->getGet('dia');
        $filters['dia'] = is_string($day) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $day) && checkdate((int) substr($day, 5, 2), (int) substr($day, 8, 2), (int) substr($day, 0, 4)) ? $day : '';
        if ($filters['dia'] !== '') {
            $model->where('agendamento_oss >=', $filters['dia'] . ' 00:00:00')->where('agendamento_oss <=', $filters['dia'] . ' 23:59:59');
        }
        $rows = $model->orderBy('id_oss', 'DESC')->paginate(15);
        return $this->page('os/index', ['title' => $user['papel_usu'] === 'eletricista' ? 'Minhas OS' : 'Ordens de serviço', 'active' => 'os', 'rows' => $rows, 'query' => $query, 'filters' => $filters, 'pager' => $model->pager]);
    }

    public function show(int $id)
    {
        $record = $this->record($id);
        $user = service('auth')->user();
        if ($user['papel_usu'] === 'eletricista' && (int) $record['eletricista_oss'] !== (int) $user['id_ele']) {
            return $this->response->setStatusCode(403)->setBody(view('errors/access', ['title' => 'Acesso não permitido', 'message' => 'Esta OS não está atribuída a você.']));
        }
        return $this->details($record);
    }

    public function new() { return $this->form(['prioridade_oss' => 'normal']); }
    public function edit(int $id)
    {
        $record = $this->record($id);
        return in_array($record['status_oss'], StatusOS::FINAIS, true) ? $this->details($record) : $this->form($record);
    }
    public function create() { return $this->save(); }
    public function update(int $id) { $this->record($id); return $this->save($id); }

    public function assign(int $id)
    {
        $this->record($id);
        $input = $this->safeInput(['eletricista_oss']);
        try {
            (new OrdemServicoService())->assign($id, $input['eletricista_oss'], (int) service('auth')->user()['id_usu']);
            return redirect()->to(site_url('os/' . $id))->setStatusCode(303)->with('success', 'Eletricista atribuído à OS.');
        } catch (FormException $e) { return $this->details($this->record($id), $e->errors, 422, $input); }
    }

    public function cancel(int $id)
    {
        $this->record($id);
        $input = $this->safeInput(['motivo']);
        try {
            (new OrdemServicoService())->cancel($id, $input['motivo'], (int) service('auth')->user()['id_usu']);
            return redirect()->to(site_url('os/' . $id))->setStatusCode(303)->with('success', 'OS cancelada. O histórico foi preservado.');
        } catch (FormException $e) { return $this->details($this->record($id), $e->errors, 422, $input); }
    }

    private function save(?int $id = null)
    {
        $input = $this->safeInput(OrdemServicoService::FIELDS);
        try {
            $savedId = (new OrdemServicoService())->save($input, (int) service('auth')->user()['id_usu'], $id);
            return redirect()->to(site_url('os/' . $savedId))->setStatusCode(303)->with('success', 'OS salva com sucesso.');
        } catch (FormException $e) {
            $record = $id ? $this->record($id) : [];
            if (in_array($record['status_oss'] ?? '', StatusOS::FINAIS, true)) { return $this->details($record, $e->errors, 422); }
            return $this->form(array_replace($record, $input), $e->errors, 422);
        }
    }

    private function record(int $id): array
    {
        $record = (new OrdemServicoModel())->overview()->find($id);
        if (!$record) { throw PageNotFoundException::forPageNotFound('OS não encontrada.'); }
        return $record;
    }

    private function form(array $record, array $errors = [], int $status = 200)
    {
        if (!empty($record['agendamento_oss']) && !str_contains($record['agendamento_oss'], 'T')) { $record['agendamento_oss'] = str_replace(' ', 'T', substr($record['agendamento_oss'], 0, 16)); }
        $clients = (new ClienteModel())->where('status_cli', 'ativo')->orderBy('nome_cli')->findAll();
        return $this->page('os/form', ['title' => isset($record['id_oss']) ? 'Editar OS #' . $record['id_oss'] : 'Nova OS', 'active' => 'os', 'record' => $record, 'clients' => $clients, 'errors' => $errors], $status);
    }

    private function details(array $record, array $errors = [], int $status = 200, array $input = [])
    {
        return $this->page('os/show', ['title' => 'OS #' . $record['id_oss'], 'active' => 'os', 'record' => $record, 'history' => (new OrdemServicoModel())->history((int) $record['id_oss']), 'electricians' => (new MedidorModel())->destinations(), 'errors' => $errors, 'input' => $input], $status);
    }
}
