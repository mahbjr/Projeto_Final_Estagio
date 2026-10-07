<?php

namespace App\Controllers;

use App\Exceptions\FormException;
use App\Services\PerfilService;

final class PerfilController extends ApplicationController
{
    public function index()
    {
        return $this->form(service('auth')->user());
    }

    public function update()
    {
        $input = $this->request->getPost();
        unset($input[config('Security')->tokenName]);
        try {
            $changedCredentials = (new PerfilService())->update($input, (int) service('auth')->user()['id_usu']);
            if ($changedCredentials) { session()->regenerate(true); }
            service('auth')->refresh();
            return redirect()->to(site_url('perfil'))->setStatusCode(303)->with('success', 'Perfil atualizado.');
        } catch (FormException $e) {
            $safe = array_intersect_key($input, array_flip(['nome_completo_usu', 'telefone_usu', 'nome_usu']));
            foreach ($safe as $field => $value) { if (!is_string($value)) { $safe[$field] = ''; } }
            return $this->form($safe + service('auth')->user(), $e->errors, 422);
        }
    }

    private function form(array $record, array $errors = [], int $status = 200)
    {
        return $this->page('perfil/index', ['title' => 'Editar perfil', 'active' => '', 'record' => $record, 'errors' => $errors, 'profilePage' => true], $status);
    }
}
