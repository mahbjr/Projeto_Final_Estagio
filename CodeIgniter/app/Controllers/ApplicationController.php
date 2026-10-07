<?php

namespace App\Controllers;

use App\Libraries\PermissionPolicy;

abstract class ApplicationController extends BaseController
{
    protected $helpers = ['form', 'url', 'app'];

    protected function page(string $view, array $data = [], int $status = 200)
    {
        $user = service('auth')->user();
        return $this->response->setStatusCode($status)->setBody(view($view, $data + [
            'user' => $user,
            'profilePage' => false,
            'can' => static fn (string $route): bool => PermissionPolicy::allows($route, $user['papel_usu'] ?? null),
        ]));
    }

    protected function safeInput(array $fields): array
    {
        $data = [];
        foreach ($fields as $field) {
            $value = $this->request->getPost($field);
            $data[$field] = is_scalar($value) ? (string) $value : '';
        }
        return $data;
    }
}
