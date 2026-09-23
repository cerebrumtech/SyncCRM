<?php

namespace Sync;

/** Collects routes declared in app/Config/Routes.php. */
class RouteCollection
{
    /** @var array<int,array<string,mixed>> */
    private $routes      = [];
    private $groupPrefix = '';
    private $groupOpts   = [];

    public function get(string $pattern, string $handler): void  { $this->add('GET', $pattern, $handler); }
    public function post(string $pattern, string $handler): void { $this->add('POST', $pattern, $handler); }

    public function group(string $prefix, array $opts, callable $callback): void
    {
        $prevPrefix = $this->groupPrefix;
        $prevOpts   = $this->groupOpts;

        $prefix            = trim($prefix, '/');
        $this->groupPrefix = $prefix === '' ? $prevPrefix : trim($prevPrefix . '/' . $prefix, '/');
        $this->groupOpts   = array_merge($prevOpts, $opts);

        $callback($this);

        $this->groupPrefix = $prevPrefix;
        $this->groupOpts   = $prevOpts;
    }

    private function add(string $method, string $pattern, string $handler): void
    {
        $pattern = trim($pattern, '/');
        $path    = trim($this->groupPrefix . '/' . $pattern, '/');
        $this->routes[] = [
            'method'  => $method,
            'path'    => '/' . $path,
            'handler' => $handler,
            'opts'    => $this->groupOpts,
        ];
    }

    public function all(): array
    {
        return $this->routes;
    }
}
