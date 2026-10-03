<?php

namespace App\Libraries;

final class Identifiers
{
    public static function cnpj(string $value): string
    {
        return strtoupper(str_replace(['.', '/', '-'], '', trim($value)));
    }

    public static function cpf(string $value): string
    {
        $digits = str_replace(['.', '-'], '', trim($value));
        return preg_match('/\A[0-9]{11}\z/', $digits) === 1
            ? substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9)
            : trim($value);
    }

    public static function cep(string $value): string
    {
        $digits = str_replace('-', '', trim($value));
        return preg_match('/\A[0-9]{8}\z/', $digits) === 1 ? substr($digits, 0, 5) . '-' . substr($digits, 5) : trim($value);
    }

    public static function displayCnpj(string $value): string
    {
        return substr($value, 0, 2) . '.' . substr($value, 2, 3) . '.' . substr($value, 5, 3) . '/' . substr($value, 8, 4) . '-' . substr($value, 12, 2);
    }
}
