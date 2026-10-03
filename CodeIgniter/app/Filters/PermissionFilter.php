<?php

namespace App\Filters;

use App\Libraries\PermissionPolicy;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class PermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $name = service('router')->getMatchedRouteOptions()['as'] ?? '';
        if (PermissionPolicy::isPublic($name)) {
            return null;
        }
        if (!PermissionPolicy::allows($name, service('auth')->user()['papel_usu'] ?? null)) {
            return service('response')->setStatusCode(403)->setHeader('Cache-Control', 'no-store')->setBody(view('errors/access', ['title' => 'Acesso não permitido', 'message' => 'Seu perfil não tem permissão para acessar esta página ou executar esta ação.']));
        }
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
