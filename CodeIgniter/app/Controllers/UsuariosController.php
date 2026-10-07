<?php

namespace App\Controllers;

use App\Exceptions\FormException;
use App\Models\EletricistaModel;
use App\Models\UsuarioModel;
use App\Services\FuncionarioService;
use CodeIgniter\Exceptions\PageNotFoundException;

class UsuariosController extends ApplicationController
{
    private const FIELDS = ['nome_usu', 'papel_usu', 'ativo_usu', 'nome_completo_usu', 'cpf_usu', 'telefone_usu', 'cargo_usu', 'matricula_ele'];

    public function index()
    {
        $model = new UsuarioModel();
        $query = $this->request->getGet('q');
        $query = is_string($query) ? mb_substr(trim($query), 0, 150) : '';
        $model->select('tbl_usuario.id_usu, nome_usu, papel_usu, ativo_usu, nome_completo_usu, cargo_usu, matricula_ele')
            ->join('tbl_eletricista', 'usuario_ele = id_usu AND data_exclusao_ele IS NULL', 'left');
        if ($query !== '') {
            $model->groupStart()->like('nome_usu', $query)->orLike('nome_completo_usu', $query)->orLike('cpf_usu', $query)->orLike('cargo_usu', $query)->groupEnd();
        }
        $rows = $model->orderBy('id_usu', 'DESC')->paginate(15);
        return $this->page('usuarios/index', ['title' => 'Funcionários e usuários', 'active' => 'usuarios', 'rows' => $rows, 'pager' => $model->pager, 'query' => $query]);
    }

    public function new()
    {
        return $this->form(['papel_usu' => 'operador', 'ativo_usu' => '1']);
    }

    public function show(int $id)
    {
        return $this->page('usuarios/show', ['title' => 'Consultar usuário', 'active' => 'usuarios', 'record' => $this->record($id)]);
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
            (new FuncionarioService())->delete($id, (int) service('auth')->user()['id_usu'], $this->request->getPost('senha_atual'));
            return redirect()->to(site_url('usuarios'))->setStatusCode(303)->with('success', 'Usuário excluído. O histórico foi preservado.');
        } catch (FormException $e) {
            return $this->page('usuarios/show', ['title' => 'Consultar usuário', 'active' => 'usuarios', 'record' => $this->record($id), 'errors' => $e->errors], 422);
        }
    }

    private function save(?int $id = null)
    {
        $input = $this->safeInput(self::FIELDS);
        if ($id && $this->request->getPost('papel_usu') === null) { unset($input['papel_usu']); }
        $credentials = $this->safeInput(['senha', 'confirmacao']);
        $serviceInput = $input + $credentials;
        $serviceInput['telefone_usu'] = $this->request->getPost('telefone_usu') ?? '';
        try {
            $savedId = (new FuncionarioService())->save($serviceInput, (int) service('auth')->user()['id_usu'], $id);
            return redirect()->to(site_url('usuarios/' . $savedId))->setStatusCode(303)->with('success', 'Usuário salvo com sucesso.');
        } catch (FormException $e) {
            $existing = $id ? $this->record($id) : [];
            return $this->form(array_replace($existing, $input), $e->errors, 422, $existing['papel_usu'] ?? null);
        }
    }

    private function record(int $id): array
    {
        $record = (new UsuarioModel())->publicFind($id);
        if (!$record) { throw PageNotFoundException::forPageNotFound('Usuário não encontrado.'); }
        $technical = (new EletricistaModel())->where('usuario_ele', $id)->first() ?? [];
        return $record + array_intersect_key($technical, array_flip(['matricula_ele']));
    }

    private function form(array $record, array $errors = [], int $status = 200, ?string $originalRole = null)
    {
        $record['_original'] = isset($record['id_usu']) ? $this->record((int) $record['id_usu']) : [];
        return $this->page('usuarios/form', ['title' => isset($record['id_usu']) ? 'Editar usuário' : 'Novo usuário', 'active' => 'usuarios', 'record' => $record, 'errors' => $errors, 'originalRole' => $originalRole ?? ($record['papel_usu'] ?? null)], $status);
    }
}
