<?php

namespace App\Controllers;

use App\Exceptions\FormError;
use App\Exceptions\Forbidden;
use App\Libraries\Auth;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
    /** @var CLIRequest|IncomingRequest */
    protected $request;

    protected $helpers = ['url', 'form', 'format', 'ui'];

    protected ?array $me = null;
    protected ?array $org = null;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);
        $this->me = Auth::user();
        $this->org = $this->me ? Auth::org() : null;
    }

    /** Renders a view inside the signed-in layout with the current user/org available. */
    protected function render(string $view, array $data = []): string
    {
        return view($view, $data + ['me' => $this->me, 'org' => $this->org]);
    }

    protected function orgId(): int
    {
        return (int) $this->me['organization_id'];
    }

    /** Trimmed POST string (null when blank). */
    protected function str(string $key, ?int $max = null): ?string
    {
        $v = $this->request->getPost($key);
        if (! is_string($v)) {
            return null;
        }
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        return $max ? mb_substr($v, 0, $max) : $v;
    }

    protected function intOrNull(string $key): ?int
    {
        $v = $this->str($key);
        return $v !== null && ctype_digit($v) ? (int) $v : null;
    }

    protected function on(string $key): bool
    {
        $v = $this->request->getPost($key);
        return $v === 'on' || $v === '1' || $v === 'true';
    }

    protected function fail(string $message): never
    {
        throw new FormError($message);
    }

    /**
     * Runs a mutation. On a FormError/Forbidden it redirects back with the message, the
     * submitted values and (optionally) the id of the dialog to reopen.
     */
    protected function attempt(callable $fn, ?string $reopen = null, ?string $backTo = null)
    {
        try {
            return $fn();
        } catch (FormError | Forbidden $e) {
            $r = ($backTo ? redirect()->to($backTo) : redirect()->back())->withInput()->with('error', $e->getMessage());
            if ($reopen) {
                $r = $r->with('open', $reopen);
            }
            return $r;
        }
    }

    /** JSON variant: {ok:true,data} or {ok:false,error}. */
    protected function attemptJson(callable $fn): ResponseInterface
    {
        try {
            return $this->response->setJSON(['ok' => true, 'data' => $fn()]);
        } catch (FormError | Forbidden $e) {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    protected function jsonBody(): array
    {
        $body = $this->request->getJSON(true);
        return is_array($body) ? $body : $this->request->getPost();
    }

    protected function ok(string $message, string $to): ResponseInterface
    {
        return redirect()->to($to)->with('success', $message);
    }
}
