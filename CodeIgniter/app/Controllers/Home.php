<?php

namespace App\Controllers;

class Home extends ApplicationController
{
    public function index()
    {
        return redirect()->to(site_url('inicio'));
    }

    public function dashboard()
    {
        $service = new \App\Services\IndicadoresService();
        try {
            $context = $service->context(service('auth')->user(), $this->request->getGet());
        } catch (\App\Exceptions\IndicadoresAccessException $e) {
            return $this->response->setStatusCode(403)->setBody(view('errors/access', ['title' => 'Acesso não permitido', 'message' => 'Você não pode consultar indicadores de outros profissionais ou clientes.']));
        }
        $metrics = $context['errors'] ? null : $service->dashboard($context);
        return $this->page('home', $context + ['metrics' => $metrics, 'title' => $context['personal'] ? 'Minha visão geral' : 'Visão geral', 'active' => 'inicio'], $context['errors'] ? 422 : 200);
    }
}
