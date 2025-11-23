<?php

namespace JivteshGhatora\Ci4ApiGenerator\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use JivteshGhatora\Ci4ApiGenerator\Libraries\DatabaseReader;
use JivteshGhatora\Ci4ApiGenerator\Libraries\RouteBuilder;

class ListApis extends BaseCommand
{
    protected $group = 'API Generator';
    protected $name = 'api:list';
    protected $description = 'List all generated API endpoints';
    protected $usage = 'api:list [table]';
    protected $arguments = [
        'table' => 'Filter by specific table name'
    ];

    public function run(array $params)
    {
        $reader = new DatabaseReader();
        $builder = new RouteBuilder();
        $config = config('ApiGenerator');

        $filterTable = $params[0] ?? null;

        $tables = $reader->getTables();
        
        if ($filterTable) {
            if (!in_array($filterTable, $tables)) {
                CLI::error("Table '{$filterTable}' not found or excluded!");
                return;
            }
            $tables = [$filterTable];
        }

        $routes = $builder->buildRoutes($tables);

        CLI::write('Available API Endpoints:', 'yellow');
        CLI::newLine();

        $routeData = [];
        foreach ($routes as $route => $handler) {
            $parts = explode(' ', $route, 2);
            $method = strtoupper($parts[0]);
            $path = $parts[1];
            
            // Extract table name from path
            $pathParts = explode('/', $path);
            $table = $pathParts[2] ?? 'unknown';
            
            // Determine action
            $action = 'Unknown';
            if (strpos($handler, '::index') !== false) {
                $action = 'List all';
            } elseif (strpos($handler, '::show') !== false) {
                $action = 'Get one';
            } elseif (strpos($handler, '::create') !== false) {
                $action = 'Create';
            } elseif (strpos($handler, '::update') !== false) {
                $action = 'Update';
            } elseif (strpos($handler, '::delete') !== false) {
                $action = 'Delete';
            }

            $routeData[] = [
                $this->colorMethod($method),
                base_url($path),
                $table,
                $action
            ];
        }

        if (empty($routeData)) {
            CLI::write('No routes found!', 'red');
            return;
        }

        $thead = ['Method', 'Endpoint', 'Table', 'Action'];
        CLI::table($routeData, $thead);
        
        CLI::newLine();
        CLI::write('Total endpoints: ' . count($routeData), 'green');
    }

    private function colorMethod(string $method): string
    {
        $colors = [
            'GET' => 'green',
            'POST' => 'blue',
            'PUT' => 'yellow',
            'PATCH' => 'yellow',
            'DELETE' => 'red'
        ];

        $color = $colors[$method] ?? 'white';
        return CLI::color($method, $color);
    }
}