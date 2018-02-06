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

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Rakit\Validation\Validator;

$basePath = dirname(__FILE__);
$basePath = rtrim(str_replace('\\', '/', $basePath), '/');

// Handle Gibbon installation redirect
if (file_exists($basePath.'/config.php') == false || filesize($basePath.'/config.php') == 0) {
    // Test if installer already invoked and ignore.
    if (false === strpos($_SERVER['PHP_SELF'], 'installer/install.php')) {
        $URL = './installer/install.php';
        header("Location: {$URL}");
        exit();
    }
}

// Setup the composer autoloader
$autoloader = require_once 'vendor/autoload.php';
$autoloader->addPsr4('Gibbon\\', [ $basePath . '/src']);
$autoloader->addPsr4('Gibbon\\', [ $basePath . '/src/Gibbon']);
$autoloader->addPsr4('Library\\', [ $basePath . '/src/Library']);
$autoloader->register(true);

// Setup the old autoloader (depricate)
require_once $basePath.'/src/Autoloader.php';
$loader = new Autoloader($basePath);
$loader->register();


// New configuration object
$gibbon = new Gibbon\core($basePath, $_SERVER['PHP_SELF']);


// Set global config variables, for backwards compatability
$guid = $gibbon->guid();
$caching = $gibbon->getCaching();
$version = $gibbon->getVersion();


// Require the system-wide functions
// require_once './functions.php';


if ($gibbon->isInstalled() == true) {

	// New PDO DB connection
	$pdo = new Gibbon\sqlConnection();
	$connection2 = $pdo->getConnection();

	// Initialize using the database connection
	$gibbon->initializeCore($pdo);
}


// ---------------------------------------------------
// Start breaking things!

$config = [
    'settings' => [
        'displayErrorDetails' => true,
        'version' => $version,
        'outputBuffering' => 'append',
    ],
];

$app = new \Slim\App($config);


// Register services with the container
$container = $app->getContainer();
$container['gibbon'] = $gibbon;
$container['pdo'] = $pdo;
$container['autoloader'] = $loader;


// Register core routes
$app->get('/module/{module}[/{actions:.*}]', function (Request $request, Response $response, array $args) {

    $response->getBody()->write('Hello, lovely.');
    return $response;
});

$app->get('/user[/[{action}]]', function (Request $request, Response $response, array $args) {

    $validator = new Validator;

    $validation = $validator->validate($request->getQueryParams(), [
        'name'                  => 'required|min:3',
        'number'                => 'required|numeric',
    ]);

    if ($validation->fails()) {
        $errors = $validation->errors();
        $response->getBody()->write('Validation: FAILED!! <br/>');

        echo '<pre>';
        print_r($errors->all());
        echo '</pre>';
        // Return a standardized validation failed response
    } else {
        $response->getBody()->write('Validation: Passed<br/>');
        // Carry on ...
    }

    return $response;
});


$app->get('/[{page}]', function(Request $request, Response $response, array $args) {

    $params = $request->getQueryParams();
    if (!empty($params['q'])) {
        $controller = new Gibbon\HTTP\LegacyPageController($this);
        $response = $controller($request, $response, $args);
    } else {
        $response->getBody()->write('Do some dashboard stuff');
    }

    return $response;
})->add(Gibbon\HTTP\HeaderFooterMiddleware::class);


$app->post('/modules/{module}/{page}', Gibbon\HTTP\LegacyProcessController::class);


$app->run();


exit;

// Done breaking things?
// ---------------------------------------------------
