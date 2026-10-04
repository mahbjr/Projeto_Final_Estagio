<?php

namespace App\Domain;

use App\Exceptions\FormException;

/** DECIMAL(15,3) represented in thousandths, without floating point rounding. */
final class Quantidade
{
    public const MAX = 999999999999999;

    public static function parse(mixed $value, int $precision, string $field = 'quantidade'): int
    {
        if (!is_string($value) || $precision < 0 || $precision > 3
            || !preg_match('/^(0|[1-9][0-9]{0,11})(?:[.,]([0-9]{1,3}))?$/D', trim($value), $parts)
            || strlen($parts[2] ?? '') > $precision) {
            throw new FormException([$field => 'Informe uma quantidade positiva, sem separador de milhar, com até ' . $precision . ' casas decimais.']);
        }
        $quantity = (int) $parts[1] * 1000 + (int) str_pad($parts[2] ?? '', 3, '0');
        if ($quantity <= 0) { throw new FormException([$field => 'A quantidade deve ser maior que zero.']); }
        return $quantity;
    }

    public static function stored(string $value): int
    {
        // Used only with DECIMAL values returned by MySQL, including zero.
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return (int) $integer * 1000 + (int) str_pad($fraction, 3, '0');
    }

    public static function decimal(int $value): string
    {
        if ($value < 0 || $value > self::MAX) { throw new FormException(['quantidade' => 'Quantidade fora do limite do estoque.']); }
        return intdiv($value, 1000) . '.' . str_pad((string) ($value % 1000), 3, '0', STR_PAD_LEFT);
    }
}
