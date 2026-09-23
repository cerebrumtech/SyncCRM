<?php

namespace Sync\Http;

class Uri
{
    private $path;
    public function __construct(string $path) { $this->path = $path; }
    public function getPath(): string { return $this->path; }
    public function __toString(): string { return $this->path; }
}

class Request
{
    private $path;
    private $get;
    private $post;
    private $json;

    public function __construct()
    {
        $uri        = $_SERVER['REQUEST_URI'] ?? '/';
        $this->path = '/' . ltrim((string) parse_url($uri, PHP_URL_PATH), '/');
        $this->get  = $_GET;
        $this->post = $_POST;
    }

    public function getUri(): Uri { return new Uri($this->path); }

    public function getMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /** @return mixed */
    public function getPost(string $key = null)
    {
        return $key === null ? $this->post : ($this->post[$key] ?? null);
    }

    /** @return mixed */
    public function getGet(string $key = null)
    {
        return $key === null ? $this->get : ($this->get[$key] ?? null);
    }

    /** @return mixed */
    public function getServer(string $key)
    {
        return $_SERVER[$key] ?? null;
    }

    /** @return mixed */
    public function getJSON(bool $assoc = false)
    {
        if ($this->json === null) {
            $this->json = json_decode((string) file_get_contents('php://input'), $assoc);
        }
        return $this->json;
    }

    public function isAJAX(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    /** @return UploadedFile|null */
    public function getFile(string $name)
    {
        if (! isset($_FILES[$name]) || ! is_array($_FILES[$name])) {
            return null;
        }
        return new UploadedFile($_FILES[$name]);
    }
}

class UploadedFile
{
    private $f;
    public function __construct(array $f) { $this->f = $f; }

    public function isValid(): bool
    {
        return ($this->f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
            && is_uploaded_file((string) ($this->f['tmp_name'] ?? ''));
    }

    public function getSize(): int          { return (int) ($this->f['size'] ?? 0); }
    public function getClientName(): string { return (string) ($this->f['name'] ?? 'file'); }
    public function getTempName(): string   { return (string) ($this->f['tmp_name'] ?? ''); }
    public function getClientMimeType(): string
    {
        return (string) ($this->f['type'] ?? 'application/octet-stream');
    }

    public function move(string $dir, string $name): bool
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return move_uploaded_file($this->getTempName(), rtrim($dir, '/') . '/' . $name);
    }
}
