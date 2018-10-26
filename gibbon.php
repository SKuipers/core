<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Gibbon\Services\DefaultServicesProvider;

if (!empty($autoloader)) return;

// Setup the composer autoloader
$autoloader = require_once __DIR__.'/vendor/autoload.php';

// Require the system-wide functions
require_once __DIR__.'/functions.php';

// Core Services
$container = new League\Container\Container();
$container->delegate(new League\Container\ReflectionContainer);
$container->add('autoloader', $autoloader);

$container->addServiceProvider(new Gibbon\Services\CoreServiceProvider(__DIR__));
$container->addServiceProvider(Gibbon\Services\HTTPServiceProvider::class);

// Globals for backwards compatibility
$gibbon = $container->get('config');
$gibbon->session = $container->get('session');
$gibbon->locale = $container->get('locale');
$guid = $gibbon->getConfig('guid');
$caching = $gibbon->getConfig('caching');
$version = $gibbon->getConfig('version');

// Handle Gibbon installation redirect
if (!$gibbon->isInstalled() && !$gibbon->isInstalling()) {
    header("Location: ./installer/install.php");
    exit;
}

// Autoload the current module namespace
if (!empty($gibbon->session->get('module'))) {
    $moduleNamespace = preg_replace('/[^a-zA-Z0-9]/', '', $gibbon->session->get('module'));
    $autoloader->addPsr4('Gibbon\\'.$moduleNamespace.'\\', realpath(__DIR__).'/modules/'.$gibbon->session->get('module'));
    $autoloader->register(true);
}

// Initialize using the database connection
if ($gibbon->isInstalled() == true) {
    
    $mysqlConnector = new Gibbon\Database\MySqlConnector();
    if ($pdo = $mysqlConnector->connect($gibbon->getConfig())) {
        $container->add('db', $pdo);
        $container->share(Gibbon\Contracts\Database\Connection::class, $pdo);
        $connection2 = $pdo->getConnection();

        $gibbon->initializeCore($container);
    } else {
        // We need to handle failed database connections after install. Display an error if no connection 
        // can be established. Needs a specific error page once header/footer is split out of index.
        if (!$gibbon->isInstalling()) {
            include('./error.php');
            exit;
        }
    }
}

$container->add('settings', [
    'httpVersion'                       => '1.1',
    'responseChunkSize'                 => 4096,
    'outputBuffering'                   => 'append',
    'determineRouteBeforeAppMiddleware' => false,
    'displayErrorDetails'               => false,
    'addContentLengthHeader'            => true,
    'routerCacheFile'                   => false,
]);


$app = new \Slim\App($container);


$app->post('/modules/{module}/{page}', function (Request $request, Response $response, array $args) {

    // Globals for backwards compatibility
    $container = $this;
    $gibbon = $this->get('config');
    $guid = $gibbon->getConfig('guid');
    $caching = $gibbon->getConfig('caching');
    $version = $gibbon->getConfig('version');
    $autoloader = $this->get('autoloader');
    $pdo = $this->get('db');
    $connection2 = $pdo->getConnection();

    $target = urldecode($request->getUri()->getPath());

    $basePath = realpath('./');

    chdir('./modules/'.$args['module'].'/');

    if (is_file($basePath.'/'.$target)) {
        include $basePath.'/'.$target;
    }

    exit;

    return $response;
});

$app->any('/', function (Request $request, Response $response, array $args) {

    // Globals for backwards compatibility
    $container = $this;
    $gibbon = $this->get('config');
    $guid = $gibbon->getConfig('guid');
    $caching = $gibbon->getConfig('caching');
    $version = $gibbon->getConfig('version');
    $autoloader = $this->get('autoloader');
    $pdo = $this->get('db');
    $connection2 = $pdo->getConnection();

    $target = basename($request->getUri()->getBasePath());

    if (!is_file('./'.$target)) {
        $target = 'index.php';
    }

    include './'.$target;

    $locationRedirect = array_filter(headers_list(), function ($item) {
        return stripos($item, 'Location:') !== false;
    });

    if (!empty($locationRedirect)) {
        header_remove("Location");
        $responseRedirect = trim(str_replace('Location:', '', current($locationRedirect)));

        return $response->withRedirect($responseRedirect, 301);
    }

    return $response;
});


$app->run();

exit;
