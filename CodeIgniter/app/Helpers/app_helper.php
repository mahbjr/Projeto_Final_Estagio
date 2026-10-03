<?php

function app_field(string $name, string $label, array $record, array $errors, array $options = []): string
{
    return view('components/field', compact('name', 'label', 'record', 'errors', 'options'));
}

function role_label(string $role): string
{
    return ['gestor' => 'Gestor', 'operador' => 'Operador', 'eletricista' => 'Eletricista'][$role] ?? $role;
}

function user_initials(string $name): string
{
    return mb_strtoupper(mb_substr($name, 0, 2));
}

function meter_label(string $value): string
{
    return ['disponivel' => 'Disponível', 'em_transito' => 'Em trânsito', 'instalado' => 'Instalado', 'defeito' => 'Defeito', 'deposito' => 'Depósito', 'viatura' => 'Viatura', 'cliente' => 'Cliente', 'galpao' => 'Galpão', 'eletricista' => 'Eletricista', 'fornecedor' => 'Fornecedor', 'descarte' => 'Descarte', 'entrada' => 'Entrada', 'transferencia' => 'Transferência', 'baixa_saida' => 'Baixa'][$value] ?? $value;
}
