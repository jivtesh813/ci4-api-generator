<?php

namespace JivteshGhatora\Ci4ApiGenerator\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use JivteshGhatora\Ci4ApiGenerator\Libraries\DatabaseReader;
use JivteshGhatora\Ci4ApiGenerator\Libraries\RouteBuilder;
use JivteshGhatora\Ci4ApiGenerator\Libraries\DocumentationGenerator;


class GenerateApis extends BaseCommand
{
    protected $group = 'API Generator';
    protected $name = 'api:generate';
    protected $description = 'Analyze database and generate API documentation/cache';
    protected $usage = 'api:generate [options]';
    protected $arguments = [];
    protected $options = [
        '--cache' => 'Generate route cache file',
        '--openapi' => 'Generate OpenAPI/Swagger spec',
        '--all' => 'Generate everything',
    ];

    public function run(array $params)
    {
        CLI::write('╔══════════════════════════════════════════╗', 'yellow');
        CLI::write('║   CodeIgniter 4 API Generator v1.0      ║', 'yellow');
        CLI::write('╚══════════════════════════════════════════╝', 'yellow');
        CLI::newLine();

        $reader = new DatabaseReader();
        $builder = new RouteBuilder();
        $config = config('ApiGenerator');

        // Step 1: Scan database
        CLI::write('📊 Scanning database tables...', 'cyan');
        CLI::newLine();

        $tables = $reader->getTables();
        $tableInfo = $reader->getTableInfo();

        if (empty($tables)) {
            CLI::error('No tables found or all tables are excluded!');
            return;
        }

        // Display found tables
        $tableData = [];
        foreach ($tables as $table) {
            $columns = count($tableInfo[$table]['columns']);
            $pk = $tableInfo[$table]['primary_key'];
            $endpoints = $config->enabledEndpoints[$table] ?? $config->defaultEndpoints;
            
            $tableData[] = [
                $table,
                $columns,
                $pk,
                implode(', ', $endpoints)
            ];
        }

        $thead = ['Table', 'Columns', 'Primary Key', 'Enabled Endpoints'];
        CLI::table($tableData, $thead);
        CLI::newLine();

        // Step 2: Build routes
        CLI::write('🔧 Building API routes...', 'cyan');
        $routes = $builder->buildRoutes($tables);
        CLI::write('✓ Generated ' . count($routes) . ' route(s)', 'green');
        CLI::newLine();

        // Step 3: Generate outputs based on options
        $generateCache = CLI::getOption('cache') || CLI::getOption('all');
        $generateOpenApi = CLI::getOption('openapi') || CLI::getOption('all');

        // If no options, show summary only
        if (!$generateCache && !$generateOpenApi) {
            CLI::write('💡 Tip: Use options to generate files:', 'yellow');            
            CLI::write('   --cache     Generate route cache file', 'white');          
            CLI::write('   --openapi   Generate OpenAPI/Swagger spec', 'white');
            CLI::write('   --all       Generate everything', 'white');
            CLI::newLine();
        }

        $docGen = new DocumentationGenerator($tableInfo, $routes, $config);

        // Generate Route Cache
        if ($generateCache) {
            CLI::write('💾 Generating route cache...', 'cyan');
            $cachePath = WRITEPATH . 'cache/api-routes.php';
            
            $cacheContent = "<?php\n\n";
            $cacheContent .= "// Auto-generated API routes cache\n";
            $cacheContent .= "// Generated: " . date('Y-m-d H:i:s') . "\n\n";
            $cacheContent .= "return " . var_export([
                'routes' => $routes,
                'generated_at' => date('Y-m-d H:i:s')
            ], true) . ";\n";

            file_put_contents($cachePath, $cacheContent);
            CLI::write('✓ Cache saved to: ' . $cachePath, 'green');
        }

        // Generate OpenAPI Spec
        if ($generateOpenApi) {
            CLI::write('📋 Generating OpenAPI specification...', 'cyan');
            $openapiPath = WRITEPATH . 'api-docs/';
            
            if (!is_dir($openapiPath)) {
                mkdir($openapiPath, 0755, true);
            }

            $openapi = $docGen->generateOpenAPI();
            file_put_contents($openapiPath . 'openapi.json', json_encode($openapi, JSON_PRETTY_PRINT));
            
            CLI::write('✓ OpenAPI spec saved to: ' . $openapiPath . 'openapi.json', 'green');
        }

        CLI::newLine();
        CLI::write('╔══════════════════════════════════════════╗', 'green');
        CLI::write('║          Generation Complete! ✓          ║', 'green');
        CLI::write('╚══════════════════════════════════════════╝', 'green');
        CLI::newLine();
        CLI::write('📍 View all routes: php spark routes', 'white');
        CLI::write('📍 Test your APIs at: ' . base_url($config->apiPrefix), 'white');
    }
}