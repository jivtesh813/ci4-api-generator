<?php

namespace JivteshGhatora\Ci4ApiGenerator\Libraries;

use JivteshGhatora\Ci4ApiGenerator\Models\ApiModel;

class RouteBuilder
{
    protected $config;

    public function __construct()
    {
        $this->config = config('ApiGenerator');
    }

    /**
     * Build routes for all tables
     * Returns array of route definitions
     * @param array $tables
     * @return array
     */
    public function buildRoutes(array $tables): array
    {
        $routes = [];
        
        foreach ($tables as $table) {
            $endpoints = $this->config->enabledEndpoints[$table] 
                ?? $this->config->defaultEndpoints;
            
            $prefix = $this->config->apiPrefix;
            $tablePath = str_replace('_', '-', $table);
            
            // Get primary key(s) to determine route pattern
            $model = new ApiModel();
            $model->setTable($table);
            $primaryKeys = $model->getPrimaryKey();
            $keyCount = $primaryKeys ? count($primaryKeys) : 1;
            
            // Build route pattern based on number of primary keys
            $keyPattern = '';
            $keyPlaceholders = '';
            for ($i = 1; $i <= $keyCount; $i++) {
                $keyPattern .= '/(:segment)';
                $keyPlaceholders .= '/$' . $i;
            }
            
            // Use route segments to pass table name instead of query parameters
            if (in_array('index', $endpoints)) {
                $routes["GET {$prefix}/{$tablePath}"] = "\JivteshGhatora\Ci4ApiGenerator\Controllers\ApiController::index/{$table}";
            }
            
            if (in_array('show', $endpoints)) {
                $routes["GET {$prefix}/{$tablePath}{$keyPattern}"] = "\JivteshGhatora\Ci4ApiGenerator\Controllers\ApiController::show{$keyPlaceholders}/{$table}";
            }
            
            if (in_array('create', $endpoints)) {
                $routes["POST {$prefix}/{$tablePath}"] = "\JivteshGhatora\Ci4ApiGenerator\Controllers\ApiController::create/{$table}";
            }
            
            if (in_array('update', $endpoints)) {
                $routes["PUT {$prefix}/{$tablePath}{$keyPattern}"] = "\JivteshGhatora\Ci4ApiGenerator\Controllers\ApiController::update{$keyPlaceholders}/{$table}";
            }
            
            if (in_array('delete', $endpoints)) {
                $routes["DELETE {$prefix}/{$tablePath}{$keyPattern}"] = "\JivteshGhatora\Ci4ApiGenerator\Controllers\ApiController::delete{$keyPlaceholders}/{$table}";
            }
        }
        
        return $routes;
    }
}