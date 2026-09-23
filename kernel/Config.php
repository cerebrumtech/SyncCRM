<?php

namespace Sync;

/** Application settings, read once from .env. */
class Config
{
    /** @var string Public base URL, always with a trailing slash. */
    public $baseURL;
    /** @var string Where uploaded files are stored, outside the web root. */
    public $uploadDir;
    /** @var string One-time installer key; blank disables /install. */
    public $installKey;
    /** @var string */
    public $timezone = 'Asia/Kolkata';
    /** @var bool */
    public $debug = false;

    private static $instance;

    public static function get(): self
    {
        if (self::$instance === null) {
            $c = new self();
            $c->baseURL    = rtrim(Env::get('app.baseURL', '/'), '/') . '/';
            $c->uploadDir  = rtrim(Env::get('app.uploadDir', ROOTPATH . 'writable/uploads'), '/');
            $c->installKey = Env::get('app.installKey', '');
            $c->timezone   = Env::get('app.timezone', 'Asia/Kolkata');
            $c->debug      = Env::get('CI_ENVIRONMENT', 'production') === 'development';
            self::$instance = $c;
        }
        return self::$instance;
    }
}
