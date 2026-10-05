<?php

namespace App\Domain;

final class StatusOS
{
    public const TODOS = ['aberta', 'atribuida', 'em_atendimento', 'encerrada', 'cancelada'];
    public const PENDENTES = ['aberta', 'atribuida', 'em_atendimento'];
    public const FINAIS = ['encerrada', 'cancelada'];
    public const RESULTADOS = ['executado', 'parcial', 'nao_executado'];
    public const PRIORIDADES = ['baixa', 'normal', 'alta', 'urgente'];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, match ($from) {
            'aberta' => ['atribuida', 'cancelada'],
            'atribuida' => ['em_atendimento', 'cancelada'],
            'em_atendimento' => ['encerrada'],
            default => [],
        }, true);
    }
}
