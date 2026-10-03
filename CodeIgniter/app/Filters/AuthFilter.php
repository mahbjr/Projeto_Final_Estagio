<?php

namespace App\Filters;

use App\Libraries\PermissionPolicy;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $name = service('router')->getMatchedRouteOptions()['as'] ?? '';
        if (PermissionPolicy::isPublic($name)) {
            return null;
        }
        if (!service('auth')->refresh()) {
            return redirect()->to(site_url('login'))->setHeader('Cache-Control', 'no-store')->deleteCookie(config('Session')->cookieName);
        }
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
