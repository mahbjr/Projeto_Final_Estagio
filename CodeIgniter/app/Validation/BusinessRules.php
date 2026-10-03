<?php

namespace App\Validation;

final class BusinessRules
{
    public function cnpj_formato($value): bool
    {
        return is_string($value) && preg_match('/\A[A-Z0-9]{12}[0-9]{2}\z/', $value) === 1;
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
