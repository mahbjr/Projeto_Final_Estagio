<?php

namespace App\Services;

use App\Domain\StatusOS;
use App\Exceptions\FormException;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;
use Throwable;

abstract class WriteService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** Preserve an unchanged historical value; validate every new or edited phone. */
    protected function phone(mixed $value, ?string $original, string $field): string
    {
        if (!is_string($value)) { throw new FormException([$field => 'Informe um telefone válido.']); }
        if ($original !== null && $value === $original) { return $original; }
        $value = trim($value);
        if ($value === '') { return ''; }
        $digits = str_replace(['(', ')', ' ', '-'], '', $value);
        if (preg_match('/\A[1-9]{2}[0-9]{8,9}\z/', $digits) !== 1) {
            throw new FormException([$field => 'Informe DDD e telefone com 10 ou 11 dígitos.']);
        }
        $length = strlen($digits) - 6;
        return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, $length) . '-' . substr($digits, -4);
    }

    protected function validate(array $data, array $rules): void
    {
        $validation = service('validation');
        $validation->reset();
        $validation->setRules($rules);
        if (!$validation->run($data)) {
            throw new FormException($validation->getErrors());
        }
    }

    protected function transaction(callable $operation): mixed
    {
        $this->db->transException(true)->transBegin();
        try {
            $result = $operation();
            if (!$this->db->transStatus() || !$this->db->transCommit()) {
                throw new FormException(['operacao' => 'Não foi possível salvar. Tente novamente.']);
            }
            return $result;
        } catch (Throwable $e) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            if ($e instanceof DatabaseException) {
                log_message('error', 'Falha transacional em {service}; código {code}.', ['service' => static::class, 'code' => $e->getCode()]);
                // SQL, credentials and password hashes must never reach the form.
                throw new FormException(['operacao' => 'Não foi possível salvar. Verifique se os identificadores já estão cadastrados.']);
            }
            throw $e;
        }
    }

    protected function pendingOrders(string $field, int $id): bool
    {
        return $this->db->table('tbl_os')->where($field, $id)->where('data_exclusao_oss', null)
            ->whereIn('status_oss', StatusOS::PENDENTES)->countAllResults() > 0;
    }

    protected function notFound(): never
    {
        throw PageNotFoundException::forPageNotFound('Cadastro não encontrado.');
    }

    protected function operationalActor(int $id, array $roles): array
    {
        $this->db->query("SELECT id_usu FROM tbl_usuario WHERE papel_usu = 'gestor' AND ativo_usu = 1 AND data_exclusao_usu IS NULL ORDER BY id_usu FOR UPDATE");
        $actor = $this->db->query('SELECT * FROM tbl_usuario WHERE id_usu = ? FOR UPDATE', [$id])->getRowArray();
        if (!$actor || !$actor['ativo_usu'] || $actor['data_exclusao_usu'] || !in_array($actor['papel_usu'], $roles, true)) {
            throw new FormException(['operacao' => 'Seu acesso não permite esta operação.']);
        }
        if ($actor['papel_usu'] === 'eletricista') {
            $tech = $this->db->query('SELECT * FROM tbl_eletricista WHERE usuario_ele = ? AND data_exclusao_ele IS NULL FOR UPDATE', [$id])->getRowArray();
            if (!$tech || trim($tech['matricula_ele']) === '') { throw new FormException(['operacao' => 'Cadastro técnico inválido.']); }
            $actor['id_ele'] = (int) $tech['id_ele'];
        }
        return $actor;
    }

    protected function confirmDeletion(int $actorId, mixed $password): void
    {
        $actor = $this->operationalActor($actorId, ['gestor']);
        if (!is_string($password) || $password === '' || strlen($password) > 72 || !password_verify($password, $actor['senha_usu'])) {
            throw new FormException(['senha_atual' => 'Informe sua senha atual corretamente para confirmar a exclusão.']);
        }
    }

    protected function operationalOrder(int $id, array $actor, array $statuses): array
    {
        $order = $this->db->query('SELECT * FROM tbl_os WHERE id_oss = ? AND data_exclusao_oss IS NULL FOR UPDATE', [$id])->getRowArray();
        if (!$order) { $this->notFound(); }
        if ($actor['papel_usu'] === 'eletricista' && (int) $order['eletricista_oss'] !== $actor['id_ele']) {
            throw new FormException(['operacao' => 'Esta OS não está atribuída a você.']);
        }
        if (!in_array($order['status_oss'], $statuses, true)) { throw new FormException(['operacao' => 'O status da OS não permite esta operação.']); }
        return $order;
    }
}
