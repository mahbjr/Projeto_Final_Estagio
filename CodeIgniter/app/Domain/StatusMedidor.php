<?php

namespace App\Domain;

final class StatusMedidor
{
    public const TODOS = ['disponivel', 'reservado', 'em_transito', 'instalado', 'defeito', 'perdido', 'baixado'];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, match ($from) {
            'disponivel' => ['reservado', 'defeito', 'baixado', 'em_transito'],
            'reservado' => ['disponivel', 'em_transito'],
            'em_transito' => ['instalado', 'disponivel', 'defeito', 'perdido'],
            'instalado' => ['em_transito', 'perdido'],
            'defeito' => ['disponivel', 'baixado'],
            'perdido' => ['baixado'],
            default => [],
        }, true);
    }
}
