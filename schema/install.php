<?php

/**
 * Command line installer: creates the tables and, with --seed, loads the demo workspace.
 *   php schema/install.php
 *   php schema/install.php --seed
 */

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}

define('ROOTPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('APPPATH', ROOTPATH . 'app' . DIRECTORY_SEPARATOR);
define('KERNELPATH', ROOTPATH . 'kernel' . DIRECTORY_SEPARATOR);
define('WRITEPATH', ROOTPATH . 'writable' . DIRECTORY_SEPARATOR);
define('FCPATH', ROOTPATH . 'public' . DIRECTORY_SEPARATOR);
define('ASSET_VERSION', '2');

require KERNELPATH . 'bootstrap.php';

try {
    $applied = Sync\Schema::install();
    echo "Database tables are up to date ({$applied} statements applied).\n";

    if (in_array('--seed', $argv, true)) {
        (new App\Database\Seeds\DemoSeeder())->run();
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
