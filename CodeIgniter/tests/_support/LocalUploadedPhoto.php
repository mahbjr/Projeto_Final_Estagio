<?php

namespace Tests\Support;

use CodeIgniter\HTTP\Files\UploadedFile;

/** Service tests replace only the PHP HTTP-upload transport; real multipart is tested separately. */
final class LocalUploadedPhoto extends UploadedFile
{
    public bool $failMove = false;
    public function isValid(): bool { return $this->error === UPLOAD_ERR_OK && is_file($this->path); }
    public function move(string $targetPath, ?string $name = null, bool $overwrite = false)
    {
        if ($this->failMove) { throw new \RuntimeException('Simulated storage failure.'); }
        if (!$name || !$this->isValid() || $this->hasMoved || file_exists($targetPath . '/' . $name)) { return false; }
        $this->hasMoved = rename($this->path,$targetPath . '/' . $name);
        $this->path = $targetPath . '/'; $this->name = $name;
        return $this->hasMoved;
    }
}
