<?php

namespace App\Validation;

final class BusinessRules
{
    public function cnpj_formato($value): bool
    {
        if (!is_string($value) || preg_match('/\A[0-9]{14}\z/', $value) !== 1 || preg_match('/\A([0-9])\1{13}\z/', $value)) { return false; }
        foreach ([[5,4,3,2,9,8,7,6,5,4,3,2], [6,5,4,3,2,9,8,7,6,5,4,3,2]] as $weights) {
            $sum = 0;
            foreach ($weights as $i => $weight) { $sum += (int) $value[$i] * $weight; }
            $digit = $sum % 11 < 2 ? 0 : 11 - $sum % 11;
            if ((int) $value[count($weights)] !== $digit) { return false; }
        }
        return true;
    }

    public function senha_segura($value): bool
    {
        return is_string($value) && mb_strlen($value) >= 8 && strlen($value) <= 72;
    }

    public function cpf_formato($value): bool
    {
        return is_string($value) && preg_match('/\A[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}\z/', $value) === 1;
    }

    public function cep_formato($value): bool
    {
        return is_string($value) && preg_match('/\A[0-9]{5}-[0-9]{3}\z/', $value) === 1;
    }
}
