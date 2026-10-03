<?php

namespace App\Exceptions;

use RuntimeException;

final class FormException extends RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Revise os dados informados.');
    }
}
