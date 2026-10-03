<?php

namespace App\Controllers;

class AuthController extends ApplicationController
{
    public function login()
    {
        if (service('auth')->refresh()) {
            return redirect()->to(site_url('inicio'));
        }
        return $this->page('auth/login', ['title' => 'Entrar', 'identifier' => '', 'error' => null]);
    }

    public function authenticate()
    {
        $input = $this->safeInput(['identificador', 'senha']);
        $identifier = trim($input['identificador']);
        if ($identifier !== '' && mb_strlen($identifier) <= 150 && $input['senha'] !== '' && strlen($input['senha']) <= 72 && service('auth')->attempt($identifier, $input['senha'])) {
            return redirect()->to(site_url('inicio'))->setStatusCode(303);
        }
        return $this->page('auth/login', ['title' => 'Entrar', 'identifier' => $identifier, 'error' => 'Usuário ou senha inválidos.'], 422);
    }

    public function logout()
    {
        service('auth')->logout();
        return redirect()->to(site_url('login'))->setStatusCode(303)->deleteCookie(config('Session')->cookieName, config('Cookie')->domain, config('Cookie')->path, config('Cookie')->prefix);
    }
}
