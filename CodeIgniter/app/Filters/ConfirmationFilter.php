<?php

namespace App\Filters;

use App\Libraries\ConfirmationPolicy;
use App\Libraries\PermissionPolicy;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class ConfirmationFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // The marker is progressive enhancement, not an authorization token.
        // Manual POSTs still reach the protected Service and require the manager password.
        if (!$request->is('post') || $request->getPost('_confirmacao') !== 'pendente') { return null; }
        $route = service('router')->getMatchedRouteOptions()['as'] ?? '';
        $action = ConfirmationPolicy::ACTIONS[$route] ?? null;
        if (!$action) { return service('response')->setStatusCode(422)->setHeader('Cache-Control', 'no-store, private')->setBody('Ação de confirmação inválida.'); }
        [$message, $fields, $needsPassword] = $action;
        $payload = [];
        foreach ($fields as $field) {
            $value = $request->getPost($field);
            if ($value === null) { continue; }
            if (!is_string($value) || strlen($value) > 8192) {
                return service('response')->setStatusCode(422)->setHeader('Cache-Control', 'no-store, private')->setBody('Informe valores válidos para confirmar a operação.');
            }
            $payload[$field] = $value;
        }
        $path = ltrim($request->getUri()->getPath(), '/');
        $basePath = trim(parse_url(config('App')->baseURL, PHP_URL_PATH) ?? '', '/');
        if ($basePath !== '' && str_starts_with($path, $basePath . '/')) { $path = substr($path, strlen($basePath) + 1); }
        $indexPage = config('App')->indexPage;
        if ($indexPage !== '' && str_starts_with($path, $indexPage . '/')) { $path = substr($path, strlen($indexPage) + 1); }
        preg_match('~^(usuarios|clientes|medidores|consumiveis|checklists|os)/[0-9]+~', $path, $matches);
        helper(['app', 'form', 'url', 'heroicon']);
        $user = service('auth')->user();
        return service('response')->setStatusCode(200)->setHeader('Cache-Control', 'no-store, private')->setBody(view('confirmacao/index', [
            'title' => 'Confirmar operação', 'active' => '', 'profilePage' => false, 'user' => $user,
            'can' => static fn (string $name): bool => PermissionPolicy::allows($name, $user['papel_usu']),
            'confirmationMessage' => $message, 'confirmationPayload' => $payload,
            'confirmationAction' => site_url($path), 'confirmationReturn' => site_url(str_starts_with($path, 'meus-medidores/') ? 'meus-medidores' : ($matches[0] ?? 'inicio')),
            'confirmationPassword' => $needsPassword,
        ]));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
