<?php

namespace App\Controllers;

use App\Domain\StatusMedidor;
use App\Models\MedidorModel;

class RelatoriosController extends ApplicationController
{
    public function electricians()
    {
        $service = new \App\Services\IndicadoresService();
        try {
            $context = $service->context(service('auth')->user(), $this->request->getGet());
        } catch (\App\Exceptions\IndicadoresAccessException $e) {
            return $this->response->setStatusCode(403)->setBody(view('errors/access', ['title' => 'Acesso não permitido', 'message' => 'Você não pode consultar indicadores de outros profissionais ou clientes.']));
        }
        $page = $this->request->getGet('page') ?? '1';
        if (!is_string($page) || !preg_match('/^[1-9][0-9]{0,8}$/D', $page)) {
            $context['errors']['page'] = 'Informe uma página válida.';
            $page = '1';
        }
        $report = $context['errors'] ? null : $service->report($context, (int) $page);
        $pager = service('pager');
        if ($report !== null) { $pager->store('default', $report['page'], 15, $report['summary']['total']); }
        return $this->page('relatorios/eletricistas', $context + [
            'report' => $report, 'pager' => $pager, 'filterPath' => 'relatorios/eletricistas',
            'title' => $context['personal'] ? 'Meu relatório de atendimentos' : 'Relatório por eletricista',
            'active' => 'relatorios/eletricistas',
        ], $context['errors'] ? 422 : 200);
    }

    public function stock()
    {
        $filters = [];
        foreach (['tipo', 'q', 'status_med', 'localizacao_med', 'detentor'] as $field) {
            $value = $this->request->getGet($field);
            $filters[$field] = is_string($value) ? mb_substr(trim($value), 0, 100) : '';
        }
        $filters['tipo'] = 'medidores';
        if (!in_array($filters['status_med'], StatusMedidor::TODOS, true)) { $filters['status_med'] = ''; }
        if (!in_array($filters['localizacao_med'], ['deposito', 'viatura', 'cliente'], true)) { $filters['localizacao_med'] = ''; }
        $owners = ['todos' => 'Todos', 'deposito' => 'Depósito'];
        foreach ((new MedidorModel())->reportOwners() as $owner) {
            $owners[(string) $owner['id_ele']] = $owner['nome_completo_usu'] . ' · ' . $owner['matricula_ele'];
        }
        if (!array_key_exists($filters['detentor'], $owners)) { $filters['detentor'] = 'todos'; }
        $model = new MedidorModel();
        $page = $this->request->getGet('page');
        $page = is_string($page) && preg_match('/^[1-9][0-9]{0,8}$/D', $page) ? (int) $page : 1;
        $rows = $model->stockReport($filters)->paginate(15, 'default', $page);
        return $this->page('relatorios/estoque', [
            'title' => 'Relatório de estoque', 'active' => $filters['tipo'], 'rows' => $rows,
            'pager' => $model->pager, 'filters' => $filters, 'owners' => $owners,
        ]);
    }
}
