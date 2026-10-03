<?php

namespace App\Services;

use App\Exceptions\FormException;
use App\Libraries\Identifiers;
use App\Models\EletricistaModel;
use App\Models\UsuarioModel;

class FuncionarioService extends WriteService
{
    public function save(array $input, int $actorId, ?int $id = null): int
    {
        return $this->transaction(function () use ($input, $actorId, $id) {
            $managers = $this->lockManagers($actorId);
            $existing = $id ? $this->db->query('SELECT * FROM tbl_usuario WHERE id_usu = ? AND data_exclusao_usu IS NULL FOR UPDATE', [$id])->getRowArray() : null;
            if ($id && !$existing) { $this->notFound(); }
            $technicalModel = new EletricistaModel($this->db);
            $technical = $id ? $technicalModel->where('usuario_ele', $id)->first() : null;
            $role = is_string($input['papel_usu'] ?? null) ? $input['papel_usu'] : ($existing['papel_usu'] ?? '');
            if ($existing && (($existing['papel_usu'] === 'eletricista' && $role !== 'eletricista') || ($existing['papel_usu'] !== 'eletricista' && $role === 'eletricista'))) {
                throw new FormException(['papel_usu' => 'Eletricista tem papel fixo. A troca é permitida apenas entre Gestor e Operador.']);
            }
            $data = [
                'id_usu' => $id,
                'nome_usu' => is_string($input['nome_usu'] ?? null) ? trim($input['nome_usu']) : '',
                'papel_usu' => $role,
                'ativo_usu' => is_scalar($input['ativo_usu'] ?? null) ? (string) $input['ativo_usu'] : '',
                'senha' => is_string($input['senha'] ?? null) ? $input['senha'] : '',
                'confirmacao' => is_string($input['confirmacao'] ?? null) ? $input['confirmacao'] : '',
            ];
            $rules = [
                'nome_usu' => ['label' => 'Identificador', 'rules' => 'required|max_length[150]|' . ($id ? 'is_unique[tbl_usuario.nome_usu,id_usu,{id_usu}]' : 'is_unique[tbl_usuario.nome_usu]')],
                'papel_usu' => ['label' => 'Papel', 'rules' => 'required|in_list[gestor,operador,eletricista]'],
                'ativo_usu' => ['label' => 'Situação', 'rules' => 'required|in_list[0,1]'],
                'senha' => ['label' => 'Senha', 'rules' => ($id ? 'permit_empty' : 'required') . '|senha_segura'],
                'confirmacao' => ['label' => 'Confirmação', 'rules' => ($data['senha'] === '' && $id ? 'permit_empty|' : 'required|') . 'matches[senha]'],
            ];
            if ($id) { $rules['id_usu'] = 'required|is_natural_no_zero'; }
            $technicalData = [];
            if ($role === 'eletricista') {
                foreach (['nome_ele', 'cpf_ele', 'telefone_ele', 'matricula_ele'] as $field) {
                    $technicalData[$field] = is_string($input[$field] ?? null) ? trim($input[$field]) : '';
                }
                $technicalData['cpf_ele'] = Identifiers::cpf($technicalData['cpf_ele']);
                $technicalData['id_ele'] = $technical['id_ele'] ?? null;
                $rules += [
                    'nome_ele' => ['label' => 'Nome completo', 'rules' => 'required|max_length[120]'],
                    'cpf_ele' => ['label' => 'CPF', 'rules' => 'required|cpf_formato|' . ($technical ? 'is_unique[tbl_eletricista.cpf_ele,id_ele,{id_ele}]' : 'is_unique[tbl_eletricista.cpf_ele]')],
                    'telefone_ele' => ['label' => 'Telefone', 'rules' => 'permit_empty|max_length[20]'],
                    'matricula_ele' => ['label' => 'Matrícula', 'rules' => 'required|max_length[50]|' . ($technical ? 'is_unique[tbl_eletricista.matricula_ele,id_ele,{id_ele}]' : 'is_unique[tbl_eletricista.matricula_ele]')],
                ];
                if ($technical) { $rules['id_ele'] = 'required|is_natural_no_zero'; }
            }
            $this->validate($data + $technicalData, $rules);
            if ($existing) {
                $reducesAccess = $data['ativo_usu'] === '0' || ($existing['papel_usu'] === 'gestor' && $role !== 'gestor');
                if ($id === $actorId && $reducesAccess) {
                    throw new FormException(['operacao' => 'Você não pode desativar ou reduzir o próprio acesso.']);
                }
                if ($existing['papel_usu'] === 'gestor' && (int) $existing['ativo_usu'] && $reducesAccess && count($managers) <= 1) {
                    throw new FormException(['operacao' => 'O sistema precisa manter pelo menos um Gestor ativo.']);
                }
                if ($role === 'eletricista' && $data['ativo_usu'] === '0' && $technical) {
                    $this->assertNoPendingWork((int) $technical['id_ele']);
                }
            }
            $account = array_intersect_key($data, array_flip(['nome_usu', 'papel_usu', 'ativo_usu']));
            if ($data['senha'] !== '') { $account['senha_usu'] = password_hash($data['senha'], PASSWORD_DEFAULT); }
            $users = new UsuarioModel($this->db);
            if ($id) { $users->update($id, $account); }
            else { $id = (int) $users->insert($account); }
            if ($role === 'eletricista') {
                unset($technicalData['id_ele']);
                $technicalData['usuario_ele'] = $id;
                if ($technical) { $technicalModel->update($technical['id_ele'], $technicalData); }
                else { $technicalModel->insert($technicalData); }
            }
            return $id;
        });
    }

