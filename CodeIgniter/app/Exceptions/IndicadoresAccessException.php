<?php

namespace App\Exceptions;

use RuntimeException;

final class IndicadoresAccessException extends RuntimeException
{
    public function __construct() { parent::__construct('Acesso aos indicadores não permitido.', 403); }
}
