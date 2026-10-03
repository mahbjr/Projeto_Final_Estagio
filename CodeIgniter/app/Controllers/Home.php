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
        return $this->page('home', ['title' => 'Visão geral', 'active' => 'inicio']);
    }
}
