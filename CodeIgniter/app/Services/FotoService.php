<?php

namespace App\Services;

use App\Domain\StatusOS;
use App\Exceptions\FormException;
use App\Models\AnexoModel;
use App\Models\OsHistoricoModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;
use Throwable;

final class FotoService extends WriteService
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const MAX_PHOTOS = 30;
    private const FORMATS = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
    private string $storage;

    public function __construct(?BaseConnection $db = null, ?string $storage = null)
    {
        parent::__construct($db);
        // Only server code chooses this directory; it is never supplied by a request.
        $this->storage = rtrim($storage ?? config('Fotos')->directory, '/');
    }

    public function upload(int $orderId, ?UploadedFile $file, mixed $description, int $actorId): int
    {
        $created = null;
        try {
            return $this->transaction(function () use ($orderId, $file, $description, $actorId, &$created) {
                $actor = $this->operationalActor($actorId, ['eletricista']);
                $order = $this->operationalOrder($orderId, $actor, ['em_atendimento']);
                if (!is_string($description) || mb_strlen(trim($description)) > 255) { throw new FormException(['descricao_foto'=>'Descrição deve ter até 255 caracteres.']); }
                if (!$file || !$file->isValid() || $file->hasMoved()) { throw new FormException(['foto'=>'Selecione uma foto válida. O upload precisa estar completo e dentro do limite do PHP.']); }
                $temporary = $file->getTempName();
                clearstatcache(true,$temporary);
                $size = @filesize($temporary);
                if ($size === false || $size < 1 || $size > self::MAX_BYTES) { throw new FormException(['foto'=>'Cada foto deve ter no máximo 10 MiB.']); }
                // Both values come from the bytes on disk, not the browser's MIME/name.
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporary);
                $image = @getimagesize($temporary);
                if (!isset(self::FORMATS[$mime]) || !$image || ($image['mime'] ?? '') !== $mime || $image[0] < 1 || $image[1] < 1) { throw new FormException(['foto'=>'Envie uma imagem JPEG, PNG ou WebP válida.']); }
                if ((new AnexoModel($this->db))->where('ordem_servico_anx',$orderId)->where('tipo_anx','foto')->countAllResults() >= self::MAX_PHOTOS) { throw new FormException(['foto'=>'A OS já possui 30 fotos ativas. Remova uma foto antes de enviar outra.']); }
                $directory = $this->storage . '/' . $orderId;
                if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) { throw new \RuntimeException('Photo directory creation failed.'); }
                $root = realpath($this->storage);
                $public = realpath(FCPATH);
                if ($root && $public && ($root === $public || str_starts_with($root,$public . '/'))) { throw new \RuntimeException('Photo storage must be private.'); }
                if (!$root || realpath($directory) !== $root . '/' . $orderId) { throw new \RuntimeException('Invalid photo directory.'); }
                $name = bin2hex(random_bytes(32)) . '.' . self::FORMATS[$mime];
                $candidate = $directory . '/' . $name;
                if (file_exists($candidate)) { throw new \RuntimeException('Photo name collision.'); }
                $created = $candidate;
                if (!$file->move($directory, $name)) { throw new \RuntimeException('Photo move failed.'); }
                if ($file->getName() !== $name) {
                    $created = $directory . '/' . $file->getName();
                    throw new \RuntimeException('Photo name changed during move.');
                }
                // CI4 resets directory permissions on move; keep photos private.
                if (!@chmod($directory,0700) || !@chmod($created,0600)) { throw new \RuntimeException('Photo permissions failed.'); }
                $id = (int) (new AnexoModel($this->db))->insert(['ordem_servico_anx'=>$orderId,'arquivo_anx'=>$orderId . '/' . $name,'tipo_anx'=>'foto','descricao_anx'=>trim($description),'usuario_anx'=>$actorId,'mime_anx'=>$mime,'tamanho_anx'=>$size,'data_anx'=>date('Y-m-d H:i:s')]);
                $this->history($order,$actorId,'foto_adicionada',$id,trim($description));
                return $id;
            });
        } catch (Throwable $e) {
            // The filesystem does not roll back with MySQL. Compensate only this new file.
            if ($created && is_file($created) && !@unlink($created)) { log_message('error','Falha na compensação de foto da OS {os}.',['os'=>$orderId]); }
            if ($e instanceof FormException || $e instanceof \CodeIgniter\Exceptions\PageNotFoundException) { throw $e; }
            log_message('error','Falha de armazenamento de foto da OS {os}; código {code}.',['os'=>$orderId,'code'=>$e->getCode()]);
            throw new FormException(['foto'=>'Não foi possível armazenar a foto. Tente novamente.']);
        }
    }

    public static function canRemove(array $order, array $actor, array $photo): bool
    {
        if (in_array($order['status_oss'],StatusOS::FINAIS,true)) { return false; }
        return $actor['papel_usu'] === 'gestor' || ($actor['papel_usu'] === 'eletricista' && $order['status_oss'] === 'em_atendimento' && (int) $order['eletricista_oss'] === (int) ($actor['id_ele'] ?? 0) && (int) $photo['usuario_anx'] === (int) $actor['id_usu']);
    }

    public function remove(int $orderId, int $photoId, mixed $reason, int $actorId): void
    {
        $this->transaction(function () use ($orderId,$photoId,$reason,$actorId) {
            $actor = $this->operationalActor($actorId,['gestor','eletricista']);
            $order = $this->operationalOrder($orderId,$actor,StatusOS::PENDENTES);
            $model = new AnexoModel($this->db);
            $photo = $model->where('ordem_servico_anx',$orderId)->where('tipo_anx','foto')->find($photoId);
            if (!$photo) { $this->notFound(); }
            if (!self::canRemove($order,$actor,$photo)) { throw new FormException(['operacao'=>'Você não pode remover esta foto.']); }
            if (!is_string($reason) || trim($reason) === '' || mb_strlen(trim($reason)) > 255) { throw new FormException(['motivo_foto'=>'Informe o motivo da remoção, até 255 caracteres.']); }
            $model->delete($photoId);
            $this->history($order,$actorId,'foto_removida',$photoId,trim($reason));
            // Preserve the bytes and original uploader for historical evidence.
        });
    }

    /** Called only after the controller checks role, OS ownership and active attachment. */
    public function path(array $photo): string
    {
        $relative = $photo['arquivo_anx'];
        $root = realpath($this->storage);
        $pattern = '~^' . (int) $photo['ordem_servico_anx'] . '/[a-f0-9]{64}\.(jpg|png|webp)$~D';
        $mime = $photo['mime_anx'] ?? '';
        $public = realpath(FCPATH);
        if ($root && $public && ($root === $public || str_starts_with($root,$public . '/'))) { $this->notFound(); }
        if (!$root || !preg_match($pattern,$relative,$parts) || !isset(self::FORMATS[$mime]) || $parts[1] !== self::FORMATS[$mime]) { $this->notFound(); }
        $path = $root . '/' . $relative;
        clearstatcache(true,$path);
        if (realpath($path) !== $path || !is_file($path) || !is_readable($path) || @filesize($path) !== (int) $photo['tamanho_anx']) {
            log_message('warning','Arquivo indisponível para foto {foto} da OS {os}.',['foto'=>$photo['id_anx'],'os'=>$photo['ordem_servico_anx']]);
            $this->notFound();
        }
        return $path;
    }

    private function history(array $order, int $actor, string $event, int $photo, string $note): void
    {
        (new OsHistoricoModel($this->db))->insert(['ordem_servico_osh'=>$order['id_oss'],'usuario_osh'=>$actor,'eletricista_osh'=>$order['eletricista_oss'],'evento_osh'=>$event,'status_anterior_osh'=>$order['status_oss'],'status_osh'=>$order['status_oss'],'observacao_osh'=>$note,'dados_osh'=>json_encode(['foto'=>$photo])]);
    }
}
