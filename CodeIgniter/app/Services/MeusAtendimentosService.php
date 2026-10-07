<?php

namespace App\Services;

use App\Domain\StatusOS;

final class MeusAtendimentosService
{
    public function filters(array $input): array
    {
        $errors = [];
        $query = $input['q'] ?? '';
        if (!is_string($query) || mb_strlen($query) > 150) { $errors['q'] = 'Informe uma busca com até 150 caracteres.'; $query = ''; }
        $status = $input['status_oss'] ?? 'pendentes';
        if (!is_string($status) || !in_array($status, ['pendentes', 'todos', ...StatusOS::TODOS], true)) {
            $errors['status_oss'] = 'Selecione um status válido.'; $status = 'pendentes';
        }
        $page = $input['page'] ?? '1';
        if (!is_string($page) || !preg_match('/\A[1-9][0-9]{0,8}\z/', $page)) { $errors['page'] = 'Informe uma página válida.'; $page = '1'; }
        return ['q'=>trim($query), 'status_oss'=>$status, 'page'=>(int) $page, 'errors'=>$errors];
    }
}
