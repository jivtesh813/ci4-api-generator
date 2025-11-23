<?php

namespace JivteshGhatora\Ci4ApiGenerator\Controllers;

use CodeIgniter\RESTful\ResourceController;
use JivteshGhatora\Ci4ApiGenerator\Models\ApiModel;

class ApiController extends ResourceController
{
    protected $format = 'json';
    protected $table;
    protected $primaryKey = 'id';
    protected $modelName = ApiModel::class;
    protected $config;

    public function __construct()
    {
        $this->config = config('ApiGenerator');
    }

    /**
     * GET /api/v1/table
     * @param string $table - Table name passed as route segment
     */
    public function index($table = null)
    {
        $this->table = $table;       
        
        if (!$this->table) {
            return $this->fail('Table name is required');
        }
        
        if (!$this->isEndpointEnabled('index')) {
            return $this->failForbidden('Endpoint disabled');
        }

        $model = new ApiModel();
        $model->setTable($this->table);

        $page = (int)($this->request->getGet('page') ?? 1);
        $perPage = (int)($this->request->getGet('per_page') ?? config('ApiGenerator')->perPage);
        $perPage = min($perPage, config('ApiGenerator')->maxPerPage);

        // Handle filters from query parameters
        $filters = $this->request->getGet();
        unset($filters['page'], $filters['per_page']);

        if(isset($this->config->enabledColumnsToShow) && isset($this->config->enabledColumnsToShow[$this->table])) {
            $model->select($this->config->enabledColumnsToShow[$this->table]);
            $validColumns = $this->config->enabledColumnsToShow[$this->table];
        } else {
            // Get all columns from the table if not restricted
            $validColumns = $model->getTableColumns();
        }

        // Only apply filters for valid columns
        foreach ($filters as $field => $value) {
            if (in_array(strtolower($field), array_map('strtolower', $validColumns))) {
                $model->where($field, $value);
            }
            else {
                // Return error if invalid filter field is passed
                return $this->fail("Invalid filter parameter: '{$field}'");
            }
        }

        if(isset($this->config->multiTenantColumns) && is_array($this->config->multiTenantColumns)) {
            //Only run below if $multiTenantExcludeTables is not set or table is not in the exclude list
            if (
                !isset($this->config->multiTenantExcludeTables) ||
                !is_array($this->config->multiTenantExcludeTables) ||
                !in_array($this->table, $this->config->multiTenantExcludeTables)
            ) 
            {
                foreach ($this->config->multiTenantColumns as $column => $value) {
                    if (!empty($value)) {
                        $model->where($column, $value);
                    }
                }
            }
        }

        $data = $model->paginate($perPage);
        $pager = $model->pager;

        return $this->respond([
            'status' => 'success',
            'data' => $data,
            'pagination' => [
                'current_page' => $pager->getCurrentPage(),
                'per_page' => $pager->getPerPage(),
                'total' => $pager->getTotal(),
                'last_page' => $pager->getLastPage()
            ]
        ]);
    }

