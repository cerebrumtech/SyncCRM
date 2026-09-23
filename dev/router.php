<?php
// Front controller for PHP's built-in dev server. Reproduces what nginx does on the
// server:  try_files $uri $uri/ /index.php?$query_string
//
// Started via dev/serve.sh. Not used in production.
$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = $root . '/public' . $path;

// An existing file under public/ (CSS, JS, an image) is served by the built-in server itself.
if ($path !== '/' && is_file($file)) {
    return false;
}

require $root . '/public/index.php';
