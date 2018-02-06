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

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
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



$config = [
    'settings' => [
        'displayErrorDetails' => true,
        'version' => $version,
        'outputBuffering' => 'append',
    ],
];

$app = new \Slim\App($config);

$container = $app->getContainer();
$container['gibbon'] = $gibbon;
$container['pdo'] = $pdo;
$container['autoloader'] = $loader;


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


$app->get('/[{page}]', function (Request $request, Response $response, array $args) {

    require_once './functions.php';

    $gibbon = $this->gibbon;
    $connection2 = $this->pdo->getConnection();
    $loader = $this->autoloader;
    $pdo = $this->pdo;
    $guid = $gibbon->guid();
    $version = $this->get('settings')['version'];

    $params = $request->getQueryParams();

    $destination = isset($params['q'])? '/'.trim($params['q'], '/') : '';

    $gibbon->session->set('address', $destination);
    $gibbon->session->set('q', $destination);
    $gibbon->session->set('module', getModuleName($destination));
    $gibbon->session->set('action', getActionName($destination));

    if (!empty($destination)) {
        include '.'.$destination;
    }

    // $response->getBody()->write('You want to go to '.($destination?? 'nowhere') );
    // $response->getBody()->write($args['page']?? '');

    return $response;
})->add(function ($request, $response, $next) {

    require_once './functions.php';
    
    
    $gibbon = $this->gibbon;
    $guid = $gibbon->guid();
    $pdo = $this->pdo;
    $version = $this->get('settings')['version'];

    print('
    <html>
    <head>
        <link rel="stylesheet" type="text/css" href="./themes/Default/css/main.css?v='.$version.'" />
    </head>
    <body>
    <div id="wrap"><div id="header">
    <div id="header-logo">
							<a href="'.$_SESSION[$guid]['absoluteURL'].'"><img height="100" width="400" class="logo" alt="Logo" src="'.$_SESSION[$guid]['absoluteURL'].'/'.$_SESSION[$guid]['organisationLogo'].'"/></a>
                        </div>
                        <div id="header-menu">');

    // Display the main menu
    $mainMenu = new Gibbon\menuMain($gibbon, $pdo);
    $mainMenu->setMenu();
    print($mainMenu->getMenu());

    print('</div></div><div id="content-wrap"><div id="content">');

    $response = $next($request, $response);

    print('</div><div id="sidebar">');

    //Invoke and show Module Menu
    $menuModule = new Gibbon\menuModule($gibbon, $pdo);
    print($menuModule->getMenu('full'));

    print('</div><br style="clear: both"></div></body></html>');

    return $response;
});


$app->post('/modules/{module}/{page}', function (Request $request, Response $response, array $args)  {

    // Provide expected global variables
    $gibbon = $this->gibbon;
    $connection2 = $this->pdo->getConnection();
    $pdo = $this->pdo;
    $guid = $gibbon->guid();
    $version = $this->get('settings')['version'];

    $destination = '/modules/'.$args['module'].'/'.$args['page'];

    chdir('./modules/'.$args['module'].'/');

    if (!empty($destination)) {
        include './'.$args['page'];
    }

    return $response->withHeader('Location', $URL);
});


$app->run();


exit;

// Done breaking things?
// ---------------------------------------------------
