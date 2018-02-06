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

namespace Gibbon\HTTP;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Container\ContainerInterface as ContainerInterface;

/**
 * LegacyProcessController
 */
class LegacyProcessController {

    protected $container;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    public function __invoke(Request $request, Response $response, array $args)
    {
        // Provide expected global variables
        $gibbon = $this->container->get('gibbon');
        $pdo = $this->container->get('pdo');
        $connection2 = $pdo->getConnection();
        $guid = $gibbon->guid();
        $version = $this->container->get('settings')['version'];

        // Prevent invalid characters in the path
        if (stristr($args['module'], '..') !== false || stristr($args['page'], '..') !== false) {
            throw new \Slim\Exception\NotFoundException($request, $response, $args);
        }

        // Disallow access to non-existant module directories
        if (!is_dir('./modules/'.$args['module'].'/')) {
            throw new \Slim\Exception\NotFoundException($request, $response, $args);
        }

        // Change directories to ensure all includes are relative to the module directory
        chdir('./modules/'.$args['module'].'/');

        $destination = '/modules/'.$args['module'].'/'.$args['page'];
        if (!empty($args['page']) && file_exists('./'.$args['page'])) {
            include './'.$args['page'];
        }

        // Exit here and allow the Location: header already sent to to it's job
        exit;
    }
}
