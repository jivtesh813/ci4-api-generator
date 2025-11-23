<?php

namespace JivteshGhatora\Ci4ApiGenerator\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class RefreshApis extends BaseCommand
{
    protected $group = 'API Generator';
    protected $name = 'api:refresh';
    protected $description = 'Clear API cache and regenerate everything';

    public function run(array $params)
    {
        CLI::write('🔄 Refreshing API configuration...', 'yellow');
        CLI::newLine();

        // Clear route cache
        $cachePath = WRITEPATH . 'cache/api-routes.php';
        if (file_exists($cachePath)) {
            unlink($cachePath);
            CLI::write('✓ Cleared route cache', 'green');
        }

        // Clear documentation
        $docsPath = WRITEPATH . 'api-docs/';
        if (is_dir($docsPath)) {
            $files = glob($docsPath . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            CLI::write('✓ Cleared documentation files', 'green');
        }

        CLI::newLine();
        CLI::write('🔧 Regenerating APIs...', 'cyan');
        CLI::newLine();

        // Call api:generate with --all option
        command('api:generate --all');
    }
}