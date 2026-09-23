<?php

namespace Sync;

/** Minimal PSR-4 autoloader: App\ maps to app/, Sync\ maps to kernel/. */
class Autoload
{
    public static function register(): void
    {
        spl_autoload_register(static function (string $class): void {
            $map = ['App\\' => APPPATH, 'Sync\\' => KERNELPATH];
            foreach ($map as $prefix => $dir) {
                if (strpos($class, $prefix) === 0) {
                    $rel  = str_replace('\\', '/', substr($class, strlen($prefix)));
                    $file = $dir . $rel . '.php';
                    if (is_file($file)) {
                        require $file;
                    }
                    return;
                }
            }
        });
    }
}
