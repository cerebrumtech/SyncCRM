<?php

use Sync\Config;
use Sync\Database\Connection;
use Sync\Http\Csrf;
use Sync\Http\RedirectResponse;
use Sync\Http\Request;
use Sync\Http\Response;
use Sync\Http\Session;
use Sync\View;

/** Shared singletons, created on first use. */
function service(string $name)
{
    static $shared = [];
    if (isset($shared[$name])) {
        return $shared[$name];
    }
    switch ($name) {
        case 'request':  $shared[$name] = new Request(); break;
        case 'response': $shared[$name] = new Response(); break;
        case 'session':  $shared[$name] = new Session(); break;
        default:
            throw new InvalidArgumentException('Unknown service: ' . $name);
    }
    return $shared[$name];
}

function session(): Session
{
    return service('session');
}

function config(string $name = 'App'): Config
{
    return Config::get();
}

/** One shared instance per model class, as the application assumes. */
function model(string $class)
{
    static $instances = [];
    if (! isset($instances[$class])) {
        $instances[$class] = new $class();
    }
    return $instances[$class];
}

function db_connect(): Connection
{
    static $conn;
    if ($conn === null) {
        $conn = new Connection([
            'hostname' => \Sync\Env::get('database.default.hostname', 'localhost'),
            'database' => \Sync\Env::get('database.default.database', ''),
            'username' => \Sync\Env::get('database.default.username', ''),
            'password' => \Sync\Env::get('database.default.password', ''),
            'port'     => \Sync\Env::get('database.default.port', '3306'),
        ]);
    }
    return $conn;
}

function view(string $name, array $data = []): string
{
    return (new View())->render($name, $data);
}

/** Escapes for HTML output. 'attr' and 'html' both need the same quoting here. */
function esc($value, string $context = 'html'): string
{
    if ($value === null) {
        return '';
    }
    if (is_array($value)) {
        $value = implode(', ', array_filter($value, 'is_scalar'));
    }
    if ($context === 'url') {
        return rawurlencode((string) $value);
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Previously submitted value, kept across a validation redirect. */
function old(string $key, $default = null)
{
    $input = session()->getFlashdata('_old_input');
    if (is_array($input) && array_key_exists($key, $input)) {
        return $input[$key];
    }
    return $default;
}

function redirect(): RedirectResponse
{
    return new RedirectResponse();
}

function csrf_token(): string
{
    return Csrf::FIELD;
}

function csrf_hash(): string
{
    return Csrf::hash();
}

function csrf_field(): string
{
    return '<input type="hidden" name="' . Csrf::FIELD . '" value="' . esc(Csrf::hash(), 'attr') . '">';
}

function base_url(string $path = ''): string
{
    return rtrim(Config::get()->baseURL, '/') . '/' . ltrim($path, '/');
}
