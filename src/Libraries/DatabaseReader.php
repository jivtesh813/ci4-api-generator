<?php

namespace JivteshGhatora\Ci4ApiGenerator\Libraries;

use Config\Database;
use Config\DatabaseReader as ReaderConfig;

class DatabaseReader
{
    protected $db;
    protected $config;
    protected string $driver;
    protected string $schema;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->config = config('ApiGenerator');

        $readerCfg = config('DatabaseReader');

        // Auto detect driver
        $this->driver = $readerCfg->driver === 'auto'
            ? strtolower($this->db->DBDriver)
            : strtolower($readerCfg->driver);

        // PostgreSQL schema
        $this->schema = $readerCfg->pgsqlSchema ?? 'public';
    }
    
    /**
     * Get all tables from database
     * Respects config for inclusion/exclusion
     * @return array
     */
    public function getTables(): array
    {
        $tables = $this->db->listTables();
        
        // Filter based on config - if specific tables are defined, only use those
        if (!empty($this->config->tables)) {
            $tables = array_intersect($tables, $this->config->tables);
        }
        
	// Exclude tables from config
        return array_values(array_diff($tables, $this->config->excludeTables));
    }

    /**
     * Get column information for a specific table
     * @param string $table
     * @return array
     */
    public function getTableColumns(string $table): array
    {
        $fields = $this->db->getFieldData($table);
        $columns = [];
        
        foreach ($fields as $field) {
            
            // For some drivers, you may need to check extra info or parse type string
            $columns[] = [
                'name' => $field->name,
                'type' => $this->normalizeType($field->type),
                'max_length' => $field->max_length,
                'primary_key' => $field->primary_key,
                'nullable' => $field->nullable,
                'default' => $field->default
            ];
        }

        // Config filter
        $enabledColumns = $this->config->enabledColumnsToShow[$table] ?? [];

        if (!empty($enabledColumns)) {
            $columns = array_values(array_filter($columns, function($c) use ($enabledColumns) {
                return in_array($c['name'], $enabledColumns);
            }));
        }
        return $columns;
    }

    /**
     * Get primary key column name for a table
     * @param string $table
     * @return string|null
     */
    public function getPrimaryKey(string $table): ?string
    {
        foreach ($this->db->getFieldData($table) as $field) {
            if ($field->primary_key) return $field->name;
        }
        return 'id';
    }

    /**
     * Get foreign key relationships for a table
     * @param string $table
     * @return array
     */
    public function getForeignKeys(string $table): array
    {
        $driver = strtolower($this->db->DBDriver);

        // ======================================
        // PostgreSQL version
        // ======================================
        if ($driver === 'postgre' || $this->driver === 'pgsql') {
            $sql = "
                SELECT
                    kcu.column_name AS column_name,
                    ccu.table_name AS referenced_table,
                    ccu.column_name AS referenced_column
                FROM information_schema.table_constraints tc
                JOIN information_schema.key_column_usage kcu
                    ON tc.constraint_name = kcu.constraint_name
                JOIN information_schema.constraint_column_usage ccu
                    ON ccu.constraint_name = tc.constraint_name
                WHERE tc.constraint_type = 'FOREIGN KEY'
                AND tc.table_name = ?
                AND tc.table_schema = ?
            ";
            $result = $this->db->query($sql, [$table, $this->schema]);

        // ======================================
        // MySQL version (original)
        // ======================================
        }else{
            $sql = "
                SELECT 
                    COLUMN_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME
                FROM 
                    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE 
                    TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?
                    AND REFERENCED_TABLE_NAME IS NOT NULL
            ";
            $result = $this->db->query($query, [$table]);   
        }
        
        $foreignKeys = [];

        if ($result) {
            foreach ($result->getResultArray() as $row) {
                $foreignKeys[] = [
                    'column'            => $row['column_name'] ?? $row['COLUMN_NAME'],
                    'referenced_table'  => $row['referenced_table_name'] ?? $row['REFERENCED_TABLE_NAME'],
                    'referenced_column' => $row['referenced_column_name'] ?? $row['REFERENCED_COLUMN_NAME']
                ];
            }
        }

        return $foreignKeys;
    }

    /** ------------------------------------------------------------
     * TYPE DETECTION (MySQL + PostgreSQL)
     * ------------------------------------------------------------ */
    private function normalizeType(string $type): string
    {
        $t = strtolower($type);

        // PostgreSQL mappings
        $pgsqlMap = [
            'integer'   => 'int',
            'smallint'  => 'int',
            'bigint'    => 'int',
            'serial'    => 'int',
            'bigserial' => 'int',
            'numeric'   => 'decimal',
            'timestamp' => 'datetime',
            'timestamptz' => 'datetime',
            'bool'      => 'boolean',
        ];

        if ($this->driver === 'pgsql') {
            foreach ($pgsqlMap as $pgType => $mapped) {
                if (str_contains($t, $pgType)) {
                    return $mapped;
                }
            }
        }

        // MySQL fallback
        return $t;
    }

    /**
     * Get complete information about all tables
     * @return array
     */
    public function getTableInfo(): array
    {
        $tables = $this->getTables();
        $info = [];
        
        foreach ($tables as $table) {
            $info[$table] = [
                'columns' => $this->getTableColumns($table),
                'primary_key' => $this->getPrimaryKey($table),
                'foreign_keys' => $this->getForeignKeys($table)
            ];
        }
        
        return $info;
    }

    /**
     * Get table statistics
     * @param string $table
     * @return array
     */
    public function getTableStats(string $table): array
    {
        $driver = strtolower($this->db->DBDriver);

        // ======================================
        // PostgreSQL version
        // ======================================
        if ($driver === 'postgre') {

            // Basic table statistics
            $sql = "
                SELECT 
                    reltuples::bigint AS row_count,
                    pg_relation_size(relid) AS data_size,
                    pg_indexes_size(relid) AS index_size,
                    pg_total_relation_size(relid) AS total_size
                FROM pg_catalog.pg_statio_user_tables
                WHERE relname = ?
            ";
            $stats = $this->db->query($sql, [$table])->getRowArray();

            if (!$stats) {
                return [
                    'row_count'   => 0,
                    'data_size'   => 0,
                    'index_size'  => 0,
                    'total_size'  => 0,
                    'avg_row_len' => 0,
                    'created_at'  => null,
                    'updated_at'  => null,
                ];
            }

            // PostgreSQL does NOT track created_at or updated_at for tables
            return [
                'row_count'   => (int) $stats['row_count'],
                'data_size'   => (int) $stats['data_size'],
                'index_size'  => (int) $stats['index_size'],
                'total_size'  => (int) $stats['total_size'],
                'avg_row_len' => $stats['row_count'] > 0 
                                    ? (int) ($stats['data_size'] / $stats['row_count'])
                                    : 0,
                'created_at'  => null,
                'updated_at'  => null,
            ];
        }

        // ======================================
        // MySQL version (original)
        // ======================================
        $sql = "
            SELECT 
                TABLE_ROWS as row_count,
                AVG_ROW_LENGTH as avg_row_len,
                DATA_LENGTH as data_size,
                INDEX_LENGTH as index_size,
                CREATE_TIME as created_at,
                UPDATE_TIME as updated_at
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
        ";

        return $this->db->query($sql, [$table])->getRowArray();
    }

    /**
     * Check if a table exists
     * @param string $table
     * @return bool
     */
    public function tableExists(string $table): bool
    {
        return in_array($table, $this->db->listTables());
    }

    /**
     * Get table indexes
     * @param string $table
     * @return array
     */
    public function getTableIndexes(string $table): array
    {
        $driver = strtolower($this->db->DBDriver);

        // ======================================
        // PostgreSQL version
        // ======================================
        if ($driver === 'postgre') {
            $sql = "
                SELECT
                    i.indexname as name,
                    ix.indisunique as unique,
                    am.amname as type,
                    ARRAY(
                        SELECT a.attname
                        FROM pg_attribute a
                        WHERE a.attrelid = ix.indrelid
                        AND a.attnum = ANY(ix.indkey)
                    ) as columns
                FROM pg_indexes i
                JOIN pg_class c ON c.relname = i.tablename
                JOIN pg_index ix ON c.oid = ix.indrelid AND i.indexname = ix.indexname
                JOIN pg_am am ON am.oid = ix.indam
                WHERE i.tablename = ?
            ";

            $indexes = [];
            foreach ($this->db->query($sql, [$table])->getResultArray() as $row) {
                $indexes[] = [
                    'name'    => $row['name'],
                    'unique'  => ($row['unique'] === true || $row['unique'] === 't'),
                    'type'    => strtoupper($row['type']),
                    'columns' => $row['columns'], // already array
                ];
            }

            return $indexes;
        }

        // ======================================
        // MySQL version (original)
        // ======================================
        $result = $this->db->query("SHOW INDEX FROM `{$table}`");
        if (!$result) return [];

        $indexes = [];
        foreach ($result->getResultArray() as $row) {
            $keyName = $row['Key_name'];
            
            if (!isset($indexes[$keyName])) {
                $indexes[$keyName] = [
                    'name' => $keyName,
                    'unique' => $row['Non_unique'] == 0,
                    'type' => $row['Index_type'],
                    'columns' => []
                ];
            }
            
            $indexes[$keyName]['columns'][] = $row['Column_name'];
        }

        return array_values($indexes);
    }

    /**
     * Get required fields (not nullable and no default value)
     * @param string $table
     * @return array
     */
    public function getRequiredFields(string $table): array
    {
        $required = [];
        foreach ($this->getTableColumns($table) as $column) {
            if (!$column['primary_key'] && !$column['nullable'] && $column['default'] === null) {
                $required[] = $column['name'];
            }
        }
        return $required;
    }

    /**
     * Get timestamp fields (created_at, updated_at, etc.)
     * @param string $table
     * @return array
     */
    public function getTimestampFields(string $table): array
    {
        $columns = $this->getTableColumns($table);
        $timestamps = [];
        
        $timestampNames = ['created_at', 'updated_at', 'deleted_at', 'timestamp'];
        
        foreach ($columns as $column) {
            if (in_array(strtolower($column['name']), $timestampNames)) {
                $timestamps[] = $column['name'];
            }
        }
        
        return $timestamps;
    }

    /**
     * Get database name
     * @return string
     */
    public function getDatabaseName(): string
    {
        return $this->db->getDatabase();
    }

    /**
     * Get validation rules based on column type
     * @param string $table
     * @return array
     * ====================
     * VALIDATION RULES (PostgreSQL aware)
     * ====================
     */
    public function getValidationRules(string $table): array
    {
        $columns = $this->getTableColumns($table);
        $rules = [];
        
        foreach ($columns as $column) {
            // Skip primary key
            if ($column['primary_key']) {
                continue;
            }
            
            $fieldRules = [];
            
            // Required rule
            if (!$column['nullable'] && $column['default'] === null) {
                $fieldRules[] = 'required';
            }
            
            // Type-based rules
            $type = strtolower($column['type']);
            
            // Numeric types
            if (in_array($type, ['integer', 'int', 'smallint', 'bigint', 'serial', 'bigserial'])) {
                $fieldRules[] = 'integer';

            } elseif (in_array($type, ['real', 'double precision', 'numeric', 'decimal', 'float'])) {
                $fieldRules[] = 'decimal';

            // Boolean
            } elseif ($type === 'boolean' || $type === 'bool') {
                $fieldRules[] = 'in_list[true,false]';

            // Character types
            } elseif (strpos($type, 'char') !== false || strpos($type, 'varchar') !== false || strpos($type, 'character varying') !== false) {
                if ($column['max_length']) {
                    $fieldRules[] = "max_length[{$column['max_length']}]";
                }

            // Text
            } elseif ($type === 'text' || $type === 'json' || $type === 'jsonb') {
                $fieldRules[] = 'string';

            // UUID
            } elseif ($type === 'uuid') {
                $fieldRules[] = 'valid_uuid';

            // Date/time
            } elseif (
                strpos($type, 'date') !== false ||
                strpos($type, 'time') !== false ||
                strpos($type, 'timestamp') !== false
            ) {
                $fieldRules[] = 'valid_date';

            // Binary
            } elseif ($type === 'bytea') {
                $fieldRules[] = 'string';
            }
            
            if (!empty($fieldRules)) {
                $rules[$column['name']] = implode('|', $fieldRules);
            }
        }
        
        return $rules;
    }
}