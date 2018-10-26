<?php
/**
 * Slim Framework (https://slimframework.com)
 *
 * @link      https://github.com/slimphp/Slim
 * @copyright Copyright (c) 2011-2017 Josh Lockhart
 * @license   https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */
namespace Gibbon\Services;

use Slim\CallableResolver;
use Slim\Router;
use Slim\Http\Headers;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Handlers\Error;
use Slim\Http\Environment;
use Slim\Handlers\NotFound;
use Slim\Handlers\PhpError;
use Slim\Handlers\NotAllowed;
use Slim\Interfaces\RouterInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Handlers\Strategies\RequestResponse;
use Slim\Interfaces\CallableResolverInterface;
use Slim\Interfaces\Http\EnvironmentInterface;
use Slim\Interfaces\InvocationStrategyInterface;
use League\Container\ServiceProvider\AbstractServiceProvider;

/**
 * Slim's default Service Provider.
 */
class HTTPServiceProvider extends AbstractServiceProvider
{

    protected $provides = [
        'environment',
        'request',
        'response',
        'router',
        'foundHandler',
        'phpErrorHandler',
        'errorHandler',
        'notFoundHandler',
        'notAllowedHandler',
        'callableResolver',
    ];

    /**
     * Register Slim's default services.
     *
     * @param Container $container A DI container implementing ArrayAccess and container-interop.
     */
    public function register()
    {
        $container = $this->getContainer();

        /**
         * This service MUST return a shared instance
         * of \Slim\Interfaces\Http\EnvironmentInterface.
         *
         * @return EnvironmentInterface
         */
        $container->share('environment', function () {
            return new Environment($_SERVER);
        });

        /**
         * PSR-7 Request object
         *
         * @param Container $container
         *
         * @return ServerRequestInterface
         */
        $container->add('request', function () use ($container) {
            return Request::createFromEnvironment($container->get('environment'));
        });

        /**
         * PSR-7 Response object
         *
         * @param Container $container
         *
         * @return ResponseInterface
         */
        $container->add('response', function () use ($container) {
            $headers = new Headers(['Content-Type' => 'text/html; charset=UTF-8']);
            $response = new Response(200, $headers);

            return $response->withProtocolVersion($container->get('settings')['httpVersion']);
        });

        /**
         * This service MUST return a SHARED instance
         * of \Slim\Interfaces\RouterInterface.
         *
         * @param Container $container
         *
         * @return RouterInterface
         */
        $container->share('router', function () use ($container) {
            $routerCacheFile = false;
            if (isset($container->get('settings')['routerCacheFile'])) {
                $routerCacheFile = $container->get('settings')['routerCacheFile'];
            }


            $router = (new Router)->setCacheFile($routerCacheFile);
            if (method_exists($router, 'setContainer')) {
                $router->setContainer($container);
            }

            return $router;
        });

        /**
         * This service MUST return a SHARED instance
         * of \Slim\Interfaces\InvocationStrategyInterface.
         *
         * @return InvocationStrategyInterface
         */
        $container->share('foundHandler', function () {
            return new RequestResponse;
        });


        /**
         * This service MUST return a callable
         * that accepts three arguments:
         *
         * 1. Instance of \Psr\Http\Message\ServerRequestInterface
         * 2. Instance of \Psr\Http\Message\ResponseInterface
         * 3. Instance of \Error
         *
         * The callable MUST return an instance of
         * \Psr\Http\Message\ResponseInterface.
         *
         * @param Container $container
         *
         * @return callable
         */
        $container->add('phpErrorHandler', function () use ($container) {
            return new PhpError($container->get('settings')['displayErrorDetails']);
        });

        /**
         * This service MUST return a callable
         * that accepts three arguments:
         *
         * 1. Instance of \Psr\Http\Message\ServerRequestInterface
         * 2. Instance of \Psr\Http\Message\ResponseInterface
         * 3. Instance of \Exception
         *
         * The callable MUST return an instance of
         * \Psr\Http\Message\ResponseInterface.
         *
         * @param Container $container
         *
         * @return callable
         */
        $container->add('errorHandler', function () use ($container) {
            return new Error(
                $container->get('settings')['displayErrorDetails']
            );
        });

        /**
         * This service MUST return a callable
         * that accepts two arguments:
         *
         * 1. Instance of \Psr\Http\Message\ServerRequestInterface
         * 2. Instance of \Psr\Http\Message\ResponseInterface
         *
         * The callable MUST return an instance of
         * \Psr\Http\Message\ResponseInterface.
         *
         * @return callable
         */
        $container->add('notFoundHandler', function () {
            return new NotFound;
        });

        /**
         * This service MUST return a callable
         * that accepts three arguments:
         *
         * 1. Instance of \Psr\Http\Message\ServerRequestInterface
         * 2. Instance of \Psr\Http\Message\ResponseInterface
         * 3. Array of allowed HTTP methods
         *
         * The callable MUST return an instance of
         * \Psr\Http\Message\ResponseInterface.
         *
         * @return callable
         */
        $container->add('notAllowedHandler', function () {
            return new NotAllowed;
        });

        /**
         * Instance of \Slim\Interfaces\CallableResolverInterface
         *
         * @param Container $container
         *
         * @return CallableResolverInterface
         */
        $container->add('callableResolver', function () use ($container) {
            return new CallableResolver($container);
        });
    }
}
