<?php

namespace JivteshGhatora\Ci4ApiGenerator\Libraries;

class DocumentationGenerator
{
    protected $config;
    protected $routes;
    protected $tableInfo;

    
    /**
     * Constructor
     *
     * @param array $tableInfo  Associative array containing table structures.
     * @param array $routes     Associative array of API routes.
     * @param object $config    Configuration object for the API generator.
     */
    public function __construct($tableInfo, $routes, $config)
    {
        $this->tableInfo = $tableInfo;
        $this->routes = $routes;        
        $this->config = $config;
    }

    /**
     * Extracts the table name from a handler definition string.
     *
     * Handler strings follow a controller-method-path pattern such as:
     *   "Vendor\Package\Controllers\ApiController::index/attributes"
     *   "Vendor\Package\Controllers\ApiController::show/$1/attributes"
     *
     * This function returns only the final path segment (e.g., "attributes"),
     * regardless of how many intermediate segments exist.
     *
     *
     * @param string $handler  The handler string from which to extract the table name.
     *
     * @return string          The extracted table name, or 'unknown' if no valid segment is found.
     */
    protected function extractTableFromHandler(string $handler): string
    {
        if (preg_match('/\/([^\/]+)$/', $handler, $matches)) {
            return $matches[1]; // always the last segment
        }

        return 'unknown';
    }

    /**
     * Cleans the given API path by removing the configured API prefix.
     * This ensures that the returned path does not contain the prefix,
     * as it is already included in the server URL.
     *
     * @param string $path The API path to be cleaned.
     * @return string The cleaned API path, starting with a leading slash.
     */  
    protected function cleanPath(string $path): string
    {
        $prefix = $this->config->apiPrefix;
        
        // Remove leading slash if present
        $path = ltrim($path, '/');
        $prefix = ltrim($prefix, '/');
        
        // If path starts with prefix, remove it
        if (strpos($path, $prefix) === 0) {
            $path = substr($path, strlen($prefix));
        }
        
        // Ensure path starts with /
        return '/' . ltrim($path, '/');
    }

