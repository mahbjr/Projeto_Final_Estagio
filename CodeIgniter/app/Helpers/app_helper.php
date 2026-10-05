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
    return ['reservado' => 'Reservado', 'perdido' => 'Perdido', 'baixado' => 'Baixado', 'disponivel' => 'Disponível', 'em_transito' => 'Em trânsito', 'instalado' => 'Instalado', 'defeito' => 'Defeito', 'deposito' => 'Depósito', 'viatura' => 'Viatura', 'cliente' => 'Cliente', 'galpao' => 'Galpão', 'eletricista' => 'Eletricista', 'fornecedor' => 'Fornecedor', 'descarte' => 'Descarte', 'entrada' => 'Entrada', 'transferencia' => 'Transferência', 'baixa_saida' => 'Baixa'][$value] ?? $value;
}

function os_label(string $value): string
{
    return ['aberta' => 'Aberta', 'atribuida' => 'Atribuída', 'em_atendimento' => 'Em Atendimento',
        'encerrada' => 'Encerrada', 'cancelada' => 'Cancelada', 'corte' => 'Corte de energia', 'nova_ligacao' => 'Nova ligação',
        'baixa' => 'Baixa', 'normal' => 'Normal', 'alta' => 'Alta', 'urgente' => 'Urgente',
        'inicio' => 'Início', 'fechamento' => 'Fechamento', 'executado' => 'Executado', 'parcial' => 'Parcial', 'nao_executado' => 'Não executado'][$value] ?? $value;
}
