<?php

namespace Sync;

use Sync\Http\Request;
use Sync\Http\Response;

abstract class Controller
{
    /** @var Request */
    protected $request;
    /** @var Response */
    protected $response;

    public function initController(Request $request, Response $response): void
    {
        $this->request  = $request;
        $this->response = $response;
    }
}
