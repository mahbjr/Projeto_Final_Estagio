<?php

namespace App\Controllers;

class Home extends ApplicationController
{
    public function index()
    {
        return redirect()->to(site_url('inicio'));
    }

    public function welcome()
    {
        $user = service('auth')->user();
        if ($user['papel_usu'] === 'eletricista') {
            return redirect()->to(site_url('os'));
        }
        $context = (new \App\Services\InicioService())->context($user, $this->request->getGet());
        return $this->page('home', $context + ['title' => 'Bem-vindo', 'active' => 'inicio'], $context['errors'] ? 422 : 200);
    }
}
