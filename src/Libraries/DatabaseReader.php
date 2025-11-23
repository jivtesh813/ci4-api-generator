<?php

namespace JivteshGhatora\Ci4ApiGenerator\Libraries;

use Config\Database;

class DatabaseReader
{
    protected $db;
    protected $config;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->config = config('ApiGenerator');
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
        $tables = array_diff($tables, $this->config->excludeTables);
        
        return array_values($tables);
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
                'type' => $field->type,
                'max_length' => $field->max_length,
                'primary_key' => $field->primary_key,
                'nullable' => $field->nullable,
                'default' => $field->default
            ];
        }
        //If $enabledColumnsToShow is set in config, filter columns
        $enabledColumnsToShow = $this->config->enabledColumnsToShow[$table] ?? [];
        if (!empty($enabledColumnsToShow)) {
            $columns = array_filter($columns, function($col) use ($enabledColumnsToShow) {
                return in_array($col['name'], $enabledColumnsToShow);
            });
            // Reindex array
            $columns = array_values($columns);
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
        $fields = $this->db->getFieldData($table);
        
        foreach ($fields as $field) {
            if ($field->primary_key) {
                return $field->name;
            }
        }
        
        return 'id'; // fallback to 'id' if no primary key found
    }

    /**
     * Get foreign key relationships for a table
     * @param string $table
     * @return array
     */
    public function getForeignKeys(string $table): array
    {
        $foreignKeys = [];
        
        // This is database-specific, here's MySQL implementation
        $query = "
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
        
        if ($result) {
            foreach ($result->getResultArray() as $row) {
                $foreignKeys[] = [
                    'column' => $row['COLUMN_NAME'],
                    'referenced_table' => $row['REFERENCED_TABLE_NAME'],
                    'referenced_column' => $row['REFERENCED_COLUMN_NAME']
                ];
            }
        }
        
        return $foreignKeys;
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
        $query = "
            SELECT 
                COUNT(*) as row_count,
                AVG(DATA_LENGTH) as avg_row_length,
                DATA_LENGTH as data_size,
                INDEX_LENGTH as index_size,
                CREATE_TIME as created_at,
                UPDATE_TIME as updated_at
            FROM 
                INFORMATION_SCHEMA.TABLES
            WHERE 
                TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
        ";
        
        $result = $this->db->query($query, [$table]);
        
        if ($result) {
            $stats = $result->getRowArray();
            
            // Get actual row count
            $countResult = $this->db->query("SELECT COUNT(*) as total FROM `{$table}`");
            $stats['row_count'] = $countResult->getRow()->total ?? 0;
            
            return $stats;
        }
        
        return [];
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
        $query = "SHOW INDEX FROM `{$table}`";
        $result = $this->db->query($query);
        
        if (!$result) {
            return [];
        }
        
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
        $columns = $this->getTableColumns($table);
        $required = [];
        
        foreach ($columns as $column) {
            // Skip primary key with auto increment
            if ($column['primary_key']) {
                continue;
            }
            
            // If not nullable and no default value, it's required
            if (!$column['nullable'] && $column['default'] === null) {
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
            
            if (strpos($type, 'int') !== false) {
                $fieldRules[] = 'integer';
            } elseif (strpos($type, 'decimal') !== false || strpos($type, 'float') !== false || strpos($type, 'double') !== false) {
                $fieldRules[] = 'decimal';
            } elseif (strpos($type, 'varchar') !== false || strpos($type, 'char') !== false) {
                if ($column['max_length']) {
                    $fieldRules[] = "max_length[{$column['max_length']}]";
                }
            } elseif (strpos($type, 'text') !== false) {
                $fieldRules[] = 'string';
            } elseif (strpos($type, 'date') !== false || strpos($type, 'time') !== false) {
                $fieldRules[] = 'valid_date';
            } elseif (strpos($type, 'email') !== false) {
                $fieldRules[] = 'valid_email';
            }
            
            if (!empty($fieldRules)) {
                $rules[$column['name']] = implode('|', $fieldRules);
            }
        }
        
        return $rules;
    }
}