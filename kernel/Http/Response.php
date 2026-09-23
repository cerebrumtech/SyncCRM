<?php

namespace Sync\Http;

class Response
{
    protected $status  = 200;
    protected $headers = [];
    protected $body    = '';

    public function setStatusCode(int $code): self { $this->status = $code; return $this; }
    public function setHeader(string $k, string $v): self { $this->headers[$k] = $v; return $this; }
    public function setBody(string $b): self { $this->body = $b; return $this; }

    public function setJSON($data): self
    {
        $this->headers['Content-Type'] = 'application/json; charset=utf-8';
        $this->body = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $this;
    }

    public function send(): void
    {
        if (! headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $k => $v) {
                header($k . ': ' . $v);
            }
        }
        echo $this->body;
    }
}