    /**
     * Generates an OpenAPI specification based on the provided configuration and routes.
     *
     * This function constructs an OpenAPI document by analyzing the application's routes,
     * extracting relevant metadata, and formatting it according to the OpenAPI standard.
     * It supports customization through configuration options and can include additional
     * information such as security schemes, servers, and tags.
     *
     * @return array The generated OpenAPI specification as an associative array.
     */
    public function generateOpenAPI(): array
    {
        $openapi = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Generated API',
                'version' => '1.0.0',
                'description' => 'Auto-generated API documentation from database tables'
            ],
            'servers' => [
                [
                    'url' => base_url($this->config->apiPrefix),
                    'description' => 'API Server'
                ]
            ],
            'paths' => []
        ];

        foreach ($this->routes as $route => $handler) {
            $parts = explode(' ', $route, 2);
            $method = strtolower($parts[0]);
            
            // Extract table from new route format first
            $table = $this->extractTableFromHandler($handler);
            
            // Get actual primary key column names for this table
            $primaryKeys = [];
            if (isset($this->tableInfo[$table]['columns'])) {
                foreach ($this->tableInfo[$table]['columns'] as $column) {
                    if ($column['primary_key']) {
                        $primaryKeys[] = $column['name'];
                    }
                }
            }
            
            // Replace (:segment) with actual primary key column names
            $rawPath = $parts[1];
            $keyIndex = 0;
            $rawPath = preg_replace_callback('/\(:segment\)/', function($matches) use (&$keyIndex, $primaryKeys) {
                $keyName = isset($primaryKeys[$keyIndex]) ? $primaryKeys[$keyIndex] : ('id' . ($keyIndex + 1));
                $keyIndex++;
                return '{' . $keyName . '}';
            }, $rawPath);
            
            // Also handle old (:num) pattern for backward compatibility
            $rawPath = preg_replace_callback('/\(:num\)/', function($matches) use ($primaryKeys) {
                return '{' . (isset($primaryKeys[0]) ? $primaryKeys[0] : 'id') . '}';
            }, $rawPath);
            
            // Clean the path - remove API prefix since it's in server URL
            $path = $this->cleanPath($rawPath);

            if (!isset($openapi['paths'][$path])) {
                $openapi['paths'][$path] = [];
            }

            $openapi['paths'][$path][$method] = $this->generateOpenAPIOperation($method, $table, $path);
        }

        return $openapi;
    }

    /**
     * Generate OpenAPI operation object for a specific method and path
     * @param string $method HTTP method (get, post, put, delete)
     * @param string $table  Database table name
     * @param string $path   API path
     * @return array         OpenAPI operation object
     */
    private function generateOpenAPIOperation(string $method, string $table, string $path): array
    {
        $operation = [
            'summary' => $this->getOperationSummary($method, $table, $path),
            'tags' => [ucwords(trim(str_replace('_', ' ', $table)))],
            'responses' => [
                '200' => [
                    'description' => 'Successful response',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'status' => ['type' => 'string'],
                                    'data' => ['type' => 'object']
                                ]
                            ],
                            'example' => json_decode($this->generateExampleResponse(strtoupper($method), $table), true)
                        ]
                    ]
                ]
            ]
        ];

        // Add parameters for routes with path parameters (primary keys)
        $parameters = [];
        
        // Extract all parameters from path (anything in curly braces)
        if (preg_match_all('/\{([^}]+)\}/', $path, $matches)) {
            $paramCount = count($matches[1]);
            foreach ($matches[1] as $index => $paramName) {
                $description = $paramCount > 1 
                    ? "Primary key part: {$paramName}" 
                    : "Primary key: {$paramName}";
                    
                $parameters[] = [
                    'name' => $paramName,
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'string'],
                    'description' => $description
                ];
            }
        }
        
        if (!empty($parameters)) {
            $operation['parameters'] = $parameters;
        }

        // Add request body for POST/PUT/PATCH
        if (in_array($method, ['post', 'put', 'patch'])) {
            $operation['requestBody'] = [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => $this->generateSchemaProperties($table),
                            'required' => $this->getRequiredFields($table)
                        ]
                    ]
                ]
            ];
        }

        return $operation;
    }

    /**
     * Get required fields for request body
     * @param string $table Database table name
     * @return array        List of required field names
     */
    private function getRequiredFields(string $table): array
    {
        $columns = $this->tableInfo[$table]['columns'] ?? [];
        $required = [];
        
        foreach ($columns as $column) {
            if (!$column['nullable'] && $column['default'] === null && !$column['primary_key']) {
                $required[] = $column['name'];
            }
        }
        
        return $required;
    }

    /**
     * Generate schema properties for request body
     * @param string $table Database table name
     * @return array        Associative array of field properties
     */
    private function generateSchemaProperties(string $table): array
    {
        $properties = [];
        
        if (isset($this->tableInfo[$table])) {
            foreach ($this->tableInfo[$table]['columns'] as $col) {
                $type = 'string';
                if (strpos($col['type'], 'int') !== false) {
                    $type = 'integer';
                } elseif (strpos($col['type'], 'decimal') !== false || strpos($col['type'], 'float') !== false) {
                    $type = 'number';
                } elseif (strpos($col['type'], 'bool') !== false) {
                    $type = 'boolean';
                }
                
                $properties[$col['name']] = ['type' => $type];
            }
        }
        
        return $properties;
    }

    /**
     * Format table name for display
     * @param string $table Database table name
     * @return string       Formatted table name
     */
    private function formatTableName(string $table): string
    {
        return ucwords(trim(str_replace('_', ' ', $table)));
    }

    /**
     * Get request name based on method and table
     * @param string $method HTTP method
     * @param string $table  Database table name
     * @return string        Request name
     */
    private function getRequestName(string $method, string $table): string
    {
        $names = [
            'GET' => 'List ' . $this->formatTableName($table),
            'POST' => 'Create ' . $this->formatTableName($table),
            'PUT' => 'Update ' . $this->formatTableName($table),
            'PATCH' => 'Update ' . $this->formatTableName($table),
            'DELETE' => 'Delete ' . $this->formatTableName($table)
        ];
        
        return $names[$method] ?? 'Request';
    }

    /**
     * Generate operation summary based on method and table
     * @param string $method HTTP method
     * @param string $table  Database table name
     * @param string $path   API path
     * @return string        Operation summary
     */
    private function getOperationSummary(string $method, string $table, string $path): string
    {
        // Check if path has parameters (single or composite key)
        $paramMatches = [];
        preg_match_all('/\{([^}]+)\}/', $path, $paramMatches);
        $hasParams = !empty($paramMatches[1]);
        $paramCount = count($paramMatches[1]);
        
        $paramDesc = '';
        if ($hasParams) {
            if ($paramCount > 1) {
                $paramDesc = ' by ' . implode(', ', $paramMatches[1]);
            } else {
                $paramDesc = ' by ' . $paramMatches[1][0];
            }
        }
        
        $summaries = [
            'get' => 'Retrieve ' . $this->formatTableName($table) . $paramDesc,
            'post' => 'Create new ' . $this->formatTableName($table),
            'put' => 'Update ' . $this->formatTableName($table) . $paramDesc,
            'patch' => 'Partially update ' . $this->formatTableName($table) . $paramDesc,
            'delete' => 'Delete ' . $this->formatTableName($table) . $paramDesc
        ];
        
        return $summaries[$method] ?? 'Operation on ' . $this->formatTableName($table);
    }

    /**
     * Generate example response based on method and table
     * @param string $method HTTP method
     * @param string $table  Database table name
     * @return string        JSON encoded example response
     */
    private function generateExampleResponse(string $method, string $table): string
    {
        $examples = [
            'GET' => json_encode([
                'status' => 'success',
                'data' => [$this->generateExampleBody($table)],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 20,
                    'total' => 1,
                    'last_page' => 1
                ]
            ], JSON_PRETTY_PRINT),
            'POST' => json_encode([
                'status' => 'success',
                'message' => 'Record created successfully',
                'id' => 1
            ], JSON_PRETTY_PRINT),
            'PUT' => json_encode([
                'status' => 'success',
                'message' => 'Record updated successfully'
            ], JSON_PRETTY_PRINT),
            'DELETE' => json_encode([
                'status' => 'success',
                'message' => 'Record deleted successfully'
            ], JSON_PRETTY_PRINT)
        ];
        
        return $examples[$method] ?? '{}';
    }

    /**
     * Generate example body for a given table
     * @param string $table Database table name
     * @return array        Example body data
     */
    private function generateExampleBody(string $table): array
    {
        $body = [];
        
        if (isset($this->tableInfo[$table])) {
            foreach ($this->tableInfo[$table]['columns'] as $col) {
                if ($col['primary_key']) continue; // Skip primary key
                
                // Generate sample value based on type
                if (strpos($col['type'], 'int') !== false) {
                    $body[$col['name']] = 1;
                } elseif (strpos($col['type'], 'decimal') !== false || strpos($col['type'], 'float') !== false) {
                    $body[$col['name']] = 10.50;
                } elseif (strpos($col['type'], 'bool') !== false) {
                    $body[$col['name']] = true;
                } elseif (strpos($col['type'], 'date') !== false) {
                    $body[$col['name']] = date('Y-m-d H:i:s');
                } else {
                    $body[$col['name']] = 'sample value';
                }
            }
        }
        
        return $body;
    }
}