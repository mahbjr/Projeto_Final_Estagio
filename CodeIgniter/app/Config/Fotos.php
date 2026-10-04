<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Fotos extends BaseConfig
{
    /** Server-controlled private storage, outside public/. */
    public string $directory = WRITEPATH . 'uploads/os-fotos';
}
