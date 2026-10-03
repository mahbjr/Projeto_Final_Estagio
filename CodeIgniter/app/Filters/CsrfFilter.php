<?php

namespace App\Filters;

use CodeIgniter\Filters\CSRF;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Security\Exceptions\SecurityException;

class CsrfFilter extends CSRF
{
    public function before(RequestInterface $request, $arguments = null)
    {
        try {
            return parent::before($request, $arguments);
        } catch (SecurityException $e) {
            return service('response')->setStatusCode(403)->setHeader('Cache-Control', 'no-store')->setBody(view('errors/access', [
                'title' => 'Formulário expirado',
                'message' => 'Atualize a página e envie o formulário novamente. Nenhuma alteração foi realizada.',
            ]));
        }
    }
}
