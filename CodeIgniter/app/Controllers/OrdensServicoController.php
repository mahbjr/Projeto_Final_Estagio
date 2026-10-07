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

    public function startAttendance(int $id) { return $this->attendanceAction($id, 'start'); }
    public function closeAttendance(int $id) { return $this->attendanceAction($id, 'close'); }
    public function noteAttendance(int $id) { return $this->attendanceAction($id, 'note'); }

    private function attendanceAction(int $id, string $action)
    {
        $record = $this->record($id);
        $user = service('auth')->user();
        if ((int) $record['eletricista_oss'] !== (int) $user['id_ele']) { return $this->show($id); }
        $input = $this->safeInput($action === 'close' ? \App\Services\AtendimentoService::FINAL_FIELDS : ($action === 'start' ? ['medidor'] : ['observacao_atendimento']));
        try {
            $service = new \App\Services\AtendimentoService();
            if ($action === 'start') { $service->start($id, (int) $user['id_usu'], $this->request->getPost('medidor')); }
            elseif ($action === 'close') {
                $final = [];
                foreach (\App\Services\AtendimentoService::FINAL_FIELDS as $field) { $final[$field] = $this->request->getPost($field) ?? ''; }
                $service->close($id, $final, (int) $user['id_usu']);
            }
            else { $service->note($id, $input['observacao_atendimento'], (int) $user['id_usu']); }
            return redirect()->to(site_url('os/' . $id))->setStatusCode(303)->with('success', match ($action) { 'start' => 'Atendimento iniciado.', 'close' => 'OS encerrada. Dados finais e histórico preservados.', default => 'Observação do atendimento registrada.' });
        } catch (FormException $e) { return $this->details($this->record($id), $e->errors, 422, $input); }
    }

    public function reserveConsumable(int $id)
    {
        $this->record($id);
        $input = $this->safeInput(['consumivel', 'quantidade']);
        try {
            (new \App\Services\ConsumivelService())->reserve($id, $input['consumivel'], $input['quantidade'], (int) service('auth')->user()['id_usu']);
            return redirect()->to(site_url('os/' . $id))->setStatusCode(303)->with('success', 'Consumível reservado no depósito.');
        } catch (FormException $e) { return $this->details($this->record($id), $e->errors, 422, $input); }
    }

    public function answerBeginning(int $id, int $template) { return $this->answerChecklist($id, $template, false); }
    public function answerClosing(int $id, int $template) { return $this->answerChecklist($id, $template, true); }

    private function answerChecklist(int $id, int $template, bool $closing)
    {
        $record = $this->record($id);
        if ((int) $record['eletricista_oss'] !== (int) service('auth')->user()['id_ele']) { return $this->show($id); }
        $input = ['template' => $template, 'respostas' => $this->request->getPost('respostas') ?? [], 'observacoes' => $this->request->getPost('observacoes') ?? []];
        try {
            ($closing ? new \App\Services\ChecklistFechamentoService() : new \App\Services\ChecklistInicioService())->answer($id, $template, $input['respostas'], $input['observacoes'], (int) service('auth')->user()['id_usu']);
            return redirect()->to(site_url('os/' . $id))->setStatusCode(303)->with('success', $closing ? 'Checklist de fechamento registrado.' : 'Checklist de início registrado.');
        } catch (FormException $e) { return $this->details($record, $e->errors, 422, $input); }
    }

    public function releaseBeginning(int $id, int $evaluation)
    {
        $record = $this->record($id);
        $input = $this->safeInput(['justificativa']);
        try {
            (new \App\Services\ChecklistInicioService())->release($id, $evaluation, $input['justificativa'], (int) service('auth')->user()['id_usu']);
            return redirect()->to(site_url('os/' . $id))->setStatusCode(303)->with('success', 'Bloqueio de início liberado com justificativa.');
        } catch (FormException $e) { return $this->details($record, $e->errors, 422, $input); }
    }

    public function deliverConsumable(int $id, int $reservation)
    {
        $record = $this->record($id);
        try {
            (new \App\Services\ConsumivelService())->deliver($id, $reservation, (int) service('auth')->user()['id_usu']);
            return redirect()->to(site_url('os/' . $id))->setStatusCode(303)->with('success', 'Entrega física registrada na custódia do Eletricista.');
        } catch (FormException $e) { return $this->details($record, $e->errors, 422); }
    }

    public function receiveConsumable(int $id, int $reservation)
    {
        $record = $this->record($id);
        $input = $this->safeInput(['quantidade_devolucao', 'observacao_devolucao']);
        $input['reserva_devolucao'] = $reservation;
        try {
            (new \App\Services\ConsumivelService())->receive($id, $reservation, $input['quantidade_devolucao'], $input['observacao_devolucao'], (int) service('auth')->user()['id_usu']);
            return redirect()->to(site_url('os/' . $id))->setStatusCode(303)->with('success', 'Devolução física recebida no depósito.');
        } catch (FormException $e) { return $this->details($record, $e->errors, 422, $input); }
    }

    public function consumeConsumable(int $id, int $reservation)
    {
        $record = $this->record($id);
        $user = service('auth')->user();
        if ((int) $record['eletricista_oss'] !== (int) $user['id_ele']) { return $this->show($id); }
        $input = $this->safeInput(['quantidade_consumo', 'observacao_consumo']);
        $input['reserva_consumo'] = $reservation;
        try {
            (new \App\Services\ConsumivelService())->consume($id, $reservation, $input['quantidade_consumo'], $input['observacao_consumo'], (int) $user['id_usu']);
            return redirect()->to(site_url('os/' . $id))->setStatusCode(303)->with('success', 'Consumo registrado na custódia do Eletricista.');
        } catch (FormException $e) { return $this->details($this->record($id), $e->errors, 422, $input); }
    }

    public function applyMeter(int $id, int $reservation) { return $this->meterAction($id, 'apply', $reservation); }
    public function withdrawMeter(int $id, int $meter) { return $this->meterAction($id, 'withdraw', $meter); }

    public function reserveMeter(int $id) { return $this->meterAction($id, 'reserve'); }
    public function deliverMeter(int $id, int $reservation) { return $this->meterAction($id, 'deliver', $reservation); }
    public function receiveMeter(int $id, int $reservation) { return $this->meterAction($id, 'receive', $reservation); }
    public function meterOccurrence(int $id, int $meter) { return $this->meterAction($id, 'occurrence', $meter); }

    private function meterAction(int $id, string $action, ?int $resource = null)
    {
        $record = $this->record($id);
        $user = service('auth')->user();
        if ($user['papel_usu'] === 'eletricista' && (int) $record['eletricista_oss'] !== (int) $user['id_ele']) { return $this->show($id); }
        $input = $this->safeInput(['medidor', 'condicao_medidor', 'tipo_ocorrencia', 'justificativa_medidor']);
        $input['recurso_medidor'] = $resource;
        $service = new \App\Services\MedidorOsService();
        try {
            match ($action) {
                'reserve' => $service->reserve($id, $input['medidor'], (int) $user['id_usu']),
                'deliver' => $service->deliver($id, $resource, (int) $user['id_usu']),
                'receive' => $service->receive($id, $resource, $input['condicao_medidor'], (int) $user['id_usu']),
                'apply' => $service->apply($id, $resource, (int) $user['id_usu']),
                'withdraw' => $service->withdraw($id, $resource, (int) $user['id_usu']),
                'occurrence' => $service->occurrence($resource, $input['tipo_ocorrencia'], $input['justificativa_medidor'], (int) $user['id_usu'], $id),
            };
            return redirect()->to(site_url('os/' . $id))->setStatusCode(303)->with('success', 'Operação do medidor registrada.');
        } catch (FormException $e) { return $this->details($record, $e->errors, 422, $input); }
    }

    public function uploadPhoto(int $id) { return $this->photoAction($id); }
    public function removePhoto(int $id, int $photo) { return $this->photoAction($id, $photo); }

    private function photoAction(int $id, ?int $photo = null)
    {
        $record = $this->record($id);
        $user = service('auth')->user();
        if ($user['papel_usu'] === 'eletricista' && (int) $record['eletricista_oss'] !== (int) $user['id_ele']) { return $this->show($id); }
        $input = $this->safeInput(['descricao_foto','motivo_foto']);
        $input['foto_remocao'] = $photo;
        try {
            $service = new \App\Services\FotoService();
            if ($photo === null) {
                $file = $this->request->getFile('foto');
                $service->upload($id, $file, $this->request->getPost('descricao_foto') ?? '', (int) $user['id_usu']);
            } else {
                $attachment = (new \App\Models\AnexoModel())->where('ordem_servico_anx',$id)->where('tipo_anx','foto')->find($photo);
                if (!$attachment) { throw PageNotFoundException::forPageNotFound(); }
                if ($user['papel_usu'] === 'eletricista' && $record['status_oss'] === 'em_atendimento' && !\App\Services\FotoService::canRemove($record,$user,$attachment)) {
                    return $this->response->setStatusCode(403)->setBody(view('errors/access',['title'=>'Acesso não permitido','message'=>'Você não pode remover esta foto.']));
                }
                $service->remove($id,$photo,$this->request->getPost('motivo_foto'),(int) $user['id_usu']);
            }
            return redirect()->to(site_url('os/' . $id))->setStatusCode(303)->with('success', $photo === null ? 'Foto anexada à OS.' : 'Foto removida da consulta. Histórico preservado.');
        } catch (FormException $e) { return $this->details($this->record($id),$e->errors,422,$input); }
    }

    public function photo(int $id, int $photo)
    {
        $record = $this->record($id);
        $user = service('auth')->user();
        if ($user['papel_usu'] === 'eletricista' && (int) $record['eletricista_oss'] !== (int) $user['id_ele']) { return $this->show($id); }
        $attachment = (new \App\Models\AnexoModel())->where('ordem_servico_anx',$id)->where('tipo_anx','foto')->find($photo);
        if (!$attachment) { throw PageNotFoundException::forPageNotFound(); }
        $path = (new \App\Services\FotoService())->path($attachment);
        return $this->response->download($path,null)->setFileName('os-' . $id . '-foto-' . $photo . '.' . pathinfo($path,PATHINFO_EXTENSION))->inline()->setContentType($attachment['mime_anx'],'')->setHeader('X-Content-Type-Options','nosniff')->setHeader('Content-Security-Policy', "default-src 'none'; sandbox");
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
        $materials = (new \App\Models\ConsumivelModel())->depotOverview()->orderBy('nome_con')->findAll();
        $reservations = (new \App\Models\ConsumivelReservaModel())->withMaterials()->where('ordem_servico_rco', $record['id_oss'])->orderBy('id_rco')->findAll();
        $beginningTemplates = (new \App\Models\ChecklistModel())->beginning($record['tipo_oss']);
        $evaluations = (new \App\Models\ChecklistAvaliacaoModel())->evidence((int) $record['id_oss']);
        $meterReservations = (new \App\Models\MedidorReservaModel())->forOrder((int) $record['id_oss']);
        $isManager = service('auth')->user()['papel_usu'] === 'gestor';
        foreach ($meterReservations as &$reservation) {
            $canShowOccurrence = in_array($reservation['status_rme'], ['entregue','devolucao_pendente','perdida'], true) && ($isManager || (service('auth')->user()['papel_usu'] === 'eletricista' && in_array($record['status_oss'], ['atribuida','em_atendimento'], true) && (int) $reservation['eletricista_posse_med'] === (int) service('auth')->user()['id_ele']));
            $reservation['occurrenceChoices'] = $canShowOccurrence ? \App\Services\MedidorOsService::occurrenceChoices($reservation, $isManager) : [];
        }
        $latestBeginning = [];
        foreach ($evaluations as $evaluation) { if ($evaluation['etapa_cav'] === 'inicio' && !isset($latestBeginning[$evaluation['checklist_cav']])) { $latestBeginning[$evaluation['checklist_cav']] = (int) $evaluation['id_cav']; } }
        $photos = (new \App\Models\AnexoModel())->forOrder((int) $record['id_oss']);
        foreach ($photos as &$photo) { $photo['canRemove'] = \App\Services\FotoService::canRemove($record,service('auth')->user(),$photo); }
        return $this->page('os/show', ['title' => 'OS #' . $record['id_oss'], 'active' => 'os', 'record' => $record, 'history' => (new OrdemServicoModel())->history((int) $record['id_oss']), 'electricians' => (new MedidorModel())->destinations(), 'materials' => $materials, 'reservations' => $reservations, 'currentInstallations' => (new \App\Models\InstalacaoAtualModel())->forOrder($record), 'meterOperations' => (new \App\Models\OsMedidorModel())->forOrder((int) $record['id_oss']), 'meterReservations' => $meterReservations, 'ownMeters' => service('auth')->user()['papel_usu'] === 'eletricista' ? (new MedidorModel())->eligibleForOrder((int) service('auth')->user()['id_ele'], (int) $record['id_oss']) : [], 'meterOccurrences' => (new \App\Models\MedidorOcorrenciaModel())->withActors()->where('ordem_servico_ome', $record['id_oss'])->orderBy('id_ome', 'DESC')->findAll(), 'photos' => $photos, 'closingTemplates' => (new \App\Models\ChecklistModel())->forStage($record['tipo_oss'], 'fechamento'), 'beginningTemplates' => $beginningTemplates, 'evaluations' => $evaluations, 'latestBeginning' => $latestBeginning, 'errors' => $errors, 'input' => $input], $status);
    }
}
