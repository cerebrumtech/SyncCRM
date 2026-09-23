<?php

namespace Sync\Exceptions;

class PageNotFound extends \RuntimeException
{
    public static function forPageNotFound(string $message = 'Page not found'): self
    {
        return new self($message);
    }
}
