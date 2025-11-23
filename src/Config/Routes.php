<?php

namespace JivteshGhatora\Ci4ApiGenerator\Config;

use JivteshGhatora\Ci4ApiGenerator\Libraries\DatabaseReader;
use JivteshGhatora\Ci4ApiGenerator\Libraries\RouteBuilder;


/**
 * Creates or updates a cache file for API routes.
 *
 * This function generates a PHP cache file containing the provided API routes 
 * and a timestamp indicating when the cache was generated.
 *  If the cache file already exists, it will be deleted
 * before creating a new one.
 *
 * @param array $apiRoutes An array of API routes to be cached.
 *
 * @return void
 */
function createCacheIfNotExists($apiRoutes)
{
    $cacheFile = WRITEPATH . 'cache/api-routes.php';
    if (file_exists($cacheFile)) {
        unlink($cacheFile);        
    }
    $cacheContent = "<?php\n\nreturn " . var_export([
        'routes' => $apiRoutes,
        'generated_at' => date('Y-m-d H:i:s')
    ], true) . ";\n";
    file_put_contents($cacheFile, $cacheContent);
}


// Cache file path
$cacheFile = WRITEPATH . 'cache/api-routes.php';
$config = config('ApiGenerator');

// Check if cache exists and is valid
if (file_exists($cacheFile)) {    
    // Load routes from cache (FAST!)
    $cachedData = include $cacheFile;
    $apiRoutes = $cachedData['routes'] ?? [];
    
    // Check if cache is older than 1 hour and regenerate
    $cacheAge = time() - filemtime($cacheFile);
    $maxCacheAge = $config->maxCacheAge ?? 3600;
    
    if ($cacheAge > $maxCacheAge) {
        // Cache is stale, regenerate
        $reader = new DatabaseReader();
        $builder = new RouteBuilder();
        $tables = $reader->getTables();
        $apiRoutes = $builder->buildRoutes($tables);

        createCacheIfNotExists($apiRoutes);
        
    }
} else {
    // No cache, generate routes (SLOW - first time only)
    $reader = new DatabaseReader();
    $builder = new RouteBuilder();
    $tables = $reader->getTables();
    $apiRoutes = $builder->buildRoutes($tables);

    createCacheIfNotExists($apiRoutes);
}

// Register all API routes
$prefix = $config->apiPrefix;  // dynamically loaded

$routes->group($prefix, function($routes) use ($apiRoutes, $prefix) {

    foreach ($apiRoutes as $route => $handler) {

        [$method, $path] = explode(' ', $route, 2);

        // Trim prefix from the path if it already begins with it
        $pattern = '#^' . preg_quote($prefix, '#') . '/#';
        $path = preg_replace($pattern, '', $path);

        $routes->match([$method], $path, $handler);
    }

    // Custom 404 Override for API routes
    $routes->set404Override(function () {

        $response = service('response');

        $data = [
            'status'   => 404,
            'error'    => 404,
            'messages' => [
                'error' => 'API endpoint not found.'
            ],
        ];

        $json = json_encode($data);

        $response
            ->setStatusCode(404)
            ->setHeader('Content-Type', 'application/json')
            ->setBody($json);

        return $response->getBody();
    });

});

// Register API Documentation route if enabled
if ($config->apiDocumentationEnabled) {   
    //Create a route to send writable/api-docs/openapi.json
    $routes->get('api-docs/openapi.json', function() {
        $filePath = WRITEPATH . 'api-docs/openapi.json';
        if (file_exists($filePath)) {
            return service('response')
                ->setHeader('Content-Type', 'application/json')
                ->setBody(file_get_contents($filePath));
        } else {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('API documentation not found.');
        }
    });
    $routes->get($config->apiDocumentationPath, '\JivteshGhatora\Ci4ApiGenerator\Controllers\DocsController::index');

}



