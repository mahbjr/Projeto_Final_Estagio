<?php

namespace App\Libraries;

final class PermissionPolicy
{
    public const PUBLIC_ROUTES = ['login.form', 'login.submit'];

    private const PERMISSIONS = [
        'os.attendance.start' => ['eletricista'],
        'os.attendance.note' => ['eletricista'],
        'os.medidores.reserve' => ['gestor'],
        'os.medidores.deliver' => ['gestor'],
        'os.medidores.receive' => ['gestor'],
        'os.medidores.occurrence' => ['gestor', 'eletricista'],
        'medidores.occurrence' => ['gestor'],
        'consumiveis.index' => ['gestor', 'operador'],
        'consumiveis.show' => ['gestor', 'operador'],
        'consumiveis.new' => ['gestor'],
        'consumiveis.create' => ['gestor'],
        'consumiveis.edit' => ['gestor'],
        'consumiveis.update' => ['gestor'],
        'consumiveis.delete' => ['gestor'],
        'consumiveis.entry' => ['gestor'],
        'os.consumiveis.reserve' => ['gestor'],
        'os.consumiveis.deliver' => ['gestor'],
        'os.consumiveis.receive' => ['gestor'],
        'os.checklist.answer' => ['eletricista'],
        'os.checklist.release' => ['gestor'],
        'os.index' => ['gestor', 'operador', 'eletricista'],
        'os.show' => ['gestor', 'operador', 'eletricista'],
        'os.new' => ['gestor', 'operador'],
        'os.create' => ['gestor', 'operador'],
        'os.edit' => ['gestor', 'operador'],
        'os.update' => ['gestor', 'operador'],
        'os.assign' => ['gestor', 'operador'],
        'os.cancel' => ['gestor', 'operador'],
        'checklists.index' => ['gestor'],
        'checklists.new' => ['gestor'],
        'checklists.create' => ['gestor'],
        'checklists.show' => ['gestor'],
        'checklists.edit' => ['gestor'],
        'checklists.update' => ['gestor'],
        'checklists.item.create' => ['gestor'],
        'checklists.item.edit' => ['gestor'],
        'checklists.item.update' => ['gestor'],
        'checklists.item.delete' => ['gestor'],

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
