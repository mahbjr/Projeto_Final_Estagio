<?php

namespace App\Services;

use App\Exceptions\FormException;
use App\Models\UsuarioModel;

final class PerfilService extends WriteService
{
    public const FIELDS = ['nome_completo_usu', 'telefone_usu', 'nome_usu', 'senha_atual', 'senha', 'confirmacao'];

    public function update(array $input, int $actorId): bool
    {
        foreach (array_keys($input) as $field) {
            if (!in_array($field, self::FIELDS, true)) {
                throw new FormException(['operacao' => 'O perfil permite alterar somente nome, telefone, e-mail e senha.']);
            }
        }
        $data = [];
        foreach (self::FIELDS as $field) {
            if (isset($input[$field]) && !is_string($input[$field])) {
                throw new FormException([$field => 'Informe um valor válido.']);
            }
            $data[$field] = $input[$field] ?? '';
            if (!in_array($field, ['senha_atual', 'senha', 'confirmacao', 'telefone_usu'], true)) { $data[$field] = trim($data[$field]); }
        }
        return $this->transaction(function () use ($data, $actorId) {
            $actor = $this->operationalActor($actorId, ['gestor', 'operador', 'eletricista']);
            $data['telefone_usu'] = $this->phone($data['telefone_usu'], $actor['telefone_usu'], 'telefone_usu');
            $changedLogin = $data['nome_usu'] !== $actor['nome_usu'];
            $changedCredentials = $changedLogin || $data['senha'] !== '';
            $rules = [
                'nome_completo_usu' => 'required|max_length[120]',
                'telefone_usu' => 'permit_empty|max_length[20]',
                'nome_usu' => 'required|max_length[150]' . ($changedLogin ? '|valid_email' : ''),
                'senha' => 'permit_empty|senha_segura',
                'confirmacao' => ($data['senha'] === '' ? 'permit_empty|' : 'required|') . 'matches[senha]',
            ];
            $this->validate($data, $rules);
            if ($changedCredentials && !password_verify($data['senha_atual'], $actor['senha_usu'])) {
                throw new FormException(['senha_atual' => 'Informe sua senha atual para alterar o e-mail ou a senha.']);
            }
            if ($changedLogin && $this->db->table('tbl_usuario')->where('nome_usu', $data['nome_usu'])->where('id_usu !=', $actorId)->countAllResults()) {
                throw new FormException(['nome_usu' => 'Este e-mail já está cadastrado.']);
            }
            $saved = array_intersect_key($data, array_flip(['nome_completo_usu', 'telefone_usu', 'nome_usu']));
            if ($data['senha'] !== '') { $saved['senha_usu'] = password_hash($data['senha'], PASSWORD_DEFAULT); }
            if (!(new UsuarioModel($this->db))->update($actorId, $saved)) {
                throw new FormException(['operacao' => 'Não foi possível atualizar seu perfil.']);
            }
            return $changedCredentials;
        });
    }
}
