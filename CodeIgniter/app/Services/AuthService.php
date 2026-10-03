<?php

namespace App\Services;

use App\Models\EletricistaModel;
use App\Models\UsuarioModel;

class AuthService
{
    private ?array $user = null;

    public function refresh(): ?array
    {
        $this->user = null;
        $session = session();
        $id = $session->get('auth_user_id');
        $lastActivity = $session->get('auth_last_activity');
        if (!$id) {
            return null;
        }
        if (!is_numeric($lastActivity) || time() - (int) $lastActivity >= 7200) {
            $this->logout();
            return null;
        }
        $user = (new UsuarioModel())->publicFind((int) $id);
        if (!$user || !(int) $user['ativo_usu'] || !in_array($user['papel_usu'], ['gestor', 'operador', 'eletricista'], true)) {
            $this->logout();
            return null;
        }
        if ($user['papel_usu'] === 'eletricista') {
            $technical = (new EletricistaModel())->where('usuario_ele', $id)->first();
            if (!$technical) {
                $this->logout();
                return null;
            }
            $user['id_ele'] = $technical['id_ele'];
            $user['display_name'] = $user['nome_completo_usu'] ?: $user['nome_usu'];
        } else {
            $user['display_name'] = $user['nome_completo_usu'] ?: $user['nome_usu'];
        }
        $session->set('auth_last_activity', time());
        return $this->user = $user;
    }

    public function user(): ?array
    {
        return $this->user;
    }

    public function attempt(string $identifier, string $password): bool
    {
        $model = new UsuarioModel();
        $user = $model->where('nome_usu', trim($identifier))->first();
        if (!$user || !(int) $user['ativo_usu'] || !password_verify($password, $user['senha_usu'])) {
            return false;
        }
        if ($user['papel_usu'] === 'eletricista' && !(new EletricistaModel())->where('usuario_ele', $user['id_usu'])->first()) {
            return false;
        }
        if (password_needs_rehash($user['senha_usu'], PASSWORD_DEFAULT)) {
            $model->update($user['id_usu'], ['senha_usu' => password_hash($password, PASSWORD_DEFAULT)]);
        }
        session()->regenerate(true);
        session()->set(['auth_user_id' => (int) $user['id_usu'], 'auth_last_activity' => time()]);
        $this->refresh();
        return true;
    }

    public function logout(): void
    {
        $this->user = null;
        session()->destroy();
        $_SESSION = [];
    }
}
