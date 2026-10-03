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
}
