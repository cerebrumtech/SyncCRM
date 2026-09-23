<?php

namespace Sync;

use RuntimeException;

/** Template renderer supporting the layout/section pattern the views already use. */
class View
{
    private $layout;
    private $sections     = [];
    private $sectionStack = [];

    /**
     * Data stays visible to nested view() calls, so partials can rely on values the
     * page already passed in (the current user, the tag list and so on).
     * @var array<string,mixed>
     */
    private static $shared = [];

    public function render(string $name, array $data = []): string
    {
        $file = APPPATH . 'Views/' . str_replace('..', '', $name) . '.php';
        if (! is_file($file)) {
            throw new RuntimeException('View not found: ' . $name);
        }
        self::$shared = array_merge(self::$shared, $data);
        extract(self::$shared, EXTR_SKIP);
        ob_start();
        include $file;
        $content = ob_get_clean();

        if ($this->layout !== null) {
            $layoutFile = APPPATH . 'Views/' . str_replace('..', '', $this->layout) . '.php';
            if (! is_file($layoutFile)) {
                throw new RuntimeException('Layout not found: ' . $this->layout);
            }
            $this->layout = null;
            ob_start();
            include $layoutFile;
            return (string) ob_get_clean();
        }
        return (string) $content;
    }

    /** Declares which layout wraps this view. Output outside sections is then discarded. */
    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    public function section(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function endSection(): void
    {
        $name = array_pop($this->sectionStack);
        if ($name === null) {
            return;
        }
        $this->sections[$name] = (string) ob_get_clean();
    }

    public function renderSection(string $name): string
    {
        return $this->sections[$name] ?? '';
    }
}
