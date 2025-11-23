<?php

namespace JivteshGhatora\Ci4ApiGenerator\Libraries;

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
            
            // Use route segments to pass table name instead of query parameters
            if (in_array('index', $endpoints)) {
                $routes["GET {$prefix}/{$tablePath}"] = "\JivteshGhatora\Ci4ApiGenerator\Controllers\ApiController::index/{$table}";
            }
            
            if (in_array('show', $endpoints)) {
                $routes["GET {$prefix}/{$tablePath}/(:num)"] = "\JivteshGhatora\Ci4ApiGenerator\Controllers\ApiController::show/$1/{$table}";
            }
            
            if (in_array('create', $endpoints)) {
                $routes["POST {$prefix}/{$tablePath}"] = "\JivteshGhatora\Ci4ApiGenerator\Controllers\ApiController::create/{$table}";
            }
            
            if (in_array('update', $endpoints)) {
                $routes["PUT {$prefix}/{$tablePath}/(:num)"] = "\JivteshGhatora\Ci4ApiGenerator\Controllers\ApiController::update/$1/{$table}";
            }
            
            if (in_array('delete', $endpoints)) {
                $routes["DELETE {$prefix}/{$tablePath}/(:num)"] = "\JivteshGhatora\Ci4ApiGenerator\Controllers\ApiController::delete/$1/{$table}";
            }
        }
        
        return $routes;
    }
}