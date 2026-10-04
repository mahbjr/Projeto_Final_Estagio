<?php

namespace App\Controllers;

use App\Domain\StatusMedidor;
use App\Models\ConsumivelModel;
use App\Models\MedidorModel;

class RelatoriosController extends ApplicationController
{
    public function stock()
    {
        $filters = [];
        foreach (['tipo', 'q', 'status_med', 'localizacao_med', 'detentor'] as $field) {
            $value = $this->request->getGet($field);
            $filters[$field] = is_string($value) ? mb_substr(trim($value), 0, 100) : '';
        }
        $filters['tipo'] = $filters['tipo'] === 'consumiveis' ? 'consumiveis' : 'medidores';
        if (!in_array($filters['status_med'], StatusMedidor::TODOS, true)) { $filters['status_med'] = ''; }
        if (!in_array($filters['localizacao_med'], ['deposito', 'viatura', 'cliente'], true)) { $filters['localizacao_med'] = ''; }
        $owners = ['todos' => 'Todos', 'deposito' => 'Depósito'];
        foreach ((new MedidorModel())->reportOwners() as $owner) {
            $owners[(string) $owner['id_ele']] = $owner['nome_completo_usu'] . ' · ' . $owner['matricula_ele'];
        }
        if (!array_key_exists($filters['detentor'], $owners)) { $filters['detentor'] = 'todos'; }
        $model = $filters['tipo'] === 'consumiveis' ? new ConsumivelModel() : new MedidorModel();
        $page = $this->request->getGet('page');
        $page = is_string($page) && preg_match('/^[1-9][0-9]{0,8}$/D', $page) ? (int) $page : 1;
        $rows = $model->stockReport($filters)->paginate(15, 'default', $page);
        return $this->page('relatorios/estoque', [
            'title' => 'Relatório de estoque', 'active' => $filters['tipo'], 'rows' => $rows,
            'pager' => $model->pager, 'filters' => $filters, 'owners' => $owners,
        ]);
    }
}
