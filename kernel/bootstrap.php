<?php

/** Common start-up for the web front controller and the command line installer. */

require KERNELPATH . 'polyfill.php';
require KERNELPATH . 'Env.php';
require KERNELPATH . 'Config.php';
require KERNELPATH . 'Autoload.php';

Sync\Env::load(ROOTPATH . '.env');
Sync\Autoload::register();

require KERNELPATH . 'helpers.php';
require APPPATH . 'Helpers/format_helper.php';
require APPPATH . 'Helpers/ui_helper.php';

date_default_timezone_set(Sync\Config::get()->timezone);
