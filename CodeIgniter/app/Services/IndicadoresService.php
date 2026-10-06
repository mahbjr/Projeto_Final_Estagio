<?php

namespace App\Services;

use App\Domain\StatusOS;
use App\Exceptions\FormException;
use App\Models\IndicadoresModel;
use App\Libraries\PermissionPolicy;
use App\Exceptions\IndicadoresAccessException;
use DateTimeImmutable;
use DateTimeZone;

final class IndicadoresService
{
    public function __construct(private readonly IndicadoresModel $model = new IndicadoresModel()) {}

    public function context(array $user, array $input): array
    {
        if (!PermissionPolicy::allows('inicio', $user['papel_usu'] ?? null)) {
            throw new IndicadoresAccessException();
        }
        $personal = $user['papel_usu'] === 'eletricista';
        $owner = $personal ? (int) ($user['id_ele'] ?? 0) : null;
        if ($personal && !$owner) { throw new IndicadoresAccessException(); }
        $now = new DateTimeImmutable('now', new DateTimeZone('America/Fortaleza'));
        $defaults = ['data_inicio' => $now->format('Y-m-01'), 'data_fim' => $now->format('Y-m-d'), 'status_oss' => '', 'eletricista' => '', 'cliente' => ''];
        $values = []; $errors = [];
        foreach ($defaults as $field => $default) {
            $value = $input[$field] ?? $default;
            if (!is_string($value)) { $errors[$field] = 'Informe um único valor válido.'; $value = ''; }
            $values[$field] = mb_substr(trim($value), 0, 100);
            if (is_string($value) && mb_strlen(trim($value)) > 100) { $errors[$field] = 'Valor de filtro inválido.'; }
        }
        $choices = $this->model->choices($owner);
        $clients = ['' => 'Todos'];
        foreach ($choices['clients'] as $row) { $clients[(string) $row['id_cli']] = $row['nome_cli']; }
        $owners = $personal ? [(string) $owner => $user['display_name']] : ['' => 'Todos', 'sem_atribuicao' => 'Não atribuída'];
        foreach ($choices['owners'] as $row) {
            $owners[(string) $row['id_ele']] = ($row['nome_completo_usu'] ?: $row['nome_usu']) . ' · ' . $row['matricula_ele'];
        }
        foreach (['data_inicio', 'data_fim'] as $field) {
            $date = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $values[$field]) ? DateTimeImmutable::createFromFormat('!Y-m-d', $values[$field], new DateTimeZone('America/Fortaleza')) : false;
            if (!$date || $date->format('Y-m-d') !== $values[$field] || $date->format('Y') < '1000'
                || ($field === 'data_fim' && $values[$field] === '9999-12-31')) {
                $errors[$field] = 'Informe uma data válida no formato dia/mês/ano.';
            }
        }
        if (!isset($errors['data_inicio']) && !isset($errors['data_fim']) && $values['data_inicio'] > $values['data_fim']) {
            $errors['data_fim'] = 'A data final deve ser igual ou posterior à inicial.';
        }
        if ($values['status_oss'] !== '' && !in_array($values['status_oss'], StatusOS::TODOS, true)) { $errors['status_oss'] = 'Selecione um status válido.'; }
        foreach (['eletricista' => $owners, 'cliente' => $clients] as $field => $allowed) {
            $value = $values[$field];
            $validId = $value === '' || ($field === 'eletricista' && $value === 'sem_atribuicao') || (bool) preg_match('/^[1-9][0-9]{0,9}$/D', $value);
            if (!$validId) { $errors[$field] = 'Selecione uma opção válida.'; continue; }
            if ($personal && $field === 'eletricista') {
                if ($value !== '' && $value !== (string) $owner) { throw new IndicadoresAccessException(); }
                $values[$field] = (string) $owner;
            } elseif (!array_key_exists($value, $allowed)) {
                if ($personal) { throw new IndicadoresAccessException(); }
                $errors[$field] = 'Selecione uma opção válida.';
            }
        }
        return ['filters' => $values, 'errors' => $errors, 'clients' => $clients, 'owners' => $owners, 'personal' => $personal, 'owner' => $owner];
    }

    public function dashboard(array $context): array
    {
        if ($context['errors']) { throw new FormException($context['errors']); }
        $filters = $context['filters'];
        $filters['fim_exclusivo'] = (new DateTimeImmutable($filters['data_fim'], new DateTimeZone('America/Fortaleza')))->modify('+1 day')->format('Y-m-d 00:00:00');
        return $this->model->dashboard($filters, $context['owner']);
    }
}
