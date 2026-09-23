<?php

namespace App\Exceptions;

class Forbidden extends \RuntimeException
{
    public function __construct(string $message = "You don't have permission to do that.")
    {
        parent::__construct($message);
    }
}
