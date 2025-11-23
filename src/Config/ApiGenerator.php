<?php

namespace JivteshGhatora\Ci4ApiGenerator\Config;

use CodeIgniter\Config\BaseConfig;

class ApiGenerator extends BaseConfig
{
    /**
     * API prefix
     */
    public string $apiPrefix = 'api/v1';

    /**
     * Enable API Documentation
     */
    public bool $apiDocumentationEnabled = true;

    /**
     * API Documentation path
     */
    public string $apiDocumentationPath = 'api/v1/docs';

    /**
     * Route Cache TTL in seconds
     */
    public int $maxCacheAge = 3600; // 1 hour

    /**
     * Tables to generate APIs for
     * Empty array means all tables
     */
    public array $tables = [];

    /**
     * Tables to exclude from API generation
     */
    public array $excludeTables = ['migrations', 'ci_sessions'];

    /**
     * Default endpoints if not specified
     * Format: ['index', 'show', 'create', 'update', 'delete']
     * 'index' - List all records
     * 'show' - Get a single record by ID
     * 'create' - Create a new record
     * 'update' - Update an existing record
     * 'delete' - Delete a record
     */
    public array $defaultEndpoints = ['index', 'show', 'create', 'update', 'delete'];

    /**
     * Enabled endpoints per table
     * Format: ['table_name' => ['index', 'show', 'create', 'update', 'delete']]
     */
    public array $enabledEndpoints = [];

    /**
     * Columns to show in responses per table
     * Format: ['table_name' => ['column1', 'column2']]
     * Empty array means all columns
     */
    public array $enabledColumnsToShow = [];

    /**
     * Custom validation rules per table
     * Format: ['table_name' => ['column' => 'required|max_length[50]']]
     * If specified, these rules will override the auto-generated validation rules for those columns.
     * For columns not specified here, auto-generated rules from database schema will be used.
     */
    public array $validationRules = [];

    
    /**
     * Multi-tenant column filters
     * Format: ['column_name' => 'value']
     * These columns will be automatically filtered in all queries.
     * Set value to null or empty string to disable filtering for that column.
     * Example: ['client_id' => null] and then set dynamically via custom filter.
     */    
    public array $multiTenantColumns = [];

    /**
     * Tables to exclude from multi-tenant filtering
     * Format: ['table_name1', 'table_name2']
     * These tables will not have multi-tenant filters applied.
     */
    public array $multiTenantExcludeTables = [];

    /**
     * Pagination settings
     */
    public int $perPage = 20;
    public int $maxPerPage = 100;

}