    public function delete(int $id, int $actorId): void
    {
        $this->transaction(function () use ($id, $actorId) {
            $managers = $this->lockManagers($actorId);
            $user = $this->db->query('SELECT id_usu, papel_usu, ativo_usu FROM tbl_usuario WHERE id_usu = ? AND data_exclusao_usu IS NULL FOR UPDATE', [$id])->getRowArray();
            if (!$user) { $this->notFound(); }
            if ($id === $actorId) { throw new FormException(['operacao' => 'Você não pode excluir o próprio acesso.']); }
            if ($user['papel_usu'] === 'gestor' && (int) $user['ativo_usu'] && count($managers) <= 1) {
                throw new FormException(['operacao' => 'O sistema precisa manter pelo menos um Gestor ativo.']);
            }
            $model = new EletricistaModel($this->db);
            $technical = $model->where('usuario_ele', $id)->first();
            if ($technical) {
                $this->assertNoPendingWork((int) $technical['id_ele']);
                $model->delete($technical['id_ele']);
            }
            (new UsuarioModel($this->db))->delete($id);
        });
    }

    private function lockManagers(int $actorId): array
    {
        $managers = $this->db->query('SELECT id_usu FROM tbl_usuario WHERE papel_usu = ? AND ativo_usu = 1 AND data_exclusao_usu IS NULL ORDER BY id_usu FOR UPDATE', ['gestor'])->getResultArray();
        if (!in_array($actorId, array_map('intval', array_column($managers, 'id_usu')), true)) {
            throw new FormException(['operacao' => 'Seu acesso de Gestor não está mais ativo.']);
        }
        return $managers;
    }

    private function assertNoPendingWork(int $technicalId): void
    {
        if ($this->pendingOrders('eletricista_oss', $technicalId)) {
            throw new FormException(['operacao' => 'O eletricista possui OS pendentes. Reatribua, conclua ou cancele os atendimentos primeiro.']);
        }
        if ($this->db->table('tbl_medidor')->where('eletricista_posse_med', $technicalId)->where('data_exclusao_med', null)->countAllResults() > 0) {
            throw new FormException(['operacao' => 'O eletricista possui medidores em posse. Regularize a devolução antes de continuar.']);
        }
    }
}
