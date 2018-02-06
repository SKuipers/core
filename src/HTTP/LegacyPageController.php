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
use Slim\Handlers\NotAllowed;

/**
 * LegacyPageController
 */
class LegacyPageController {

    protected $container;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    public function __invoke(Request $request, Response $response, array $args)
    {
        require_once './functions.php';

        // Provide expected global variables
        $gibbon = $this->container->get('gibbon');
        $loader = $this->container->get('autoloader');
        $pdo = $this->container->get('pdo');
        $connection2 = $pdo->getConnection();
        $guid = $gibbon->guid();
        $version = $this->container->get('settings')['version'];

        // Determine the destination
        $params = $request->getQueryParams();
        $destination = isset($params['q'])? '/'.trim($params['q'], '/') : '';

        // Prevent invalid characters in the path
        if (stristr($destination, '..') !== false) {
            throw new \Slim\Exception\NotFoundException($request, $response, $args);
        }

        // Disallow access to non-existant pages
        if (!file_exists('.'.$destination)) {
            throw new \Slim\Exception\NotFoundException($request, $response, $args);
        }

        $gibbon->session->set('address', $destination);
        $gibbon->session->set('q', $destination);
        $gibbon->session->set('module', getModuleName($destination));
        $gibbon->session->set('action', getActionName($destination));

        if (!empty($destination)) {
            include '.'.$destination;
        }

        return $response;
    }
}
