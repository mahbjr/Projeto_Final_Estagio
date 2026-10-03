<?php

namespace App\Libraries;

final class PermissionPolicy
{
    public const PUBLIC_ROUTES = ['login.form', 'login.submit'];

    private const PERMISSIONS = [
        'medidores.index' => ['gestor', 'operador'],
        'medidores.show' => ['gestor', 'operador'],
        'medidores.new' => ['gestor'],
        'medidores.create' => ['gestor'],
        'medidores.edit' => ['gestor'],
        'medidores.update' => ['gestor'],
        'medidores.delete' => ['gestor'],
        'medidores.send' => ['gestor'],
        'medidores.return' => ['gestor'],
        'entrada' => ['gestor', 'operador', 'eletricista'],
        'inicio' => ['gestor', 'operador', 'eletricista'],
        'logout' => ['gestor', 'operador', 'eletricista'],
        'usuarios.index' => ['gestor'],
        'usuarios.show' => ['gestor'],
        'usuarios.new' => ['gestor'],
        'usuarios.create' => ['gestor'],
        'usuarios.edit' => ['gestor'],
        'usuarios.update' => ['gestor'],
        'usuarios.delete' => ['gestor'],
        'clientes.index' => ['gestor', 'operador'],
        'clientes.show' => ['gestor', 'operador'],
        'clientes.new' => ['gestor', 'operador'],
        'clientes.create' => ['gestor', 'operador'],
        'clientes.edit' => ['gestor', 'operador'],
        'clientes.update' => ['gestor', 'operador'],
        'clientes.delete' => ['gestor'],
    ];

    public static function allows(string $route, ?string $role): bool
    {
        return in_array($role, self::PERMISSIONS[$route] ?? [], true);
    }

    public static function isPublic(string $route): bool
    {
        return in_array($route, self::PUBLIC_ROUTES, true);
    }
}