    /**
     * GET /api/v1/table/:id
     * @param int $id - Record ID
     * @param string $table - Table name passed as route segment
     */
    public function show($id = null, $table = null)
    {
        $this->table = $table;
        
        if (!$this->table) {
            return $this->fail('Table name is required');
        }
        
        if (!$this->isEndpointEnabled('show')) {
            return $this->failForbidden('Endpoint disabled');
        }

        $model = new ApiModel();
        $model->setTable($this->table);
        $model->setPrimaryKey($this->primaryKey);

        if(isset($this->config->enabledColumnsToShow) && isset($this->config->enabledColumnsToShow[$this->table])) {
            $model->select($this->config->enabledColumnsToShow[$this->table]);
        }

        if(isset($this->config->multiTenantColumns) && is_array($this->config->multiTenantColumns)) {
             if (
                !isset($this->config->multiTenantExcludeTables) ||
                !is_array($this->config->multiTenantExcludeTables) ||
                !in_array($this->table, $this->config->multiTenantExcludeTables)
            ) 
            {
                foreach ($this->config->multiTenantColumns as $column => $value) {
                    if ($value !== null) {
                        $model->where($column, $value);
                    }
                }
            }
        }

        $data = $model->find($id);

        if (!$data) {
            return $this->failNotFound('Record not found');
        }

        return $this->respond([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * POST /api/v1/table
     * @param string $table - Table name passed as route segment
     */
    public function create($table = null)
    {
        $this->table = $table;
        
        if (!$this->table) {
            return $this->fail('Table name is required');
        }
        
        if (!$this->isEndpointEnabled('create')) {
            return $this->failForbidden('Endpoint disabled');
        }

        $model = new ApiModel();
        $model->setTable($this->table);

        $rules = $this->getValidationRules($model);
        if (!$this->validate($rules)) {
            return $this->fail($this->validator->getErrors());
        }

        $data = $this->request->getJSON(true);
        
        if (empty($data)) {
            $data = $this->request->getPost();
        }

        if (empty($data)) {
            return $this->fail('No data provided');
        }

        try {
            if ($model->insert($data)) {
                return $this->respondCreated([
                    'status' => 'success',
                    'message' => 'Record created successfully',
                    'id' => $model->getInsertID()
                ]);
            }

            return $this->fail($model->errors());
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->fail($this->parseDatabaseError($e->getMessage()));
        }
    }

    /**
     * PUT /api/v1/table/:id
     * @param int $id - Record ID
     * @param string $table - Table name passed as route segment
     */
    public function update($id = null, $table = null)
    {
        $this->table = $table;
        
        if (!$this->table) {
            return $this->fail('Table name is required');
        }
        
        if (!$this->isEndpointEnabled('update')) {
            return $this->failForbidden('Endpoint disabled');
        }

        $model = new ApiModel();
        $model->setTable($this->table);
        $model->setPrimaryKey($this->primaryKey);

        $data = $this->request->getJSON(true);
        
        if (empty($data)) {
            $data = $this->request->getRawInput();
        }

        if (empty($data)) {
            return $this->fail('No data provided');
        }

        // Get validation rules and make them optional for updates
        $rules = $this->getValidationRules($model, true);
        
        // Only validate fields that are present in the data
        $rulesToValidate = array_intersect_key($rules, $data);
        
        if (!empty($rulesToValidate) && !$this->validate($rulesToValidate)) {
            return $this->fail($this->validator->getErrors());
        }

        try {
            if ($model->update($id, $data)) {
                return $this->respond([
                    'status' => 'success',
                    'message' => 'Record updated successfully'
                ]);
            }

            return $this->fail($model->errors());
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->fail($this->parseDatabaseError($e->getMessage()));
        }
    }

    /**
     * DELETE /api/v1/table/:id
     * @param int $id - Record ID
     * @param string $table - Table name passed as route segment
     */
    public function delete($id = null, $table = null)
    {
        $this->table = $table;
        
        if (!$this->table) {
            return $this->fail('Table name is required');
        }
        
        if (!$this->isEndpointEnabled('delete')) {
            return $this->failForbidden('Endpoint disabled');
        }

        $model = new ApiModel();
        $model->setTable($this->table);
        $model->setPrimaryKey($this->primaryKey);

        if ($model->delete($id)) {
            return $this->respondDeleted([
                'status' => 'success',
                'message' => 'Record deleted successfully'
            ]);
        }

        return $this->fail('Failed to delete record');
    }

    /**
     * Check if endpoint is enabled for this table
     * @param string $endpoint
     * @return bool
     */
    protected function isEndpointEnabled(string $endpoint): bool
    {
        $config = config('ApiGenerator');
        
        if (isset($config->enabledEndpoints[$this->table])) {
            return in_array($endpoint, $config->enabledEndpoints[$this->table]);
        }
        
        return in_array($endpoint, $config->defaultEndpoints);
    }

    /**
     * Parse database error message to provide user-friendly error
     * @param string $errorMessage
     * @return string
     */
    protected function parseDatabaseError(string $errorMessage): string
    {
        // Check for foreign key constraint violations
        if (stripos($errorMessage, 'foreign key constraint fails') !== false) {
            // Extract the constraint name if possible
            if (preg_match('/CONSTRAINT `([^`]+)`/', $errorMessage, $matches)) {
                $constraintName = $matches[1];
                // Try to extract field name from constraint name
                if (preg_match('/_([^_]+)_foreign$/', $constraintName, $fieldMatches)) {
                    $fieldName = $fieldMatches[1];
                    return "Invalid value for '{$fieldName}'. The referenced record does not exist.";
                }
            }
            return 'Foreign key constraint violation. Please ensure all referenced records exist.';
        }
        
        // Check for duplicate entry errors
        if (stripos($errorMessage, 'duplicate entry') !== false) {
            // Extract the duplicate value and key name
            if (preg_match("/Duplicate entry '([^']+)' for key '([^']+)'/", $errorMessage, $matches)) {
                $value = $matches[1];
                $keyName = $matches[2];
                // Try to extract field name from key name
                if (preg_match('/\.([^.]+)$/', $keyName, $fieldMatches)) {
                    $fieldName = str_replace('_UNIQUE', '', $fieldMatches[1]);
                    return "The value '{$value}' already exists for '{$fieldName}'. Please use a unique value.";
                }
            }
            return 'Duplicate entry error. This value already exists in the database.';
        }
        
        // Check for NULL constraint violations
        if (stripos($errorMessage, 'cannot be null') !== false || stripos($errorMessage, 'column') !== false && stripos($errorMessage, 'null') !== false) {
            if (preg_match("/Column '([^']+)'/", $errorMessage, $matches)) {
                $columnName = $matches[1];
                return "The field '{$columnName}' is required and cannot be empty.";
            }
            return 'Required field is missing or null.';
        }
        
        // Return generic database error message
        return 'Database error occurred. Please check your input data and try again.';
    }

    /**
     * Get validation rules for the table
     * Merges auto-generated rules with custom rules from config
     * 
     * @param ApiModel $model
     * @param bool $isUpdate If true, makes fields optional by prepending permit_empty
     */
    protected function getValidationRules(ApiModel $model, bool $isUpdate = false): array
    {
        // Get auto-generated rules from database schema
        $rules = $model->generateValidationRules();
        
        // Check if custom rules exist for this table
        if (isset($this->config->validationRules[$this->table])) {
            // Merge with custom rules, custom rules override auto-generated ones
            $rules = array_merge($rules, $this->config->validationRules[$this->table]);
        }
        
        // For updates, make all fields optional (partial updates allowed)
        if ($isUpdate) {
            foreach ($rules as $field => $rule) {
                // Remove 'required' rule for partial updates
                $rule = str_replace('required|', '', $rule);
                $rule = str_replace('|required', '', $rule);
                if ($rule === 'required') {
                    $rule = '';
                }
                
                // Keep other validation rules intact (don't add permit_empty automatically)
                // Empty strings will still be validated against the remaining rules
                if (!empty($rule)) {
                    $rules[$field] = $rule;
                } else {
                    // If no rules left, remove the field from validation
                    unset($rules[$field]);
                }
            }
        }
        
        return $rules;
    }
}