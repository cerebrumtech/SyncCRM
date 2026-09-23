<?php

namespace Sync;

use Sync\Exceptions\PageNotFound;
use Sync\Http\RedirectResponse;
use Sync\Http\Request;
use Sync\Http\Response;

class Router
{
    private $routes;
    /** @var array<string,string> filter alias => class */
    private $filters;

    public function __construct(RouteCollection $routes, array $filters)
    {
        $this->routes  = $routes;
        $this->filters = $filters;
    }

    private function toRegex(string $path): string
    {
        $regex = str_replace(
            ['(:num)', '(:segment)', '(:any)'],
            ['([0-9]+)', '([^/]+)', '(.+)'],
            $path
        );
        return '#^' . $regex . '$#';
    }

    /** @return Response|RedirectResponse|string */
    public function dispatch(Request $request)
    {
        $path   = rtrim($request->getUri()->getPath(), '/');
        $path   = $path === '' ? '/' : $path;
        $method = $request->getMethod();
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        foreach ($this->routes->all() as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (! preg_match($this->toRegex($route['path']), $path, $m)) {
                continue;
            }
            array_shift($m);

            foreach ((array) ($route['opts']['filter'] ?? []) as $alias) {
                $class = $this->filters[$alias] ?? null;
                if ($class === null) {
                    continue;
                }
                $result = (new $class())->before($request);
                if ($result !== null) {
                    return $result;
                }
            }

            [$controller, $action] = explode('::', $route['handler']);
            // Handlers are written as "Controller::method/$1"; the captures are already in $m.
            if (strpos($action, '/') !== false) {
                $action = substr($action, 0, strpos($action, '/'));
            }
            $namespace = $route['opts']['namespace'] ?? 'App\\Controllers';
            $class     = $namespace . '\\' . $controller;
            if (! class_exists($class)) {
                throw PageNotFound::forPageNotFound('Controller not found: ' . $class);
            }
            $instance = new $class();
            $instance->initController($request, service('response'));
            if (! method_exists($instance, $action)) {
                throw PageNotFound::forPageNotFound('Method not found: ' . $class . '::' . $action);
            }
            return $instance->{$action}(...$m);
        }

        throw PageNotFound::forPageNotFound('No route for ' . $method . ' ' . $path);
    }
}
