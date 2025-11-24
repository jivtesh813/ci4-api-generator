<?php

namespace JivteshGhatora\Ci4ApiGenerator\Models;

use CodeIgniter\Model;

class ApiModel extends Model
{
    protected $table;
    protected $primaryKey = 'id';
    protected $allowedFields = [];
    protected $useTimestamps = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    /**
     * Set the table name dynamically
     */
    public function setTable(string $table)
    {
        $this->table = $table;
        $this->autoSetAllowedFields();
        $this->detectTimestamps();
        return $this;
    }

    /**
     * Set the primary key dynamically
     */
    public function setPrimaryKey(string $key)
    {
        $this->primaryKey = $key;
        return $this;
    }

    /**
     * Automatically set allowed fields based on table columns
     * Renamed to avoid conflict with parent method
     */
    protected function autoSetAllowedFields()
    {
        $fields = $this->db->getFieldNames($this->table);
        
        // Remove primary key from allowed fields for insert/update
        $filtered = array_filter($fields, function($field) {
            return $field !== $this->primaryKey;
        });
        
        // Use parent's setAllowedFields method with proper signature
        $this->allowedFields = array_values($filtered);
    }

    /**
     * Detect if table has timestamp columns
     */
    protected function detectTimestamps()
    {
        $fields = $this->db->getFieldNames($this->table);
        
        // Check for common timestamp column names
        $hasCreatedAt = in_array('created_at', $fields);
        $hasUpdatedAt = in_array('updated_at', $fields);
        
        if ($hasCreatedAt && $hasUpdatedAt) {
            $this->useTimestamps = true;
            $this->createdField = 'created_at';
            $this->updatedField = 'updated_at';
        }
        
        // Check for soft deletes
        if (in_array('deleted_at', $fields)) {
            $this->useSoftDeletes = true;
            $this->deletedField = 'deleted_at';
        }
    }

    /**
     * Generate validation rules for the table
     * Renamed to avoid conflict with parent method
     */
    public function generateValidationRules(): array
    {
        $rules = [];
        $fields = $this->db->getFieldData($this->table);
        
        foreach ($fields as $field) {
            // Skip primary key
            if ($field->name === $this->primaryKey) {
                continue;
            }
            
            $fieldRules = [];
            
            // Required fields
            if (!$field->nullable && $field->default === null) {
                $fieldRules[] = 'required';
            }
            
            // Type validation
            if (strpos($field->type, 'int') !== false) {
                $fieldRules[] = 'integer';
            } elseif (in_array($field->type, ['decimal', 'float', 'double'])) {
                $fieldRules[] = 'decimal';
            } elseif (strpos($field->type, 'varchar') !== false && $field->max_length) {
                $fieldRules[] = "max_length[{$field->max_length}]";
            }
            
            if (!empty($fieldRules)) {
                $rules[$field->name] = implode('|', $fieldRules);
            }
        }
        
        return $rules;
    }

    /**
     * Search across multiple columns
     */
    public function search(string $query, array $columns = [])
    {
        if (empty($columns)) {
            $columns = $this->allowedFields;
        }
        
        $this->groupStart();
        foreach ($columns as $column) {
            $this->orLike($column, $query);
        }
        $this->groupEnd();
        
        return $this;
    }

    /**
     * Apply filters from request
     */
    public function applyFilters(array $filters)
    {
        foreach ($filters as $field => $value) {
            if (in_array($field, $this->allowedFields)) {
                // Handle different operators
                if (is_array($value)) {
                    $this->whereIn($field, $value);
                } else {
                    $this->where($field, $value);
                }
            }
        }
        
        return $this;
    }

    /**
     * Get table column information
     */
    public function getTableStructure(): array
    {
        return $this->db->getFieldData($this->table);
    }

    /**
     * Get the primary key(s) of a given table
     * 
     * @param string|null $table Table name (uses current table if not provided)
     * @return array|null Array of primary key column names or null if not found
     */
    public function getPrimaryKey(?string $table = null): ?array
    {
        $tableName = $table ?? $this->table;
        
        if (empty($tableName)) {
            return null;
        }
        
        // Get table indexes - handles both single and composite primary keys
        $query = $this->db->query("SHOW KEYS FROM `{$tableName}` WHERE Key_name = 'PRIMARY'");
        $results = $query->getResult();
        
        if (empty($results)) {
            return null;
        }
        
        // Extract column names (ordered by Seq_in_index for composite keys)
        $primaryKeys = array_map(function($row) {
            return $row->Column_name;
        }, $results);
        
        return $primaryKeys;
    }
}