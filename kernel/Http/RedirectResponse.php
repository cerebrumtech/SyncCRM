<?php

namespace Sync\Http;

class RedirectResponse extends Response
{
    private $target;

    public function __construct(string $target = '/')
    {
        $this->target = $target;
    }

    public function to(string $url): self { $this->target = $url; return $this; }

    public function back(): self
    {
        $this->target = (string) ($_SERVER['HTTP_REFERER'] ?? '/');
        return $this;
    }

    /** Flash a value for the next request. */
    public function with(string $key, $value): self
    {
        session()->setFlashdata($key, $value);
        return $this;
    }

    /** Keep submitted fields so old() can repopulate the form. */
    public function withInput(): self
    {
        session()->setFlashdata('_old_input', $_POST);
        return $this;
    }

    public function send(): void
    {
        $target = $this->target;
        // Never redirect off this site.
        if (preg_match('#^https?://#i', $target)) {
            $base = rtrim(\Sync\Config::get()->baseURL, '/');
            if (strpos($target, $base) !== 0) {
                $target = $base . '/';
            }
        } elseif ($target === '' || $target[0] !== '/') {
            $target = '/' . $target;
        }
        if (! headers_sent()) {
            header('Location: ' . $target, true, 302);
        }
    }
}
