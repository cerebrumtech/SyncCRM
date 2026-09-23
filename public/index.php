<?php

/**
 * SyncCRM front controller.
 * Runs on PHP 7.4 and newer with no third-party dependencies.
 */

define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
define('ROOTPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('APPPATH', ROOTPATH . 'app' . DIRECTORY_SEPARATOR);
define('KERNELPATH', ROOTPATH . 'kernel' . DIRECTORY_SEPARATOR);
define('WRITEPATH', ROOTPATH . 'writable' . DIRECTORY_SEPARATOR);
define('ASSET_VERSION', '4');

if (PHP_VERSION_ID < 70400) {
    http_response_code(500);
    exit('SyncCRM needs PHP 7.4 or newer. This server runs ' . PHP_VERSION . '.');
}

require KERNELPATH . 'bootstrap.php';

$config = Sync\Config::get();
ini_set('display_errors', $config->debug ? '1' : '0');
error_reporting($config->debug ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);

// --- security headers -------------------------------------------------------------------
// Set before any output. The CSP is strict on scripts because every script in this app is
// an external file; inline styles are still allowed, which is a far smaller risk.
if (! headers_sent()) {
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; "
        . "object-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if ($https) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

session()->start('synccrm_session', 60 * 60 * 24 * 14);

$request  = service('request');
$response = service('response');

try {
    Sync\Http\Csrf::verify($request);

    $routes = new Sync\RouteCollection();
    require APPPATH . 'Config/Routes.php';

    $router = new Sync\Router($routes, [
        'auth'  => App\Filters\AuthFilter::class,
        'admin' => App\Filters\AdminFilter::class,
    ]);

    $result = $router->dispatch($request);

    if ($result instanceof Sync\Http\Response) {
        $result->send();
    } elseif (is_string($result)) {
        echo $result;
    }
} catch (Sync\Exceptions\SecurityError $e) {
    http_response_code(403);
    echo view('errors/message', ['title' => 'Not allowed', 'message' => $e->getMessage()]);
} catch (Sync\Exceptions\PageNotFound $e) {
    http_response_code(404);
    echo view('errors/message', ['title' => 'Page not found', 'message' => 'That page does not exist.']);
} catch (Throwable $e) {
    http_response_code(500);
    @file_put_contents(
        WRITEPATH . 'logs/error-' . date('Y-m-d') . '.log',
        date('c') . ' ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL
            . $e->getTraceAsString() . PHP_EOL . PHP_EOL,
        FILE_APPEND
    );
    if ($config->debug) {
        echo '<pre>' . esc($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>';
    } else {
        echo view('errors/message', ['title' => 'Something went wrong', 'message' => 'The error has been logged. Please try again.']);
    }
}
