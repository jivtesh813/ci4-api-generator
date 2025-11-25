<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class DatabaseReader extends BaseConfig
{
    /**
     * Mode deteksi driver database:
     *   - "auto"  → otomatis membaca driver dari Database::connect()
     *   - "mysql" → paksa MySQL mode
     *   - "pgsql" → paksa PostgreSQL mode
     */
    public string $driver = 'auto';

    /**
     * Opsi tambahan jika PostgreSQL membutuhkan schema tertentu
     */
    public string $pgsqlSchema = 'public';
}
