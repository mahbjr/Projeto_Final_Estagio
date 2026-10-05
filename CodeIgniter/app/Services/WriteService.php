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
